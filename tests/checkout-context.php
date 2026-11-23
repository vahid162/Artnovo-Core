<?php
// Exercise Core's real gateway filter with synthetic requests and cart; no WordPress, database, mail or HTTP.
namespace Artnovo\Core {
	function bridge_available(): bool { return $GLOBALS['bridge_ready']; }
	function is_service_order( \WC_Order $order ): bool { return $order->service; }
}
namespace Artnovo\HomeOrderUI\Wizard {
	function is_page(): bool { return true; }
}
namespace Elementor\Core\DynamicTags {
	class Data_Tag {}
}
namespace {
if ( PHP_VERSION_ID < 80500 || PHP_VERSION_ID >= 80600 ) {
	fwrite( STDERR, "Use PHP 8.5.x.\n" );
	exit( 1 );
}
if ( ! isset( $argv[1] ) ) {
	foreach ( [ 'ready', 'missing-bridge' ] as $case ) {
		$process = proc_open( [ PHP_BINARY, __FILE__, $case ], [ 0 => STDIN, 1 => STDOUT, 2 => STDERR ], $pipes );
		if ( ! is_resource( $process ) || 0 !== proc_close( $process ) ) {
			exit( 1 );
		}
	}
	foreach ( [ 'auth-contract.php', 'selection-contract.php', 'compact-auth.php', 'payment-methods.php' ] as $fixture ) {
		$process = proc_open( [ PHP_BINARY, __DIR__ . '/' . $fixture ], [ 0 => STDIN, 1 => STDOUT, 2 => STDERR ], $pipes );
		if ( ! is_resource( $process ) || 0 !== proc_close( $process ) ) {
			exit( 1 );
		}
	}
	$process = proc_open( [ PHP_BINARY, __DIR__ . '/selection-contract.php', 'legacy-bridge' ], [ 0 => STDIN, 1 => STDOUT, 2 => STDERR ], $pipes );
	if ( ! is_resource( $process ) || 0 !== proc_close( $process ) ) {
		exit( 1 );
	}
	exit;
}

define( 'ABSPATH', __DIR__ . '/unused-wordpress/' );
$hooks = [];
$classic_pay = false;
$bridge_ready = 'missing-bridge' !== $argv[1];
$cart_plan = true;
$failures = 0;
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ): void {
	$GLOBALS['hooks'][ $hook ][ $priority ][] = [ $callback, $accepted ];
}
function add_action( $hook, $callback, $priority = 10, $accepted = 1 ): void {
	add_filter( $hook, $callback, $priority, $accepted );
}
function apply_filters( $hook, $value, ...$args ): mixed {
	$priorities = $GLOBALS['hooks'][ $hook ] ?? [];
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as [ $callback, $accepted ] ) {
			$value = $callback( ...array_slice( [ $value, ...$args ], 0, $accepted ) );
		}
	}
	return $value;
}
function do_action( $hook, ...$args ): void {
	$priorities = $GLOBALS['hooks'][ $hook ] ?? [];
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as [ $callback, $accepted ] ) {
			$callback( ...array_slice( $args, 0, $accepted ) );
		}
	}
}
function __return_false(): bool { return false; }
function __return_empty_string(): string { return ''; }
function is_wc_endpoint_url( $endpoint = false ): bool { return $GLOBALS['classic_pay']; }
function has_term( ...$args ): bool { return $GLOBALS['cart_plan']; }
function is_preview(): bool { return false; }
function is_user_logged_in(): bool { return true; }
function is_page( $id ): bool { return 20 === $id; }
function wc_get_page_id( $name ): int { return 'cart' === $name ? 10 : 20; }
function home_url( $path ): string { return 'https://example.invalid' . $path; }
function wc_nocache_headers(): void {}
function wc_get_page_permalink( $name ): string { return 'https://example.invalid/my-account/'; }
class CheckoutContextRedirect extends \RuntimeException {}
class CheckoutContextDie extends \RuntimeException {}
function wp_safe_redirect( ...$args ): never { throw new CheckoutContextRedirect(); }
function wp_die( $message, $title = '', $args = [] ): never { throw new CheckoutContextDie( $message, $args['response'] ); }
function wc_get_order( $id ): WC_Order|false { return $GLOBALS['orders'][ $id ] ?? false; }
class WC_Product {
	public function is_type( $type ): bool { return false; }
	public function get_id(): int { return 25; }
	public function is_in_stock(): bool { return false; }
}
class WP_REST_Request {
	public function __construct( private string $method, private string $route ) {}
	public function get_method(): string { return $this->method; }
	public function get_route(): string { return $this->route; }
}
class WP_Error {
	public function __construct( public string $code = '', public string $message = '', public mixed $data = null ) {}
	public function get_error_data(): mixed { return $this->data; }
}
class WC_Order {
	public function __construct( public bool $service = false ) {}
}
class CheckoutContextCart {
	public function get_cart(): array { return [ [ 'data' => new WC_Product() ] ]; }
}
class CheckoutContextSession {
	public function get( $name ): array { return []; }
}
function WC(): object {
	static $woo;
	return $woo ??= (object) [ 'cart' => new CheckoutContextCart(), 'session' => new CheckoutContextSession() ];
}
function check( string $case, bool $passed ): void {
	$GLOBALS['failures'] += $passed ? 0 : 1;
	echo ( $passed ? 'PASS ' : 'FAIL ' ) . $case . "\n";
}
function gateways( array $incoming ): array {
	return apply_filters( 'woocommerce_available_payment_gateways', $incoming );
}
function begin_request( WP_REST_Request $request, mixed $response = null ): mixed {
	return apply_filters( 'rest_request_before_callbacks', $response, [], $request );
}
function end_request( WP_REST_Request $request, mixed $response = null ): mixed {
	return apply_filters( 'rest_request_after_callbacks', $response, [], $request );
}

