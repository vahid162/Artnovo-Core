<?php
// Isolated bootstrap contract: no WordPress installation, database, mail or HTTP.
if ( ! isset( $argv[1] ) ) {
	$cases = [ 'ready', 'missing-woocommerce', 'missing-bridge', 'missing-pinova', 'missing-elementor', 'legacy-header-missing-bridge', 'legacy-account', 'legacy-header', 'legacy-home', 'legacy-checkout', 'network-legacy', 'legacy-mail', 'restored-mail', 'retired-loaded-mail' ];
	foreach ( $cases as $case ) {
		$process = proc_open( [ PHP_BINARY, __FILE__, $case ], [ 0 => STDIN, 1 => STDOUT, 2 => STDERR ], $pipes );
		if ( ! is_resource( $process ) || 0 !== proc_close( $process ) ) {
			exit( 1 );
		}
	}
	echo "PASS all bootstrap scenarios\n";
	exit;
}

$case = $argv[1];
$hooks = [];
$active = [];
$network = [];
$legacy = [
	'legacy-account' => 'artnovo-account-ui/artnovo-account-ui.php',
	'legacy-header' => 'artnovo-header-ui/artnovo-header-ui.php',
	'legacy-header-missing-bridge' => 'artnovo-header-ui/artnovo-header-ui.php',
	'legacy-home' => 'artnovo-home-order-ui/artnovo-home-order-ui.php',
	'legacy-checkout' => 'artnovo-plan-checkout/artnovo-plan-checkout.php',
];
if ( isset( $legacy[ $case ] ) ) {
	$active[] = $legacy[ $case ];
}
if ( 'network-legacy' === $case ) {
	$network['artnovo-header-ui/artnovo-header-ui.php'] = time();
}
$mu = sys_get_temp_dir() . '/artnovo-core-bootstrap-' . bin2hex( random_bytes( 6 ) );
mkdir( $mu, 0700 );
define( 'ABSPATH', $mu . '/wordpress/' );
define( 'WPMU_PLUGIN_DIR', $mu );
register_shutdown_function( static function () use ( $mu ): void {
	if ( is_file( $mu . '/artnovo-mail-sender.php' ) ) {
		unlink( $mu . '/artnovo-mail-sender.php' );
	}
	rmdir( $mu );
} );
function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ): void {
	$GLOBALS['hooks'][ $hook ][ $priority ][] = $callback;
}
function add_action( $hook, $callback, $priority = 10, $accepted = 1 ): void {
	add_filter( $hook, $callback, $priority, $accepted );
}
function get_option( $key, $default = false ): mixed {
	return 'active_plugins' === $key ? $GLOBALS['active'] : $default;
}
function get_site_option( $key, $default = false ): mixed {
	return 'active_sitewide_plugins' === $key ? $GLOBALS['network'] : $default;
}
function is_multisite(): bool { return 'network-legacy' === $GLOBALS['case']; }
function current_user_can( $cap ): bool { return true; }
function esc_html( $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wp_normalize_path( string $path ): string { return str_replace( '\\', '/', $path ); }
function check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL {$GLOBALS['case']}: $message\n" );
		exit( 1 );
	}
}
function mail_sender( string $address ): string {
	foreach ( $GLOBALS['hooks']['wp_mail_from'][10] ?? [] as $callback ) {
		$address = $callback( $address );
	}
	return $address;
}
class WC_Product {
	public function __construct( private bool $service ) {}
	public function get_meta( string $key, bool $single = true ): string {
		return '_wpvpn_enabled' === $key && $this->service ? 'yes' : '';
	}
}
function product_purchasable( bool $purchasable, WC_Product $product ): bool {
	$filters = $GLOBALS['hooks']['woocommerce_is_purchasable'] ?? [];
	ksort( $filters );
	foreach ( $filters as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$purchasable = $callback( $purchasable, $product );
		}
	}
	return $purchasable;
}
foreach ( [ 'woocommerce' => 'WooCommerce', 'bridge' => 'WPVPNBridge\\Commerce\\Orders', 'pinova' => 'Pinova\\Pinova', 'elementor' => 'Elementor\\Plugin' ] as $dependency => $class ) {
	if ( str_ends_with( $case, 'missing-' . $dependency ) ) {
		continue;
	}
	$parts = explode( '\\', $class );
	$name = array_pop( $parts );
	eval( ( $parts ? 'namespace ' . implode( '\\', $parts ) . ';' : '' ) . 'class ' . $name . ' {}' );
}
if ( in_array( $case, [ 'legacy-mail', 'restored-mail', 'retired-loaded-mail' ], true ) ) {
	copy( dirname( __DIR__ ) . '/modules/mail/artnovo-mail-sender.php', $mu . '/artnovo-mail-sender.php' );
	if ( 'restored-mail' !== $case ) {
		require $mu . '/artnovo-mail-sender.php';
	}
	if ( 'retired-loaded-mail' === $case ) {
		unlink( $mu . '/artnovo-mail-sender.php' );
	}
}
$entry = dirname( __DIR__ ) . '/artnovo-core.php';
check( is_file( $entry ), 'Core entrypoint must exist' );
require $entry;
check( '1.0.0' === Artnovo\Core\VERSION, 'release version' );
check( count( $hooks['plugins_loaded'][30] ?? [] ) === 1, 'one dependency-ready loader at priority 30' );
check( count( $hooks['wp_mail_from'][10] ?? [] ) === 1, 'exactly one early sender filter' );
check( 'orders@artnovo.ir' === mail_sender( 'wordpress@artnovo.ir' ), 'default sender retained' );
check( 'support@artnovo.ir' === mail_sender( 'support@artnovo.ir' ), 'explicit sender retained' );
Artnovo\Core\boot();
$expected = in_array( $case, [ 'ready', 'legacy-mail', 'restored-mail', 'retired-loaded-mail' ], true );
check( function_exists( 'Artnovo\\AccountUI\\account_url' ) === $expected, 'account loading is guarded' );
check( function_exists( 'Artnovo\\PlanCheckout\\is_plan' ) === $expected, 'checkout loading is guarded' );
check( function_exists( 'Artnovo\\HomeOrderUI\\Wizard\\is_page' ) === $expected, 'wizard loading is guarded' );
check( function_exists( 'Artnovo\\AccountUI\\received_state' ) === $expected, 'received loading is guarded' );
if ( $expected ) {
	$paths = array_map( static fn( $callback ) => ( new ReflectionFunction( $callback ) )->getFileName(), $hooks['wp_enqueue_scripts'][10] ?? [] );
	$modules = array_map( static fn( $path ) => basename( dirname( $path ) ), $paths );
	check( [ 'account', 'header', 'home', 'home', 'checkout' ] === $modules, 'same-priority asset callback order retained' );
	check( count( $hooks['gettext_wp-vpn-bridge'][10] ?? [] ) === 1 && count( $hooks['gettext_wp-vpn-bridge'][20] ?? [] ) === 1, 'translation priorities retained' );
	$before = array_map( static fn( $priorities ) => array_map( 'count', $priorities ), $hooks );
	Artnovo\Core\boot();
	$after = array_map( static fn( $priorities ) => array_map( 'count', $priorities ), $hooks );
	check( $before === $after, 'repeated boot does not duplicate callbacks' );
} else {
	check( ! empty( $hooks['admin_notices'] ), 'blocked load explains the conflict to the administrator' );
	if ( str_contains( $case, 'missing-' ) ) {
		check( ! product_purchasable( true, new WC_Product( true ) ), 'missing dependency blocks service purchases' );
		check( product_purchasable( true, new WC_Product( false ) ), 'missing dependency preserves ordinary product purchases' );
		check( ! product_purchasable( false, new WC_Product( false ) ), 'missing dependency preserves existing ordinary product denial' );
	} else {
		check( empty( $hooks['woocommerce_is_purchasable'] ), 'a legacy collision alone does not block the valid existing flow' );
	}
}
echo "PASS $case\n";
