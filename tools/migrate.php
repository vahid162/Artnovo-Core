<?php
/** Run with: wp eval-file tools/migrate.php dry-run|apply|rollback /private/backup */

namespace Artnovo\Core\Migration;

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'WP-CLI only.' );
}

const CORE = 'artnovo-core/artnovo-core.php';
const LEGACY = [
	'artnovo-account-ui/artnovo-account-ui.php',
	'artnovo-header-ui/artnovo-header-ui.php',
	'artnovo-home-order-ui/artnovo-home-order-ui.php',
	'artnovo-plan-checkout/artnovo-plan-checkout.php',
];
const SENDER = 'mu-plugins/artnovo-mail-sender.php';

function activation_plan( array $active ): array {
	if ( count( array_intersect( LEGACY, $active ) ) !== count( LEGACY ) ) {
		throw new \RuntimeException( 'Expected all four legacy custom plugins; activation state needs review.' );
	}
	$after = [];
	foreach ( $active as $plugin ) {
		if ( ! is_string( $plugin ) ) {
			throw new \RuntimeException( 'Invalid active plugin entry.' );
		}
		if ( in_array( $plugin, LEGACY, true ) ) {
			if ( ! in_array( CORE, $after, true ) ) {
				$after[] = CORE;
			}
		} elseif ( CORE !== $plugin ) {
			$after[] = $plugin;
		}
	}
	return $after;
}

function backup_directory( string $directory ): string {
	$directory = rtrim( $directory, '/' );
	clearstatcache( true, $directory );
	$real = realpath( $directory );
	if ( '' === $directory || '/' !== $directory[0] || is_link( $directory ) || ! $real || ! is_dir( $real )
		|| ! is_writable( $real ) || 0 !== ( fileperms( $real ) & 0077 ) ) {
		throw new \RuntimeException( 'Provide an existing writable private directory (mode 0700), without a symlink.' );
	}
	$forbidden_paths = [ ABSPATH, WP_CONTENT_DIR ];
	$project = dirname( __DIR__ );
	if ( is_file( $project . '/artnovo-core.php' ) || is_dir( $project . '/.git' ) ) {
		$forbidden_paths[] = $project;
	}
	foreach ( $forbidden_paths as $forbidden ) {
		$base = realpath( $forbidden );
		if ( $base && ( $real === $base || str_starts_with( $real, rtrim( $base, '/' ) . '/' ) ) ) {
			throw new \RuntimeException( 'Backup must be outside WordPress, its content directory, and this repository.' );
		}
	}
	for ( $parent = $real; '/' !== $parent; $parent = dirname( $parent ) ) {
		if ( file_exists( $parent . '/.git' ) ) {
			throw new \RuntimeException( 'Backup must be outside a Git working tree.' );
		}
	}
	return $real;
}

function tree_hashes( string $directory, string $prefix = '' ): array {
	if ( ! is_dir( $directory ) || is_link( $directory ) ) {
		throw new \RuntimeException( 'Missing or linked source directory: ' . $directory );
	}
	$hashes = [];
	$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $entry ) {
		if ( $entry->isLink() || ! $entry->isFile() ) {
			throw new \RuntimeException( 'Source contains a symlink or unsupported file.' );
		}
		$hash = hash_file( 'sha256', $entry->getPathname() );
		if ( false === $hash ) {
			throw new \RuntimeException( 'Could not hash a source file.' );
		}
		$hashes[ $prefix . substr( $entry->getPathname(), strlen( $directory ) + 1 ) ] = $hash;
	}
	ksort( $hashes );
	return $hashes;
}

function legacy_hashes(): array {
	$hashes = [];
	foreach ( LEGACY as $plugin ) {
		$directory = dirname( $plugin );
		$hashes += tree_hashes( WP_PLUGIN_DIR . '/' . $directory, 'plugins/' . $directory . '/' );
	}
	$sender = WP_CONTENT_DIR . '/' . SENDER;
	if ( is_link( $sender ) || ( file_exists( $sender ) && ! is_file( $sender ) ) ) {
		throw new \RuntimeException( 'Legacy sender is not a regular file.' );
	}
	if ( is_file( $sender ) ) {
		$hashes[ SENDER ] = hash_file( 'sha256', $sender );
	}
	ksort( $hashes );
	return $hashes;
}

