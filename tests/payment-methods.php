<?php
// Real Core callbacks with native-style gateway discovery and synthetic purchase state; no I/O.
namespace Artnovo\Core { function bridge_available(): bool { return $GLOBALS['bridge_ready']; } }
namespace Artnovo\HomeOrderUI\Wizard { function is_page(): bool { return $GLOBALS['wizard_page']; } }
namespace WPVPNBridge {
	class Settings { public function ready(): bool { return $GLOBALS['bridge_configured']; } }
}
namespace WPVPNBridge\Commerce {
	class Orders {
		public static function product_terms( $product ): array|\WP_Error { return $GLOBALS['valid_terms'] ? [] : new \WP_Error(); }
		public static function max_purchase_quantity( $product, ?string $method = null ): int {
			return null === $method ? max( $GLOBALS['method_limits'] ) : ( $GLOBALS['method_limits'][$method] ?? 1 );
		}
	}
}
namespace WPVPNBridge\Payment {
	if ( 'missing-wallet' !== ( $argv[1] ?? '' ) ) {
		class WalletPayment {
			public const GATEWAY = 'wpvpn_wallet';
			public static function enabled(): bool { return $GLOBALS['wallet_enabled']; }
			public static function allowed_customer( int $id ): bool { return $id > 0 && $GLOBALS['wallet_customer']; }
			public static function submitted( mixed $key ): bool { return false; }
		}
	}
}
namespace {
if ( PHP_VERSION_ID < 80500 || PHP_VERSION_ID >= 80600 ) { exit( 1 ); }
if ( ! isset( $argv[1] ) ) {
	foreach ( [ 'ready', 'missing-wallet' ] as $case ) {
		$process = proc_open( [ PHP_BINARY, __FILE__, $case ], [ 0 => STDIN, 1 => STDOUT, 2 => STDERR ], $pipes );
		if ( ! is_resource( $process ) || 0 !== proc_close( $process ) ) { exit( 1 ); }
	}
	exit;
}
define( 'ABSPATH', __DIR__ . '/unused-wordpress/' );
$hooks = [];
$bridge_ready = $bridge_configured = $valid_terms = $wizard_page = true;
$wallet_enabled = $wallet_customer = $bank_destination = true;
$current_user = 7;
$method_limits = [ 'bacs' => 10, 'wpvpn_wallet' => 2, 'new_gateway' => 3 ];
$denied_methods = [];
$classic_pay = $hide_wallet_without_service = false;
$failures = $assertions = $native_calls = 0;
class WP_Error {}
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function add_filter( string $name, callable $callback, int $priority = 10, int $accepted = 1 ): void { $GLOBALS['hooks'][$name][$priority][] = [ $callback, $accepted ]; }
function add_action( ...$args ): void { add_filter( ...$args ); }
function apply_filters( string $name, mixed $value, ...$args ): mixed {
	$priorities = $GLOBALS['hooks'][$name] ?? [];
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as [ $callback, $accepted ] ) { $value = $callback( ...array_slice( [ $value, ...$args ], 0, $accepted ) ); }
	}
	return $value;
}
function get_current_user_id(): int { return $GLOBALS['current_user']; }
function is_user_logged_in(): bool { return get_current_user_id() > 0; }
function is_wc_endpoint_url( $endpoint = false ): bool { return $GLOBALS['classic_pay']; }
function has_term( ...$args ): bool { return true; }
function post_password_required( int $id ): bool { return $GLOBALS['password_required'] ?? false; }
function get_option( string $name, mixed $default = false ): mixed {
	return match ( $name ) {
		'woocommerce_bacs_settings' => [ 'enabled' => 'yes' ],
		'woocommerce_bacs_accounts' => $GLOBALS['bank_destination'] ? [ [ 'iban' => 'synthetic' ] ] : [],
		default => $default,
	};
}
class WC_Product {
	public string $status = 'publish';
	public bool $stock = true;
	public bool $virtual = true;
	public bool $configured = true;
	public bool $purchasable = true;
	public int $stock_limit = -1;
	public function get_id(): int { return 25; }
	public function is_type( string $type ): bool { return 'simple' === $type; }
	public function get_status(): string { return $this->status; }
	public function get_meta( string $key, bool $single = true ): string { return $this->configured ? 'yes' : 'no'; }
	public function is_virtual(): bool { return $this->virtual; }
	public function is_in_stock(): bool { return $this->stock; }
	public function get_max_purchase_quantity(): int { return $this->stock_limit; }
	public function is_purchasable(): bool { return apply_filters( 'woocommerce_is_purchasable', $this->purchasable, $this ); }
}
function wc_get_product( int $id ): WC_Product|false { return 25 === $id ? $GLOBALS['product'] : false; }
class SyntheticGateway {
	public string $enabled = 'yes';
	public bool $available = true;
	public bool $requires_cart = false;
	public function __construct( public string $id, private string $title ) {}
	public function get_title(): string { return $this->title; }
	public function is_available(): bool {
		// Extensions may consult both native checkout context and product purchasability.
		apply_filters( 'woocommerce_is_checkout', false );
		$GLOBALS['product']->is_purchasable();
		return 'yes' === $this->enabled && $this->available && ( ! $this->requires_cart || [] !== WC()->cart->items );
	}
}
class SyntheticGateways {
	public array $registered;
	public function __construct() {
		$this->registered = [
			'bacs' => new SyntheticGateway( 'bacs', 'انتقال بانکی فروشگاه' ),
			'wpvpn_wallet' => new SyntheticGateway( 'wpvpn_wallet', 'کیف پول اصلی' ),
			'new_gateway' => new SyntheticGateway( 'new_gateway', 'درگاه تازهٔ فروشگاه' ),
		];
	}
	public function payment_gateways(): array { return $this->registered; }
	public function get_available_payment_gateways(): array {
		if ( ++$GLOBALS['native_calls'] > 1000 ) { throw new RuntimeException( 'Recursive native gateway discovery' ); }
		return apply_filters( 'woocommerce_available_payment_gateways', array_filter( $this->registered, static fn( $gateway ): bool => $gateway->is_available() ) );
	}
}
class SyntheticSession {
	public array $data = [];
	public int $writes = 0;
	public function get( string $name, mixed $default = null ): mixed { return $this->data[$name] ?? $default; }
	public function set( string $name, mixed $value ): void { ++$this->writes; $this->data[$name] = $value; }
	public function __unset( string $name ): void { ++$this->writes; unset( $this->data[$name] ); }
}
class SyntheticCart {
	public array $items = [];
	public int $writes = 0;
	public function get_cart(): array { return $this->items; }
	public function get_applied_coupons(): array { return []; }
	public function set_applied_coupons( array $coupons ): void {}
	public function calculate_totals(): void {}
	public function set_cart_contents( array $items ): void { ++$this->writes; $this->items = $items; }
	public function add_to_cart( int $id, int $quantity ): string {
		$this->set_cart_contents( [ [ 'product_id' => $id, 'quantity' => $quantity, 'data' => wc_get_product( $id ) ] ] );
		return 'synthetic-plan';
	}
}
class SyntheticWoo {
	public SyntheticSession $session;
	public SyntheticCart $cart;
	public SyntheticGateways $gateways;
	public function __construct() { $this->session = new SyntheticSession(); $this->cart = new SyntheticCart(); $this->gateways = new SyntheticGateways(); }
	public function payment_gateways(): SyntheticGateways { return $this->gateways; }
}
function WC(): SyntheticWoo { return $GLOBALS['woo']; }
function check( string $name, bool $passed ): void {
	++$GLOBALS['assertions'];
	if ( ! $passed ) { ++$GLOBALS['failures']; fwrite( STDERR, 'FAIL ' . $name . "\n" ); }
}
$woo = new SyntheticWoo();
$product = new WC_Product();
add_filter( 'woocommerce_available_payment_gateways', static function ( array $gateways ): array {
	// Stand in for Bridge/other extension policy. Core may never restore a removal.
	if ( $GLOBALS['hide_wallet_without_service'] && [] === WC()->cart->items ) {
		unset( $gateways['wpvpn_wallet'] );
	}
	return array_diff_key( $gateways, array_flip( $GLOBALS['denied_methods'] ) );
}, 10 );
require dirname( __DIR__ ) . '/modules/checkout/artnovo-plan-checkout.php';
$woo->cart->items = [ [ 'data' => $product, 'product_id' => 25, 'quantity' => 1 ] ];
$woo->session->data[Artnovo\PlanCheckout\WIZARD_DRAFT] = [ 'product' => 25, 'quantity' => 1, 'expires' => time() + 1800 ];
$_GET = [ 'artnovo_plan' => '25' ];
$_SERVER['REQUEST_METHOD'] = 'GET';
$read_snapshot = [ $woo->session->data, $woo->cart->items ];
check( 'current signed-in selection reuses native checkout without a checkout flag', Artnovo\PlanCheckout\wizard_checkout_active() );
check( 'native checkout context recognizes direct plan URL', apply_filters( 'woocommerce_is_checkout', false ) );
$expected = 'missing-wallet' === $argv[1] ? [ 'bacs', 'new_gateway' ] : [ 'bacs', 'wpvpn_wallet', 'new_gateway' ];
check( 'a future upstream-permitted third gateway is discovered automatically', $expected === Artnovo\PlanCheckout\payment_methods( $product, 1 ) );
check( 'native discovery and direct checkout reuse preserve the complete purchase state', $read_snapshot === [ $woo->session->data, $woo->cart->items ] );
$denied_methods = [ 'new_gateway' ];
check( 'an upstream-denied gateway never returns', ! in_array( 'new_gateway', Artnovo\PlanCheckout\payment_methods( $product, 1 ), true ) );
$denied_methods = [];
$woo->gateways->registered['new_gateway']->enabled = 'no';
check( 'disabled native gateway stays unavailable', ! in_array( 'new_gateway', Artnovo\PlanCheckout\payment_methods( $product, 1 ), true ) );
check( 'disabled native gateway cannot become a preparation candidate', ! in_array( 'new_gateway', Artnovo\PlanCheckout\payment_candidates( $product, 1 ), true ) );
$woo->gateways->registered['new_gateway']->enabled = 'yes';
$woo->gateways->registered['new_gateway']->available = false;
check( 'gateway-specific availability remains authoritative', ! in_array( 'new_gateway', Artnovo\PlanCheckout\payment_methods( $product, 1 ), true ) );
$woo->gateways->registered['new_gateway']->available = true;
$wallet_enabled = false;
check( 'a disabled or absent Bridge wallet is not restored', ! in_array( 'wpvpn_wallet', Artnovo\PlanCheckout\payment_methods( $product, 1 ), true ) );
$wallet_enabled = true;
$wallet_customer = false;
check( 'wallet customer restrictions are retained', ! in_array( 'wpvpn_wallet', Artnovo\PlanCheckout\payment_methods( $product, 1 ), true ) );
$wallet_customer = true;
$bank_destination = false;
check( 'unconfigured bank destination remains unavailable', ! in_array( 'bacs', Artnovo\PlanCheckout\payment_methods( $product, 1 ), true ) );
$bank_destination = true;
check( 'method-specific Bridge quantity limits are retained', [ 'bacs' ] === Artnovo\PlanCheckout\payment_methods( $product, 4 ) );
check( 'invalid quantity has no payment methods', [] === Artnovo\PlanCheckout\payment_methods( $product, 0 ) );
$product->stock_limit = 2;
check( 'stock limit constrains all gateways', [] === Artnovo\PlanCheckout\payment_methods( $product, 3 ) );
check( 'preselection quantity uses potential methods and stock', 2 === Artnovo\PlanCheckout\quantity_limit( $product ) );
$product->stock_limit = -1;
$woo->gateways->registered['bacs']->enabled = 'no';
$woo->gateways->registered['wpvpn_wallet']->enabled = 'no';
check( 'preselection quantity derives a registered third gateway limit without hardcoded IDs', 3 === Artnovo\PlanCheckout\quantity_limit( $product ) );
$woo->gateways->registered['new_gateway']->enabled = 'no';
check( 'no enabled preparation candidate gives a zero quantity limit', 0 === Artnovo\PlanCheckout\quantity_limit( $product ) );
foreach ( $woo->gateways->registered as $gateway ) { $gateway->enabled = 'yes'; }
$woo->cart->items[0]['quantity'] = 4;
check( 'native rendering hides methods that cannot pay the current quantity', [ 'bacs' ] === array_keys( $woo->gateways->get_available_payment_gateways() ) );
check( 'lower quantity can be prepared without changing the current cart on GET', in_array( 'new_gateway', Artnovo\PlanCheckout\payment_candidates( $product, 1 ), true ) && 4 === $woo->cart->items[0]['quantity'] );
$woo->cart->items[0]['quantity'] = 1;
$prepared_cart = $woo->cart->items;
$woo->cart->items = [];
$hide_wallet_without_service = true;
$woo->gateways->registered['bacs']->enabled = 'no';
$woo->gateways->registered['new_gateway']->enabled = 'no';
$wallet_candidate = 'missing-wallet' === $argv[1] ? [] : [ 'wpvpn_wallet' ];
check( 'wallet-only empty cart can prepare the legitimate registered method', $wallet_candidate === Artnovo\PlanCheckout\payment_candidates( $product, 1 ) );
check( 'wallet-only preparation limit survives an empty-cart upstream removal', ( $wallet_candidate ? 2 : 0 ) === Artnovo\PlanCheckout\quantity_limit( $product ) );
check( 'empty-cart upstream removal is never bypassed in actual availability', [] === Artnovo\PlanCheckout\payment_methods( $product, 1 ) );
if ( $wallet_candidate ) {
	$prepared = Artnovo\PlanCheckout\prepare_wizard_cart( 25, 1, $wallet_candidate, 'wpvpn_wallet' );
	check( 'real Core preparation accepts an empty wallet-only cart after native add establishes its context', true === $prepared && 'same' === Artnovo\PlanCheckout\cart_selection( 25, 1 ) );
} else {
	$woo->cart->items = $prepared_cart;
}
check( 'prepared wallet-only cart uses strict native availability', $wallet_candidate === Artnovo\PlanCheckout\payment_methods( $product, 1 ) );
$denied_methods = [ 'wpvpn_wallet' ];
check( 'wallet-only candidate cannot restore an upstream-denied prepared payment', [] === Artnovo\PlanCheckout\payment_methods( $product, 1 ) );
$before_cart = $woo->cart->items;
$denied_prepare = Artnovo\PlanCheckout\prepare_wizard_cart( 25, 1, [ 'wpvpn_wallet' ], 'wpvpn_wallet' );
check( 'real preparation rejected by upstream restores the exact prior selection', is_wp_error( $denied_prepare ) && $before_cart === $woo->cart->items );
$denied_methods = [];
$hide_wallet_without_service = false;
$woo->gateways->registered['new_gateway']->enabled = 'yes';
$woo->gateways->registered['wpvpn_wallet']->enabled = 'no';
$woo->gateways->registered['new_gateway']->requires_cart = true;
$woo->cart->items = [];
check( 'cart-dependent online gateway permits initial preparation', [ 'new_gateway' ] === Artnovo\PlanCheckout\payment_candidates( $product, 1 ) );
check( 'cart-dependent online gateway is not displayed as an actual method before preparation', [] === Artnovo\PlanCheckout\payment_methods( $product, 1 ) );
$woo->cart->items = $prepared_cart;
check( 'future permitted cart-dependent online gateway is available after preparation', [ 'new_gateway' ] === Artnovo\PlanCheckout\payment_methods( $product, 1 ) );
foreach ( $woo->gateways->registered as $gateway ) { $gateway->enabled = 'yes'; }
$woo->gateways->registered['new_gateway']->requires_cart = false;
$product->purchasable = false;
check( 'native product purchase denial stops every gateway', [] === Artnovo\PlanCheckout\payment_methods( $product, 1 ) );
$product->purchasable = true;
$valid_terms = false;
check( 'invalid service terms stop every gateway', [] === Artnovo\PlanCheckout\payment_methods( $product, 1 ) );
$valid_terms = true;
$classic_pay = true;
$upstream_order = [ 'new_gateway' => $woo->gateways->registered['new_gateway'] ];
check( 'native order-pay preserves only the upstream-authorized original method', $upstream_order === apply_filters( 'woocommerce_available_payment_gateways', $upstream_order ) );
check( 'native order-pay never restores a removed method', [] === apply_filters( 'woocommerce_available_payment_gateways', [] ) );
$classic_pay = false;
$valid_draft = $woo->session->data[Artnovo\PlanCheckout\WIZARD_DRAFT];
foreach ( [
	'expired' => [ 'expires' => time() - 1 ], 'malformed expiry' => [ 'expires' => 'later' ],
	'wrong product' => [ 'product' => 26 ], 'string product' => [ 'product' => '25' ],
	'zero quantity' => [ 'quantity' => 0 ], 'string quantity' => [ 'quantity' => '1' ],
	'fractional quantity' => [ 'quantity' => 1.5 ], 'excessive quantity' => [ 'quantity' => 11 ],
] as $name => $change ) {
	$woo->session->data[Artnovo\PlanCheckout\WIZARD_DRAFT] = array_replace( $valid_draft, $change );
	$woo->cart->items[0]['quantity'] = $change['quantity'] ?? 1;
	check( 'direct checkout rejects ' . $name, ! Artnovo\PlanCheckout\wizard_checkout_active() );
}
$woo->session->data[Artnovo\PlanCheckout\WIZARD_DRAFT] = $valid_draft;
$woo->cart->items[0]['quantity'] = 1;
$_GET['artnovo_plan'] = '26';
check( 'direct checkout rejects a different URL plan', ! Artnovo\PlanCheckout\wizard_checkout_active() );
$_GET['artnovo_plan'] = '25';
$woo->cart->items[0]['product_id'] = 26;
check( 'direct checkout rejects a different cart selection', ! Artnovo\PlanCheckout\wizard_checkout_active() );
$woo->cart->items[0]['product_id'] = 25;
$current_user = 0;
check( 'guest cannot reuse authenticated checkout', ! Artnovo\PlanCheckout\wizard_checkout_active() );
$current_user = 7;
$bridge_configured = false;
check( 'unconfigured Bridge stops direct checkout', ! Artnovo\PlanCheckout\wizard_checkout_active() );
$bridge_configured = true;
$product->stock = false;
check( 'out-of-stock plan stops direct checkout', ! Artnovo\PlanCheckout\wizard_checkout_active() );
$product->stock = true;
$password_required = true;
check( 'protected plan stops direct checkout', ! Artnovo\PlanCheckout\wizard_checkout_active() );
$password_required = false;
$product->stock_limit = 0;
check( 'stock purchase limit stops direct checkout', ! Artnovo\PlanCheckout\wizard_checkout_active() );
$product->stock_limit = -1;
$before_native = $native_calls;
$before_writes = [ $woo->session->writes, $woo->cart->writes ];
check( 'authenticated checkout readback still succeeds', Artnovo\PlanCheckout\wizard_checkout_active() );
check( 'checkout context never recursively discovers gateways', $before_native === $native_calls );
Artnovo\PlanCheckout\payment_methods( $product, 1 );
Artnovo\PlanCheckout\payment_candidates( $product, 1 );
check( 'GET discovery and checkout context do not write the cart or session', $before_writes === [ $woo->session->writes, $woo->cart->writes ] );
$bridge_ready = false;
check( 'missing Bridge cannot offer a service payment', [] === Artnovo\PlanCheckout\payment_methods( $product, 1 ) );
check( 'missing Bridge cannot prepare a service payment candidate', [] === Artnovo\PlanCheckout\payment_candidates( $product, 1 ) );
check( 'missing Bridge cannot reuse service checkout', ! Artnovo\PlanCheckout\wizard_checkout_active() );
echo ( $failures ? 'FAIL ' : 'PASS ' ) . $assertions . ' payment/checkout assertions (' . $argv[1] . ")\n";
exit( $failures ? 1 : 0 );
}
