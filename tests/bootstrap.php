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
function has_term( ...$args ): bool { return $GLOBALS['legacy_route']['is_plan'] ?? false; }
function get_queried_object_id(): int { return $GLOBALS['legacy_route']['page'] ?? 0; }
function is_page( int $id ): bool { return ( $GLOBALS['legacy_route']['is_page'] ?? false ) && $id === get_queried_object_id(); }
function is_admin(): bool { return $GLOBALS['legacy_route']['admin'] ?? false; }
function wp_doing_ajax(): bool { return $GLOBALS['legacy_route']['ajax'] ?? false; }
function is_preview(): bool { return $GLOBALS['legacy_route']['preview'] ?? false; }
function is_woocommerce(): bool { return $GLOBALS['commerce_document'] ?? false; }
function post_password_required( $id ): bool { return $GLOBALS['legacy_route']['password'] ?? false; }
function home_url( string $path ): string { return 'https://example.invalid' . $path; }
function add_query_arg( array $query, string $url ): string { return $url . '?' . http_build_query( $query ); }
function wc_get_product( $id ): WC_Product|false { return 25 === $id ? new WC_Product( true ) : false; }
function is_user_logged_in(): bool { return true; }
function plugins_url( string $path, string $file ): string { return 'https://example.invalid/plugins/' . $path; }
function wp_enqueue_script( string $handle, ...$args ): void { $GLOBALS['route_scripts'][] = $handle; }
function wp_enqueue_style( string $handle, ...$args ): void { $GLOBALS['route_styles'][] = $handle; }
class LegacyWorkflowRedirect extends RuntimeException {
	public function __construct( public string $destination, public int $status ) {}
}
function wp_safe_redirect( string $url, int $status = 302 ): never { throw new LegacyWorkflowRedirect( $url, $status ); }
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
	public function is_type( string $type ): bool { return false; }
	public function get_id(): int { return 25; }
	public function get_status(): string { return $GLOBALS['legacy_route']['status'] ?? 'publish'; }
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
check( '1.4.1' === Artnovo\Core\VERSION, 'release version' );
check( count( $hooks['plugins_loaded'][30] ?? [] ) === 1, 'one dependency-ready loader at priority 30' );
check( count( $hooks['wp_mail_from'][10] ?? [] ) === 1, 'exactly one early sender filter' );
check( 'orders@artnovo.ir' === mail_sender( 'wordpress@artnovo.ir' ), 'default sender retained' );
check( 'support@artnovo.ir' === mail_sender( 'support@artnovo.ir' ), 'explicit sender retained' );
Artnovo\Core\boot();
$expected = in_array( $case, [ 'ready', 'missing-bridge', 'legacy-mail', 'restored-mail', 'retired-loaded-mail' ], true );
check( function_exists( 'Artnovo\\AccountUI\\account_url' ) === $expected, 'account loading is guarded' );
check( function_exists( 'Artnovo\\PlanCheckout\\is_plan' ) === $expected, 'checkout loading is guarded' );
check( function_exists( 'Artnovo\\HomeOrderUI\\Wizard\\is_page' ) === $expected, 'wizard loading is guarded' );
check( function_exists( 'Artnovo\\AccountUI\\received_state' ) === $expected, 'received loading is guarded' );
if ( $expected ) {
	check( function_exists( 'Artnovo\\AccountUI\\account_email_login_ready' ), 'account email-code helpers remain available' );
	if ( 'missing-bridge' === $case ) {
		check( ! product_purchasable( true, new WC_Product( true ) ), 'missing Bridge blocks service purchases while Core runs' );
		check( product_purchasable( true, new WC_Product( false ) ), 'missing Bridge preserves ordinary product purchases' );
		check( ! product_purchasable( false, new WC_Product( false ) ), 'missing Bridge preserves existing ordinary product denial' );
	}
	$paths = array_map( static fn( $callback ) => ( new ReflectionFunction( $callback ) )->getFileName(), $hooks['wp_enqueue_scripts'][10] ?? [] );
	$modules = array_map( static fn( $path ) => basename( dirname( $path ) ), $paths );
	$expected_modules = 'missing-bridge' === $case ? [ 'auth', 'account', 'header', 'home', 'home' ] : [ 'auth', 'account', 'header', 'home', 'home', 'checkout' ];
	check( $expected_modules === $modules, 'same-priority base asset callback order retained; absent service integration adds no checkout assets' );
	check( count( $hooks['gettext_wp-vpn-bridge'][10] ?? [] ) === 1 && count( $hooks['gettext_wp-vpn-bridge'][20] ?? [] ) === 1, 'translation priorities retained' );
	$before = array_map( static fn( $priorities ) => array_map( 'count', $priorities ), $hooks );
	Artnovo\Core\boot();
	$after = array_map( static fn( $priorities ) => array_map( 'count', $priorities ), $hooks );
	check( $before === $after, 'repeated boot does not duplicate callbacks' );
	$skip_links = $hooks['hello_elementor_skip_link_url'][10] ?? [];
	check( 1 === count( $skip_links ), 'one theme skip-link adapter is registered' );
	$commerce_document = true;
	check( '#main' === $skip_links[0]( '#content' ), 'shop, product and product-archive skip link uses the native main target' );
	$commerce_document = false;
	foreach ( [ '#content', '#custom-content' ] as $original ) {
		check( $original === $skip_links[0]( $original ), 'non-catalog pages preserve the original skip-link target' );
	}
	unset( $commerce_document );
	if ( 'ready' === $case ) {
		$redirects = array_values( array_filter( $hooks['template_redirect'][2] ?? [],
			static fn( $callback ) => 'artnovo-home-order-ui.php' === basename( ( new ReflectionFunction( $callback ) )->getFileName() ) ) );
		check( 1 === count( $redirects ), 'one legacy purchase redirect is registered' );
		$home_callbacks = static function ( string $hook, string $file ): array {
			return array_values( array_filter( $GLOBALS['hooks'][ $hook ][10] ?? [],
				static fn( $callback ) => $file === basename( ( new ReflectionFunction( $callback ) )->getFileName() ) ) );
		};
		foreach ( [ 61, 68 ] as $id ) {
			foreach ( [ 'category', 'tag', 'product taxonomy', 'author', 'custom post type' ] as $type ) {
				$legacy_route = [ 'page' => $id, 'is_page' => false, 'type' => $type, 'method' => 'GET' ];
				$_SERVER['REQUEST_METHOD'] = 'GET';
				$_GET = [];
				$actual = null;
				try { $redirects[0](); } catch ( LegacyWorkflowRedirect $redirect ) { $actual = $redirect->destination; }
				check( null === $actual, "$type ID $id is not redirected as a purchase page" );
				check( ! Artnovo\HomeOrderUI\on_workflow_page() && ! Artnovo\HomeOrderUI\Wizard\is_page()
					&& ! Artnovo\PlanCheckout\wizard_page(), "$type ID $id does not activate either purchase context" );
				foreach ( [ 'artnovo-home-order-ui.php', 'artnovo-home-wizard.php' ] as $file ) {
					$source = '<!--artnovo-home-order--><!--artnovo-wizard-progress-->';
					foreach ( $home_callbacks( 'elementor/frontend/the_content', $file ) as $callback ) {
						check( $source === $callback( $source ), "$type ID $id keeps $file content unchanged" );
					}
					$route_scripts = [];
					$route_styles = [];
					foreach ( $home_callbacks( 'wp_enqueue_scripts', $file ) as $callback ) { $callback(); }
					check( [] === $route_scripts && [] === $route_styles, "$type ID $id enqueues no purchase assets from $file" );
				}
			}
			$legacy_route = [ 'page' => $id, 'is_page' => true ];
			check( ( 61 === $id ) === Artnovo\HomeOrderUI\on_workflow_page()
				&& ( 68 === $id ) === Artnovo\HomeOrderUI\Wizard\is_page()
				&& ( 68 === $id ) === Artnovo\PlanCheckout\wizard_page(), "actual page $id retains its purchase context" );
			$route_scripts = [];
			foreach ( $home_callbacks( 'wp_enqueue_scripts', 61 === $id ? 'artnovo-home-order-ui.php' : 'artnovo-home-wizard.php' ) as $callback ) { $callback(); }
			check( [ 61 === $id ? 'artnovo-home-flow' : 'artnovo-home-wizard' ] === $route_scripts, "actual page $id retains its purchase script" );
		}
		$home = 'https://example.invalid/#artnovo-categories';
		foreach ( [
			[ 'legacy GET', [], [], $home ],
			[ 'legacy HEAD', [ 'method' => 'HEAD' ], [], $home ],
			[ 'valid selected plan', [], [ 'artnovo_plan' => '25' ], 'https://example.invalid/?artnovo_plan=25#artnovo-order' ],
			[ 'unknown plan', [], [ 'artnovo_plan' => '999' ], $home ],
			[ 'unpublished plan', [ 'status' => 'draft' ], [ 'artnovo_plan' => '25' ], $home ],
			[ 'password-protected plan', [ 'password' => true ], [ 'artnovo_plan' => '25' ], $home ],
			[ 'unrelated product', [ 'is_plan' => false ], [ 'artnovo_plan' => '25' ], $home ],
			[ 'other page', [ 'page' => 68 ], [], null ],
			[ 'POST', [ 'method' => 'POST' ], [], null ],
			[ 'admin', [ 'admin' => true ], [], null ],
			[ 'AJAX', [ 'ajax' => true ], [], null ],
			[ 'WordPress preview', [ 'preview' => true ], [], null ],
			[ 'Elementor editor preview', [], [ 'elementor-preview' => '61' ], null ],
		] as [ $name, $overrides, $query, $expected_url ] ) {
			$legacy_route = array_replace( [ 'page' => 61, 'is_page' => true, 'method' => 'GET', 'is_plan' => true ], $overrides );
			$_SERVER['REQUEST_METHOD'] = $legacy_route['method'];
			$_GET = $query;
			$actual = null;
			try { $redirects[0](); } catch ( LegacyWorkflowRedirect $redirect ) { $actual = $redirect->destination; check( 302 === $redirect->status, $name . ' uses a temporary redirect' ); }
			check( $expected_url === $actual, $name . ' keeps the correct purchase route' );
		}
		unset( $legacy_route );
		$_GET = [];
	}
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
