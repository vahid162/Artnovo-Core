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
// The account menu can query gateways; keep WooCommerce's shipping cache in memory on fresh installations.
add_filter( 'pre_transient_shipping-transient-version', static fn() => 'artnovo-account-fixture' );
add_filter( 'pre_transient_wc_shipping_method_count', static fn() => [
	'version' => 'artnovo-account-fixture', 'legacy' => 0, 'enabled' => 0, 'disabled' => 0,
] );
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
$assert = static function ( bool $passed, string $label ) use ( &$failures ): void {
	if ( ! $passed ) {
		++$failures;
	}
	WP_CLI::log( ( $passed ? 'PASS ' : 'FAIL ' ) . $label );
};
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
		$assert( str_contains( $markup, '<h1>اکانت‌های من</h1>' ), 'selected account layout uses its customer-facing page title' );
		$assert( 1 === substr_count( $markup, '#artnovo-categories' ) && str_contains( $markup, 'artnovo-account-mobile-nav' ),
			'compact subscriptions expose one purchase link and a native mobile menu' );
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
			$markup = substr_replace( $markup, '<div class="artnovo-account-subscriptions">' . $card . '</div>', $position, $end - $position );
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
$sources = [];
foreach ( [ 2147000010 => true, 2147000011 => false ] as $order_id => $owned ) {
	$base = trailingslashit( wc_get_account_endpoint_url( 'view-order' ) );
	$source = '<section class="wpvpn-subscription"><h3>اشتراک آزمایشی</h3><p><a href="' . esc_url( $base . $order_id . '/' ) . '">جزئیات خرید</a></p></section>';
	$sources[ $order_id ] = $source;
	$decorated = Artnovo\AccountUI\decorate_subscriptions( $source );
	$tags = new WP_HTML_Tag_Processor( $decorated );
	$tags->next_tag( [ 'class_name' => 'wpvpn-subscription' ] );
	$metadata = $tags->get_attribute( 'data-artnovo-card' );
	$passed = $owned ? is_string( $metadata ) && is_array( json_decode( $metadata, true ) ) : null === $metadata;
	if ( ! $passed ) {
		++$failures;
	}
	WP_CLI::log( ( $passed ? 'PASS ' : 'FAIL ' ) . ( $owned ? 'decorate own native subscription' : 'skip metadata for other customer order' ) );
	$_GET['order'] = (string) $order_id;
	ob_start();
	Artnovo\AccountUI\render_account_support();
	$support = ob_get_clean();
	$assert( $owned === str_contains( $support, 'href="' . esc_url( $base . $order_id . '/' ) . '"' )
		&& $owned === str_contains( $support, Artnovo\Core\display_digits( (string) $order_id ) ), 'support shows the order reference and link only to its owner' );
}
$mixed = new WP_HTML_Tag_Processor( Artnovo\AccountUI\decorate_subscriptions( implode( '', $sources ) ) );
$metadata = [];
while ( $mixed->next_tag( [ 'class_name' => 'wpvpn-subscription' ] ) ) {
	$value = $mixed->get_attribute( 'data-artnovo-card' );
	$metadata[] = is_string( $value ) ? json_decode( $value, true ) : null;
}
$assert( 2 === count( $metadata ) && is_array( $metadata[0] ) && null === $metadata[1], 'mixed native cards keep metadata on the owned order only' );
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

