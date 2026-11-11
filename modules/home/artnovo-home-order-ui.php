<?php

namespace Artnovo\HomeOrderUI;

defined( 'ABSPATH' ) || exit;

const PAGE_ID = 61;
const ORDER_MARKER = '<!--artnovo-home-order-->';

function connection_copy( string $text ): string {
	return str_replace(
		[ 'یک‌ماهه', 'یکماهه', 'دوماهه', 'دو‌ماهه', 'سه‌ماهه', 'سهماهه' ],
		[ 'یک ماهه', 'یک ماهه', 'دو ماهه', 'دو ماهه', 'سه ماهه', 'سه ماهه' ],
		preg_replace( '/(?<![\p{L}\p{N}_-])VPN(?![\p{L}\p{N}_-]|\s+Bridge)/iu', 'اتصال', $text )
	);
}

add_filter( 'gettext_wp-vpn-bridge', static function ( string $translated, string $source ): string {
	$labels = [
		'VPN subscription' => 'اشتراک اتصال',
		'Your VPN service' => 'سرویس اتصال شما',
		'VPN service' => 'سرویس اتصال',
		'TeraWallet for VPN services' => 'کیف پول اشتراک اتصال',
		'VPN service payment' => 'پرداخت اشتراک اتصال',
		'VPN wallet payments' => 'پرداخت‌های کیف پول اتصال',
		'VPN order recovery' => 'بازیابی سفارش اتصال',
		'Your VPN subscription is ready' => 'اشتراک اتصال شما آماده است',
		'VPN issuance needs review' => 'صدور اشتراک اتصال به بررسی نیاز دارد',
		'Sign in to your account to view your VPN subscription.' => 'برای مشاهدهٔ اشتراک اتصال، وارد حساب خود شوید.',
		'Open the order in WooCommerce to review the VPN issuance record.' => 'برای بررسی سابقهٔ صدور اتصال، سفارش را در ووکامرس باز کنید.',
		'Sign in and choose a valid quantity of one configured VPN plan in a separate order.' => 'وارد حساب شوید و تعداد مجاز از یک پلن اتصال را در سفارش جداگانه انتخاب کنید.',
		'A VPN service must be purchased in a separate order.' => 'اشتراک اتصال باید در سفارش جداگانه خریداری شود.',
		'Choose a supported VPN quantity.' => 'تعداد مجاز اشتراک اتصال را انتخاب کنید.',
		'This VPN payment request is not authorized.' => 'این درخواست پرداخت اتصال مجاز نیست.',
		'This VPN order must use its original payment method.' => 'این سفارش اتصال باید با روش پرداخت اولیهٔ خود پرداخت شود.',
		'The VPN order changed and requires administrator review.' => 'سفارش اتصال تغییر کرده و به بررسی مدیر نیاز دارد.',
		'This existing VPN order has no frozen purchase terms.' => 'مشخصات خرید برای این سفارش اتصال ثبت نشده است.',
		'The VPN order could not be accepted. Sign in and check its service terms and payment method.' => 'ثبت سفارش اتصال انجام نشد. وارد حساب شوید و مشخصات سرویس و روش پرداخت را بررسی کنید.',
		'The frozen VPN order terms cannot be changed. Create a new order.' => 'مشخصات ثبت‌شدهٔ سفارش اتصال قابل تغییر نیست. سفارش تازه بسازید.',
		'A VPN purchase requires one plan, a signed-in customer and a supported payment method.' => 'خرید اتصال به یک پلن، ورود مشتری و روش پرداخت معتبر نیاز دارد.',
		'The VPN quantity is invalid for this payment method.' => 'تعداد اشتراک اتصال برای این روش پرداخت مجاز نیست.',
		'The VPN service terms are invalid.' => 'مشخصات سرویس اتصال معتبر نیست.',
		'Wallet payment is unavailable for this VPN purchase.' => 'پرداخت با کیف پول برای این خرید اتصال در دسترس نیست.',
	];
	if ( isset( $labels[ $source ] ) ) {
		return $labels[ $source ];
	}
	return connection_copy( $translated );
}, 20, 2 );

add_filter( 'woocommerce_order_item_get_name', static function ( string $name, $item ): string {
	return $item instanceof \WC_Order_Item_Product ? connection_copy( $name ) : $name;
}, 20, 2 );

// Legacy wallet details are rendered through wp_kses_post in both wallet views.
add_filter( 'pre_kses', static function ( string $content ): string {
	return 'VPN service payment' === $content ? 'پرداخت اشتراک اتصال' : $content;
} );

