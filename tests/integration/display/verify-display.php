<?php
// WP-CLI fixture: exercise display boundaries with native HTML API; no persisted orders or messages.
require_once dirname( __DIR__ ) . '/fixture-paths.php';

add_filter( 'query', static function ( string $query ): string {
	if ( ! preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $query ) ) {
		WP_CLI::error( 'A database write was blocked during display formatting.' );
	}
	return $query;
}, PHP_INT_MAX );
add_filter( 'pre_wp_mail', static function (): never { WP_CLI::error( 'Email is forbidden during display formatting.' ); } );
add_filter( 'pre_http_request', static function (): never { WP_CLI::error( 'HTTP is forbidden during display formatting.' ); } );
remove_action( 'shutdown', '_wp_cron' );
if ( WC()->session instanceof WC_Session_Handler ) {
	remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
}
if ( WC()->customer instanceof WC_Customer ) {
	remove_action( 'shutdown', [ WC()->customer, 'save' ], 10 );
}
artnovo_fixture_boot_core();

$failures = $assertions = 0;
$check = static function ( string $name, bool $passed ) use ( &$failures, &$assertions ): void {
	++$assertions;
	$failures += $passed ? 0 : 1;
	WP_CLI::log( ( $passed ? 'PASS ' : 'FAIL ' ) . $name );
};
$check( 'Latin and Arabic digit alphabets display in Persian without changing letters',
	'۰۱۲۳۴۵۶۷۸۹ ۰۱۲۳۴۵۶۷۸۹ ۰۱۲۳۴۵۶۷۸۹ Plan' === \Artnovo\Core\display_digits( '0123456789 ٠١٢٣٤٥٦٧٨٩ ۰۱۲۳۴۵۶۷۸۹ Plan' ) );

$technical = '<input type="password" value="Pass012" data-order="25"><textarea>https://example.invalid/sub/123?key=abc456</textarea>'
	. '<code>reference-123</code><pre>password456</pre><kbd>Ctrl1</kbd><samp>token123</samp>'
	. '<script>const id = 123;</script><style>.key123{opacity:0.5}</style><svg><text>QR123</text></svg>';
$controlled = '<p data-order="25" aria-describedby="field123">تعداد 12، مدت ٣ ماه</p>'
	. '<p>user123@example.invalid https://example.invalid/private123?token=456</p>' . $technical;
$formatted = \Artnovo\Core\display_number_html( $controlled );
$check( 'controlled human text is Persian while element identities and attributes are unchanged',
	str_contains( $formatted, '<p data-order="25" aria-describedby="field123">تعداد ۱۲، مدت ۳ ماه</p>' ) );
$check( 'emails and textual URLs remain byte-identical in human fragments',
	str_contains( $formatted, '<p>user123@example.invalid https://example.invalid/private123?token=456</p>' ) );
$check( 'input, copyable connection data, secrets, code and QR content remain byte-identical', str_contains( $formatted, $technical ) );

$thousands = static fn(): string => '_';
$decimal = static fn(): string => ':';
add_filter( 'wc_get_price_thousand_separator', $thousands );
add_filter( 'wc_get_price_decimal_separator', $decimal );
try {
	$price = '<span class="woocommerce-Price-amount amount" data-value="9007199254740993123_456:78"><bdi>9007199254740993123_456:78&nbsp;<span class="woocommerce-Price-currencySymbol">تومان</span></bdi></span>';
	$display = \Artnovo\Core\display_number_html( $price, true );
	$check( 'configured separators localize as text without rounding large amounts',
		str_contains( $display, '۹۰۰۷۱۹۹۲۵۴۷۴۰۹۹۳۱۲۳٬۴۵۶٫۷۸' ) );
	$check( 'native price markup and numeric attributes retain their structure and original values',
		html_entity_decode( $display, ENT_QUOTES, 'UTF-8' ) === html_entity_decode( str_replace( '>9007199254740993123_456:78', '>۹۰۰۷۱۹۹۲۵۴۷۴۰۹۹۳۱۲۳٬۴۵۶٫۷۸', $price ), ENT_QUOTES, 'UTF-8' ) );
	$check( 'ordinary human text does not adopt money separators',
		'<p>نسخه ۱:۲_۳</p>' === \Artnovo\Core\display_number_html( '<p>نسخه 1:2_3</p>' ) );
	$native_price = wc_price( 1234567.89, [ 'decimals' => 2 ] );
	$localized_native = \Artnovo\Core\display_number_html( $native_price, true );
	$check( 'real WooCommerce money markup keeps its native currency and markup while digits localize',
		str_contains( $localized_native, '۱٬۲۳۴٬۵۶۷٫۸۹' )
		&& substr_count( $localized_native, 'woocommerce-Price-currencySymbol' ) === substr_count( $native_price, 'woocommerce-Price-currencySymbol' ) );
} finally {
	remove_filter( 'wc_get_price_thousand_separator', $thousands );
	remove_filter( 'wc_get_price_decimal_separator', $decimal );
}

