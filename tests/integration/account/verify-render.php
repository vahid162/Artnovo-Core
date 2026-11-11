<?php
// WP-CLI-only fixture. Customer identity and all cart/session values stay in memory.
require_once dirname( __DIR__ ) . '/fixture-paths.php';
artnovo_fixture_boot_core();
$root = artnovo_fixture_root() . '/modules/account';

add_filter( 'query', static function ( string $query ): string {
	if ( ! preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $query ) ) {
		WP_CLI::error( 'A database write was blocked during account rendering.' );
	}
	return $query;
}, PHP_INT_MAX );
add_filter( 'pre_wp_mail', static function (): never {
	WP_CLI::error( 'An email send was blocked during account rendering.' );
} );
add_filter( 'pre_http_request', static function (): never {
	WP_CLI::error( 'An HTTP request was blocked during account rendering.' );
} );
remove_action( 'shutdown', '_wp_cron' );
if ( WC()->session instanceof WC_Session_Handler ) {
	remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
}
if ( WC()->customer instanceof WC_Customer ) {
	remove_action( 'shutdown', [ WC()->customer, 'save' ], 10 );
}
WC()->session = new class extends WC_Session {
	public function has_session(): bool {
		return false;
	}
};
WC()->customer = new WC_Customer( 0, false );
$id = 2147000001;
if ( get_user_by( 'id', $id ) ) {
	WP_CLI::error( 'The synthetic customer ID is already in use.' );
}
$user = new WP_User();
$user->init( (object) [
	'ID' => $id,
	'user_login' => 'artnovo-render-fixture',
	'user_email' => 'account-test@example.invalid',
	'display_name' => 'کاربر آزمایشی',
] );
$GLOBALS['current_user'] = $user;
$query = new WP_Query();
$query->is_page = true;
$query->queried_object = get_post( wc_get_page_id( 'myaccount' ) );
$query->queried_object_id = wc_get_page_id( 'myaccount' );
$GLOBALS['wp_query'] = $query;
$_GET = [];
$_POST = [];

$failures = 0;
foreach ( [ 'subscriptions', 'help', 'support', 'profile' ] as $view ) {
	$_GET['artnovo_view'] = $view;
	ob_start();
	Artnovo\AccountUI\render_account();
	$markup = ob_get_clean();
	if ( ! str_contains( $markup, 'artnovo-account-layout' )
		|| str_contains( $markup, 'woocommerce-cart-form' )
		|| str_contains( $markup, 'Fatal error' ) ) {
		++$failures;
		WP_CLI::log( 'FAIL render ' . $view );
		continue;
	}
	$css = file_get_contents( $root . '/artnovo-account.css' );
	$js = file_get_contents( $root . '/artnovo-account.js' );
	$jquery = file_get_contents( ABSPATH . WPINC . '/js/jquery/jquery.min.js' );
	$qr = file_get_contents( $root . '/jquery.qrcode.min.js' );
	$copy = file_get_contents( WP_PLUGIN_DIR . '/wp-vpn-bridge/assets/subscriptions.js' );
	if ( 'subscriptions' === $view ) {
		$data = [
			'buy_url' => 'https://artnovo.ir/?artnovo_plan=25#artnovo-order',
			'buy_label' => 'خرید دوبارهٔ این پلن',
			'help_url' => 'https://artnovo.ir/my-account/?artnovo_view=help',
			'support_url' => 'https://artnovo.ir/my-account/?artnovo_view=support',
			'days_left' => 12,
			'usage' => null,
			'renew_url' => '',
			'purchase' => [
				'number' => 'QA-000',
				'date' => '۲۰۲۶/۱۱/۱۱',
				'amount' => '۱۰۰٬۰۰۰ تومان',
				'view_url' => 'https://artnovo.ir/my-account/view-order/0/',
			],
		];
		$card = '<section class="wpvpn-subscription" data-artnovo-card="' . esc_attr( wp_json_encode( $data ) ) . '">'
			. '<h3>اشتراک آزمایشی با عنوان بلند برای بررسی نمایش روی موبایل</h3><p>آمادهٔ دریافت</p><p>حجم خریداری‌شده ۱۰ گیگابایت</p>'
			. '<p>پایان اعتبار ۲۰۲۶/۱۲/۰۱</p><p><a href="https://artnovo.ir/my-account/view-order/0/">جزئیات خرید و رسید</a></p>'
			. '<div class="wpvpn-subscription-link" data-copied="کپی شد" data-selected="انتخاب شد"><p><a href="https://example.invalid/subscription/fixture-only">باز کردن لینک اتصال</a></p>'
			. '<p><label>لینک خصوصی اشتراک <input dir="ltr" type="url" readonly value="https://example.invalid/subscription/fixture-only"></label></p>'
			. '<p><button type="button" class="button" hidden>کپی لینک اتصال</button><span role="status"></span></p></div></section>';
		$position = strpos( $markup, '<section class="artnovo-account-card artnovo-account-empty">' );
		if ( false !== $position ) {
			$end = strpos( $markup, '</section>', $position ) + strlen( '</section>' );
			$markup = substr_replace( $markup, $card, $position, $end - $position );
		}
	}
	$document = '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>ArtNovo isolated account fixture</title><style>html{background:#0c1723}body{margin:0;font-family:Tahoma,sans-serif}</style><style>'
		. $css . '</style><body>' . $markup . '<script>' . $jquery . '</script><script>' . $qr . '</script><script>' . $copy . '</script><script>' . $js . '</script></body></html>';
	artnovo_fixture_write( 'account-' . $view . '.html', $document );
	WP_CLI::log( 'PASS render ' . $view );
}

