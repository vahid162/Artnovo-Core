<?php

namespace Artnovo\AccountUI;

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__ ) . '/auth/account-auth.php';
require_once __DIR__ . '/account-views.php';
require_once dirname( __DIR__ ) . '/order-received/received.php';

function account_view(): string {
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'edit-account' ) ) {
		return 'profile';
	}
	$view = $_GET['artnovo_view'] ?? '';
	return is_string( $view ) && in_array( $view, [ 'help', 'support', 'profile' ], true ) ? $view : 'subscriptions';
}

function account_url( string $view = 'subscriptions' ): string {
	return 'subscriptions' === $view ? wc_get_account_endpoint_url( 'vpn-subscriptions' )
		: add_query_arg( 'artnovo_view', $view, wc_get_page_permalink( 'myaccount' ) );
}

add_action( 'template_redirect', static function (): void {
	global $wp;
	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_user_logged_in()
		&& ( is_wc_endpoint_url( 'order-pay' ) || is_wc_endpoint_url( 'order-received' ) ) ) {
		nocache_headers();
		$request = is_string( $_SERVER['REQUEST_URI'] ?? null ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$return = account_auth_destination( home_url( $request ) );
		wp_safe_redirect( add_query_arg( 'artnovo_redirect', $return, wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}
	if ( 'login' === ( $wp->request ?? '' ) && function_exists( 'wc_get_page_permalink' ) ) {
		nocache_headers();
		$return = isset( $_GET['back_url'] ) && is_string( $_GET['back_url'] )
			? account_auth_destination( wp_unslash( $_GET['back_url'] ) ) : account_url();
		wp_safe_redirect( is_user_logged_in() ? $return : add_query_arg( 'artnovo_redirect', $return, wc_get_page_permalink( 'myaccount' ) ) );
		exit;
	}
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}
	nocache_headers();
	header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	if ( class_exists( '\Pinova\Pinova' ) ) {
		remove_action( 'template_redirect', [ \Pinova\Pinova::instance(), 'handle_urls' ] );
	}
	if ( is_wc_endpoint_url( 'customer-logout' ) ) {
		return;
	}
	$endpoint = WC()->query->get_current_endpoint();
	if ( is_user_logged_in() && ! isset( $_POST['artnovo_account_action'] ) && 'subscriptions' === account_view()
		&& in_array( $endpoint, [ '', 'dashboard', 'orders', 'downloads', 'edit-address', 'payment-methods', 'lost-password' ], true ) ) {
		wp_safe_redirect( account_url() );
		exit;
	}
}, 4 );

add_filter( 'template_include', static function ( string $template ): string {
	return function_exists( 'is_account_page' ) && is_account_page() && ! is_wc_endpoint_url( 'customer-logout' )
		? __DIR__ . '/account-page.php' : $template;
}, 99 );

add_filter( 'body_class', static function ( array $classes ): array {
	if ( function_exists( 'is_account_page' ) && ( is_account_page() || received_page() ) ) {
		$classes[] = 'artnovo-account-page';
	}
	return $classes;
} );

add_filter( 'wp_robots', static function ( array $robots ): array {
	if ( function_exists( 'is_account_page' ) && ( is_account_page() || received_page() ) ) {
		$robots['noindex'] = true;
		$robots['nofollow'] = true;
	}
	return $robots;
} );

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( ! function_exists( 'is_account_page' ) || ( ! is_account_page() && ! received_page() ) ) {
		return;
	}
	wp_enqueue_style( 'artnovo-account-ui', plugins_url( 'artnovo-account.css', __FILE__ ), [], (string) filemtime( __DIR__ . '/artnovo-account.css' ) );
	$dependencies = [ 'jquery' ];
	if ( received_page() ) {
		wp_enqueue_style( 'artnovo-received-ui', plugins_url( 'modules/order-received/received.css', \Artnovo\Core\FILE ), [ 'artnovo-account-ui' ], (string) filemtime( dirname( __DIR__ ) . '/order-received/received.css' ) );
		wp_enqueue_script( 'artnovo-received-ui', plugins_url( 'modules/order-received/received.js', \Artnovo\Core\FILE ), [], (string) filemtime( dirname( __DIR__ ) . '/order-received/received.js' ), true );
		$copy = WP_PLUGIN_DIR . '/wp-vpn-bridge/assets/subscriptions.js';
		if ( file_exists( $copy ) ) {
			wp_enqueue_script( 'wpvpn-subscriptions', plugins_url( 'wp-vpn-bridge/assets/subscriptions.js' ), [ 'artnovo-received-ui' ], (string) hash_file( 'sha256', $copy ), true );
			$dependencies[] = 'wpvpn-subscriptions';
		}
	}
	if ( is_user_logged_in() && file_exists( __DIR__ . '/jquery.qrcode.min.js' ) ) {
		wp_enqueue_script( 'artnovo-account-qr', plugins_url( 'jquery.qrcode.min.js', __FILE__ ), [ 'jquery' ], (string) filemtime( __DIR__ . '/jquery.qrcode.min.js' ), true );
		$dependencies[] = 'artnovo-account-qr';
	}
	wp_enqueue_script( 'artnovo-account-ui', plugins_url( 'artnovo-account.js', __FILE__ ), $dependencies, (string) filemtime( __DIR__ . '/artnovo-account.js' ), true );
} );

