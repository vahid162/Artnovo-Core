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
	function display_number_html( string $value, bool $localize ): string { return $value; }
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
	use function Artnovo\WalletUI\render_topup_order;
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
	final class FixtureJson extends \RuntimeException {
		public function __construct( public bool $ok, public mixed $payload, int $status ) { parent::__construct( '', $status ); }
	}
	final class WC_Product {}
	final class WC_AJAX {
		public static function get_endpoint( string $action ): string { return 'https://artnovo.test/?wc-ajax=' . $action; }
	}
	final class WC_Order {
		public string $status = 'pending';
		public string $method = 'artnovo_gpante';
		public int $credit_id = 0;
		public bool $payment_allowed = true;
		public float $refunded = 0.0;
		public function __construct( public int $id, public int $owner, public bool $service = false ) {}
		public function get_customer_id(): int { return $this->owner; }
		public function get_currency(): string { return 'IRT'; }
		public function get_id(): int { return $this->id; }
		public function get_meta( string $key ): bool|int {
			return match ( $key ) {
				'_wallet_rechargeable_order' => ! $this->service,
				'_wallet_payment_transaction_id' => $this->credit_id,
				'_wc_wallet_purchase_credited' => $this->credit_id > 0,
				default => false,
			};
		}
		public function get_order_key(): string { return 'synthetic-order-' . $this->id; }
		public function get_payment_method( string $context ): string { return $this->method; }
		public function get_status(): string { return $this->status; }
		public function has_status( string $status ): bool { return $this->status === $status; }
		public function get_total_refunded(): float { return $this->refunded; }
		public function is_paid(): bool { return 'processing' === $this->status; }
		public function needs_payment(): bool { return $this->payment_allowed && in_array( $this->status, [ 'pending', 'failed' ], true ); }
		public function get_order_number(): string { return (string) $this->id; }
		public function get_total(): string { return '200000'; }
		public function get_payment_method_title(): string { return ''; }
		public function get_checkout_order_received_url(): string { return 'https://artnovo.test/checkout/order-received/' . $this->id; }
		public function get_checkout_payment_url(): string { return 'https://artnovo.test/checkout/order-pay/' . $this->id; }
		public function get_view_order_url(): string { return 'https://artnovo.test/account/view-order/' . $this->id; }
	}

	$orders = [];
	$requests = [];
	$lock_keys = [];
	$lock_busy = false;
	$actor = 7;
	$wallet_locked = false;
	$wallet_product = true;
	$actions = [];
	$filters = [];
	$endpoint = 'woo-wallet';
	$query_order_id = 0;
	$route_error = false;
	$routes = [ '/terawallet/v1/me/topup' => true, '/artnovo-pay-bridge/v1/wallet-topup/payment' => true ];
	function add_action( string $name, callable $callback, mixed ...$unused ): void { $GLOBALS['actions'][ $name ][] = $callback; }
	function add_filter( string $name, callable $callback, mixed ...$unused ): void { $GLOBALS['filters'][ $name ][] = $callback; }
	function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
	function is_wallet_rechargeable_order( WC_Order $order ): bool { return ! $order->service; }
	function is_wallet_account_locked(): bool { return $GLOBALS['wallet_locked']; }
	function get_current_user_id(): int { return $GLOBALS['actor']; }
	function is_user_logged_in(): bool { return get_current_user_id() > 0; }
	function woo_wallet(): object {
		return (object) [
			'settings_api' => new class {
				public function get_option( string $name, string $group, mixed $default ): mixed { return $default; }
			},
			'wallet' => new class {
				public function get_wallet_balance( int $user ): string { return '650,000 تومان'; }
			},
		];
	}
	function get_option( string $name ): int { return '_woo_wallet_recharge_product' === $name && $GLOBALS['wallet_product'] ? 12 : 0; }
	function wc_get_product( int $id ): WC_Product|false { return 12 === $id ? new WC_Product() : false; }
	function get_wallet_transaction( int $id ): ?object {
		return 90 === $id ? (object) [ 'user_id' => 7, 'type' => 'credit', 'amount' => 200000, 'currency' => 'IRT' ] : null;
	}
	function woo_wallet_get_dashboard_url(): string { return 'https://artnovo.test/account/wallet/'; }
	function wc_get_page_permalink( string $page ): string { return 'https://artnovo.test/account/'; }
	function wc_get_endpoint_url( string $endpoint, string $slug, string $url ): string { return $url . $endpoint . '/' . $slug . '/'; }
	function wc_price( float $amount, array $format ): string { return number_format( $amount ) . ' تومان'; }
	function wp_kses( string $html, array $tags ): string { return $html; }
	function wp_kses_allowed_html( string $context ): array { return []; }
	function wp_kses_post( string $html ): string { return $html; }
	function add_query_arg( array $args, string $url ): string { return $url . '?' . http_build_query( $args ); }
	function esc_attr( string $value ): string { return htmlspecialchars( $value ); }
	function esc_html( string $value ): string { return htmlspecialchars( $value ); }
	function esc_html__( string $value, string $domain ): string { return esc_html( $value ); }
	function esc_url( string $value ): string { return htmlspecialchars( $value ); }
	function wp_nonce_field( mixed ...$unused ): void {}
	function wp_create_nonce( string $action ): string { return get_current_user_id() . '_' . $action; }
	function wp_verify_nonce( string $nonce, string $action ): bool { return hash_equals( wp_create_nonce( $action ), $nonce ); }
	function wp_send_json_success( mixed $payload ): never { throw new FixtureJson( true, $payload, 200 ); }
	function wp_send_json_error( mixed $payload, int $status ): never { throw new FixtureJson( false, $payload, $status ); }
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
	function wc_get_order_status_name( string $status ): string { return $status; }
	function get_query_var( string $name ): int { return $GLOBALS['query_order_id']; }
	function WC(): object {
		return (object) [ 'query' => new class {
			public function get_current_endpoint(): string { return $GLOBALS['endpoint']; }
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

	$status_action = $actions['wc_ajax_artnovo_wallet_topup_status'][0];
	$poll = static function ( array $post, string $method = 'POST' ) use ( $status_action ): FixtureJson {
		$_SERVER['REQUEST_METHOD'] = $method;
		$_POST = $post;
		try {
			$status_action();
		} catch ( FixtureJson $json ) {
			return $json;
		}
		throw new \RuntimeException( 'Status handler must return a JSON response' );
	};
	$request = [ 'order' => '2', 'key' => $order->get_order_key(), '_ajax_nonce' => wp_create_nonce( 'artnovo_wallet_topup_status_2' ) ];
	$order->status = 'pending';
	Payments::$status = [ 'phase' => 'open', 'can_retry' => false ];
	$requests_before_poll = $requests;
	$waiting = $poll( $request );
	check( $waiting->ok && str_contains( $waiting->payload['html'], 'data-artnovo-wallet-topup-view="pending:open:unpaid"' )
		&& str_contains( $waiting->payload['html'], 'data-artnovo-wallet-topup-status-url=' )
		&& str_contains( $waiting->payload['html'], 'data-artnovo-wallet-topup-poll-expired' )
		&& str_contains( $waiting->payload['html'], 'پیگیری وضعیت' ), 'private pending bank card offers bounded refresh and manual fallback' );
	foreach ( [
		$poll( $request, 'GET' ),
		$poll( [ '_ajax_nonce' => 'wrong' ] + $request ),
		$poll( [ 'key' => 'wrong' ] + $request ),
	] as $denied ) {
		check( ! $denied->ok && 403 === $denied->getCode() && null === $denied->payload, 'invalid method or credentials yield no wallet result' );
	}
	$actor = 0;
	check( ! $poll( $request )->ok, 'guest cannot read the wallet result' );
	$actor = 8;
	check( ! $poll( [ '_ajax_nonce' => wp_create_nonce( 'artnovo_wallet_topup_status_2' ) ] + $request )->ok, 'another authenticated customer cannot use the order key' );
	$actor = 7;
	$wallet_locked = true;
	check( ! $poll( $request )->ok, 'locked wallet cannot read the status' );
	$wallet_locked = false;
	$order->service = true;
	check( ! $poll( $request )->ok, 'service order is outside wallet status scope' );
	$order->service = false;
	$order->method = 'bacs';
	check( ! $poll( $request )->ok, 'other payment method is outside topup polling scope' );
	$order->method = 'artnovo_gpante';
	$wallet_product = false;
	check( ! $poll( $request )->ok, 'missing recharge product cannot be recreated by status reads' );
	$wallet_product = true;

	$order->status = 'processing';
	Payments::$status = [ 'phase' => 'paid', 'can_retry' => false ];
	$paid = $poll( $request )->payload['html'];
	check( str_contains( $paid, 'data-artnovo-wallet-topup-view="pending:paid:paid"' )
		&& str_contains( $paid, 'data-artnovo-wallet-topup-status-url=' )
		&& str_contains( $paid, 'پرداخت تأیید شد. در انتظار ثبت اعتبار' ), 'paid with no TeraWallet credit keeps a distinct pending card' );
	$order->credit_id = 90;
	$credited = $poll( $request )->payload['html'];
	check( str_contains( $credited, 'data-artnovo-wallet-topup-view="credited:paid:paid"' )
		&& str_contains( $credited, 'مشاهدهٔ تراکنش‌ها' ) && str_contains( $credited, '650,000 تومان' )
		&& ! str_contains( $credited, 'data-artnovo-wallet-topup-status-url=' ), 'recorded native credit stops polling and displays balance and statement' );
	$order->credit_id = 0;
	$order->status = 'failed';
	Payments::$status = [ 'phase' => 'failed_final', 'can_retry' => true ];
	$failed = $poll( $request )->payload['html'];
	check( str_contains( $failed, 'name="artnovo_topup_retry" value="2"' )
		&& ! str_contains( $failed, 'data-artnovo-wallet-topup-status-url=' ), 'confirmed failure stops polling and keeps the original retry form' );
	check( $requests === $requests_before_poll, 'status polling made no payment or order-creation requests' );

	$render_surfaces = static function ( WC_Order $order ): array {
		ob_start();
		render_topup_result( $order, true );
		$wallet = ob_get_clean();
		ob_start();
		render_topup_result( $order, false );
		$return = ob_get_clean();
		ob_start();
		render_topup_order( $order );
		return [ $wallet, $return, ob_get_clean() ];
	};
	foreach ( [
		[ 'pending', 'open', 0, 'شارژ در انتظار تأیید پرداخت است', 'پیگیری وضعیت' ],
		[ 'failed', 'open', 0, 'شارژ در انتظار تأیید پرداخت است', 'پیگیری وضعیت' ],
		[ 'failed', 'review', 0, 'پرداخت شارژ نیاز به بررسی دارد', 'پیگیری وضعیت' ],
		[ 'failed', 'failed_final', 0, 'پرداخت شارژ ناموفق بود', 'پرداخت دوباره' ],
		[ 'pending', 'none', 0, 'پرداخت شارژ شروع نشده است', 'ادامهٔ پرداخت' ],
		[ 'processing', 'paid', 0, 'پرداخت تأیید شد. در انتظار ثبت اعتبار', 'پیگیری وضعیت' ],
		[ 'processing', 'paid', 90, 'شارژ کیف پول انجام شد', 'مشاهدهٔ تراکنش‌ها' ],
	] as [ $order->status, $phase, $order->credit_id, $title, $action ] ) {
		Payments::$status = [ 'phase' => $phase, 'can_retry' => 'failed_final' === $phase ];
		foreach ( $render_surfaces( $order ) as $surface => $html ) {
			check( str_contains( $html, $title ) && str_contains( $html, $action ) && ! str_contains( $html, '/checkout/order-pay/' ),
				"same topup status and action in surface $surface for $order->status / $phase" );
			check( ! $order->credit_id || str_contains( $html, '200,000 تومان' ), "recorded wallet credit amount in surface $surface" );
		}
	}
	$order->credit_id = 0;
	$order->status = 'failed';
	Payments::$status = [];
	foreach ( $render_surfaces( $order ) as $html ) {
		check( str_contains( $html, 'پرداخت شارژ نیاز به بررسی دارد' ) && ! str_contains( $html, 'پرداخت دوباره' ), 'missing bank status never authorizes retry' );
	}

	$stock_failure = 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.';
	$details_status = $filters['woocommerce_order_details_status'][0];
	$endpoint = 'view-order';
	$query_order_id = $order->get_id();
	Payments::$status = [ 'phase' => 'open' ];
	check( '<mark class="order-status">شارژ در انتظار تأیید پرداخت است</mark>' === $details_status( '<mark class="order-status">failed</mark>', $order ),
		'WooCommerce order details disclosure uses the projected status' );
	$failed_text = $filters['woocommerce_thankyou_order_failed_text'][0];
	$account_actions = $filters['woocommerce_my_account_my_orders_actions'][0];
	$endpoint = 'order-received';
	$query_order_id = $order->get_id();
	$_GET = [ 'key' => $order->get_order_key() ];
	check( '' === $failed_text( $stock_failure, $order ) && 'Custom payment message' === $failed_text( 'Custom payment message', $order ), 'only stock failure copy is suppressed for the canonical owned wallet return' );
	check( [] === $account_actions( [ 'pay' => [ 'name' => 'Pay' ] ], $order ), 'managed topup raw pay action is suppressed' );
	$_GET['key'] = 'wrong';
	check( $stock_failure === $failed_text( $stock_failure, $order ), 'wrong key keeps the native response untouched' );
	$actor = 8;
	check( isset( $account_actions( [ 'pay' => [] ], $order )['pay'] ), 'foreign order actions remain untouched' );
	$actor = 7;
	$_GET['key'] = $order->get_order_key();
	$order->method = 'bacs';
	Payments::$status = [ 'phase' => 'open' ];
	check( str_contains( $render_surfaces( $order )[2], '/checkout/order-pay/' )
		&& $stock_failure === $failed_text( $stock_failure, $order )
		&& isset( $account_actions( [ 'pay' => [] ], $order )['pay'] ), 'legacy recharge keeps its own status and native payment controls' );
	echo "PASS isolated wallet topup contracts\n";
}