$connection = '<div class="wpvpn-subscription-link" data-order="25" data-selected="Selected 123">'
	. '<p><a rel="noreferrer" href="https://example.invalid/sub/123?key=456">https://example.invalid/sub/123?key=456</a></p>'
	. '<p><label>URL<input type="url" value="https://example.invalid/sub/123?key=456"></label></p>'
	. '<p><button data-token="123">Copy 123</button><span role="status">Code123</span></p></div>';
$card = '<section class="wpvpn-subscription" data-artnovo-card="{&quot;number&quot;:&quot;123&quot;}">'
	. '<h3>اکانت 10 گیگ · اشتراک 2</h3><p>حجم خریداری‌شده 10 گیگابایت</p><p>پایان اعتبار 2026-11-17 10:45</p>'
	. '<p>شناسهٔ اشتراک <code>service-ref123</code></p><p>اطلاعات فنی token123</p>' . $connection . '</section>';
$unrelated = '<section><h3>عنوان فنی 123</h3><p>Expires 2026</p></section>';
$localized = \Artnovo\AccountUI\localize_subscription_display( $card . $unrelated );
$check( 'known native plan headings and subscription positions localize without JavaScript',
	str_contains( $localized, '<h3>اکانت ۱۰ گیگ · اشتراک ۲</h3>' ) );
$check( 'known native quota and expiration metadata localize without JavaScript',
	str_contains( $localized, '<p>حجم خریداری‌شده ۱۰ گیگابایت</p><p>پایان اعتبار ۲۰۲۶-۱۱-۱۷ ۱۰:۴۵</p>' ) );
$check( 'native connection controls and payload remain byte-identical', str_contains( $localized, $connection ) );
$check( 'native service references and unknown metadata are never blanket-converted',
	str_contains( $localized, '<p>شناسهٔ اشتراک <code>service-ref123</code></p><p>اطلاعات فنی token123</p>' ) );
$check( 'unrelated section text and raw metadata attributes remain byte-identical',
	str_ends_with( $localized, $unrelated ) && str_contains( $localized, 'data-artnovo-card="{&quot;number&quot;:&quot;123&quot;}"' ) );
$service = '<section class="wpvpn-service"><h3>اشتراک 3</h3><p>حجم 20 گیگابایت، پایان اعتبار 2026-11-17</p><p>شناسهٔ اشتراک <code>abc123</code></p></section>';
$check( 'received native service headings and known usage metadata localize selectively',
	\Artnovo\AccountUI\localize_subscription_display( $service ) === '<section class="wpvpn-service"><h3>اشتراک ۳</h3><p>حجم ۲۰ گیگابایت، پایان اعتبار ۲۰۲۶-۱۱-۱۷</p><p>شناسهٔ اشتراک <code>abc123</code></p></section>' );

