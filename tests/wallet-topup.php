<?php
// Isolated contract fixtures for optional wallet funding. No WordPress or provider I/O.

namespace ArtNovo\PayBridge {
	const METHOD = 'artnovo_gpante';

	final class Topup {
		public static bool $available = true;
		public static function can_start(): bool { return self::$available; }
		public static function minimum_amount( string $currency ): int { return 'IRT' === $currency ? 100 : 1000; }
	}

	final class Payments {
		public static array $status = [];
		public static function customer_status( \WC_Order $order ): array { return self::$status; }
	}
}

namespace Artnovo\Core {
	function is_service_order( \WC_Order $order ): bool { return $order->service; }
	function display_digits( string $value ): string { return $value; }
}

namespace Artnovo\PlanCheckout {
	function checkout_post_value( string $key ): string { return (string) ( $_POST[ $key ] ?? '' ); }
	function wizard_otp_key( string $scope, string $value ): string { return $scope . ':' . $value; }
	function wizard_otp_locked( string $key, callable $callback ): mixed {
		$GLOBALS['lock_keys'][] = $key;
		return $GLOBALS['lock_busy'] ? new \WP_Error( 'busy', 'Busy' ) : $callback();
	}
}

namespace {
	use ArtNovo\PayBridge\Payments;
	use ArtNovo\PayBridge\Topup;
	use function Artnovo\WalletUI\direct_topup_available;
	use function Artnovo\WalletUI\render_topup_result;
	use function Artnovo\WalletUI\start_direct_topup;
	use function Artnovo\WalletUI\wallet_result_order;

	define( 'ABSPATH', '/fixture' );
	final class WP_Error {
		public function __construct( public string $code, public string $message ) {}
	}
	final class WP_REST_Request {
		public array $body = [];
		public array $headers = [];
		public function __construct( public string $method, public string $route ) {}
		public function set_header( string $name, string $value ): void { $this->headers[ $name ] = $value; }
		public function set_body_params( array $body ): void { $this->body = $body; }
	}
	final class WP_REST_Response {
		public function __construct( private array $data, private bool $error = false ) {}
		public function get_data(): array { return $this->data; }
		public function is_error(): bool { return $this->error; }
	}
	final class WC_Order {
		public string $status = 'pending';
		public bool $payment_allowed = true;
		public float $refunded = 0.0;
		public function __construct( public int $id, public int $owner, public bool $service = false ) {}
		public function get_customer_id(): int { return $this->owner; }
		public function get_currency(): string { return 'IRT'; }
		public function get_id(): int { return $this->id; }
		public function get_meta( string $key ): bool { return '_wallet_rechargeable_order' === $key && ! $this->service; }
		public function get_order_key(): string { return 'synthetic-order-' . $this->id; }
		public function has_status( string $status ): bool { return $this->status === $status; }
		public function get_total_refunded(): float { return $this->refunded; }
		public function is_paid(): bool { return 'processing' === $this->status; }
		public function needs_payment(): bool { return $this->payment_allowed && in_array( $this->status, [ 'pending', 'failed' ], true ); }
		public function get_order_number(): string { return (string) $this->id; }
		public function get_view_order_url(): string { return 'https://artnovo.test/account/view-order/' . $this->id; }
	}

	$orders = [];
	$requests = [];
	$lock_keys = [];
	$lock_busy = false;
	$actor = 7;
	$route_error = false;
	$routes = [ '/terawallet/v1/me/topup' => true, '/artnovo-pay-bridge/v1/wallet-topup/payment' => true ];
	function add_action( mixed ...$unused ): void {}
	function add_filter( mixed ...$unused ): void {}
	function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
	function is_wallet_rechargeable_order( WC_Order $order ): bool { return ! $order->service; }
	function is_wallet_account_locked(): bool { return false; }
	function get_current_user_id(): int { return $GLOBALS['actor']; }
	function is_user_logged_in(): bool { return get_current_user_id() > 0; }
	function woo_wallet(): object {
		return (object) [ 'settings_api' => new class {
			public function get_option( string $name, string $group, mixed $default ): mixed { return $default; }
		} ];
	}
	function woo_wallet_get_dashboard_url(): string { return 'https://artnovo.test/account/wallet/'; }
	function add_query_arg( array $args, string $url ): string { return $url . '?' . http_build_query( $args ); }
	function esc_attr( string $value ): string { return htmlspecialchars( $value ); }
	function esc_html( string $value ): string { return htmlspecialchars( $value ); }
	function esc_url( string $value ): string { return htmlspecialchars( $value ); }
	function wp_nonce_field( mixed ...$unused ): void {}
	function wc_terms_and_conditions_checkbox_enabled(): bool { return false; }
	function wc_checkout_privacy_policy_text(): void {}
	function get_woocommerce_currency(): string { return 'IRT'; }
	function absint( mixed $value ): int { return abs( (int) $value ); }
	function sanitize_key( string $value ): string { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
	function wp_unslash( string $value ): string { return $value; }
	function wp_is_uuid( string $value, int $version ): bool {
		return 4 === $version && (bool) preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value );
	}
	function wc_get_order( int $id ): WC_Order|false { return $GLOBALS['orders'][ $id ] ?? false; }
	function WC(): object {
		return (object) [ 'query' => new class {
			public function get_current_endpoint(): string { return 'woo-wallet'; }
		} ];
	}
	function rest_get_server(): object {
		return new class {
			public function get_routes(): array { return $GLOBALS['routes']; }
		};
	}
	function rest_do_request( WP_REST_Request $request ): WP_REST_Response {
		$GLOBALS['requests'][] = $request;
		if ( str_starts_with( $request->route, '/terawallet/' ) ) {
			if ( $GLOBALS['route_error'] ) {
				return new WP_REST_Response( [ 'message' => 'Invalid limits' ], true );
			}
			static $idempotency = [];
			$key = get_current_user_id() . ':' . $request->headers['Idempotency-Key'];
			if ( isset( $idempotency[ $key ] ) ) {
				return new WP_REST_Response( [ 'order_id' => $idempotency[ $key ] ] );
			}
			$id = count( $GLOBALS['orders'] ) + 1;
			$idempotency[ $key ] = $id;
			$GLOBALS['orders'][ $id ] = new WC_Order( $id, get_current_user_id() );
			return new WP_REST_Response( [ 'order_id' => $id ] );
		}
		return new WP_REST_Response( [ 'redirect' => 'https://gateway.artnovo.test/synthetic' ] );
	}