require dirname( __DIR__ ) . '/modules/checkout/artnovo-plan-checkout.php';

// This incoming list stands for the gateways already allowed by WooCommerce and Bridge.
$allowed = [ 'bacs' => (object) [ 'id' => 'bacs' ] ];
if ( ! $bridge_ready ) {
	check( 'missing Bridge preserves ordinary gateways', $allowed === gateways( $allowed ) );
	check( 'missing Bridge preserves persistent carts', true === apply_filters( 'woocommerce_persistent_cart_enabled', true ) );
	check( 'missing Bridge preserves native add-to-cart notices', 'Added' === apply_filters( 'wc_add_to_cart_message_html', 'Added' ) );
	check( 'missing Bridge disables wizard checkout context', ! Artnovo\PlanCheckout\wizard_page() );
	check( 'missing Bridge retains shared OTP verification helper', function_exists( 'Artnovo\\PlanCheckout\\wizard_otp_verify' ) );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	try {
		do_action( 'template_redirect' );
		check( 'missing Bridge preserves native checkout route', true );
	} catch ( CheckoutContextRedirect ) {
		check( 'missing Bridge preserves native checkout route', false );
	}
	$orders = [ 123 => new WC_Order( true ), 456 => new WC_Order() ];
	do_action( 'woocommerce_before_pay_action', $orders[456] );
	check( 'missing Bridge allows ordinary classic order payment', true );
	try {
		do_action( 'woocommerce_before_pay_action', $orders[123] );
		check( 'missing Bridge blocks classic service payment before gateway execution', false );
	} catch ( CheckoutContextDie $error ) {
		check( 'missing Bridge blocks classic service payment before gateway execution', 503 === $error->getCode() );
	}
	foreach ( [ '/wc/store/v1/checkout/123', '/wc/store/checkout/123', '/wc/store/v1/checkout/000123', '/WC/STORE/V1/CHECKOUT/123' ] as $route ) {
		$result = apply_filters( 'rest_dispatch_request', null, new WP_REST_Request( 'POST', $route ) );
		check( 'missing Bridge blocks existing service payment before REST callback: ' . $route, $result instanceof WP_Error && [ 'status' => 503 ] === $result->get_error_data() );
	}
	check( 'missing Bridge preserves ordinary REST order payment', null === apply_filters( 'rest_dispatch_request', null, new WP_REST_Request( 'POST', '/wc/store/v1/checkout/456' ) ) );
	$prior = new WP_Error( 'prior_denial' );
	check( 'missing Bridge preserves an earlier REST denial', $prior === apply_filters( 'rest_dispatch_request', $prior, new WP_REST_Request( 'POST', '/wc/store/v1/checkout/123' ) ) );
	$manager = new class {
		public array $tags = [];
		public function register( $tag ): void { $this->tags[ $tag->get_name() ] = $tag; }
	};
	do_action( 'elementor/dynamic_tags/register', $manager );
	check( 'missing Bridge retains saved checkout tag with native account destination', isset( $manager->tags['artnovo-plan-checkout'] ) && wc_get_page_permalink( 'myaccount' ) === $manager->tags['artnovo-plan-checkout']->get_value() );
	exit( $failures ? 1 : 0 );
}
check( 'ordinary checkout retains the stale-cart denial', [] === gateways( $allowed ) );
$classic_pay = true;
check( 'classic order-pay preserves upstream gateways', $allowed === gateways( $allowed ) );
$classic_pay = false;