$native_orders = '<table class="woocommerce-orders-table"><tbody><tr>'
	. '<th class="woocommerce-orders-table__cell-order-number" data-title="Order123"><a href="https://example.invalid/my-account/view-order/123/">#123</a></th>'
	. '<td class="woocommerce-orders-table__cell-order-date"><time datetime="2026-11-17T10:45:00+00:00">2026-11-17</time></td>'
	. '<td class="woocommerce-orders-table__cell-order-total"><span>۱۰۰٬۰۰۰ تومان</span> برای 2 اکانت<code>ref123</code><input value="123"></td>'
	. '<td class="customer-secret">user123@example.invalid</td></tr></tbody></table>'
	. '<p>خرید <mark class="order-number">123</mark> در <mark class="order-date">2026-11-17</mark></p>'
	. '<p class="woocommerce-OrderUpdate-meta">2026-11-17 10:45</p><p>شرح مشتری token123</p>';
$native_display = \Artnovo\AccountUI\localize_subscription_display( $native_orders, true );
$check( 'native order number, date and item count display in Persian with original links and machine dates',
	str_contains( $native_display, 'href="https://example.invalid/my-account/view-order/123/">#۱۲۳</a>' )
	&& str_contains( $native_display, 'datetime="2026-11-17T10:45:00+00:00">۲۰۲۶-۱۱-۱۷</time>' )
	&& str_contains( $native_display, 'برای ۲ اکانت<code>ref123</code><input value="123">' ) );
$check( 'native view-order status and update dates localize while customer notes and unknown cells stay untouched',
	str_contains( $native_display, '<mark class="order-number">۱۲۳</mark>' )
	&& str_contains( $native_display, '<mark class="order-date">۲۰۲۶-۱۱-۱۷</mark>' )
	&& str_contains( $native_display, '<p class="woocommerce-OrderUpdate-meta">۲۰۲۶-۱۱-۱۷ ۱۰:۴۵</p><p>شرح مشتری token123</p>' )
	&& str_contains( $native_display, '<td class="customer-secret">user123@example.invalid</td>' )
	&& str_contains( $native_display, 'data-title="Order123"' ) );
$check( 'native order field conversion requires its explicit Core order-view scope',
	$native_orders === \Artnovo\AccountUI\localize_subscription_display( $native_orders ) );

$previous_query = $GLOBALS['wp_query'];
$previous_vars = $GLOBALS['wp']->query_vars;
$previous_user = $GLOBALS['current_user'] ?? null;
try {
	$identity = new WP_User();
	$identity->init( (object) [ 'ID' => 2147900001, 'user_login' => 'display-fixture', 'user_email' => 'display123@example.invalid', 'display_name' => 'آزمون' ] );
	$GLOBALS['current_user'] = $identity;
	$query = new WP_Query();
	$query->is_page = true;
	$query->queried_object = get_post( wc_get_page_id( 'myaccount' ) );
	$query->queried_object_id = wc_get_page_id( 'myaccount' );
	$GLOBALS['wp_query'] = $query;
	$GLOBALS['wp']->query_vars = [ 'view-order' => '123' ];
	$item = '<a href="https://example.invalid/product25/?ref=123">پلن 10 گیگ</a>';
	$quantity = '<strong class="product-quantity">× 2</strong>';
	$order_item = new WC_Order_Item_Product();
	$check( 'native item-name and quantity filters localize only their human fragments in the Core account frame',
		'<a href="https://example.invalid/product25/?ref=123">پلن ۱۰ گیگ</a>' === apply_filters( 'woocommerce_order_item_name', $item, $order_item, true )
		&& '<strong class="product-quantity">× ۲</strong>' === apply_filters( 'woocommerce_order_item_quantity_html', $quantity, $order_item ) );
	$query->is_page = false;
	$check( 'native item fragments outside the account page retain their source values',
		$item === apply_filters( 'woocommerce_order_item_name', $item, $order_item, true ) && $quantity === apply_filters( 'woocommerce_order_item_quantity_html', $quantity, $order_item ) );
} finally {
	$GLOBALS['wp_query'] = $previous_query;
	$GLOBALS['wp']->query_vars = $previous_vars;
	$GLOBALS['current_user'] = $previous_user;
}

if ( $failures ) {
	WP_CLI::error( $failures . ' of ' . $assertions . ' display formatting assertions failed.' );
}
WP_CLI::success( $assertions . ' display formatting and technical-data boundary assertions passed.' );