// Register navigation and endpoint sentinels only in this PHP process.
$route = static function ( string $endpoint = '', string $value = '' ) use ( $query ): void {
	$_GET = [];
	$_POST = [];
	$variables = '' === $endpoint ? [] : [ $endpoint => $value ];
	$GLOBALS['wp']->query_vars = $variables;
	$query->query_vars = $variables;
};
$render = static function ( callable $callback ): string {
	ob_start();
	try {
		$callback();
		return ob_get_contents();
	} finally {
		ob_end_clean();
	}
};
$wallet_registered = true;
$menu_filter = static function () use ( &$wallet_registered ): array {
	$items = [
		'dashboard' => 'Raw dashboard', 'vpn-subscriptions' => 'Raw subscriptions',
		'orders' => 'Raw orders', 'woo-wallet' => 'Raw wallet', 'downloads' => 'Raw downloads',
		'edit-address' => 'Raw address', 'edit-account' => 'Raw profile',
		'artnovo-qa-extra' => 'بخش افزونهٔ آزمایشی', 'customer-logout' => 'Raw logout',
	];
	if ( ! $wallet_registered ) {
		unset( $items['woo-wallet'] );
	}
	return $items;
};
$query_vars_filter = static function ( array $variables ) use ( &$wallet_registered ): array {
	$variables['artnovo-qa-extra'] = 'artnovo-qa-extra';
	if ( $wallet_registered ) {
		$variables['woo-wallet'] = 'woo-wallet';
	} else {
		unset( $variables['woo-wallet'] );
	}
	return $variables;
};
add_filter( 'woocommerce_account_menu_items', $menu_filter, PHP_INT_MAX );
add_filter( 'woocommerce_get_query_vars', $query_vars_filter, PHP_INT_MAX );
try {
	$route();
	$navigation = Artnovo\AccountUI\account_navigation();
	$assert( [ 'subscriptions', 'buy', 'orders', 'woo-wallet', 'help', 'support' ] === array_keys( $navigation['primary'] ), 'account navigation has the selected product-focused primary order' );
	$assert( [ 'profile', 'downloads', 'artnovo-qa-extra', 'logout' ] === array_keys( $navigation['secondary'] ), 'native extras and logout remain in secondary navigation without addresses' );
	$expected = [
		'subscriptions' => [ 'اکانت‌های من', Artnovo\AccountUI\account_url() ],
		'buy' => [ 'خرید اکانت جدید', home_url( '/#artnovo-categories' ) ],
		'orders' => [ 'خریدها و پرداخت‌ها', wc_get_account_endpoint_url( 'orders' ) ],
		'woo-wallet' => [ 'کیف پول', wc_get_account_endpoint_url( 'woo-wallet' ) ],
		'help' => [ 'راهنمای اتصال', Artnovo\AccountUI\account_url( 'help' ) ],
		'support' => [ 'پشتیبانی', Artnovo\AccountUI\account_url( 'support' ) ],
		'profile' => [ 'اطلاعات حساب', Artnovo\AccountUI\account_url( 'profile' ) ],
		'downloads' => [ 'فایل‌های قابل دریافت', wc_get_account_endpoint_url( 'downloads' ) ],
		'artnovo-qa-extra' => [ 'بخش افزونهٔ آزمایشی', wc_get_account_endpoint_url( 'artnovo-qa-extra' ) ],
		'logout' => [ 'خروج از حساب', wp_logout_url( wc_get_page_permalink( 'myaccount' ) ) ],
	];
	foreach ( array_merge( $navigation['primary'], $navigation['secondary'] ) as $key => $item ) {
		$assert( isset( $expected[ $key ] ) && $expected[ $key ] === [ $item['label'], $item['url'] ]
			&& is_bool( $item['current'] ), 'navigation label and native destination ' . $key );
	}
	foreach ( [ 'subscriptions', 'help', 'support', 'profile' ] as $view ) {
		$route();
		$_GET['artnovo_view'] = $view;
		$navigation = Artnovo\AccountUI\account_navigation();
		$current = array_keys( array_filter( array_merge( $navigation['primary'], $navigation['secondary'] ), static fn( array $item ): bool => $item['current'] ) );
		$assert( [ $view ] === $current, 'exactly one current navigation item for Core view ' . $view );
	}
	$wallet_registered = false;
	$navigation = Artnovo\AccountUI\account_navigation();
	$assert( [ 'subscriptions', 'buy', 'orders', 'help', 'support' ] === array_keys( $navigation['primary'] )
		&& ! isset( $navigation['secondary']['woo-wallet'] ), 'an unregistered wallet has no navigation item or replacement balance' );
	$wallet_registered = true;

	foreach ( [ 'orders' => 'orders', 'view-order' => 'orders', 'edit-account' => 'profile', 'woo-wallet' => 'woo-wallet', 'artnovo-qa-extra' => 'artnovo-qa-extra' ] as $endpoint => $current ) {
		$route( $endpoint, 'view-order' === $endpoint ? '2147000010' : '' );
		$_GET['artnovo_view'] = 'help';
		$hook = 'woocommerce_account_' . $endpoint . '_endpoint';
		$saved_hook = $GLOBALS['wp_filter'][ $hook ] ?? null;
		$GLOBALS['wp_filter'][ $hook ] = new WP_Hook();
		$calls = 0;
		$content_calls = 0;
		$sentinel = static function () use ( &$calls, $endpoint ): void {
			++$calls;
			echo '<p data-native-fixture="' . esc_attr( $endpoint ) . '">ناحیهٔ بومی آزمایشی</p>';
		};
		$observe_content = static function () use ( &$content_calls ): void { ++$content_calls; };
		add_action( $hook, $sentinel );
		add_action( 'woocommerce_account_content', $observe_content, 1 );
		$handlers = $GLOBALS['wp_filter'][ $hook ]->callbacks;
		$content_handlers = $GLOBALS['wp_filter']['woocommerce_account_content']->callbacks;
		try {
			$assert( is_wc_endpoint_url( $endpoint ) && Artnovo\AccountUI\custom_account_page(), 'registered native endpoint receives the Core frame ' . $endpoint );
			$markup = $render( '\\Artnovo\\AccountUI\\render_account' );
			$assert( 1 === $calls && 1 === $content_calls && str_contains( $markup, 'artnovo-account-native' )
				&& str_contains( $markup, 'data-native-fixture="' . $endpoint . '"' ), 'native account content delegates exactly once ' . $endpoint );
			$assert( $handlers === $GLOBALS['wp_filter'][ $hook ]->callbacks
				&& $content_handlers === $GLOBALS['wp_filter']['woocommerce_account_content']->callbacks, 'native handlers are preserved while rendering ' . $endpoint );
			$navigation = Artnovo\AccountUI\account_navigation();
			$items = array_merge( $navigation['primary'], $navigation['secondary'] );
			$active = array_keys( array_filter( $items, static fn( array $item ): bool => $item['current'] ) );
			$layout = Artnovo\AccountUI\account_endpoint_layout( $endpoint );
			$title = $layout['title'] ?? $items[ $current ]['label'];
			$wallet_gallery = 'woo-wallet' === $endpoint && function_exists( '\\Artnovo\\WalletUI\\account_view' ) && Artnovo\WalletUI\account_view();
			$expected_heading = $wallet_gallery ? ! preg_match( '/<h1\b/', $markup ) : str_contains( $markup, '<h1>' . esc_html( $title ) . '</h1>' );
			$assert( [ $current ] === $active && $expected_heading, 'native endpoint owns the current label over a conflicting Core query ' . $endpoint );
			$tags = new WP_HTML_Tag_Processor( $markup );
			$current_urls = [];
			while ( $tags->next_tag( 'A' ) ) {
				if ( 'page' === $tags->get_attribute( 'aria-current' ) ) {
					$current_urls[] = $tags->get_attribute( 'href' );
				}
			}
			$assert( array_fill( 0, $layout || $wallet_gallery ? 2 : 1, $items[ $current ]['url'] ) === $current_urls,
				'rendered current links preserve the native or Core destination across layouts ' . $endpoint );
		} finally {
			remove_action( 'woocommerce_account_content', $observe_content, 1 );
			if ( null === $saved_hook ) { unset( $GLOBALS['wp_filter'][ $hook ] ); }
			else { $GLOBALS['wp_filter'][ $hook ] = $saved_hook; }
		}
	}
	foreach ( [ 'customer-logout', 'lost-password', 'edit-address' ] as $endpoint ) {
		$route( $endpoint );
		$assert( ! Artnovo\AccountUI\custom_account_page(), 'excluded native route keeps its existing template ' . $endpoint );
	}
	$route();
	$_GET['artnovo_view'] = 'profile';
	$has_native_edit_link = static function ( string $markup ): bool {
		$tags = new WP_HTML_Tag_Processor( $markup );
		while ( $tags->next_tag( 'A' ) ) {
			if ( wc_get_account_endpoint_url( 'edit-account' ) === $tags->get_attribute( 'href' ) ) {
				return str_contains( $markup, 'ویرایش اطلاعات حساب' );
			}
		}
		return false;
	};
	$email = $user->user_email;
	$user->user_email = '';
	try {
		$markup = $render( '\\Artnovo\\AccountUI\\render_account' );
		$tags = new WP_HTML_Tag_Processor( $markup );
		$optional = $tags->next_tag( [ 'tag_name' => 'DETAILS', 'class_name' => 'artnovo-account-email-completion' ] );
		$assert( $optional && null === $tags->get_attribute( 'open' ) && str_contains( $markup, 'ثبت ایمیل برای خرید و دریافت اکانت الزامی نیست.' ), 'blank-email profile keeps email completion optional and initially closed' );
		$assert( $has_native_edit_link( $markup ), 'blank-email profile retains native name and password editing' );

		$profile_session = WC()->session;
		WC()->session = new class extends WC_Session {
			public function has_session(): bool { return true; }
			public function get_customer_id(): string { return 'artnovo-profile-render-only'; }
		};
		$challenge_key = '';
		$read_challenge = null;
		$enable_email = null;
		try {
			$profile_email = 'profile-render@example.invalid';
			$expires = time() + 120;
			$challenge = [];
			$challenge_key = Artnovo\AccountUI\account_auth_key( true );
			$read_challenge = static function () use ( &$challenge ) { return $challenge; };
			$enable_email = static fn() => 'yes';
			add_filter( 'pre_transient_' . $challenge_key, $read_challenge, PHP_INT_MAX );
			add_filter( 'pre_option_' . Artnovo\PlanCheckout\EMAIL_OTP_OPTION, $enable_email, PHP_INT_MAX );
			foreach ( [ 'active' => [ $expires, '', false ], 'error' => [ 0, 'کد تأیید آزمایشی درست نیست.', true ] ] as $case => [ $expiry, $message, $error ] ) {
				$challenge = $expiry ? [
					'email' => Artnovo\PlanCheckout\wizard_otp_key( 'address', $profile_email ),
					'ip' => Pinova\Helpers\IP::get(), 'expires' => $expiry,
				] : [];
				WC()->session->set( Artnovo\AccountUI\ACCOUNT_AUTH_STATE, [
					'email' => $profile_email, 'customer' => $id, 'until' => time() + 1800,
					'redirect' => Artnovo\AccountUI\account_url( 'profile' ), 'message' => $message, 'error' => $error,
				] );
				$markup = $render( '\\Artnovo\\AccountUI\\render_account' );
				$tags = new WP_HTML_Tag_Processor( $markup );
				$opened = $tags->next_tag( [ 'tag_name' => 'DETAILS', 'class_name' => 'artnovo-account-email-completion' ] )
					&& true === $tags->get_attribute( 'open' );
				$tags = new WP_HTML_Tag_Processor( $markup );
				$kept_email = false;
				while ( $tags->next_tag( 'INPUT' ) ) {
					if ( 'artnovo-account-email' === $tags->get_attribute( 'id' ) ) {
						$kept_email = $profile_email === $tags->get_attribute( 'value' );
						break;
					}
				}
				$tags = new WP_HTML_Tag_Processor( $markup );
				$resumed = $expiry ? $tags->next_tag( [ 'tag_name' => 'FORM', 'class_name' => 'artnovo-account-auth' ] )
					&& (string) $expiry === $tags->get_attribute( 'data-artnovo-expires' ) : str_contains( $markup, esc_html( $message ) );
				$assert( $opened && $kept_email && $resumed, 'email completion stays visible after returning with ' . $case . ' OTP state' );
			}
		} finally {
			if ( $read_challenge instanceof Closure ) { remove_filter( 'pre_transient_' . $challenge_key, $read_challenge, PHP_INT_MAX ); }
			if ( $enable_email instanceof Closure ) { remove_filter( 'pre_option_' . Artnovo\PlanCheckout\EMAIL_OTP_OPTION, $enable_email, PHP_INT_MAX ); }
			WC()->session = $profile_session;
		}
	} finally {
		$user->user_email = $email;
	}
	$assert( $has_native_edit_link( $render( '\\Artnovo\\AccountUI\\render_account' ) ), 'profile with an existing email retains native name and password editing' );

	$route( 'downloads' );
	$download_displayed = null;
	$downloads_hook = static function ( array $downloads ) use ( &$download_displayed ): void { $download_displayed = $downloads; };
	$download_list = [];
	$downloads = static function () use ( &$download_list ): array { return $download_list; };
	add_filter( 'woocommerce_customer_get_downloadable_products', $downloads );
	add_action( 'woocommerce_available_downloads', $downloads_hook );
	try {
		$markup = $render( '\\Artnovo\\AccountUI\\render_account' );
		$assert( str_contains( $markup, 'هنوز فایلی برای دانلود ندارید' ) && str_contains( $markup, 'رفتن به اکانت‌های من' )
			&& null === $download_displayed, 'downloads empty state points to subscriptions without suppressing the native endpoint' );
		$download_list = [ [
			'download_url' => 'https://example.invalid/authorized-download',
			'download_name' => 'فایل آزمایشی',
			'product_name' => 'محصول آزمایشی',
			'product_url' => '',
			'downloads_remaining' => '',
			'access_expires' => null,
		] ];
		$markup = $render( '\\Artnovo\\AccountUI\\render_account' );
		$assert( $download_list === $download_displayed && str_contains( $markup, 'https://example.invalid/authorized-download' )
			&& ! str_contains( $markup, 'هنوز فایلی برای دانلود ندارید' ), 'authorized downloads continue through the native table and URL' );
	} finally {
		remove_filter( 'woocommerce_customer_get_downloadable_products', $downloads );
		remove_action( 'woocommerce_available_downloads', $downloads_hook );
	}
} finally {
	remove_filter( 'woocommerce_account_menu_items', $menu_filter, PHP_INT_MAX );
	remove_filter( 'woocommerce_get_query_vars', $query_vars_filter, PHP_INT_MAX );
	$route();
}