function setting_hashes(): array {
	$hashes = [];
	foreach ( [ 'artnovo_checkout_email_otp', 'woocommerce_bacs_settings', 'woocommerce_bacs_accounts',
		'woocommerce_myaccount_page_id', 'woocommerce_checkout_page_id', 'wpvpn_schema_version', 'wpvpn_account_rewrite' ] as $key ) {
		$hashes[ $key ] = hash( 'sha256', serialize( get_option( $key, null ) ) );
	}
	return $hashes;
}

function copy_file( string $source, string $destination, int $mode ): void {
	$parent = dirname( $destination );
	if ( ! is_dir( $parent ) && ! mkdir( $parent, 0600 === $mode ? 0700 : 0755, true ) ) {
		throw new \RuntimeException( 'Could not create a source directory.' );
	}
	if ( is_link( $source ) || is_link( $destination ) || ! copy( $source, $destination ) || ! chmod( $destination, $mode ) ) {
		throw new \RuntimeException( 'Could not preserve a source file.' );
	}
}

function read_snapshot( string $backup ): array {
	$path = $backup . '/snapshot.json';
	if ( is_link( $path ) || ! is_file( $path ) ) {
		throw new \RuntimeException( 'Missing regular snapshot.json; run dry-run first.' );
	}
	try {
		$snapshot = json_decode( file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
	} catch ( \Throwable ) {
		throw new \RuntimeException( 'Invalid snapshot JSON.' );
	}
	if ( ! is_array( $snapshot ) || 1 !== ( $snapshot['format'] ?? null ) ) {
		throw new \RuntimeException( 'Unsupported snapshot format.' );
	}
	foreach ( [ 'before', 'after', 'settings', 'sources', 'core' ] as $key ) {
		if ( ! isset( $snapshot[ $key ] ) || ! is_array( $snapshot[ $key ] ) ) {
			throw new \RuntimeException( 'Incomplete snapshot.' );
		}
	}
	if ( activation_plan( $snapshot['before'] ) !== $snapshot['after'] ) {
		throw new \RuntimeException( 'Invalid snapshot activation plan.' );
	}
	foreach ( $snapshot['sources'] as $relative => $hash ) {
		if ( ! is_string( $relative ) || ! is_string( $hash ) ) {
			throw new \RuntimeException( 'Invalid snapshot source entry.' );
		}
		$allowed = SENDER === $relative;
		foreach ( LEGACY as $plugin ) {
			$allowed = $allowed || str_starts_with( $relative, 'plugins/' . dirname( $plugin ) . '/' );
		}
		if ( ! $allowed || preg_match( '#(?:^|/)\.{1,2}(?:/|$)|\\\\|\x00#', $relative ) || ! preg_match( '/\A[a-f0-9]{64}\z/', $hash ) ) {
			throw new \RuntimeException( 'Invalid snapshot source path or checksum.' );
		}
	}
	if ( tree_hashes( $backup . '/source' ) !== $snapshot['sources'] ) {
		throw new \RuntimeException( 'Backup source checksums changed.' );
	}
	return $snapshot;
}

function check_core( array $active ): array {
	foreach ( [ 'woocommerce/woocommerce.php', 'wp-vpn-bridge/wp-vpn-bridge.php', 'pinova/pinova.php', 'elementor/elementor.php' ] as $dependency ) {
		if ( ! in_array( $dependency, $active, true ) ) {
			throw new \RuntimeException( 'Missing active dependency: ' . $dependency );
		}
	}
	$file = WP_PLUGIN_DIR . '/' . CORE;
	if ( ! is_file( $file ) || is_link( $file ) ) {
		throw new \RuntimeException( 'Install the verified Artnovo Core package before migration.' );
	}
	$headers = get_file_data( $file, [ 'name' => 'Plugin Name', 'version' => 'Version' ] );
	if ( 'Artnovo Core' !== $headers['name'] || '1.0.0' !== $headers['version']
		|| ! is_file( WP_PLUGIN_DIR . '/artnovo-core/modules/mail/artnovo-mail-sender.php' ) ) {
		throw new \RuntimeException( 'Expected the complete Artnovo Core 1.0.0 candidate.' );
	}
	return tree_hashes( WP_PLUGIN_DIR . '/artnovo-core', 'plugins/artnovo-core/' );
}

function write_activation( array $expected, array $target ): void {
	global $wpdb;
	wp_cache_delete( 'active_plugins', 'options' );
	wp_cache_delete( 'alloptions', 'options' );
	if ( get_option( 'active_plugins', [] ) !== $expected ) {
		throw new \RuntimeException( 'Activation state changed; no replacement was made.' );
	}
	if ( $expected === $target ) {
		return;
	}
	$prefix = $wpdb->prepare( 'UPDATE `' . $wpdb->options . '` SET `option_value` = %s', serialize( $target ) );
	$suffix = $wpdb->prepare( ' WHERE `option_name` = %s', 'active_plugins' );
	$option = $wpdb->prepare( '%s', 'active_plugins' );
	$option_where = '~\bWHERE\b.*`?option_name`?\s*=\s*' . preg_quote( $option, '~' ) . '~is';
	$option_insert = '~\(\s*`?option_name`?\s*,[^)]*\)\s*VALUES\s*\(\s*' . preg_quote( $option, '~' ) . '\s*,~is';
	$guarded = false;
	$guard = static function ( string $query ) use ( $wpdb, $prefix, $suffix, $option_where, $option_insert, $expected, &$guarded ): string {
		if ( str_starts_with( $query, $prefix ) && str_ends_with( $query, $suffix ) && ! $guarded ) {
			$guarded = true;
			// Retain native option hooks/caches while atomically rejecting a later activation change.
			return $query . $wpdb->prepare( ' AND BINARY `option_value` = %s', serialize( $expected ) );
		}
		if ( preg_match( '/^\s*(UPDATE|INSERT|REPLACE|DELETE)\b/i', $query )
			&& str_contains( $query, $wpdb->options ) && ( preg_match( $option_where, $query ) || preg_match( $option_insert, $query ) ) ) {
			throw new \RuntimeException( 'Unexpected activation write; no unguarded replacement was allowed.' );
		}
		return $query;
	};
	$updated = false;
	add_filter( 'query', $guard, PHP_INT_MAX );
	try {
		$updated = update_option( 'active_plugins', $target );
	} finally {
		remove_filter( 'query', $guard, PHP_INT_MAX );
		if ( ! $updated ) {
			wp_cache_delete( 'active_plugins', 'options' );
			wp_cache_delete( 'alloptions', 'options' );
		}
	}
	if ( ! $guarded || ! $updated || get_option( 'active_plugins', [] ) !== $target ) {
		throw new \RuntimeException( 'Activation update failed; inspect active_plugins before retrying.' );
	}
}

function run( string $mode, string $directory ): array {
	if ( ! in_array( $mode, [ 'dry-run', 'apply', 'rollback' ], true ) ) {
		throw new \RuntimeException( 'Usage: wp eval-file tools/migrate.php dry-run|apply|rollback /private/backup' );
	}
	$backup = backup_directory( $directory );
	$network = array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) );
	if ( array_intersect( [ CORE, ...LEGACY ], $network ) ) {
		throw new \RuntimeException( 'Network-active custom plugins require a separate migration.' );
	}
	$lock_file = $backup . '/.migration.lock';
	if ( is_link( $lock_file ) || ( file_exists( $lock_file ) && ! is_file( $lock_file ) ) ) {
		throw new \RuntimeException( 'Migration lock must be a regular file.' );
	}
	$lock = fopen( $lock_file, 'c' );
	if ( ! $lock ) {
		throw new \RuntimeException( 'Could not open the migration lock.' );
	}
	if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
		fclose( $lock );
		throw new \RuntimeException( 'Another migration owns this backup directory.' );
	}
	chmod( $lock_file, 0600 );
	try {
		$active = get_option( 'active_plugins', [] );
		if ( ! is_array( $active ) ) {
			throw new \RuntimeException( 'Invalid active_plugins option.' );
		}
		if ( 'dry-run' === $mode && ! file_exists( $backup . '/snapshot.json' ) ) {
			if ( array_diff( scandir( $backup ), [ '.', '..', '.migration.lock' ] ) ) {
				throw new \RuntimeException( 'Backup directory is not empty; no files were overwritten.' );
			}
			$snapshot = [ 'format' => 1, 'before' => $active, 'after' => activation_plan( $active ),
				'settings' => setting_hashes(), 'sources' => legacy_hashes(), 'core' => check_core( $active ) ];
			foreach ( $snapshot['sources'] as $relative => $hash ) {
				copy_file( WP_CONTENT_DIR . '/' . $relative, $backup . '/source/' . $relative, 0600 );
			}
			$json = json_encode( $snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
			if ( strlen( $json ) !== file_put_contents( $backup . '/snapshot.json', $json, LOCK_EX ) || ! chmod( $backup . '/snapshot.json', 0600 ) ) {
				throw new \RuntimeException( 'Could not write the activation snapshot.' );
			}
		}
		$snapshot = read_snapshot( $backup );
		if ( $active !== $snapshot['before'] && $active !== $snapshot['after'] ) {
			throw new \RuntimeException( 'Activation state differs from the snapshot; later changes were preserved.' );
		}
		if ( setting_hashes() !== $snapshot['settings'] ) {
			throw new \RuntimeException( 'Preserved settings changed; review before continuing.' );
		}
		if ( 'rollback' === $mode ) {
			// Restore code before activation so the next request can load the old plugins.
			$current = [];
			foreach ( LEGACY as $plugin ) {
				$slug = dirname( $plugin );
				if ( is_dir( WP_PLUGIN_DIR . '/' . $slug ) ) {
					$current += tree_hashes( WP_PLUGIN_DIR . '/' . $slug, 'plugins/' . $slug . '/' );
				}
			}
			if ( file_exists( WP_CONTENT_DIR . '/' . SENDER ) || is_link( WP_CONTENT_DIR . '/' . SENDER ) ) {
				if ( is_link( WP_CONTENT_DIR . '/' . SENDER ) ) {
					throw new \RuntimeException( 'Current sender is a symlink.' );
				}
				$current[ SENDER ] = hash_file( 'sha256', WP_CONTENT_DIR . '/' . SENDER );
			}
			foreach ( $current as $relative => $hash ) {
				if ( ( $snapshot['sources'][ $relative ] ?? null ) !== $hash ) {
					throw new \RuntimeException( 'Legacy source changed; rollback did not overwrite it.' );
				}
			}
			foreach ( $snapshot['sources'] as $relative => $hash ) {
				if ( ! isset( $current[ $relative ] ) ) {
					copy_file( $backup . '/source/' . $relative, WP_CONTENT_DIR . '/' . $relative, 0644 );
				}
			}
			write_activation( $active, $snapshot['before'] );
		} else {
			if ( check_core( $active ) !== $snapshot['core'] ) {
				throw new \RuntimeException( 'Installed Core source changed; no replacement was made.' );
			}
			if ( $active === $snapshot['before'] && legacy_hashes() !== $snapshot['sources'] ) {
				throw new \RuntimeException( 'Legacy source changed since dry-run; no replacement was made.' );
			}
			if ( 'apply' === $mode ) {
				write_activation( $active, $snapshot['after'] );
				$sender = WP_CONTENT_DIR . '/' . SENDER;
				if ( file_exists( $sender ) || is_link( $sender ) ) {
					if ( is_link( $sender ) || ( $snapshot['sources'][ SENDER ] ?? null ) !== hash_file( 'sha256', $sender ) ) {
						throw new \RuntimeException( 'Core is active; a changed legacy sender was preserved for review.' );
					}
					$retired = $backup . '/retired-sender.php';
					if ( is_link( $retired ) || ( file_exists( $retired ) && ( $snapshot['sources'][ SENDER ] ?? null ) !== hash_file( 'sha256', $retired ) ) ) {
						throw new \RuntimeException( 'Core is active; a changed retired sender backup was preserved for review.' );
					}
					if ( ! rename( $sender, $retired ) ) {
						throw new \RuntimeException( 'Core is active; legacy sender could not be retired. It still supplies the sender filter.' );
					}
				}
			}
		}
		return [ 'mode' => $mode, 'before' => $snapshot['before'], 'after' => $snapshot['after'], 'settings_unchanged' => true ];
	} finally {
		flock( $lock, LOCK_UN );
		fclose( $lock );
	}
}

if ( isset( $args ) ) {
	try {
		$result = run( (string) ( $args[0] ?? '' ), (string) ( $args[1] ?? '' ) );
		\WP_CLI::success( json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) );
	} catch ( \Throwable $error ) {
		\WP_CLI::error( $error->getMessage() );
	}
}
