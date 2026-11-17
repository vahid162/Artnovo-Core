<?php
// Reuse the cart fixture's in-memory identity, cart, SQL/mail/HTTP guards and shutdown cleanup.
require __DIR__ . '/verify.php';

$query_before = $GLOBALS['wp_query'];
$get_before = $_GET;
$query = new WP_Query();
$query->is_page = true;
$query->queried_object = get_post( \Artnovo\HomeOrderUI\Wizard\PAGE_ID );
$query->queried_object_id = \Artnovo\HomeOrderUI\Wizard\PAGE_ID;
$GLOBALS['wp_query'] = $query;
$GLOBALS['wp']->query_vars = [];
$_GET = [ 'artnovo_plan' => '25' ];
$_POST = [];

$seed( 25 );
$result = $prepare( 25 );
$check( 'UI fixture prepares only its synthetic cart', ! is_wp_error( $result ) );
WC()->session->set( \Artnovo\PlanCheckout\WIZARD_DRAFT, [
	'product' => 25, 'quantity' => 1, 'expires' => time() + 600,
	'revision' => hash( 'sha256', 'synthetic-ui-selection' ),
] );
$check( 'same authenticated draft opens payment without a prepare redirect flag', \Artnovo\PlanCheckout\wizard_checkout_active() );
$contents = WC()->cart->get_cart_contents();
$draft = \Artnovo\PlanCheckout\wizard_draft();
$product = wc_get_product( 25 );
$markup = \Artnovo\HomeOrderUI\Wizard\render_order( $product );
$check( 'ready native form is rendered without auto preparation', str_contains( $markup, 'data-artnovo-checkout-review="1"' )
	&& str_contains( $markup, 'data-artnovo-auto-prepare="0"' ) && str_contains( $markup, 'name="woocommerce-process-checkout-nonce"' ) );
$check( 'rendering reuses the current selection and revision', $contents === WC()->cart->get_cart_contents()
	&& $draft === \Artnovo\PlanCheckout\wizard_draft() );
$check( 'native payment controls, coupon and financial fragments remain', str_contains( $markup, 'name="payment_method"' )
	&& str_contains( $markup, 'woocommerce-checkout-review-order-table' ) && str_contains( $markup, 'id="place_order"' )
	&& str_contains( $markup, 'name="coupon_code"' ) );
$check( 'verified virtual purchase requires no customer email field', ! str_contains( $markup, 'name="billing_email"' ) );
$check( 'order summary and the three familiar steps share one main card', 1 === substr_count( $markup, 'class="artnovo-order-layout"' )
	&& str_contains( $markup, 'class="artnovo-order-steps"' ) && str_contains( $markup, 'مبلغ قابل پرداخت' ) );
$product_row = strpos( $markup, 'class="artnovo-order-product"' );
$quantity_row = strpos( $markup, 'class="artnovo-order-quantity"' );
$total_row = strpos( $markup, 'class="artnovo-order-total"' );
$check( 'summary keeps selection, visible quantity and payable total in three ordered rows', false !== $product_row
	&& false !== $quantity_row && false !== $total_row && $product_row < $quantity_row && $quantity_row < $total_row
	&& str_contains( $markup, 'data-artnovo-quantity-step="-1"' ) && str_contains( $markup, 'data-artnovo-quantity-step="1"' )
	&& ! str_contains( $markup, 'data-artnovo-quantity-disclosure' ) );

// Render a hypothetical eligible gateway's native component without registering or authorizing it.
$gateway = new class extends WC_Payment_Gateway {
	public function __construct() {
		$this->id = 'artnovo_component_fixture';
		$this->title = 'عنوان درگاه آزمایشی';
		$this->enabled = 'yes';
		$this->has_fields = true;
	}
	public function payment_fields() {
		echo '<label for="native-gateway-field">فیلد اختصاصی درگاه</label><input id="native-gateway-field" name="native_gateway_field" type="text">';
	}
};
ob_start();
wc_get_template( 'checkout/payment-method.php', [ 'gateway' => $gateway ] );
$gateway_markup = ob_get_clean();
$check( 'Core preserves a future gateway native title, identifier and custom payment fields',
	str_contains( $gateway_markup, 'عنوان درگاه آزمایشی' ) && str_contains( $gateway_markup, 'value="artnovo_component_fixture"' )
	&& str_contains( $gateway_markup, 'name="native_gateway_field"' ) );

$write_preview = static function ( string $name, string $order_markup ): void {
	$core = artnovo_fixture_root();
	$groups = '';
	foreach ( [ 1 => 25, 2 => 26, 3 => 27 ] as $category => $id ) {
		$plan = wc_get_product( $id );
		$groups .= '<div data-artnovo-group="' . $category . '"><article><p data-artnovo-plan-title>' . esc_html( $plan->get_name() )
			. '</p>' . $plan->get_price_html() . '<a href="' . esc_url( \Artnovo\PlanCheckout\wizard_url( $id ) ) . '">انتخاب این پلن</a></article></div>';
	}
	$markup = '<main data-artnovo-wizard><nav data-artnovo-progress="3"><button data-artnovo-go="1">مدت اشتراک</button><button data-artnovo-go="2">انتخاب پلن</button><button data-artnovo-go="3">مشخصات سفارش</button></nav>'
		. '<section data-artnovo-panel="1"><h2>مدت اشتراک</h2><button data-artnovo-category="1">یک ماه</button><button data-artnovo-category="2">دو ماه</button><button data-artnovo-category="3">سه ماه</button></section>'
		. '<section data-artnovo-panel="2"><h2>پلن</h2>' . $groups . '</section><section data-artnovo-panel="3">' . $order_markup . '</section></main>';
	$css = file_get_contents( $core . '/modules/home/artnovo-home-wizard.css' ) . file_get_contents( $core . '/modules/notices/notices.css' );
	$jquery = file_get_contents( ABSPATH . WPINC . '/js/jquery/jquery.min.js' );
	$js = file_get_contents( $core . '/modules/home/artnovo-home-wizard.js' );
	$font_css = artnovo_fixture_font_css();
	$document = '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Isolated checkout fixture</title>'
		. '<style>' . $font_css . 'body{margin:0;padding:24px 14px;background:#0c1723;color:#bdd1d0;font:14px Vazir,Tahoma,sans-serif}body [hidden]{display:none!important}'
		. '[data-artnovo-wizard]{max-width:1160px;margin:auto}.screen-reader-text{position:absolute;clip-path:inset(50%);width:1px;height:1px;overflow:hidden}</style>'
		. '<style>' . $css . '</style><body>' . $markup . '<script>' . $jquery . '</script><script>' . $js . '</script></body></html>';
	artnovo_fixture_write( 'checkout-' . $name . '.html', $document );
};
$write_preview( 'payment', $markup );

$GLOBALS['current_user'] = new WP_User();
$markup = \Artnovo\HomeOrderUI\Wizard\render_order( $product );
$check( 'guest view has one compact auth form and no payment submit', str_contains( $markup, 'artnovo-compact-auth' )
	&& ! str_contains( $markup, 'id="place_order"' ) && str_contains( $markup, 'موبایل، ایمیل یا نام کاربری' ) );
$write_preview( 'guest', $markup );

$GLOBALS['wp_query'] = $query_before;
$_GET = $get_before;
if ( $failures ) { WP_CLI::error( $failures . ' checkout UI checks failed.' ); }
WP_CLI::success( 'Native checkout UI rendered with synthetic state and unchanged purchase selection.' );