foreach ( [ '/wc/store/v1/checkout/123', '/wc/store/checkout/123', '/wc/store/v1/checkout/000123', '/WC/STORE/V1/CHECKOUT/123' ] as $route ) {
	$request = new WP_REST_Request( 'POST', $route );
	begin_request( $request );
	check( 'existing-order payment ignores unrelated stale cart: ' . $route, $allowed === gateways( $allowed ) );
	check( 'existing-order payment never restores an upstream-denied gateway: ' . $route, [] === gateways( [] ) );
	end_request( $request );
	check( 'existing-order context ends with its request: ' . $route, [] === gateways( $allowed ) );
}

foreach ( [
	[ 'POST', '/wc/store/v1/checkout' ],
	[ 'POST', '/wc/store/v1/cart' ],
	[ 'POST', '/wp/v2/checkout/123' ],
	[ 'POST', '/wc/store/v1/checkout/0' ],
	[ 'POST', '/wc/store/v1/checkout/123/extra' ],
	[ 'GET', '/wc/store/v1/checkout/123' ],
] as [ $method, $route ] ) {
	$request = new WP_REST_Request( $method, $route );
	begin_request( $request );
	check( 'cart guard remains active for ' . $method . ' ' . $route, [] === gateways( $allowed ) );
	end_request( $request );
}

$outer = new WP_REST_Request( 'POST', '/wc/store/v1/checkout/123' );
$inner = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
begin_request( $outer );
begin_request( $inner );
check( 'nested new checkout keeps its cart guard', [] === gateways( $allowed ) );
end_request( $inner );
check( 'outer existing-order context resumes after nested checkout', $allowed === gateways( $allowed ) );
end_request( $outer );
check( 'nested request cleanup restores ordinary checkout guard', [] === gateways( $allowed ) );

$batch = new WP_REST_Request( 'POST', '/batch/v1' );
begin_request( $batch );
begin_request( $outer );
check( 'existing-order subrequest works inside a batch', $allowed === gateways( $allowed ) );
$error = new WP_Error();
check( 'after-callback cleanup preserves an error response', $error === end_request( $outer, $error ) );
check( 'batch sibling does not inherit existing-order bypass', [] === gateways( $allowed ) );
end_request( $batch );
check( 'before-callback tracking preserves a permission error', $error === begin_request( $outer, $error ) );
end_request( $outer, $error );
check( 'permission-error cleanup restores ordinary checkout guard', [] === gateways( $allowed ) );

$cart_plan = false;
check( 'ordinary product or wallet top-up retains upstream gateways', $allowed === gateways( $allowed ) );
check( 'ordinary product or wallet top-up retains persistent carts', true === apply_filters( 'woocommerce_persistent_cart_enabled', true ) );
check( 'ordinary product or wallet top-up retains cart notices', 'Added' === apply_filters( 'wc_add_to_cart_message_html', 'Added' ) );
$_SERVER['REQUEST_METHOD'] = 'GET';
try {
	do_action( 'template_redirect' );
	check( 'ordinary product or wallet top-up retains native checkout', true );
} catch ( CheckoutContextRedirect ) {
	check( 'ordinary product or wallet top-up retains native checkout', false );
}

exit( $failures ? 1 : 0 );
}
