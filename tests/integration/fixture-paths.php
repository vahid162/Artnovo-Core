<?php
// Fixture paths and process-local bootstrapping; no WordPress option is written.

function artnovo_fixture_root(): string {
	$requested = getenv( 'ARTNOVO_CORE_DIR' ) ?: dirname( __DIR__, 2 );
	$root = realpath( $requested );
	if ( false === $root || ! is_file( $root . '/artnovo-core.php' ) ) {
		throw new RuntimeException( 'ARTNOVO_CORE_DIR must identify the candidate Core plugin directory.' );
	}
	return $root;
}

function artnovo_fixture_boot_core(): void {
	if ( function_exists( '\Artnovo\Core\boot' ) || function_exists( '\Artnovo\PlanCheckout\is_plan' )
		|| function_exists( '\Artnovo\AccountUI\account_url' ) || function_exists( '\Artnovo\HomeOrderUI\selected_plan' ) ) {
		throw new RuntimeException( 'Skip Core and all four legacy custom plugins in this WP-CLI process; see tests/integration/README.md.' );
	}
	$excluded = [
		'artnovo-core/artnovo-core.php',
		'artnovo-account-ui/artnovo-account-ui.php',
		'artnovo-header-ui/artnovo-header-ui.php',
		'artnovo-home-order-ui/artnovo-home-order-ui.php',
		'artnovo-plan-checkout/artnovo-plan-checkout.php',
	];
	$local = static fn( array $plugins ): array => array_values( array_diff( $plugins, $excluded ) );
	$network = static fn( array $plugins ): array => array_diff_key( $plugins, array_flip( $excluded ) );
	add_filter( 'option_active_plugins', $local, PHP_INT_MAX );
	add_filter( 'site_option_active_sitewide_plugins', $network, PHP_INT_MAX );
	try {
		require_once artnovo_fixture_root() . '/artnovo-core.php';
		\Artnovo\Core\boot();
	} finally {
		remove_filter( 'option_active_plugins', $local, PHP_INT_MAX );
		remove_filter( 'site_option_active_sitewide_plugins', $network, PHP_INT_MAX );
	}
	if ( ! function_exists( '\Artnovo\PlanCheckout\prepare_wizard_cart' )
		|| ! function_exists( '\Artnovo\AccountUI\received_state' ) ) {
		throw new RuntimeException( 'Candidate Core did not load its modules; verify its external dependencies.' );
	}
}

function artnovo_fixture_write( string $filename, string $document ): void {
	$directory = getenv( 'ARTNOVO_FIXTURE_OUTPUT_DIR' ) ?: sys_get_temp_dir() . '/artnovo-core-fixtures';
	if ( is_link( $directory ) || ( ! is_dir( $directory ) && ! mkdir( $directory, 0700, true ) )
		|| ! is_writable( $directory ) || basename( $filename ) !== $filename
		|| false === file_put_contents( $directory . '/' . $filename, $document ) ) {
		throw new RuntimeException( 'Fixture output requires a writable private directory outside the repository.' );
	}
}
