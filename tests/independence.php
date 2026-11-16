<?php
// Run Core's actual account/received callbacks with synthetic Woo objects and no Bridge.
// No WordPress installation, database, customer data, mail, payment or provider calls.
if ( PHP_VERSION_ID < 80500 || PHP_VERSION_ID >= 80600 ) {
	fwrite( STDERR, "Use PHP 8.5.x.\n" );
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/unused-wordpress/' );
define( 'WPMU_PLUGIN_DIR', __DIR__ . '/unused-mu/' );
$hooks = [];
$failures = 0;
$assertions = 0;
$user = 7;
$account_page = true;
$received_page = false;
$endpoint = '';
$assets = [];
$wp = (object) [ 'request' => 'my-account' ];
class RedirectAttempt extends RuntimeException {}
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
function wp_normalize_path( string $path ): string { return $path; }
function wc_get_page_permalink( string $page ): string { return 'https://example.test/' . ( 'myaccount' === $page ? 'my-account' : $page ) . '/'; }
function wc_get_account_endpoint_url( string $endpoint ): string { return wc_get_page_permalink( 'myaccount' ) . $endpoint . '/'; }
function home_url( string $path = '' ): string { return 'https://example.test' . $path; }
function trailingslashit( string $value ): string { return rtrim( $value, '/' ) . '/'; }
function add_query_arg( $key, $value, $url = null ): string {
	$args = is_array( $key ) ? $key : [ $key => $value ];
	$base = is_array( $key ) ? $value : $url;
	return $base . '?' . http_build_query( $args );
}
function wp_parse_url( string $url, int $component = -1 ): mixed { return parse_url( $url, $component ); }
function esc_url_raw( string $url ): string { return $url; }
function wp_validate_redirect( string $url, string $fallback ): string {
	return str_starts_with( $url, 'https://example.test/' ) ? $url : $fallback;
}
function wp_unslash( string $value ): string { return $value; }
function is_account_page(): bool { return $GLOBALS['account_page']; }
function is_order_received_page(): bool { return $GLOBALS['received_page']; }
function is_checkout(): bool { return $GLOBALS['received_page'] || 'order-pay' === $GLOBALS['endpoint']; }
function is_wc_endpoint_url( $endpoint = false ): bool {
	return false === $endpoint ? '' !== $GLOBALS['endpoint'] : $endpoint === $GLOBALS['endpoint'];
}
function is_user_logged_in(): bool { return $GLOBALS['user'] > 0; }
function get_current_user_id(): int { return $GLOBALS['user']; }
function get_query_var( string $name ): mixed { return 'order-received' === $name ? 123 : null; }
function nocache_headers(): void {}
function wp_safe_redirect( string $url ): never { throw new RedirectAttempt( $url ); }
function plugins_url( string $file, string $source = '' ): string { return 'https://example.test/plugins/' . $file; }
function wp_enqueue_style( string $handle, ...$args ): void { $GLOBALS['assets'][] = $handle; }
function wp_enqueue_script( string $handle, ...$args ): void { $GLOBALS['assets'][] = $handle; }
class IndependenceQuery {
	public function get_current_endpoint(): string { return $GLOBALS['endpoint']; }
	public function get_query_vars(): array { return [ 'orders' => 'orders', 'view-order' => 'view-order', 'edit-account' => 'edit-account' ]; }
}
function WC(): object {
	static $woo;
	return $woo ??= (object) [ 'query' => new IndependenceQuery() ];
}
class WC_Product {
	public function __construct( private bool $service = false ) {}
	public function get_meta( string $key, bool $single = true ): mixed { return '_wpvpn_enabled' === $key && $this->service ? 'yes' : ''; }
}
class WC_Order_Item_Product {
	public function __construct( private mixed $snapshot = '', private ?WC_Product $product = null ) {}
	public function get_meta( string $key, bool $single = true ): mixed { return '_wpvpn_snapshot' === $key ? $this->snapshot : ''; }
	public function get_product(): WC_Product|false { return $this->product ?? false; }
}
class WC_Order {
	public function __construct( private mixed $snapshot = '', private array $items = [], private int $customer = 7 ) {}
	public function get_meta( string $key, bool $single = true ): mixed { return '_wpvpn_snapshot' === $key ? $this->snapshot : ''; }
	public function get_items( string $type = 'line_item' ): array { return $this->items; }
	public function get_id(): int { return 123; }
	public function get_customer_id(): int { return $this->customer; }
	public function get_order_key(): string { return 'synthetic-key'; }
	public function has_status( string|array $statuses ): bool { return in_array( 'pending', (array) $statuses, true ); }
	public function get_total_refunded(): float { return 0.0; }
	public function get_payment_method(): string { return 'bacs'; }
}
function wc_get_order( int $id ): WC_Order|false { return 123 === $id ? $GLOBALS['order'] : false; }
function check( string $case, bool $passed ): void {
	++$GLOBALS['assertions'];
	$GLOBALS['failures'] += $passed ? 0 : 1;
	if ( ! $passed ) {
		fwrite( STDERR, "FAIL $case\n" );
	}
}

require dirname( __DIR__ ) . '/artnovo-core.php';
require dirname( __DIR__ ) . '/modules/account/artnovo-account-ui.php';
check( 'Bridge is genuinely absent', ! class_exists( '\WPVPNBridge\Commerce\Orders' ) );
check( 'Core account links fall back to native Woo account', wc_get_page_permalink( 'myaccount' ) === Artnovo\AccountUI\account_url() );
check( 'email-code return defaults to native account', wc_get_page_permalink( 'myaccount' ) === Artnovo\AccountUI\account_auth_destination() );
check( 'external return URLs still fall back safely', wc_get_page_permalink( 'myaccount' ) === Artnovo\AccountUI\account_auth_destination( 'https://other.test/' ) );
check( 'stale subscription return URL falls back to native account', wc_get_page_permalink( 'myaccount' ) === Artnovo\AccountUI\account_auth_destination( wc_get_account_endpoint_url( 'vpn-subscriptions' ) ) );

foreach ( [ '', 'orders', 'view-order', 'edit-account', 'vpn-subscriptions' ] as $endpoint ) {
	try {
		foreach ( $hooks['template_redirect'][4] ?? [] as [ $callback ] ) {
			$callback();
		}
		check( 'no absent subscription redirect for native endpoint ' . $endpoint, true );
	} catch ( RedirectAttempt $redirect ) {
		check( 'no absent subscription redirect for native endpoint ' . $endpoint, false );
	}
	check( 'logged-in native account template survives at ' . $endpoint, '/native.php' === apply_filters( 'template_include', '/native.php' ) );
	check( 'native account avoids custom body styling at ' . $endpoint, ! in_array( 'artnovo-account-page', apply_filters( 'body_class', [] ), true ) );
	$assets = [];
	foreach ( $hooks['wp_enqueue_scripts'][10] ?? [] as [ $callback ] ) {
		$callback();
	}
	check( 'native account avoids custom assets at ' . $endpoint, [] === $assets );
}
$endpoint = '';
foreach ( [ 'help', 'support', 'profile' ] as $view ) {
	$_GET['artnovo_view'] = $view;
	check( 'explicit Core account view remains available: ' . $view, dirname( __DIR__ ) . '/modules/account/account-page.php' === apply_filters( 'template_include', '/native.php' ) );
}
unset( $_GET['artnovo_view'] );
$user = 0;
check( 'Core sign-in template remains available without Bridge', dirname( __DIR__ ) . '/modules/account/account-page.php' === apply_filters( 'template_include', '/native.php' ) );
check( 'Core sign-in retains its body styling', in_array( 'artnovo-account-page', apply_filters( 'body_class', [] ), true ) );
$assets = [];
foreach ( $hooks['wp_enqueue_scripts'][10] ?? [] as [ $callback ] ) {
	$callback();
}
check( 'Core sign-in retains its required assets', [ 'artnovo-account-ui', 'artnovo-account-ui' ] === $assets );
$user = 7;
$account_page = false;
$received_page = true;
$endpoint = 'order-received';
$_GET['key'] = 'synthetic-key';
$ordinary = new WC_Order( '', [ new WC_Order_Item_Product( '', new WC_Product() ) ] );
$service = new WC_Order( [ 'product_id' => 25 ], [] );
$order = $ordinary;
check( 'owned ordinary order is resolved without Bridge', $ordinary === Artnovo\AccountUI\received_order() );
check( 'ordinary received page retains native Woo template', '/native.php' === apply_filters( 'template_include', '/native.php' ) );

if ( function_exists( 'Artnovo\\Core\\is_service_order' ) ) {
	check( 'ordinary order remains ordinary', ! Artnovo\Core\is_service_order( $ordinary ) );
	check( 'order snapshot identifies service after product removal', Artnovo\Core\is_service_order( $service ) );
	check( 'item snapshot identifies service after product removal', Artnovo\Core\is_service_order( new WC_Order( '', [ new WC_Order_Item_Product( [ 'product_id' => 25 ] ) ] ) ) );
	check( 'enabled service product is identified before a snapshot exists', Artnovo\Core\is_service_order( new WC_Order( '', [ new WC_Order_Item_Product( '', new WC_Product( true ) ) ] ) ) );
} else {
	check( 'service identity remains available without Bridge', false );
}
$order = $service;
check( 'service order uses the safe Core received template', dirname( __DIR__ ) . '/modules/order-received/received-page.php' === apply_filters( 'template_include', '/native.php' ) );
check( 'service state explains the missing integration', 'unavailable' === Artnovo\AccountUI\received_state( $service )['state'] );
check( 'private services are unavailable without Bridge', [ 'html' => '', 'links' => [] ] === Artnovo\AccountUI\received_services( $service ) );
check( 'receipt upload fails closed without Bridge', ! Artnovo\AccountUI\received_receipt_can_upload( $service ) );
check( 'receipt existence cannot be claimed without Bridge', ! Artnovo\AccountUI\received_receipt_exists( $service ) );
$user = 8;
check( 'another customer cannot resolve the order', false === Artnovo\AccountUI\received_order() );
check( 'another customer cannot upload a receipt', ! Artnovo\AccountUI\received_receipt_can_upload( $service ) );
$user = 7;
$_GET['key'] = 'wrong-key';
check( 'wrong order key remains denied', false === Artnovo\AccountUI\received_order() );
$user = 0;
check( 'signed-out order access remains denied', false === Artnovo\AccountUI\received_order() );

// Native WooCommerce owns guest authorization for ordinary orders when Bridge is absent.
$order = new WC_Order( '', [ new WC_Order_Item_Product( '', new WC_Product() ) ], 0 );
$_GET['key'] = 'synthetic-key';
foreach ( [ 'order-pay', 'order-received' ] as $endpoint ) {
	$received_page = 'order-received' === $endpoint;
	$_SERVER['REQUEST_URI'] = '/checkout/' . $endpoint . '/123/?key=synthetic-key';
	try {
		foreach ( $hooks['template_redirect'][4] ?? [] as [ $callback ] ) {
			$callback();
		}
		check( 'ordinary guest ' . $endpoint . ' stays with native Woo authorization', true );
	} catch ( RedirectAttempt $redirect ) {
		check( 'ordinary guest ' . $endpoint . ' stays with native Woo authorization', false );
	}
}
check( 'ordinary guest received order keeps native Woo template', '/native.php' === apply_filters( 'template_include', '/native.php' ) );
check( 'Core never resolves a guest order as an authenticated owner', false === Artnovo\AccountUI\received_order() );
$_GET['key'] = 'wrong-key';
check( 'wrong guest key keeps the safe Core denial template', dirname( __DIR__ ) . '/modules/order-received/received-page.php' === apply_filters( 'template_include', '/native.php' ) );
check( 'wrong guest key never resolves an authenticated owner', false === Artnovo\AccountUI\received_order() );
$_GET['key'] = 'synthetic-key';
$user = 7;
check( 'signed-in customer viewing a valid guest order uses native Woo verification', '/native.php' === apply_filters( 'template_include', '/native.php' ) );
check( 'being signed in does not claim ownership of a guest order', false === Artnovo\AccountUI\received_order() );
$user = 0;
$_GET['key'] = 'wrong-key';
$order = $service;
check( 'guest service order stays on the protected Core template', dirname( __DIR__ ) . '/modules/order-received/received-page.php' === apply_filters( 'template_include', '/native.php' ) );
check( 'guest service order with wrong key is not resolved', false === Artnovo\AccountUI\received_order() );
$_GET['key'] = 'synthetic-key';
check( 'guest service order with known key still requires its owner', false === Artnovo\AccountUI\received_order() );
check( 'guest service receipt upload remains denied', ! Artnovo\AccountUI\received_receipt_can_upload( $service ) );

// The healthy integration keeps its existing account journey; only absence uses native fallback.
class IndependenceBridgeOrders {
	public static function fresh_order( int $id ): WC_Order|false { return wc_get_order( $id ); }
	public static function is_service_order( WC_Order $order ): bool { return is_array( $order->get_meta( '_wpvpn_snapshot', true ) ); }
}
class_alias( IndependenceBridgeOrders::class, 'WPVPNBridge\\Commerce\\Orders' );
$user = 7;
$account_page = true;
$received_page = false;
$endpoint = '';
check( 'Bridge account link retains its registered endpoint', wc_get_account_endpoint_url( 'vpn-subscriptions' ) === Artnovo\AccountUI\account_url() );
check( 'Bridge email-code return retains its registered endpoint', wc_get_account_endpoint_url( 'vpn-subscriptions' ) === Artnovo\AccountUI\account_auth_destination() );
check( 'Bridge account retains the Core template', dirname( __DIR__ ) . '/modules/account/account-page.php' === apply_filters( 'template_include', '/native.php' ) );
try {
	foreach ( $hooks['template_redirect'][4] ?? [] as [ $callback ] ) {
		$callback();
	}
	check( 'healthy Bridge retains the subscriptions redirect', false );
} catch ( RedirectAttempt $redirect ) {
	check( 'healthy Bridge retains the subscriptions redirect', wc_get_account_endpoint_url( 'vpn-subscriptions' ) === $redirect->getMessage() );
}
foreach ( [ 'orders', 'view-order', 'edit-account', 'woo-wallet', 'wallet-transactions', 'add-payment-method' ] as $endpoint ) {
	check( 'other plugins retain the native template with Bridge: ' . $endpoint, '/native.php' === apply_filters( 'template_include', '/native.php' ) );
	try {
		foreach ( $hooks['template_redirect'][4] ?? [] as [ $callback ] ) {
			$callback();
		}
		check( 'Core does not redirect native endpoint: ' . $endpoint, true );
	} catch ( RedirectAttempt ) {
		check( 'Core does not redirect native endpoint: ' . $endpoint, false );
	}
}
$user = 0;
$endpoint = 'view-order';
$_SERVER['REQUEST_URI'] = '/my-account/view-order/123/';
try {
	foreach ( $hooks['template_redirect'][4] ?? [] as [ $callback ] ) { $callback(); }
	check( 'guest order sign-in retains its safe native order return', false );
} catch ( RedirectAttempt $redirect ) {
	check( 'guest order sign-in retains its safe native order return', add_query_arg( 'artnovo_redirect', 'https://example.test/my-account/view-order/123/', wc_get_page_permalink( 'myaccount' ) ) === $redirect->getMessage() );
}
$account_page = false;
$wp->request = 'login';
$user = 0;
try {
	foreach ( $hooks['template_redirect'][4] ?? [] as [ $callback ] ) {
		$callback();
	}
	check( 'Pinova retains its native login route', true );
} catch ( RedirectAttempt ) {
	check( 'Pinova retains its native login route', false );
}
$user = 7;
$wp->request = 'my-account';
$account_page = false;
$received_page = true;
$endpoint = 'order-received';
$_GET['key'] = 'synthetic-key';
$order = $ordinary;
check( 'ordinary received page remains native with Bridge too', '/native.php' === apply_filters( 'template_include', '/native.php' ) );

class IndependenceReceiptStore {}
class IndependenceReceipts {
	public static bool $allowed = false;
	public static array $calls = [];
	public function __construct( IndependenceReceiptStore $store ) {}
	public function can_upload( int $id ): bool { self::$calls[] = $id; return self::$allowed; }
}
class_alias( IndependenceReceiptStore::class, 'WPVPNBridge\\Persistence\\Store' );
class_alias( IndependenceReceipts::class, 'WPVPNBridge\\Commerce\\Receipts' );
$user = 7;
check( 'Core respects a denied native Bridge receipt policy', ! Artnovo\AccountUI\received_receipt_can_upload( $service ) && [123] === IndependenceReceipts::$calls );
IndependenceReceipts::$allowed = true;
check( 'Core delegates receipt eligibility to the native Bridge policy', Artnovo\AccountUI\received_receipt_can_upload( $service ) && [123,123] === IndependenceReceipts::$calls );
$user = 8;
check( 'Core still denies another customer before invoking receipt policy', ! Artnovo\AccountUI\received_receipt_can_upload( $service ) && [123,123] === IndependenceReceipts::$calls );

echo "{$assertions} independence assertions, {$failures} failures\n";
exit( $failures ? 1 : 0 );
