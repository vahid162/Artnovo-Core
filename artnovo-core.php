<?php
/**
 * Plugin Name: Artnovo Core
 * Plugin URI: https://github.com/vahid162/Artnovo-Core
 * Description: ArtNovo purchase flow, email sign-in, customer panel and subscription delivery interface.
 * Version: 1.0.0
 * Requires at least: 7.0
 * Requires PHP: 8.5
 * Requires Plugins: woocommerce, elementor, pinova, wp-vpn-bridge
 * Update URI: https://github.com/vahid162/Artnovo-Core
 * Author: ArtNovo
 * Text Domain: artnovo-core
 */

namespace Artnovo\Core;

defined( 'ABSPATH' ) || exit;

const VERSION = '1.0.0';
const FILE = __FILE__;
const LEGACY_PLUGINS = [
	'artnovo-account-ui/artnovo-account-ui.php',
	'artnovo-header-ui/artnovo-header-ui.php',
	'artnovo-home-order-ui/artnovo-home-order-ui.php',
	'artnovo-plan-checkout/artnovo-plan-checkout.php',
];

// Rollback can restore the file after this request's MU loading phase.
$legacy_sender = WPMU_PLUGIN_DIR . '/artnovo-mail-sender.php';
$legacy_sender = wp_normalize_path( realpath( $legacy_sender ) ?: $legacy_sender );
if ( ! in_array( $legacy_sender, array_map( 'wp_normalize_path', get_included_files() ), true ) ) {
	require_once __DIR__ . '/modules/mail/artnovo-mail-sender.php';
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\boot', 30 );

function boot(): void {
	$active = (array) get_option( 'active_plugins', [] );
	if ( is_multisite() ) {
		$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
	}
	$message = '';
	if ( ! class_exists( '\WooCommerce' ) || ! class_exists( '\WPVPNBridge\Commerce\Orders' )
		|| ! class_exists( '\Pinova\Pinova' ) || ! class_exists( '\Elementor\Plugin' ) ) {
		add_filter( 'woocommerce_is_purchasable', static function ( bool $purchasable, \WC_Product $product ): bool {
			return 'yes' === $product->get_meta( '_wpvpn_enabled', true ) ? false : $purchasable;
		}, PHP_INT_MAX, 2 );
		$message = 'Artnovo Core به ووکامرس، المنتور، Pinova و افزونهٔ مستقل WP VPN Bridge نیاز دارد. فعال بودن این وابستگی‌ها را بررسی کنید.';
	} elseif ( array_intersect( LEGACY_PLUGINS, $active ) ) {
		$message = 'برای اجرای Artnovo Core، ابتدا انتقال افزونه‌های قدیمی آرت‌نوو را طبق راهنمای نصب کامل کنید. کدهای قدیمی هم‌زمان با Core اجرا نمی‌شوند.';
	}
	if ( '' !== $message ) {
		add_action( 'admin_notices', static function () use ( $message ): void {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
			}
		} );
		return;
	}

	// Preserve the original registration order for callbacks sharing a priority.
	require_once __DIR__ . '/modules/account/artnovo-account-ui.php';
	require_once __DIR__ . '/modules/header/artnovo-header-ui.php';
	require_once __DIR__ . '/modules/home/artnovo-home-order-ui.php';
	require_once __DIR__ . '/modules/checkout/artnovo-plan-checkout.php';
}