function decorate_subscriptions( string $html ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	$base = trailingslashit( wc_get_account_endpoint_url( 'view-order' ) );
	$store = class_exists( '\WPVPNBridge\Persistence\Store' ) ? new \WPVPNBridge\Persistence\Store() : null;
	$contexts = [];
	$unit_numbers = [];
	$has_card = false;
	while ( $tags->next_tag() ) {
		if ( 'SECTION' === $tags->get_tag() && $tags->has_class( 'wpvpn-subscription' ) ) {
			$tags->set_bookmark( 'artnovo-card' );
			$has_card = true;
		}
		$href = $tags->get_attribute( 'href' );
		if ( ! $has_card || 'A' !== $tags->get_tag() || ! is_string( $href ) || ! str_starts_with( $href, $base ) ) {
			continue;
		}
		$id = trim( substr( $href, strlen( $base ) ), '/' );
		if ( ! ctype_digit( $id ) ) {
			continue;
		}
		$id = (int) $id;
		$order = wc_get_order( $id );
		if ( ! $order instanceof \WC_Order || get_current_user_id() !== $order->get_customer_id() ) {
			continue;
		}
		$snapshot = $order->get_meta( '_wpvpn_snapshot', true );
		if ( ! is_array( $snapshot ) ) {
			continue;
		}
		if ( ! isset( $contexts[ $id ] ) ) {
			$contexts[ $id ] = [ $store?->get_by_order( $id ), $store?->get_units( $id ) ?? [] ];
		}
		$number = ( $unit_numbers[ $id ] ?? 0 ) + 1;
		$unit_numbers[ $id ] = $number;
		[ $operation, $units ] = $contexts[ $id ];
		$expiry = (int) ( $units[ $number - 1 ]['expires_at'] ?? $operation['expires_at'] ?? 0 );
		$product = wc_get_product( $snapshot['product_id'] ?? 0 );
		$can_buy = $product && $product->is_in_stock() && $product->is_purchasable()
			&& function_exists( '\Artnovo\PlanCheckout\wizard_url' );
		$usage = apply_filters( 'artnovo_account_subscription_usage', null, $order, $number );
		if ( ! is_array( $usage ) || ! is_int( $usage['used_bytes'] ?? null ) || ! is_int( $usage['remaining_bytes'] ?? null )
			|| $usage['used_bytes'] < 0 || $usage['remaining_bytes'] < 0 ) {
			$usage = null;
		} else {
			$usage = [ 'used_bytes' => $usage['used_bytes'], 'remaining_bytes' => $usage['remaining_bytes'] ];
		}
		$renew = apply_filters( 'artnovo_account_subscription_renewal_url', '', $order, $number );
		$data = [
			'buy_url' => $can_buy ? \Artnovo\PlanCheckout\wizard_url( $product->get_id() ) : home_url( '/#artnovo-categories' ),
			'buy_label' => $can_buy ? 'خرید دوبارهٔ این پلن' : 'انتخاب پلن جدید',
			'help_url' => account_url( 'help' ),
			'support_url' => add_query_arg( 'order', $id, account_url( 'support' ) ),
			'days_left' => $expiry > 0 ? max( 0, (int) ceil( ( $expiry - time() ) / DAY_IN_SECONDS ) ) : null,
			'usage' => $usage,
			'renew_url' => is_string( $renew ) && $renew ? wp_validate_redirect( $renew, '' ) : '',
			'purchase' => [
				'number' => (string) $order->get_order_number(),
				'date' => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
				'amount' => html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ),
				'view_url' => $order->get_view_order_url(),
			],
		];
		$tags->set_bookmark( 'artnovo-link' );
		$tags->seek( 'artnovo-card' );
		$tags->set_attribute( 'data-artnovo-card', wp_json_encode( $data ) );
		$tags->seek( 'artnovo-link' );
		$has_card = false;
	}
	return $tags->get_updated_html();
}