$subscription_hook = 'woocommerce_account_vpn-subscriptions_endpoint';
$saved_hook = $GLOBALS['wp_filter'][ $subscription_hook ] ?? null;
$GLOBALS['wp_filter'][ $subscription_hook ] = new WP_Hook();
$native_empty = '<p>' . esc_html__( 'No subscriptions on this page.', 'wp-vpn-bridge' ) . '</p>';
$native_error = '<p class="woocommerce-error">' . esc_html__( 'Subscriptions could not be loaded.', 'wp-vpn-bridge' ) . '</p>';
$cases = [
	'confirmed first-page empty' => [ 1, $native_empty, true ],
	'confirmed later-page empty' => [ 2, $native_empty, false ],
	'native loading error' => [ 1, $native_error, false ],
	'empty notice with a loading error' => [ 1, $native_empty . $native_error, false ],
	'empty notice with an unknown error' => [ 1, $native_empty . '<p class="woocommerce-error">خطای ناشناختهٔ آزمایشی</p>', false ],
	'unknown error markup' => [ 1, '<p class="woocommerce-error">خطای ناشناختهٔ آزمایشی</p>', false ],
	'unknown provider output' => [ 1, '<div data-native-unknown="1">خروجی ناشناختهٔ آزمایشی</div>', false ],
	'blank provider output' => [ 1, '', false ],
	'native card alongside empty wording' => [ 1, '<section class="wpvpn-subscription"><h3>اکانت آزمایشی</h3>' . $native_empty . '</section>', false ],
];
try {
	foreach ( $cases as $label => [ $page, $source, $empty ] ) {
		$route( 'vpn-subscriptions', (string) $page );
		$seen_page = null;
		$sentinel = static function ( $value ) use ( &$seen_page, $source ): void { $seen_page = $value; echo $source; };
		add_action( $subscription_hook, $sentinel );
		try {
			$markup = $render( '\\Artnovo\\AccountUI\\render_account_subscriptions' );
			$assert( $page === $seen_page && $empty === str_contains( $markup, 'artnovo-account-empty' )
				&& ( $empty || '' === $source || str_contains( $markup, $source ) ), 'subscription empty/error distinction: ' . $label );
		} finally {
			remove_action( $subscription_hook, $sentinel );
		}
	}
} finally {
	if ( null === $saved_hook ) { unset( $GLOBALS['wp_filter'][ $subscription_hook ] ); }
	else { $GLOBALS['wp_filter'][ $subscription_hook ] = $saved_hook; }
	$route();
}

