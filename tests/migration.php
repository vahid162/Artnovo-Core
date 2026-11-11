<?php
// Standalone filesystem/WordPress-API fixture; never loads a WordPress installation.

namespace Artnovo\Core\Migration {
	function rename( string $source, string $destination ): bool {
		return ! ( $GLOBALS['migration_rename_fails'] ?? false ) && \rename( $source, $destination );
	}
}

namespace {

define( 'WP_CLI', true );
$fixture = sys_get_temp_dir() . '/artnovo-core-migration-test-' . bin2hex( random_bytes( 6 ) );
mkdir( $fixture, 0700 );
define( 'ABSPATH', $fixture . '/wordpress/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
define( 'WPMU_PLUGIN_DIR', WP_CONTENT_DIR . '/mu-plugins' );

function get_option( string $name, mixed $default = false ): mixed {
	if ( 'active_plugins' === $name && isset( $GLOBALS['migration_cached_active'] ) ) {
		return $GLOBALS['migration_cached_active'];
	}
	if ( 'active_plugins' === $name && ( $GLOBALS['migration_cache_enabled'] ?? false ) ) {
		$GLOBALS['migration_cached_active'] = $GLOBALS['migration_options'][ $name ] ?? $default;
		return $GLOBALS['migration_cached_active'];
	}
	return $GLOBALS['migration_options'][ $name ] ?? $default;
}
function get_site_option( string $name, mixed $default = false ): mixed {
	return $default;
}
function update_option( string $name, mixed $value ): bool {
	if ( 'active_plugins' !== $name ) {
		throw new RuntimeException( 'Unexpected option write: ' . $name );
	}
	++$GLOBALS['migration_writes'];
	if ( $GLOBALS['migration_write_fails'] ) {
		return false;
	}
	if ( $GLOBALS['migration_before_write'] ) {
		$callback = $GLOBALS['migration_before_write'];
		$GLOBALS['migration_before_write'] = null;
		$callback();
	}
	global $wpdb;
	$unrelated = "UPDATE `{$wpdb->options}` SET `option_value` = 'active_plugins' WHERE `option_name` = 'audit_option'";
	$GLOBALS['migration_unrelated_query_preserved'] = apply_filters( 'query', $unrelated ) === $unrelated;
	$query = $wpdb->prepare( 'UPDATE `' . $wpdb->options . '` SET `option_value` = %s WHERE `option_name` = %s', serialize( $value ), $name );
	if ( $GLOBALS['migration_unknown_sql'] ) {
		$query = str_replace( '`', '', $query );
	}
	$query = apply_filters( 'query', $query );
	$GLOBALS['migration_last_query'] = $query;
	$condition = ' AND BINARY `option_value` = ';
	if ( str_contains( $query, $condition ) && ! str_ends_with( $query, $wpdb->prepare( $condition . '%s', serialize( $GLOBALS['migration_options'][ $name ] ) ) ) ) {
		return false;
	}
	$GLOBALS['migration_options'][ $name ] = $value;
	if ( isset( $GLOBALS['migration_cached_active'] ) ) {
		$GLOBALS['migration_cached_active'] = $value;
	}
	return true;
}
function add_filter( string $hook, callable $callback, int $priority = 10 ): void {
	$GLOBALS['migration_filters'][ $hook ][ $priority ][] = $callback;
}
function remove_filter( string $hook, callable $callback, int $priority = 10 ): void {
	foreach ( $GLOBALS['migration_filters'][ $hook ][ $priority ] ?? [] as $index => $registered ) {
		if ( $registered === $callback ) {
			unset( $GLOBALS['migration_filters'][ $hook ][ $priority ][ $index ] );
		}
	}
}
function apply_filters( string $hook, string $value ): string {
	$priorities = $GLOBALS['migration_filters'][ $hook ] ?? [];
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$value = $callback( $value );
		}
	}
	return $value;
}
function wp_cache_delete( string $key, string $group ): bool {
	$GLOBALS['migration_cache_deletions'][] = [ $key, $group ];
	if ( 'options' === $group && in_array( $key, [ 'active_plugins', 'alloptions' ], true ) ) {
		unset( $GLOBALS['migration_cached_active'] );
	}
	return true;
}
$wpdb = new class {
	public string $options = 'fixture_options';
	public function prepare( string $query, mixed ...$values ): string {
		foreach ( $values as $value ) {
			$position = strpos( $query, '%s' );
			if ( false === $position ) { throw new RuntimeException( 'Unexpected fixture SQL placeholder.' ); }
			$query = substr_replace( $query, "'" . addslashes( (string) $value ) . "'", $position, 2 );
		}
		return $query;
	}
};
function get_file_data( string $file, array $headers ): array {
	$body = file_get_contents( $file );
	$result = [];
	foreach ( $headers as $key => $label ) {
		preg_match( '/^\s*\*?\s*' . preg_quote( $label, '/' ) . ':\s*(.+)$/m', $body, $match );
		$result[ $key ] = trim( $match[1] ?? '' );
	}
	return $result;
}

