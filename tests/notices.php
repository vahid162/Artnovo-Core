<?php
// Isolated frontend-asset contract: no WordPress, database, external plugins or HTTP.
define( 'ABSPATH', __DIR__ . '/unused-wordpress/' );
$hooks = [];
$assets = [];
$admin = false;
function add_action( string $hook, callable $callback, int $priority = 10 ): void { $GLOBALS['hooks'][ $hook ][ $priority ][] = $callback; }
function is_admin(): bool { return $GLOBALS['admin']; }
function plugins_url( string $path, string $file ): string { return 'https://example.invalid/core/notices/' . $path; }
function wp_enqueue_style( string $handle, ...$args ): void { $GLOBALS['assets'][] = [ 'style', $handle, ...$args ]; }
function wp_enqueue_script( string $handle, ...$args ): void { $GLOBALS['assets'][] = [ 'script', $handle, ...$args ]; }
function check( bool $condition, string $label ): void { if ( ! $condition ) { fwrite( STDERR, 'FAIL ' . $label . "\n" ); exit( 1 ); } echo 'PASS ' . $label . "\n"; }
require dirname( __DIR__ ) . '/modules/notices/notices.php';
check( 1 === count( $hooks['wp_enqueue_scripts'][30] ?? [] ), 'one frontend notice adapter is registered after native assets' );
$hooks['wp_enqueue_scripts'][30][0]();
check( 2 === count( $assets ) && [ 'style', 'artnovo-notices' ] === array_slice( $assets[0], 0, 2 ) && [ 'script', 'artnovo-notices' ] === array_slice( $assets[1], 0, 2 ), 'frontend receives only Core notice CSS and JS' );
check( [ 'jquery' ] === $assets[1][3] && true === $assets[1][5], 'script reuses native jQuery and runs in the footer' );
check( [] === $assets[0][3], 'CSS has no dependency on Bridge, Wallet or Pinova' );
$assets = [];
$admin = true;
$hooks['wp_enqueue_scripts'][30][0]();
check( [] === $assets, 'WordPress administration is excluded' );
