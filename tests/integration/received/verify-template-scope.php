<?php
// Invoke with Core and all four legacy custom plugins skipped; see ../README.md.
require_once dirname( __DIR__ ) . '/fixture-paths.php';
if ( function_exists( '\Artnovo\AccountUI\received_order' ) ) {
	WP_CLI::error( 'Core and legacy custom plugins must be skipped; this test requires candidate code.' );
}
require __DIR__ . '/qa-bootstrap.php';
require_once artnovo_fixture_root() . '/artnovo-core.php';
require artnovo_fixture_root() . '/modules/order-received/received.php';

class Artnovo_Received_QA_Template_Store extends Artnovo_Received_QA_Order_Store {
	public function read( &$order ) {
		parent::read( $order );
		if ( ! empty( $GLOBALS['artnovo_received_cases'][ $order->get_id() ]['nonservice'] ) ) {
			$order->delete_meta_data( '_wpvpn_snapshot' );
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$item->set_product_id( 0 );
			}
		}
	}
	public function read_meta( &$order ) {
		return ! empty( $GLOBALS['artnovo_received_cases'][ $order->get_id() ]['nonservice'] ) ? [] : parent::read_meta( $order );
	}
}
add_filter( 'woocommerce_order_data_store', static fn() => Artnovo_Received_QA_Template_Store::class, PHP_INT_MAX );

$failed = 0;
$check = static function ( string $name, bool $passed ) use ( &$failed ): void {
	$failed += $passed ? 0 : 1;
	WP_CLI::log( ( $passed ? 'PASS ' : 'FAIL ' ) . $name );
};
$set_route = static function ( WC_Order $order, ?string $key = null ): void {
	$GLOBALS['wp']->query_vars = [ 'order-received' => $order->get_id() ];
	$GLOBALS['wp_query']->query_vars['order-received'] = (string) $order->get_id();
	$_GET = [ 'key' => $key ?? $order->get_order_key() ];
};
$native = '/synthetic/theme/page-checkout.php';
$custom = artnovo_fixture_root() . '/modules/order-received/received-page.php';
$service = artnovo_received_qa_order( 2147100400 );
$set_route( $service );
$check( 'owned keyed service order uses custom received template', apply_filters( 'template_include', $native ) === $custom );

$nonservice = artnovo_received_qa_order( 2147100401 );
$GLOBALS['artnovo_received_cases'][ $nonservice->get_id() ]['nonservice'] = true;
$nonservice = new WC_Order( $nonservice->get_id() );
$set_route( $nonservice );
$check( 'fixture has no bridge snapshot or service product', ! \WPVPNBridge\Commerce\Orders::is_service_order( $nonservice ) );
$check( 'owned keyed non-service order keeps native template', apply_filters( 'template_include', $native ) === $native );

$set_route( $nonservice, 'wc_order_wrong_fixture_key' );
$check( 'invalid key still uses custom denial template', apply_filters( 'template_include', $native ) === $custom
	&& false === \Artnovo\AccountUI\received_order() );
$nonservice_owner = $GLOBALS['artnovo_received_cases'][ $nonservice->get_id() ]['owner'];
$GLOBALS['artnovo_received_cases'][ $nonservice->get_id() ]['owner'] = ARTNOVO_RECEIVED_TEST_USER + 1;
$set_route( $nonservice );
$check( 'different owner still uses custom denial template', apply_filters( 'template_include', $native ) === $custom
	&& false === \Artnovo\AccountUI\received_order() );