function migration_remove_fixture( string $path ): void {
	if ( is_link( $path ) || is_file( $path ) ) {
		unlink( $path );
	} elseif ( is_dir( $path ) ) {
		foreach ( new FilesystemIterator( $path ) as $entry ) {
			migration_remove_fixture( $entry->getPathname() );
		}
		rmdir( $path );
	}
}
function migration_fixture(): string {
	global $fixture;
	migration_remove_fixture( ABSPATH );
	migration_remove_fixture( $fixture . '/backup' );
	$old = [
		'artnovo-account-ui/artnovo-account-ui.php',
		'artnovo-header-ui/artnovo-header-ui.php',
		'artnovo-home-order-ui/artnovo-home-order-ui.php',
		'artnovo-plan-checkout/artnovo-plan-checkout.php',
	];
	$active = [ 'unrelated-a/main.php', $old[0], 'unrelated-b/main.php', ...array_slice( $old, 1 ),
		'woocommerce/woocommerce.php', 'wp-vpn-bridge/wp-vpn-bridge.php', 'pinova/pinova.php', 'elementor/elementor.php' ];
	$GLOBALS['migration_options'] = [
		'active_plugins' => $active,
		'artnovo_checkout_email_otp' => 'yes',
		'woocommerce_bacs_settings' => [ 'account_number' => 'PRIVATE-BANK-FIXTURE' ],
	];
	$GLOBALS['migration_writes'] = 0;
	$GLOBALS['migration_write_fails'] = false;
	$GLOBALS['migration_before_write'] = null;
	$GLOBALS['migration_unknown_sql'] = false;
	$GLOBALS['migration_rename_fails'] = false;
	$GLOBALS['migration_filters'] = [];
	$GLOBALS['migration_cache_deletions'] = [];
	$GLOBALS['migration_cache_enabled'] = false;
	unset( $GLOBALS['migration_cached_active'] );
	foreach ( $old as $entry ) {
		$file = WP_PLUGIN_DIR . '/' . $entry;
		mkdir( dirname( $file ), 0755, true );
		file_put_contents( $file, "<?php // preserved legacy source\n" );
	}
	mkdir( WPMU_PLUGIN_DIR, 0755, true );
	file_put_contents( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php', "<?php // old sender\n" );
	mkdir( WP_PLUGIN_DIR . '/artnovo-core/modules/mail', 0755, true );
	file_put_contents( WP_PLUGIN_DIR . '/artnovo-core/artnovo-core.php', "<?php\n/**\n * Plugin Name: Artnovo Core\n * Version: 1.0.0\n */\n" );
	file_put_contents( WP_PLUGIN_DIR . '/artnovo-core/modules/mail/artnovo-mail-sender.php', "<?php // core sender\n" );
	mkdir( $fixture . '/backup', 0700 );
	return $fixture . '/backup';
}

$tool = dirname( __DIR__ ) . '/tools/migrate.php';
if ( ! is_file( $tool ) ) {
	fwrite( STDERR, "FAIL migration preservation contract: tools/migrate.php is missing\n" );
	migration_remove_fixture( $fixture );
	exit( 1 );
}
require $tool;

$failures = 0;
$check = static function ( string $label, bool $passed ) use ( &$failures ): void {
	$failures += $passed ? 0 : 1;
	echo ( $passed ? 'PASS ' : 'FAIL ' ) . $label . "\n";
};
$rejects = static function ( callable $action ): bool {
	try { $action(); } catch ( RuntimeException ) { return true; }
	return false;
};

try {
	$backup = migration_fixture();
	$before = get_option( 'active_plugins' );
	$expected = [ 'unrelated-a/main.php', 'artnovo-core/artnovo-core.php', 'unrelated-b/main.php',
		'woocommerce/woocommerce.php', 'wp-vpn-bridge/wp-vpn-bridge.php', 'pinova/pinova.php', 'elementor/elementor.php' ];
	$plan = Artnovo\Core\Migration\run( 'dry-run', $backup );
	$check( 'replacement retains unrelated entries and their order', $plan['after'] === $expected );
	$check( 'dry-run writes no option', 0 === $GLOBALS['migration_writes'] && get_option( 'active_plugins' ) === $before );
	$check( 'snapshot contains hashes instead of bank settings', ! str_contains( file_get_contents( $backup . '/snapshot.json' ), 'PRIVATE-BANK-FIXTURE' ) );
	Artnovo\Core\Migration\run( 'apply', $backup );
	$check( 'apply makes one activation API write', 1 === $GLOBALS['migration_writes'] && get_option( 'active_plugins' ) === $expected );
	$check( 'activation guard leaves unrelated option SQL unchanged', $GLOBALS['migration_unrelated_query_preserved'] );
	$check( 'sender is retired after Core activation', ! file_exists( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php' ) );
	$check( 'normal legacy source remains for in-flight requests', is_file( WP_PLUGIN_DIR . '/artnovo-account-ui/artnovo-account-ui.php' ) );
	Artnovo\Core\Migration\run( 'apply', $backup );
	$check( 'apply is idempotent', 1 === $GLOBALS['migration_writes'] );
	migration_remove_fixture( WP_PLUGIN_DIR . '/artnovo-account-ui' );
	Artnovo\Core\Migration\run( 'rollback', $backup );
	$check( 'rollback restores exact activation set', 2 === $GLOBALS['migration_writes'] && get_option( 'active_plugins' ) === $before );
	$check( 'rollback restores archived source and sender', is_file( WP_PLUGIN_DIR . '/artnovo-account-ui/artnovo-account-ui.php' ) && is_file( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php' ) );
	Artnovo\Core\Migration\run( 'rollback', $backup );
	$check( 'rollback is idempotent', 2 === $GLOBALS['migration_writes'] );

	$backup = migration_fixture();
	$plan = Artnovo\Core\Migration\run( 'dry-run', $backup );
	$GLOBALS['migration_cache_enabled'] = true;
	$GLOBALS['migration_cached_active'] = $plan['before'];
	$concurrent = [ ...$plan['before'], 'concurrent-third-party/main.php' ];
	$GLOBALS['migration_before_write'] = static function () use ( $concurrent ): void {
		$GLOBALS['migration_options']['active_plugins'] = $concurrent;
	};
	$check( 'apply refuses a persisted activation race despite its stale process cache', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) )
		&& $GLOBALS['migration_options']['active_plugins'] === $concurrent );
	$check( 'failed compare-and-swap refreshes cache and leaves the old sender', get_option( 'active_plugins' ) === $concurrent
		&& is_file( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php' ) );
	$check( 'query guard is removed after a refused write', apply_filters( 'query', 'SELECT fixture' ) === 'SELECT fixture'
		&& ! array_filter( $GLOBALS['migration_filters']['query'] ?? [] ) );

	$backup = migration_fixture();
	$plan = Artnovo\Core\Migration\run( 'dry-run', $backup );
	Artnovo\Core\Migration\run( 'apply', $backup );
	$GLOBALS['migration_cache_enabled'] = true;
	$GLOBALS['migration_cached_active'] = $plan['after'];
	$concurrent = [ ...$plan['after'], 'concurrent-third-party/main.php' ];
	$GLOBALS['migration_before_write'] = static function () use ( $concurrent ): void {
		$GLOBALS['migration_options']['active_plugins'] = $concurrent;
	};
	$check( 'rollback refuses a persisted activation race without losing the later plugin', $rejects( static fn() => Artnovo\Core\Migration\run( 'rollback', $backup ) )
		&& $GLOBALS['migration_options']['active_plugins'] === $concurrent && get_option( 'active_plugins' ) === $concurrent );

	$backup = migration_fixture();
	$plan = Artnovo\Core\Migration\run( 'dry-run', $backup );
	$GLOBALS['migration_cache_enabled'] = true;
	$GLOBALS['migration_cached_active'] = $plan['before'];
	$concurrent = $plan['before'];
	$concurrent[0] = 'Unrelated-a/main.php';
	$GLOBALS['migration_before_write'] = static function () use ( $concurrent ): void {
		$GLOBALS['migration_options']['active_plugins'] = $concurrent;
	};
	$check( 'binary compare preserves even a case-only persisted activation change', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) )
		&& $GLOBALS['migration_options']['active_plugins'] === $concurrent );

	$backup = migration_fixture();
	$plan = Artnovo\Core\Migration\run( 'dry-run', $backup );
	$GLOBALS['migration_unknown_sql'] = true;
	$check( 'unexpected activation SQL is rejected before changing storage', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) )
		&& $GLOBALS['migration_options']['active_plugins'] === $plan['before'] && is_file( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php' ) );

	$backup = migration_fixture();
	$plan = Artnovo\Core\Migration\run( 'dry-run', $backup );
	$GLOBALS['migration_rename_fails'] = true;
	$check( 'sender retirement failure leaves Core active and the old sender intact', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) )
		&& get_option( 'active_plugins' ) === $plan['after'] && 1 === $GLOBALS['migration_writes'] && is_file( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php' ) );
	$GLOBALS['migration_rename_fails'] = false;
	Artnovo\Core\Migration\run( 'apply', $backup );
	$check( 'retry after retirement failure performs no second activation write', 1 === $GLOBALS['migration_writes']
		&& ! is_file( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php' ) );
	Artnovo\Core\Migration\run( 'rollback', $backup );
	$check( 'rollback after retirement recovery restores the exact old state', get_option( 'active_plugins' ) === $plan['before']
		&& 2 === $GLOBALS['migration_writes'] && is_file( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php' ) );

	$backup = migration_fixture();
	$plan = Artnovo\Core\Migration\run( 'dry-run', $backup );
	$GLOBALS['migration_rename_fails'] = true;
	$rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) );
	$GLOBALS['migration_rename_fails'] = false;
	Artnovo\Core\Migration\run( 'rollback', $backup );
	$check( 'rollback directly from failed retirement restores the exact old state', get_option( 'active_plugins' ) === $plan['before']
		&& 2 === $GLOBALS['migration_writes'] && is_file( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php' ) );

	$backup = migration_fixture();
	$plan = Artnovo\Core\Migration\run( 'dry-run', $backup );
	file_put_contents( $backup . '/retired-sender.php', 'preserve this different backup' );
	$check( 'retirement obstruction preserves both the working sender and unrelated backup', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) )
		&& get_option( 'active_plugins' ) === $plan['after'] && is_file( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php' )
		&& 'preserve this different backup' === file_get_contents( $backup . '/retired-sender.php' ) );
	unlink( $backup . '/retired-sender.php' );
	Artnovo\Core\Migration\run( 'apply', $backup );
	Artnovo\Core\Migration\run( 'rollback', $backup );
	$check( 'retry and rollback after clearing a retirement obstruction preserve activation history', get_option( 'active_plugins' ) === $plan['before']
		&& 2 === $GLOBALS['migration_writes'] && is_file( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php' ) );

	$backup = migration_fixture();
	Artnovo\Core\Migration\run( 'dry-run', $backup );
	file_put_contents( WP_PLUGIN_DIR . '/artnovo-header-ui/artnovo-header-ui.php', '<?php // newer edit' );
	$check( 'changed legacy source prevents apply', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) ) && 0 === $GLOBALS['migration_writes'] );

	$backup = migration_fixture();
	Artnovo\Core\Migration\run( 'dry-run', $backup );
	$GLOBALS['migration_options']['active_plugins'][] = 'new-third-party/main.php';
	$check( 'changed activation set is not overwritten', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) ) && 0 === $GLOBALS['migration_writes'] );

	$backup = migration_fixture();
	Artnovo\Core\Migration\run( 'dry-run', $backup );
	$GLOBALS['migration_options']['woocommerce_bacs_settings']['account_number'] = 'changed';
	$check( 'changed settings prevent apply without rewriting them', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) ) && 0 === $GLOBALS['migration_writes'] );

	$backup = migration_fixture();
	Artnovo\Core\Migration\run( 'dry-run', $backup );
	file_put_contents( WP_PLUGIN_DIR . '/artnovo-core/modules/mail/artnovo-mail-sender.php', '<?php // different core' );
	$check( 'changed Core candidate prevents apply', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) ) && 0 === $GLOBALS['migration_writes'] );

	$backup = migration_fixture();
	Artnovo\Core\Migration\run( 'dry-run', $backup );
	$GLOBALS['migration_write_fails'] = true;
	$check( 'failed option write preserves the sender', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) ) && is_file( WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php' ) );

	$backup = migration_fixture();
	Artnovo\Core\Migration\run( 'dry-run', $backup );
	Artnovo\Core\Migration\run( 'apply', $backup );
	$GLOBALS['migration_options']['active_plugins'][] = 'new-third-party/main.php';
	$check( 'rollback refuses to discard later plugin changes', $rejects( static fn() => Artnovo\Core\Migration\run( 'rollback', $backup ) ) && 1 === $GLOBALS['migration_writes'] );

	$backup = migration_fixture();
	Artnovo\Core\Migration\run( 'dry-run', $backup );
	Artnovo\Core\Migration\run( 'apply', $backup );
	file_put_contents( WP_PLUGIN_DIR . '/artnovo-header-ui/artnovo-header-ui.php', '<?php // newer edit' );
	$check( 'rollback never overwrites later source edits', $rejects( static fn() => Artnovo\Core\Migration\run( 'rollback', $backup ) ) && 1 === $GLOBALS['migration_writes'] );

	$backup = migration_fixture();
	$unsafe = WP_CONTENT_DIR . '/backup';
	mkdir( $unsafe, 0700 );
	$check( 'backup inside web content is rejected', $rejects( static fn() => Artnovo\Core\Migration\run( 'dry-run', $unsafe ) ) && 0 === $GLOBALS['migration_writes'] );
	chmod( $backup, 0755 );
	$check( 'non-private backup permissions are rejected', $rejects( static fn() => Artnovo\Core\Migration\run( 'dry-run', $backup ) ) );
	chmod( $backup, 0700 );
	$link = $fixture . '/backup-link';
	symlink( $backup, $link );
	$check( 'symlink backup is rejected', $rejects( static fn() => Artnovo\Core\Migration\run( 'dry-run', $link ) ) );
	file_put_contents( $backup . '/unrelated.txt', 'leave alone' );
	$check( 'nonempty unknown backup is not overwritten', $rejects( static fn() => Artnovo\Core\Migration\run( 'dry-run', $backup ) ) );

	$backup = migration_fixture();
	symlink( '/etc/hosts', WP_PLUGIN_DIR . '/artnovo-header-ui/linked-file' );
	$check( 'symlink legacy source is rejected without copying it', $rejects( static fn() => Artnovo\Core\Migration\run( 'dry-run', $backup ) ) && 0 === $GLOBALS['migration_writes'] );

	$backup = migration_fixture();
	$lock_target = $fixture . '/leave-unchanged.txt';
	file_put_contents( $lock_target, 'unchanged' );
	chmod( $lock_target, 0644 );
	symlink( $lock_target, $backup . '/.migration.lock' );
	$check( 'symlink lock is rejected without touching its target', $rejects( static fn() => Artnovo\Core\Migration\run( 'dry-run', $backup ) )
		&& 'unchanged' === file_get_contents( $lock_target ) && 0644 === ( fileperms( $lock_target ) & 0777 ) );

	$backup = migration_fixture();
	Artnovo\Core\Migration\run( 'dry-run', $backup );
	file_put_contents( $backup . '/source/plugins/artnovo-header-ui/artnovo-header-ui.php', '<?php // corrupted backup' );
	$check( 'corrupt source backup prevents activation', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) ) && 0 === $GLOBALS['migration_writes'] );

	$backup = migration_fixture();
	$GLOBALS['migration_options']['active_plugins'] = array_values( array_diff( get_option( 'active_plugins' ), [ 'wp-vpn-bridge/wp-vpn-bridge.php' ] ) );
	$check( 'missing independent bridge prevents migration', $rejects( static fn() => Artnovo\Core\Migration\run( 'dry-run', $backup ) ) && 0 === $GLOBALS['migration_writes'] );

	$backup = migration_fixture();
	Artnovo\Core\Migration\run( 'dry-run', $backup );
	unlink( WP_PLUGIN_DIR . '/artnovo-core/artnovo-core.php' );
	$check( 'missing Core candidate prevents activation', $rejects( static fn() => Artnovo\Core\Migration\run( 'apply', $backup ) ) && 0 === $GLOBALS['migration_writes'] );

	$git = $fixture . '/git-project';
	mkdir( $git . '/.git', 0700, true );
	mkdir( $git . '/backup', 0700 );
	$check( 'backup inside any Git working tree is rejected', $rejects( static fn() => Artnovo\Core\Migration\run( 'dry-run', $git . '/backup' ) ) );
} finally {
	migration_remove_fixture( $fixture );
}
exit( $failures ? 1 : 0 );

}