function on_workflow_page(): bool {
	return PAGE_ID === get_queried_object_id();
}

function selected_plan(): ?\WC_Product {
	$id = filter_var( $_GET['artnovo_plan'] ?? null, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
	if ( ! $id || ! function_exists( 'wc_get_product' ) || ! function_exists( '\Artnovo\PlanCheckout\is_plan' ) ) {
		return null;
	}

	$product = wc_get_product( $id );
	if ( ! \Artnovo\PlanCheckout\is_plan( $product ) || 'publish' !== $product->get_status() || post_password_required( $id ) ) {
		return null;
	}
	return $product;
}

function render( ?\WC_Product $plan ): string {
	ob_start();
	?>
	<style>
		.artnovo-order { scroll-margin-top: 24px; padding: clamp(18px, 4vw, 32px); border-radius: 20px; background: #152735; color: #bdd1d0; box-shadow: 0 12px 30px #0003; text-align: start; }
		.artnovo-order h2, .artnovo-order h3, .artnovo-order strong { color: #f0f9f7; }
		.artnovo-order h2 { margin: 0 0 12px; font-size: clamp(22px, 4vw, 30px); }
		.artnovo-order p { margin: 0 0 12px; }
		.artnovo-order [hidden] { display: none; }
		.artnovo-order .artnovo-order-summary { padding: 16px; margin-block: 16px 24px; border-radius: 12px; background: #0c1723; display: grid; gap: 6px; }
		.artnovo-order .artnovo-order-price { color: #46d7a6; font-size: 1.2rem; font-weight: 700; }
		.artnovo-order a { width: fit-content; color: #9adaf0; text-decoration: underline; text-underline-offset: 4px; }
		.artnovo-order a:focus-visible, .artnovo-order input:focus-visible { outline: 2px solid #9adaf0; outline-offset: 3px; }
		.artnovo-order-form, .artnovo-order-fields, .artnovo-order-field { display: grid; gap: 12px; margin: 0; }
		.artnovo-order-fields { grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); }
		.artnovo-order-field { gap: 6px; min-width: 0; }
		.artnovo-order .artnovo-order-field input { width: 100%; min-height: 46px; border: 0; border-radius: 9px; padding: 9px 12px; color: #f0f9f7; background: #0c1723; font: inherit; box-sizing: border-box; }
		.artnovo-order .artnovo-order-methods { display: flex; flex-wrap: wrap; gap: 8px; padding: 0; margin: 0; list-style: none; }
		.artnovo-order-methods button { padding: 8px 12px; min-height: 44px; border: 0; border-radius: 10px; background: #0c1723; color: #f0f9f7; font: inherit; }
		.artnovo-order-methods button[aria-pressed="true"] { background: #46d7a6; color: #06261b; }
		.artnovo-order-methods button:focus-visible { outline: 2px solid #9adaf0; outline-offset: 3px; }
		.artnovo-order .artnovo-order-account { margin-block-end: 16px; }
		.artnovo-order .artnovo-order-account summary { cursor: pointer; min-height: 42px; color: #9adaf0; }
		.artnovo-order .artnovo-order-submit:disabled { width: fit-content; min-height: 44px; padding: 10px 18px; border: 0; border-radius: 9px; background: #314958; color: #f0f9f7; font: inherit; cursor: default; }
	</style>
	<section id="artnovo-order" class="artnovo-order" dir="rtl" tabindex="-1" aria-labelledby="artnovo-order-heading" data-artnovo-plan-id="<?php echo esc_attr( $plan ? (string) $plan->get_id() : '' ); ?>">
		<h2 id="artnovo-order-heading">۳. ثبت سفارش و پرداخت</h2>
		<div data-artnovo-empty <?php echo $plan ? 'hidden' : ''; ?>>
			<p>یک پلن انتخاب کن تا خلاصه و فرم سفارش اینجا نمایش داده شوند.</p>
			<a href="#artnovo-plans">مشاهدهٔ پلن‌ها</a>
		</div>
		<div data-artnovo-selected <?php echo $plan ? '' : 'hidden'; ?>>
			<div class="artnovo-order-summary">
				<span>پلن انتخاب‌شده</span>
				<strong data-artnovo-title><?php echo $plan ? esc_html( $plan->get_name() ) : ''; ?></strong>
				<span class="artnovo-order-price" data-artnovo-price><?php echo $plan ? wp_kses_post( $plan->get_price_html() ) : ''; ?></span>
				<a href="#artnovo-plans">تغییر پلن</a>
			</div>
			<details class="artnovo-order-account">
				<summary>ورود یا ساخت حساب</summary>
				<p>پیش‌نمایش مرحلهٔ حساب کاربری</p>
				<div class="artnovo-order-fields">
					<label class="artnovo-order-field">ایمیل یا نام کاربری<input type="text" disabled></label>
					<label class="artnovo-order-field">رمز حساب<input type="password" disabled></label>
				</div>
			</details>
			<form class="artnovo-order-form" aria-label="اطلاعات سفارش" aria-describedby="artnovo-order-status">
				<div class="artnovo-order-fields">
					<label class="artnovo-order-field">نام و نام خانوادگی<input type="text" autocomplete="name"></label>
					<label class="artnovo-order-field">شمارهٔ همراه<input type="tel" dir="ltr" autocomplete="tel" inputmode="tel"></label>
					<label class="artnovo-order-field">ایمیل (اختیاری)<input type="email" dir="ltr" autocomplete="email"></label>
				</div>
				<h3>روش‌های پرداخت</h3>
				<ul class="artnovo-order-methods">
					<li><button type="button" data-artnovo-method aria-pressed="false">درگاه بانکی</button></li>
					<li><button type="button" data-artnovo-method aria-pressed="false">کیف پول</button></li>
					<li><button type="button" data-artnovo-method aria-pressed="false">انتقال بانکی</button></li>
				</ul>
				<p id="artnovo-order-status">این فرم پیش‌نمایش است. ثبت سفارش و پرداخت پس از راه‌اندازی فروش فعال می‌شوند.</p>
				<button type="submit" class="artnovo-order-submit" disabled>ثبت سفارش و پرداخت</button>
			</form>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

add_action( 'elementor/frontend/after_render', static function ( $element ): void {
	if ( ! on_workflow_page() ) {
		return;
	}
	switch ( $element->get_id() ) {
		case '68deff07':
			echo '<span id="artnovo-categories"></span>';
			break;
		case '1a55e9b0':
			echo '<span id="artnovo-plans"></span>';
			break;
		case '34aff58a':
			echo ORDER_MARKER;
			break;
	}
} );

add_filter( 'elementor/frontend/the_content', static function ( string $content ): string {
	if ( ! on_workflow_page() || ! str_contains( $content, ORDER_MARKER ) ) {
		return $content;
	}
	// Resolve the selected plan after Elementor renders its cached document shell.
	$plan = selected_plan();
	$html = new \WP_HTML_Tag_Processor( str_replace( ORDER_MARKER, render( $plan ), $content ) );
	$steps = [ '18959f70' => 1, '97083ed' => 2, '5e3c4598' => 3 ];
	$current = $plan ? 3 : 1;
	while ( $html->next_tag( 'P' ) ) {
		$id = $html->get_attribute( 'data-interaction-id' );
		if ( ! is_string( $id ) || ! isset( $steps[ $id ] ) ) {
			continue;
		}
		$html->set_attribute( 'data-artnovo-step', (string) $steps[ $id ] );
		if ( $current === $steps[ $id ] ) {
			$html->set_attribute( 'aria-current', 'step' );
		}
	}
	return $html->get_updated_html();
} );

require_once __DIR__ . '/artnovo-home-wizard.php';

add_action( 'elementor/dynamic_tags/register', static function ( $manager ): void {
	$manager->register( new class extends \Elementor\Core\DynamicTags\Data_Tag {
		public function get_name() { return 'artnovo-order-preview'; }
		public function get_title() { return 'انتخاب پلن در صفحهٔ اول آرت‌نوو'; }
		public function get_group() { return 'post'; }
		public function get_categories() { return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ]; }
		public function get_value( array $options = [] ) {
			$id = get_the_ID();
			if ( ! function_exists( 'wc_get_product' ) || ! function_exists( '\Artnovo\PlanCheckout\is_plan' ) || ! \Artnovo\PlanCheckout\is_plan( wc_get_product( $id ) ) ) {
				return '';
			}
			return add_query_arg( 'artnovo_plan', $id, get_permalink( PAGE_ID ) ) . '#artnovo-order';
		}
	} );
} );

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( on_workflow_page() ) {
		wp_enqueue_script( 'artnovo-home-flow', plugins_url( 'artnovo-home-flow.js', __FILE__ ), [], (string) filemtime( __DIR__ . '/artnovo-home-flow.js' ), true );
	}
} );
