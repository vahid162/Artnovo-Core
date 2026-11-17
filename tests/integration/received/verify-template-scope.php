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
$before = $after = 0;
$observe_before = static function () use ( &$before ): void { ++$before; };
$observe_after = static function () use ( &$after ): void { ++$after; };
add_action( 'woocommerce_pay_order_before_payment', $observe_before );
add_action( 'woocommerce_pay_order_after_submit', $observe_after );
try {
	ob_start();
	wc_get_template( 'checkout/form-pay.php', [ 'order' => $pay, 'available_gateways' => [ 'bacs' => $gateway ], 'order_button_text' => 'ادامهٔ پرداخت آزمایشی' ] );
	$payment_markup = ob_get_clean();
} finally {
	remove_action( 'woocommerce_pay_order_before_payment', $observe_before );
	remove_action( 'woocommerce_pay_order_after_submit', $observe_after );
}
$check( 'payment skin wraps the actual native form and table', str_contains( $payment_markup, 'data-artnovo-native-order-pay' )
	&& str_contains( $payment_markup, '<form id="order_review" method="post">' ) && str_contains( $payment_markup, 'shop_table' ) );
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
$check( 'native pay hooks run once without changing frozen order data', 1 === $before && 1 === $after
	&& 'failed' === $pay->get_status() && 'bacs' === $pay->get_payment_method() && '100000' === $pay->get_total() );
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