	require dirname( __DIR__ ) . '/modules/wallet/wallet.php';
	function check( bool $ok, string $state ): void {
		if ( ! $ok ) {
			fwrite( STDERR, 'FAIL ' . $state . PHP_EOL );
			exit( 1 );
		}
	}

	check( direct_topup_available(), 'both optional endpoints available' );
	unset( $routes['/terawallet/v1/me/topup'] );
	check( ! direct_topup_available(), 'native checkout remains when wallet API is unavailable' );
	$routes['/terawallet/v1/me/topup'] = true;
	Topup::$available = false;
	check( ! direct_topup_available(), 'native checkout remains when the gateway pauses new topups' );
	Topup::$available = true;
	$intent = '84774621-5e7d-488a-9b16-386687697fc7';
	check( is_wp_error( start_direct_topup( $intent, '99', true ) ) && [] === $requests, 'bank minimum' );
	check( is_wp_error( start_direct_topup( 'not-an-intent', '200000', true ) ) && [] === $requests, 'valid intent before order creation' );
	$lock_busy = true;
	check( is_wp_error( start_direct_topup( $intent, '200000', true ) ) && [] === $requests, 'refuse concurrent creation' );
	$lock_busy = false;
	$route_error = true;
	check( start_direct_topup( $intent, '200000', true )->is_error() && 1 === count( $requests ), 'native rejection prevents bank start' );
	$route_error = false;
	$requests = [];

	$result = start_direct_topup( $intent, '200000', true );
	check( ! $result->is_error() && 2 === count( $requests ), 'creation then handoff' );
	check( $requests[0]->body === [ 'amount' => '200000', 'currency' => 'IRT', 'payment_method' => 'artnovo_gpante' ], 'native topup reads exact amount and method' );
	check( $requests[1]->body === [ 'order_id' => 1, 'order_key' => 'synthetic-order-1', 'amount' => '200000', 'currency' => 'IRT', 'terms' => true ], 'bank receives authorized order and terms' );
	start_direct_topup( $intent, '200000', true );
	check( count( $orders ) === 1 && $requests[0]->headers === $requests[2]->headers && $lock_keys[2] === $lock_keys[3], 'same owner and immutable intent reuse native order' );
	start_direct_topup( $intent, '300000', true );
	check( count( $orders ) === 2 && $requests[0]->headers !== $requests[4]->headers, 'different amount creates a distinct intent' );

	$_GET = [ 'artnovo_topup' => '1', 'key' => 'synthetic-order-1' ];
	check( wallet_result_order() === $orders[1], 'owner can read own result' );
	$actor = 8;
	check( false === wallet_result_order(), 'foreign owner cannot read result even with key' );
	$actor = 7;
	$_GET['key'] = 'wrong';
	check( false === wallet_result_order(), 'wrong order key denied' );
	$orders[1]->service = true;
	$_GET['key'] = 'synthetic-order-1';
	check( false === wallet_result_order(), 'service order cannot enter wallet result' );

	$order = $orders[2];
	foreach ( [
		[ 'pending', 'failed_final', true, true, 0.0, 'پرداخت دوباره' ],
		[ 'failed', 'failed_final', true, true, 0.0, 'پرداخت دوباره' ],
		[ 'failed', 'failed_final', false, true, 0.0, '' ],
		[ 'failed', 'failed_final', true, false, 0.0, '' ],
		[ 'failed', 'failed_final', true, true, 100.0, '' ],
		[ 'processing', 'failed_final', true, true, 0.0, 'پیگیری وضعیت' ],
		[ 'cancelled', 'failed_final', true, true, 0.0, '' ],
		[ 'refunded', 'failed_final', true, true, 0.0, '' ],
		[ 'failed', 'open', false, true, 0.0, 'پیگیری وضعیت' ],
		[ 'failed', 'review', false, true, 0.0, 'پیگیری وضعیت' ],
		[ 'failed', 'none', false, true, 0.0, '' ],
		[ 'pending', 'none', false, true, 0.0, 'ادامهٔ پرداخت' ],
	] as [ $status, $phase, $can_retry, $is_payable, $refunded, $action ] ) {
		$order->status = $status;
		$order->payment_allowed = $is_payable;
		$order->refunded = $refunded;
		Payments::$status = [ 'phase' => $phase, 'can_retry' => $can_retry ];
		ob_start();
		render_topup_result( $order, true );
		$html = ob_get_clean();
		$has_form = in_array( $action, [ 'پرداخت دوباره', 'ادامهٔ پرداخت' ], true );
		check( str_contains( $html, 'name="artnovo_topup_retry" value="2"' ) === $has_form,
			"payment form for original order: $status / $phase / " . (int) $can_retry . ' / ' . (int) $is_payable . " / $refunded" );
		check( '' === $action || str_contains( $html, $action ), "expected action for $status / $phase" );
	}
	echo "PASS isolated wallet topup contracts\n";
}