add_filter( 'gettext_wp-vpn-bridge', static function ( string $translated, string $text ): string {
	if ( ! function_exists( 'is_account_page' ) || ( ! is_account_page() && ! received_page() ) ) {
		return $translated;
	}
	$labels = [
		'My subscriptions' => 'اشتراک‌های من',
		'VPN subscription' => 'اشتراک VPN',
		'%1$s · Subscription %2$d' => '%1$s · اشتراک %2$d',
		'Subscription %d' => 'اشتراک %d',
		'Subscriptions could not be loaded.' => 'دریافت اشتراک‌ها انجام نشد. کمی بعد دوباره تلاش کنید.',
		'No subscriptions on this page.' => 'در این صفحه اشتراکی پیدا نشد.',
		'%s GiB' => 'حجم خریداری‌شده %s گیگابایت',
		'Expires %s' => 'پایان اعتبار %s',
		'View order' => 'جزئیات خرید و رسید',
		'Open subscription link' => 'باز کردن لینک اتصال',
		'Subscription URL' => 'لینک خصوصی اشتراک',
		'Copy subscription link' => 'کپی لینک اتصال',
		'Subscription link copied.' => 'لینک اتصال کپی شد.',
		'Link selected. Copy it using your device.' => 'لینک انتخاب شد. آن را با دستگاه خود کپی کنید.',
		'Ready' => 'آمادهٔ دریافت',
		'Expired' => 'پایان اعتبار',
		'Access held' => 'نیازمند بررسی پشتیبانی',
		'Awaiting verified wallet payment' => 'در انتظار تأیید پرداخت',
		'Payment needs administrator verification' => 'در انتظار بررسی پرداخت',
		'Awaiting bank transfer confirmation' => 'در انتظار تأیید رسید پرداخت',
		'Queued for issuance' => 'در صف آماده‌سازی اشتراک',
		'Issuing service' => 'در حال آماده‌سازی اشتراک',
		'Waiting to retry safely' => 'در انتظار ادامهٔ آماده‌سازی',
		'Checking the previous result' => 'در حال بررسی نتیجهٔ آماده‌سازی',
		'Administrator review required' => 'نیازمند بررسی پشتیبانی',
		'Subscription pages' => 'صفحه‌های اشتراک',
		'First page' => 'صفحهٔ اول',
		'Previous page' => 'صفحهٔ قبل',
		'Next page' => 'صفحهٔ بعد',
		'Private payment receipt' => 'رسید پرداخت',
		'Download your receipt' => 'دریافت رسید ثبت‌شده',
		'Receipt image' => 'تصویر رسید',
		'Submit receipt for review' => 'ارسال رسید برای بررسی',
		'Upload a JPEG or PNG up to 2 MiB. Uploading a receipt does not confirm payment.' => 'تصویر رسید را با فرمت JPG یا PNG و حجم حداکثر ۲ مگابایت بفرستید. پس از بررسی پرداخت، اشتراک آماده می‌شود.',
		'Your VPN service' => 'وضعیت اشتراک',
		'Awaiting actual payment confirmation by the store administrator.' => 'رسید پرداخت در انتظار تأیید فروشگاه است.',
		'Service issued' => 'اشتراک آماده شده است',
		'Service reference:' => 'شناسهٔ اشتراک',
		'Open your private subscription link' => 'باز کردن لینک خصوصی اتصال',
		'The service has expired.' => 'اعتبار این اشتراک به پایان رسیده است.',
		'The service is not available.' => 'دریافت لینک این اشتراک نیاز به بررسی دارد.',
		'%1$s GiB; expires %2$s.' => 'حجم %1$s گیگابایت، پایان اعتبار %2$s',
	];
	return $labels[ $text ] ?? $translated;
}, 10, 2 );