// Exercise the restored native handlers, not the earlier delegation sentinels.
$request_method = $_SERVER['REQUEST_METHOD'] ?? null;
$request = $_REQUEST ?? [];
$cache_class = new ReflectionClass( 'WP_Object_Cache' );
if ( realpath( $cache_class->getFileName() ) !== realpath( ABSPATH . WPINC . '/class-wp-object-cache.php' ) ) {
	WP_CLI::error( 'Native rendering requires the standard process-local WordPress object cache.' );
}
$original_cache = $GLOBALS['wp_object_cache'];
$original_ext_cache = wp_using_ext_object_cache();
$GLOBALS['wp_object_cache'] = new WP_Object_Cache();
// WooCommerce re-reads edit-account identity through get_user_by(), not current_user.
wp_cache_add( $id, clone $user->data, 'users' );
$wallet_readonly_query = static function ( string $sql ) use ( $id ): string {
	return $id === get_current_user_id() && 'woo-wallet' === WC()->query->get_current_endpoint()
		&& 'SET SESSION SQL_BIG_SELECTS=1' === $sql ? 'SELECT 1' : $sql;
};
$_SERVER['REQUEST_METHOD'] = 'GET';
$_REQUEST = [];
$GLOBALS['current_user'] = $user;
$assert( $id === get_current_user_id(), 'real native rendering remains bound to the synthetic customer' );
try {
	foreach ( [ 'orders', 'edit-account', 'woo-wallet' ] as $endpoint ) {
		if ( 'woo-wallet' === $endpoint && ! isset( WC()->query->get_query_vars()[ $endpoint ] ) ) {
			WP_CLI::log( 'SKIP real native wallet rendering: endpoint is not registered.' );
			continue;
		}
		$route( $endpoint );
		if ( 'woo-wallet' === $endpoint ) {
			// Keep native reads and cache behavior; no connection SET or persistent cache write.
			add_filter( 'query', $wallet_readonly_query, PHP_INT_MAX - 1 );
			wp_using_ext_object_cache( true );
		}
		$hook = 'woocommerce_account_' . $endpoint . '_endpoint';
		$assert( has_action( $hook ) && Artnovo\AccountUI\custom_account_page(), 'real native handler and shared frame are available ' . $endpoint );
		$handlers = $GLOBALS['wp_filter'][ $hook ]->callbacks ?? [];
		try {
			$markup = $render( '\\Artnovo\\AccountUI\\render_account' );
		} catch ( Throwable $error ) {
			$assert( false, 'real native rendering ' . $endpoint . ': ' . $error::class . ': ' . $error->getMessage() );
			continue;
		}
		$matches = [];
		$assert( ! str_contains( $markup, 'Fatal error' ) && str_contains( $markup, 'artnovo-account-layout' )
			&& preg_match( '/<section\b[^>]*class="[^"]*\bartnovo-account-native\b[^"]*"[^>]*>(.*?)<\/section>/s', $markup, $matches ) === 1,
			'real native content renders inside the shared frame ' . $endpoint );
		$native = $matches[1] ?? '';
		$tags = new WP_HTML_Tag_Processor( $native );
		$native_urls = [];
		while ( $tags->next_tag( 'A' ) ) {
			$href = $tags->get_attribute( 'href' );
			if ( is_string( $href ) ) { $native_urls[] = $href; }
		}
		if ( 'orders' === $endpoint ) {
			$tags = new WP_HTML_Tag_Processor( $native );
			$shop = apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) );
			$assert( $tags->next_tag( [ 'class_name' => 'artnovo-orders-empty' ] ) && in_array( $shop, $native_urls, true ),
				'compact orders retain a clear empty state and the native shop destination' );
		} elseif ( 'edit-account' === $endpoint ) {
			$tags = new WP_HTML_Tag_Processor( $native );
			$form = $tags->next_tag( [ 'tag_name' => 'FORM', 'class_name' => 'woocommerce-EditAccountForm' ] )
				&& 'post' === strtolower( (string) $tags->get_attribute( 'method' ) );
			$form_action = $form ? $tags->get_attribute( 'action' ) : false;
			$tags = new WP_HTML_Tag_Processor( $native );
			$fields = [];
			while ( $tags->next_tag( 'INPUT' ) ) {
				$name = $tags->get_attribute( 'name' );
				if ( is_string( $name ) ) { $fields[ $name ] = $tags->get_attribute( 'value' ); }
			}
			$required_fields = [ 'account_first_name', 'account_last_name', 'account_display_name', 'account_email', 'password_current', 'password_1', 'password_2', 'save-account-details-nonce', 'action' ];
			$assert( $form && in_array( $form_action, [ null, '', wc_get_account_endpoint_url( 'edit-account' ) ], true )
				&& ! array_diff( $required_fields, array_keys( $fields ) )
				&& $user->user_email === ( $fields['account_email'] ?? null )
				&& $user->display_name === ( $fields['account_display_name'] ?? null )
				&& 'save_account_details' === ( $fields['action'] ?? null ), 'real native edit form retains synthetic identity, name/password fields and save nonce' );
			$assert( str_contains( $native, 'artnovo-profile-form' ) && 3 === substr_count( $native, 'class="artnovo-profile-section"' )
				&& wp_verify_nonce( $fields['save-account-details-nonce'] ?? '', 'save_account_details' ),
				'sectioned profile submits one native nonce across all three sections' );
			$tags = new WP_HTML_Tag_Processor( $native );
			while ( $tags->next_tag( 'INPUT' ) && 'account_email' !== $tags->get_attribute( 'name' ) ) {}
			$assert( array_key_exists( 'pinova_mobile', $fields ) && 'account_email' === $tags->get_attribute( 'name' )
				&& null === $tags->get_attribute( 'required' ) && null === $tags->get_attribute( 'aria-required' ),
				'Pinova mobile remains present and native optional email is represented accurately' );
		} else {
			$tags = new WP_HTML_Tag_Processor( $native );
			$wallet_url = wc_get_account_endpoint_url( 'woo-wallet' );
			$wallet_links = array_filter( $native_urls, static fn( string $url ): bool => str_starts_with( $url, $wallet_url ) );
			$assert( $tags->next_tag( [ 'class_name' => 'woo-wallet-my-wallet-container' ] ) && (bool) $wallet_links,
				'real native wallet retains its container and account endpoint destinations' );
			if ( function_exists( '\\Artnovo\\WalletUI\\account_view' ) && Artnovo\WalletUI\account_view() ) {
				$assert( 1 === preg_match_all( '/<h1\b/', $markup ) && str_contains( $native, '>کیف پول</h1>' ), 'real native wallet supplies one heading inside the shared account frame' );
			}
			$wallet = function_exists( 'woo_wallet' ) ? woo_wallet() : null;
			$balance = is_callable( [ $wallet->wallet ?? null, 'get_wallet_balance' ] ) ? $wallet->wallet->get_wallet_balance( $id, 'edit' ) : null;
			$assert( is_numeric( $balance ) && 0.0 === (float) $balance, 'real wallet reads a zero balance for the synthetic customer without crediting it' );
		}
		$account_origin = wp_parse_url( wc_get_page_permalink( 'myaccount' ) );
		preg_match( '/<nav\b[^>]*class="[^"]*\bartnovo-account-nav\b[^"]*"[^>]*>(.*?)<\/nav>/s', $markup, $matches );
		$tags = new WP_HTML_Tag_Processor( $matches[1] ?? '' );
		$menu_local = true;
		$menu_links = 0;
		while ( $tags->next_tag( 'A' ) ) {
			$url = wp_parse_url( (string) $tags->get_attribute( 'href' ) );
			++$menu_links;
			$menu_local = $menu_local && $url && ! isset( $url['user'] ) && ! isset( $url['pass'] )
				&& ( $url['scheme'] ?? '' ) === ( $account_origin['scheme'] ?? '' )
				&& ( $url['host'] ?? '' ) === ( $account_origin['host'] ?? '' )
				&& ( $url['port'] ?? null ) === ( $account_origin['port'] ?? null );
		}
		$assert( $menu_links > 0 && $menu_local && $handlers === ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? [] ),
			'real native rendering preserves handlers and same-origin account navigation ' . $endpoint );
		$layout = Artnovo\AccountUI\account_endpoint_layout( $endpoint );
		$layout_css = $layout ? file_get_contents( $root . '/' . $layout['file'] . '.css' ) : '';
		$layout_js = 'edit-account' === $endpoint ? file_get_contents( $root . '/account-profile.js' ) : '';
		$document = '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
			. '<title>ArtNovo isolated native account fixture: ' . esc_html( $endpoint ) . '</title><style>html{background:#0c1723}body{margin:0;font-family:Tahoma,sans-serif}</style><style>'
			. $css . $layout_css . '</style><body>' . $markup . '<script>' . $jquery . '</script><script>' . $qr . '</script><script>' . $copy . '</script><script>' . $js . '</script><script>' . $layout_js . '</script></body></html>';
		artnovo_fixture_write( 'account-native-' . $endpoint . '.html', $document );
	}
} finally {
	remove_filter( 'query', $wallet_readonly_query, PHP_INT_MAX - 1 );
	wp_using_ext_object_cache( $original_ext_cache );
	$GLOBALS['wp_object_cache'] = $original_cache;
	if ( null === $request_method ) { unset( $_SERVER['REQUEST_METHOD'] ); }
	else { $_SERVER['REQUEST_METHOD'] = $request_method; }
	$_REQUEST = $request;
	$route();
}

if ( $failures ) {
	WP_CLI::error( 'Account rendering checks failed.' );
}
WP_CLI::success( 'Synthetic account views rendered without database writes, mail or HTTP.' );