$pay = artnovo_received_qa_order( 2147100402, 'failed' );
$pay_route = static function ( WC_Order $order, ?string $key = null ): void {
	$GLOBALS['wp']->query_vars = [ 'order-pay' => $order->get_id() ];
	$GLOBALS['wp_query']->query_vars = [ 'order-pay' => (string) $order->get_id() ];
	$_GET = [ 'key' => $key ?? $order->get_order_key(), 'pay_for_order' => 'true' ];
};
$pay_route( $pay );
$payment_order = \Artnovo\AccountUI\received_payment_order();
$check( 'owned keyed unpaid service order permits only the payment skin', $payment_order instanceof WC_Order && $payment_order->get_id() === $pay->get_id() );
$gateway = new class extends WC_Payment_Gateway {
	public int $fields_rendered = 0;
	public function __construct() { $this->id = 'bacs'; $this->title = 'روش بومی آزمایشی'; $this->has_fields = true; $this->chosen = true; }
	public function payment_fields() { ++$this->fields_rendered; echo '<p data-native-payment-field>فیلد بومی آزمایشی<input name="artnovo_fixture_payment_field" value="fixture-only"></p>'; }
};
$default_pay_text = __( 'Pay for order', 'woocommerce' );
$native_pay = static function ( WC_Order $order, array $gateways, string $button ): string {
	ob_start();
	try {
		wc_get_template( 'checkout/form-pay.php', [ 'order' => $order, 'available_gateways' => $gateways, 'order_button_text' => $button ] );
		return ob_get_contents();
	} finally {
		ob_end_clean();
	}
};
$preview_pay = static function ( string $name, string $markup ): void {
	$root = artnovo_fixture_root() . '/modules/';
	$style = file_get_contents( $root . 'account/artnovo-account.css' ) . file_get_contents( $root . 'order-received/received.css' );
	$script = file_get_contents( $root . 'order-received/received.js' );
	artnovo_fixture_write( $name, '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1"><style>html{background:#0c1723}body{margin:0;font-family:Vazir,Tahoma,sans-serif}'
		. artnovo_fixture_font_css() . $style . '</style><body class="woocommerce-checkout"><main class="woocommerce">'
		. $markup . '</main><script>' . $script . '</script></body></html>' );
};
$total_html = \Artnovo\Core\display_number_html( wc_price( $pay->get_total(), [
	'currency' => $pay->get_currency(), 'price_format' => '%2$s&nbsp;%1$s',
] ), true );
$total_plain = trim( html_entity_decode( wp_strip_all_tags( $total_html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
$pay_text = apply_filters( 'woocommerce_pay_order_button_text', $default_pay_text );
$check( 'default button shows the same Persian amount in the saved order currency',
	str_contains( $pay_text, 'پرداخت' ) && str_contains( $pay_text, $total_plain ) );
$check( 'a gateway can keep its existing native button label',
	'درگاه آزمایشی' === apply_filters( 'woocommerce_pay_order_button_text', 'درگاه آزمایشی' ) );
$before = $after = 0;
$observe_before = static function () use ( &$before ): void { ++$before; };
$observe_after = static function () use ( &$after ): void { ++$after; };
$terms_page_id = 2147100490;
if ( get_post( $terms_page_id ) ) {
	WP_CLI::error( 'Synthetic terms page ID is already in use.' );
}
wp_cache_set( $terms_page_id, (object) [
	'ID' => $terms_page_id, 'post_type' => 'page', 'post_status' => 'publish',
	'post_title' => 'شرایط خرید آزمایشی', 'post_name' => 'test-terms',
	'post_content' => 'متن شرایط آزمایشی.', 'post_parent' => 0,
], 'posts' );
$fixture_terms_page = static fn() => $terms_page_id;
add_filter( 'woocommerce_terms_and_conditions_page_id', $fixture_terms_page );
add_action( 'woocommerce_pay_order_before_payment', $observe_before );
add_action( 'woocommerce_pay_order_after_submit', $observe_after );
try {
	$payment_markup = $native_pay( $pay, [ 'bacs' => $gateway ], $pay_text );
} finally {
	remove_action( 'woocommerce_pay_order_before_payment', $observe_before );
	remove_action( 'woocommerce_pay_order_after_submit', $observe_after );
}
$check( 'payment skin wraps the actual native form and table', str_contains( $payment_markup, 'data-artnovo-native-order-pay' )
	&& str_contains( $payment_markup, '<form id="order_review" method="post">' ) && str_contains( $payment_markup, 'shop_table' ) );
$amount_tags = new WP_HTML_Tag_Processor( $payment_markup );
$visible_amount = '';
$has_directional_amount = false;
if ( $amount_tags->next_tag( [ 'tag_name' => 'DIV', 'class_name' => 'artnovo-order-pay-amount' ] ) ) {
	while ( $amount_tags->next_token() && ! ( 'DIV' === $amount_tags->get_tag() && $amount_tags->is_tag_closer() ) ) {
		$has_directional_amount = $has_directional_amount || ( 'BDI' === $amount_tags->get_tag() && ! $amount_tags->is_tag_closer() );
		if ( '#text' === $amount_tags->get_token_name() ) {
			$visible_amount .= $amount_tags->get_modifiable_text();
		}
	}
}
$check( 'compact card shows the stored amount, selected plan and failed-order status',
	str_contains( $payment_markup, 'artnovo-order-pay-card' ) && str_contains( $payment_markup, 'artnovo-order-pay-amount' )
	&& str_contains( $payment_markup, 'artnovo-order-pay-plans' ) && str_contains( $payment_markup, 'artnovo-order-pay-reference' )
	&& str_contains( $payment_markup, 'پرداخت ناموفق' ) && str_contains( $payment_markup, 'اشتراک آزمایشی' )
	&& $has_directional_amount && str_contains( $visible_amount, $total_plain ) );
$tags = new WP_HTML_Tag_Processor( $payment_markup );
$payment_fields = [];
while ( $tags->next_tag( 'INPUT' ) ) {
	$name = $tags->get_attribute( 'name' );
	if ( is_string( $name ) ) { $payment_fields[ $name ] = $tags->get_attribute( 'value' ); }
}
$check( 'native payment fields, original method and pay nonce remain', 1 === $gateway->fields_rendered
	&& 'bacs' === ( $payment_fields['payment_method'] ?? null ) && '1' === ( $payment_fields['woocommerce_pay'] ?? null )
	&& 'fixture-only' === ( $payment_fields['artnovo_fixture_payment_field'] ?? null )
	&& wp_verify_nonce( $payment_fields['woocommerce-pay-nonce'] ?? '', 'woocommerce-pay' ) );
$check( 'native terms and submit button still have their original attributes and label integration',
	array_key_exists( 'terms', $payment_fields ) && '1' === ( $payment_fields['terms-field'] ?? null )
	&& str_contains( $payment_markup, 'id="place_order" value="' . esc_attr( $pay_text ) . '" data-value="' . esc_attr( $pay_text ) . '"' ) );
$check( 'native pay hooks run once without changing frozen order data', 1 === $before && 1 === $after
	&& 'failed' === $pay->get_status() && 'bacs' === $pay->get_payment_method() && '100000' === $pay->get_total() );
$preview_pay( 'order-pay-failed.html', $payment_markup );

$pending = artnovo_received_qa_order( 2147100403, 'pending' );
$pay_route( $pending );
$secondary = clone $gateway;
$secondary->id = 'artnovo_fixture_secondary';
$secondary->title = 'درگاه دوم آزمایشی';
$secondary->order_button_text = 'ادامه در درگاه دوم';
$secondary->chosen = false;
$multiple = $native_pay( $pending, [ 'bacs' => $gateway, $secondary->id => $secondary ], $pay_text );
$check( 'pending payment keeps the approved status and all native gateway choices',
	str_contains( $multiple, 'پرداخت تکمیل نشده' ) && substr_count( $multiple, 'name="payment_method"' ) === 2
	&& str_contains( $multiple, 'data-order_button_text="ادامه در درگاه دوم"' ) );
$preview_pay( 'order-pay-multiple.html', $multiple );

$no_gateway_notice = static fn() => 'روش پرداخت آزمایشی در دسترس نیست.';
add_filter( 'woocommerce_no_available_payment_methods_message', $no_gateway_notice );
try {
	$unavailable = $native_pay( $pending, [], $pay_text );
} finally {
	remove_filter( 'woocommerce_no_available_payment_methods_message', $no_gateway_notice );
}
$check( 'missing gateway retains the native message and produces no invented payment method',
	str_contains( $unavailable, 'روش پرداخت آزمایشی در دسترس نیست.' ) && ! str_contains( $unavailable, 'name="payment_method"' ) );
$preview_pay( 'order-pay-unavailable.html', $unavailable );

add_filter( 'woocommerce_order_item_visible', '__return_false' );
try {
	$hidden_product = $native_pay( $pending, [ 'bacs' => $gateway ], $pay_text );
	$check( 'compact summary respects the native line-item visibility filter',
		! str_contains( $hidden_product, 'اشتراک آزمایشی' ) );
} finally {
	remove_filter( 'woocommerce_order_item_visible', '__return_false' );
	remove_filter( 'woocommerce_terms_and_conditions_page_id', $fixture_terms_page );
	wp_cache_delete( $terms_page_id, 'posts' );
}

$deny_skin = static function ( WC_Order $order ) use ( $check ): void {
	ob_start();
	do_action( 'woocommerce_before_template_part', 'checkout/form-pay.php', '', '', [ 'order' => $order ] );
	echo '<p data-native-denied-fixture>خروجی بومی</p>';
	do_action( 'woocommerce_after_template_part', 'checkout/form-pay.php', '', '', [ 'order' => $order ] );
	$markup = ob_get_clean();
	$check( 'ineligible payment context retains only native output', '<p data-native-denied-fixture>خروجی بومی</p>' === $markup );
};
$pay_route( $pay, 'wrong-key' );
$check( 'wrong payment key cannot activate the payment skin', false === \Artnovo\AccountUI\received_payment_order() );
$check( 'wrong payment key retains the default submit text without amount',
	$default_pay_text === apply_filters( 'woocommerce_pay_order_button_text', $default_pay_text ) );
$deny_skin( $pay );
$GLOBALS['artnovo_received_cases'][ $pay->get_id() ]['owner'] = ARTNOVO_RECEIVED_TEST_USER + 1;
$pay_route( $pay );
$check( 'another owner cannot activate the payment skin', false === \Artnovo\AccountUI\received_payment_order() );
$deny_skin( $pay );
$GLOBALS['artnovo_received_cases'][ $pay->get_id() ]['owner'] = ARTNOVO_RECEIVED_TEST_USER;
$GLOBALS['artnovo_received_cases'][ $pay->get_id() ]['status'] = 'processing';
$check( 'already paid order cannot activate a retry payment skin', false === \Artnovo\AccountUI\received_payment_order() );
$deny_skin( $pay );
$GLOBALS['artnovo_received_cases'][ $nonservice->get_id() ]['owner'] = $nonservice_owner;
$pay_route( $nonservice );
$check( 'ordinary payment order remains native', false === \Artnovo\AccountUI\received_payment_order() );
$deny_skin( $nonservice );
$set_route( $pay );
$check( 'order-received is not mistaken for the payment route', false === \Artnovo\AccountUI\received_payment_order() );

if ( $failed ) {
	WP_CLI::error( $failed . ' template scope assertions failed.' );
}
WP_CLI::success( 'Candidate received template scope passed with writes/mail/HTTP blocked.' );