class Artnovo_Account_QA_Order_Store extends WC_Order_Data_Store_CPT {
	public function get_order_type( $order_id ) {
		return in_array( (int) $order_id, [ 2147000010, 2147000011 ], true ) ? 'shop_order' : parent::get_order_type( $order_id );
	}

	public function read( &$order ) {
		if ( ! in_array( $order->get_id(), [ 2147000010, 2147000011 ], true ) ) {
			parent::read( $order );
			return;
		}
		$owner = 2147000010 === $order->get_id() ? 2147000001 : 2147000002;
		$order->set_customer_id( $owner );
		$order->update_meta_data( '_wpvpn_snapshot', [ 'product_id' => 25, 'customer_id' => $owner ] );
		$order->set_object_read( true );
	}
}

foreach ( [ 2147000010, 2147000011 ] as $order_id ) {
	if ( wc_get_order( $order_id ) ) {
		WP_CLI::error( 'The synthetic order ID is already in use.' );
	}
}
$store_filter = static fn() => Artnovo_Account_QA_Order_Store::class;
add_filter( 'woocommerce_order_data_store', $store_filter, PHP_INT_MAX );
foreach ( [ 2147000010 => true, 2147000011 => false ] as $order_id => $owned ) {
	$base = trailingslashit( wc_get_account_endpoint_url( 'view-order' ) );
	$source = '<section class="wpvpn-subscription"><h3>اشتراک آزمایشی</h3><p><a href="' . esc_url( $base . $order_id . '/' ) . '">جزئیات خرید</a></p></section>';
	$decorated = Artnovo\AccountUI\decorate_subscriptions( $source );
	$tags = new WP_HTML_Tag_Processor( $decorated );
	$tags->next_tag( [ 'class_name' => 'wpvpn-subscription' ] );
	$metadata = $tags->get_attribute( 'data-artnovo-card' );
	$passed = $owned ? is_string( $metadata ) && is_array( json_decode( $metadata, true ) ) : null === $metadata;
	if ( ! $passed ) {
		++$failures;
	}
	WP_CLI::log( ( $passed ? 'PASS ' : 'FAIL ' ) . ( $owned ? 'decorate own native subscription' : 'skip metadata for other customer order' ) );
}
remove_filter( 'woocommerce_order_data_store', $store_filter, PHP_INT_MAX );

$_GET = [];
$_POST = [];
$GLOBALS['wp']->query_vars = [];
$capture_redirect = static function ( string $location ): never {
	throw new RuntimeException( $location );
};
$routed = false;
add_filter( 'wp_redirect', $capture_redirect, PHP_INT_MAX );
foreach ( $GLOBALS['wp_filter']['template_redirect']->callbacks[4] ?? [] as $callback ) {
	$function = $callback['function'];
	if ( ! $function instanceof Closure ) {
		continue;
	}
	$reflection = new ReflectionFunction( $function );
	if ( realpath( $reflection->getFileName() ) !== realpath( $root . '/artnovo-account-ui.php' ) ) {
		continue;
	}
	$account_route = $function;
	try {
		$function();
	} catch ( RuntimeException $redirect ) {
		$routed = wc_get_account_endpoint_url( 'vpn-subscriptions' ) === $redirect->getMessage();
	}
}
remove_filter( 'wp_redirect', $capture_redirect, PHP_INT_MAX );
if ( ! $routed ) {
	++$failures;
}
WP_CLI::log( ( $routed ? 'PASS ' : 'FAIL ' ) . 'signed-in bare account routes to subscriptions' );

$auth_post_preserved = false;
$_POST['artnovo_account_action'] = 'send';
add_filter( 'wp_redirect', $capture_redirect, PHP_INT_MAX );
try {
	$account_route();
	$auth_post_preserved = true;
} catch ( RuntimeException $redirect ) {
	$auth_post_preserved = false;
}
remove_filter( 'wp_redirect', $capture_redirect, PHP_INT_MAX );
if ( ! $auth_post_preserved ) {
	++$failures;
}
WP_CLI::log( ( $auth_post_preserved ? 'PASS ' : 'FAIL ' ) . 'account auth POST reaches its JSON handler' );

if ( $failures ) {
	WP_CLI::error( 'Account rendering checks failed.' );
}
WP_CLI::success( 'Synthetic account views rendered without database writes, mail or HTTP.' );
