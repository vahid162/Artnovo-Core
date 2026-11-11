<?php

namespace Artnovo\HomeOrderUI\Wizard;

use Artnovo\PlanCheckout;

defined( 'ABSPATH' ) || exit;

const PAGE_ID = 68;
const DURATIONS = [
	1 => [ 'vpn-one-month', 'یک‌ماهه', 'artnovo-wizard-one-month', '5e3a70a2' ],
	2 => [ 'vpn-two-month', 'دوماهه', 'artnovo-wizard-two-month', '6d8cdb7a' ],
	3 => [ 'vpn-three-month', 'سه‌ماهه', 'artnovo-wizard-three-month', '17e8d163' ],
];
const PANELS = [
	'599de042' => [ 1, 'artnovo-categories' ],
	'4ef8e004' => [ 2, 'artnovo-plans' ],
	'ad7be96' => [ 3, 'artnovo-order-stage' ],
];
const GROUPS = [ '5eab593d' => 1, '57aa40ef' => 2, '6f83054a' => 3 ];
const MARKERS = [
	'6ad23061' => '<!--artnovo-wizard-progress-->',
	'24c5a782' => '<!--artnovo-wizard-durations-->',
	'1a6e289c' => '<!--artnovo-wizard-order-->',
];

function is_page(): bool {
	return PAGE_ID === get_queried_object_id();
}

function catalog(): array {
	static $products;
	if ( isset( $products ) ) {
		return $products;
	}
	$products = [ 1 => [], 2 => [], 3 => [] ];
	if ( ! function_exists( 'wc_get_products' ) ) {
		return $products;
	}
	foreach ( DURATIONS as $month => [ $slug ] ) {
		if ( ! get_term_by( 'slug', $slug, 'product_cat' ) ) {
			continue;
		}
		$ids = wc_get_products( [
			'category' => [ $slug ], 'status' => 'publish', 'stock_status' => 'instock',
			'visibility' => 'catalog', 'limit' => -1, 'return' => 'ids',
		] );
		$products[ $month ] = array_values( array_filter( array_map( 'absint', $ids ), static fn( int $id ): bool => '' === get_post_field( 'post_password', $id ) ) );
	}
	return $products;
}

foreach ( DURATIONS as $month => [ , , $query_id ] ) {
	add_action( 'elementor/query/' . $query_id, static function ( \WP_Query $query ) use ( $month ): void {
		$query->set( 'post__in', catalog()[ $month ] ?: [ 0 ] );
		$query->set( 'posts_per_page', -1 );
		$query->set( 'nopaging', true );
	} );
}

add_filter( 'elementor/element/is_dynamic_content', static function ( bool $dynamic, array $data, $element ): bool {
	return $dynamic || ( is_page() && in_array( $element->get_id(), array_column( DURATIONS, 3 ), true ) );
}, 10, 3 );

function state(): array {
	$available = catalog();
	$month = (int) filter_var( $_GET['artnovo_category'] ?? null, FILTER_VALIDATE_INT );
	$plan_id = (int) filter_var( $_GET['artnovo_plan'] ?? null, FILTER_VALIDATE_INT );
	$month = empty( $available[ $month ] ) ? 0 : $month;
	if ( $plan_id ) {
		foreach ( $available as $category => $ids ) {
			if ( in_array( $plan_id, $ids, true ) ) {
				return [ 3, $category, wc_get_product( $plan_id ) ];
			}
		}
	}
	return [ $month ? 2 : 1, $month, null ];
}

function persian_number( int $number ): string {
	return strtr( (string) $number, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

function progress( int $step, int $month, ?\WC_Product $plan ): string {
	$labels = [ 1 => 'مدت اشتراک', 2 => 'انتخاب پلن', 3 => 'مشخصات سفارش' ];
	$controls = [ 1 => 'artnovo-categories', 2 => 'artnovo-plans', 3 => 'artnovo-order-stage' ];
	ob_start();
	?>
	<h1 class="screen-reader-text">خرید اشتراک</h1>
	<nav data-artnovo-progress="<?php echo esc_attr( (string) $step ); ?>" aria-label="مراحل خرید">
		<ol>
			<?php foreach ( $labels as $number => $label ) : ?>
				<li><button type="button" data-artnovo-go="<?php echo esc_attr( (string) $number ); ?>" aria-controls="<?php echo esc_attr( $controls[ $number ] ); ?>" <?php echo $number === $step ? 'aria-current="step"' : ''; ?> <?php disabled( ( 2 === $number && ! $month ) || ( 3 === $number && ! $plan ) ); ?>><span class="artnovo-step-number"><?php echo esc_html( persian_number( $number ) ); ?></span><span class="artnovo-step-label"><?php echo esc_html( $label ); ?></span></button></li>
			<?php endforeach; ?>
		</ol>
		<div class="artnovo-progress-track" aria-hidden="true"></div>
	</nav>
	<?php
	return ob_get_clean();
}

function duration_cards(): string {
	$available = catalog();
	ob_start();
	?>
	<form method="get" action="<?php echo esc_url( get_permalink( PAGE_ID ) . '#artnovo-plans' ); ?>" class="artnovo-duration-grid" aria-label="انتخاب مدت اشتراک">
		<?php foreach ( DURATIONS as $month => [ , $name ] ) : ?>
			<?php $count = count( $available[ $month ] ); ?>
			<button class="artnovo-duration-card" type="submit" name="artnovo_category" value="<?php echo esc_attr( (string) $month ); ?>" data-artnovo-category="<?php echo esc_attr( (string) $month ); ?>" <?php disabled( 0 === $count ); ?>><span class="artnovo-duration-name"><?php echo esc_html( $name ); ?></span><span class="artnovo-duration-count"><?php echo esc_html( $count ? persian_number( $count ) . ' پلن موجود' : 'ناموجود' ); ?></span></button>
		<?php endforeach; ?>
	</form>
	<?php
	return ob_get_clean();
}

function checkout_coupon_form(): void {
	if ( ! wc_coupons_enabled() ) {
		return;
	}
	?>
	<details class="artnovo-order-coupon">
		<summary>کد تخفیف دارید؟</summary>
		<form class="checkout_coupon woocommerce-form-coupon" id="woocommerce-checkout-form-coupon" method="post" aria-labelledby="artnovo-coupon-label">
			<p class="form-row artnovo-order-field">
				<label id="artnovo-coupon-label" for="coupon_code">کد تخفیف</label>
				<input type="text" name="coupon_code" class="input-text" id="coupon_code" dir="ltr" autocomplete="off">
			</p>
			<button type="submit" class="button" name="apply_coupon">اعمال کد</button>
		</form>
	</details>
	<?php
}

function checkout_account_hint(): void {
	echo '<p class="artnovo-order-hint artnovo-order-account">با حساب خود وارد شده‌اید. اطلاعات سفارش به ایمیل زیر ارسال می‌شود.</p>';
}

function checkout_payment_heading(): void {
	echo '<h3 class="artnovo-payment-heading">روش پرداخت</h3>';
}

function render_order( ?\WC_Product $plan ): string {
	$woocommerce = function_exists( 'WC' );
	$decimals = $woocommerce ? wc_get_price_decimals() : 0;
	$currency = $woocommerce ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '';
	$checkout = function_exists( '\\Artnovo\\PlanCheckout\\wizard_checkout_active' );
	$review = $checkout && $plan && PlanCheckout\wizard_checkout_active();
	$draft = $checkout ? PlanCheckout\wizard_draft() : [];
	$prices = [];
	$limits = [];
	$ready = [];
	foreach ( array_unique( array_merge( ...array_values( catalog() ) ) ) as $id ) {
		$product = wc_get_product( $id );
		if ( $product && '' !== $product->get_price() ) {
			$prices[ $id ] = (int) round( wc_add_number_precision( wc_get_price_to_display( $product ) ) );
			$limits[ $id ] = $checkout ? PlanCheckout\quantity_limit( $product ) : 1;
			if ( $checkout && $limits[ $id ] > 0 && $product->is_purchasable() ) {
				$ready[] = (string) $id;
			}
		}
	}
	$plan_id = $plan ? $plan->get_id() : 0;
	$login = is_user_logged_in();
	$draft_plan = $draft['product'] ?? 0;
	$draft_quantity = $draft['quantity'] ?? 1;
	if ( $checkout && $login && ( ! $draft_plan || ( $plan_id && $draft_plan !== $plan_id ) ) ) {
		$items = PlanCheckout\cart_items();
		$item = 1 === count( $items ) ? reset( $items ) : false;
		$cart_plan = is_array( $item ) ? (int) ( $item['product_id'] ?? 0 ) : 0;
		if ( isset( $limits[ $cart_plan ] ) && ( ! $plan_id || $plan_id === $cart_plan ) ) {
			$draft_plan = $cart_plan;
			$draft_quantity = (int) ( $item['quantity'] ?? 1 );
		}
	}
	$draft_quantity = max( 1, min( $draft_quantity, $limits[ $draft_plan ] ?? 1 ) );
	$quantity = $plan_id === $draft_plan ? $draft_quantity : 1;
	$can_checkout = in_array( (string) $plan_id, $ready, true );
	$auto_prepare = $login && $can_checkout && ! $review && '1' !== ( $_GET['artnovo_prepare_failed'] ?? null );
	$coupons = $woocommerce && wc_coupons_enabled();
	$email_otp = ! $login && function_exists( '\\Artnovo\\PlanCheckout\\wizard_email_otp_enabled' ) && PlanCheckout\wizard_email_otp_enabled();
	$otp_expires = $email_otp && $plan_id === $draft_plan
		? PlanCheckout\wizard_email_otp_expiry( $draft['billing_email'] ?? '' )
		: 0;
	$checkout_hint = $email_otp
		? 'برای ورود یا ساخت حساب، ایمیل‌تان را با کد تأیید کنید.'
		: 'برای ثبت سفارش وارد حساب خود شوید. پلن، تعداد و ایمیل حفظ می‌شوند.';
	$unit_price = $plan ? ( $prices[ $plan->get_id() ] ?? 0 ) / ( 10 ** $decimals ) : 0;
	$formatted_price = $plan ? wp_kses_post( wc_price( $unit_price ) ) : '';
	$formatted_total = $plan ? wp_kses_post( wc_price( $quantity * $unit_price ) ) : '';

	ob_start();
	?>
	<section id="artnovo-order" class="artnovo-order" dir="rtl" tabindex="-1" aria-labelledby="artnovo-order-heading"
		data-artnovo-plan-id="<?php echo esc_attr( $plan ? (string) $plan_id : '' ); ?>"
		data-artnovo-unit-amounts="<?php echo esc_attr( wp_json_encode( $prices ) ); ?>"
		data-artnovo-quantity-limits="<?php echo esc_attr( wp_json_encode( $limits ) ); ?>"
		data-artnovo-checkout-plans="<?php echo esc_attr( wp_json_encode( $ready ) ); ?>"
		data-artnovo-checkout-review="<?php echo $review ? '1' : '0'; ?>"
		data-artnovo-auto-prepare="<?php echo $auto_prepare ? '1' : '0'; ?>"
		data-artnovo-draft-plan="<?php echo esc_attr( $draft_plan ? (string) $draft_plan : '' ); ?>"
		data-artnovo-draft-quantity="<?php echo esc_attr( (string) $draft_quantity ); ?>"
		data-artnovo-price-decimals="<?php echo esc_attr( (string) $decimals ); ?>"
		data-artnovo-currency="<?php echo esc_attr( $currency ); ?>">
		<div class="artnovo-order-intro">
			<h2 id="artnovo-order-heading">ثبت سفارش و پرداخت</h2>
			<p>پلن، تعداد و مبلغ سفارش را اینجا بررسی کنید.</p>
		</div>
		<?php if ( $checkout && $login ) : ?>
			<form method="post" action="<?php echo esc_url( get_permalink( PAGE_ID ) ); ?>" data-artnovo-prepare hidden>
				<?php wp_nonce_field( 'artnovo_wizard_checkout', 'artnovo_wizard_nonce', false ); ?>
				<input type="hidden" name="artnovo_product" value="<?php echo esc_attr( (string) $plan_id ); ?>">
				<input type="hidden" name="artnovo_quantity" value="<?php echo esc_attr( (string) $quantity ); ?>">
			</form>
		<?php endif; ?>
		<?php if ( ! $review && $woocommerce && $plan ) : ?>
			<?php wc_print_notices(); ?>
		<?php endif; ?>
		<div data-artnovo-empty <?php echo $plan ? 'hidden' : ''; ?>>
			<p>برای دیدن خلاصهٔ سفارش، ابتدا پلن را انتخاب کنید.</p>
			<a href="#artnovo-plans">مشاهدهٔ پلن‌ها</a>
		</div>
		<div class="artnovo-order-layout" data-artnovo-selected <?php echo $plan ? '' : 'hidden'; ?>>
			<aside class="artnovo-order-summary" aria-labelledby="artnovo-summary-heading">
				<div class="artnovo-order-product">
					<h3 id="artnovo-summary-heading">پلن انتخاب‌شده</h3>
					<strong class="artnovo-order-plan" data-artnovo-title><?php echo $plan ? esc_html( $plan->get_name() ) : ''; ?></strong>
					<a href="#artnovo-plans">تغییر پلن</a>
				</div>
				<div class="artnovo-order-unit">
					<span>قیمت هر اشتراک</span>
					<strong data-artnovo-price data-artnovo-unit-price><?php echo $formatted_price; ?></strong>
				</div>
				<div class="artnovo-order-quantity">
					<span id="artnovo-order-quantity-label">تعداد اشتراک</span>
					<div class="artnovo-order-quantity-control" role="group" aria-labelledby="artnovo-order-quantity-label" dir="ltr">
						<button type="button" data-artnovo-quantity-step="-1" aria-label="کم‌کردن تعداد اشتراک" disabled>−</button>
						<span data-artnovo-quantity dir="auto"><?php echo esc_html( persian_number( $quantity ) ); ?></span>
						<button type="button" data-artnovo-quantity-step="1" aria-label="زیادکردن تعداد اشتراک" disabled>+</button>
					</div>
				</div>
				<?php if ( $review ) : ?>
					<p data-artnovo-quantity-status class="artnovo-order-hint" role="status" hidden></p>
				<?php endif; ?>
			</aside>
			<?php if ( $review ) : ?>
				<div class="artnovo-order-native">
					<?php
					$open_review = static function (): void {
						echo '<section class="artnovo-checkout-review" aria-labelledby="order_review_heading">';
					};
					$close_review = static function (): void {
						echo '</section>';
					};
					$default_coupon_form = remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form' );
					add_action( 'woocommerce_before_checkout_form', __NAMESPACE__ . '\\checkout_coupon_form', 20 );
					add_action( 'woocommerce_before_checkout_billing_form', __NAMESPACE__ . '\\checkout_account_hint' );
					add_action( 'woocommerce_checkout_before_order_review_heading', $open_review );
					add_action( 'woocommerce_checkout_after_order_review', $close_review, 5 );
					add_action( 'woocommerce_review_order_before_payment', __NAMESPACE__ . '\\checkout_payment_heading' );
					try {
						echo do_shortcode( '[woocommerce_checkout]' );
					} finally {
						remove_action( 'woocommerce_review_order_before_payment', __NAMESPACE__ . '\\checkout_payment_heading' );
						remove_action( 'woocommerce_checkout_after_order_review', $close_review, 5 );
						remove_action( 'woocommerce_checkout_before_order_review_heading', $open_review );
						remove_action( 'woocommerce_before_checkout_billing_form', __NAMESPACE__ . '\\checkout_account_hint' );
						remove_action( 'woocommerce_before_checkout_form', __NAMESPACE__ . '\\checkout_coupon_form', 20 );
						if ( $default_coupon_form ) {
							add_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form' );
						}
					}
					?>
				</div>
			<?php elseif ( $login ) : ?>
				<div class="artnovo-order-panel">
					<div class="artnovo-order-total" aria-live="polite" aria-atomic="true">
						<span>جمع سفارش</span>
						<strong data-artnovo-total><?php echo $formatted_total; ?></strong>
					</div>
					<p role="status"><?php echo $auto_prepare ? 'در حال آماده‌سازی فرم ثبت سفارش.' : ( $can_checkout ? 'فرم سفارش آماده نشد. دوباره تلاش کنید.' : 'خرید این پلن در حال حاضر در دسترس نیست.' ); ?></p>
					<button type="button" data-artnovo-retry-prepare class="artnovo-order-submit" <?php disabled( ! $can_checkout ); ?> <?php echo $auto_prepare ? 'hidden' : ''; ?>>باز کردن فرم ثبت سفارش</button>
					<noscript>
						<form method="post" action="<?php echo esc_url( get_permalink( PAGE_ID ) ); ?>">
							<?php wp_nonce_field( 'artnovo_wizard_checkout', 'artnovo_wizard_nonce', false ); ?>
							<input type="hidden" name="artnovo_product" value="<?php echo esc_attr( (string) $plan_id ); ?>">
							<input type="hidden" name="artnovo_quantity" value="<?php echo esc_attr( (string) $quantity ); ?>">
							<button type="submit" <?php disabled( ! $can_checkout ); ?>>باز کردن فرم ثبت سفارش</button>
						</form>
					</noscript>
				</div>
			<?php else : ?>
			<form class="artnovo-order-form" method="post" action="<?php echo esc_url( get_permalink( PAGE_ID ) ); ?>" aria-label="اطلاعات سفارش" aria-describedby="artnovo-order-status" <?php if ( $email_otp ) : ?>data-artnovo-email-otp data-artnovo-otp-expires="<?php echo esc_attr( (string) $otp_expires ); ?>"<?php endif; ?>>
				<?php wp_nonce_field( 'artnovo_wizard_checkout', 'artnovo_wizard_nonce', false ); ?>
				<input type="hidden" name="artnovo_product" value="<?php echo esc_attr( (string) $plan_id ); ?>">
				<input type="hidden" name="artnovo_quantity" value="<?php echo esc_attr( (string) $quantity ); ?>">
				<section class="artnovo-order-panel" aria-labelledby="artnovo-order-contact-heading">
					<h3 id="artnovo-order-contact-heading">اطلاعات سفارش</h3>
					<label class="artnovo-order-field">ایمیل سفارش
						<input name="artnovo_email" type="email" dir="ltr" autocomplete="email" maxlength="<?php echo $email_otp ? '100' : '254'; ?>" value="<?php echo esc_attr( $draft['billing_email'] ?? '' ); ?>" <?php wp_readonly( (bool) $otp_expires ); ?> required>
					</label>
					<?php if ( $email_otp ) : ?>
						<div class="artnovo-order-field" data-artnovo-otp-fields <?php echo $otp_expires ? '' : 'hidden'; ?>>
							<label for="artnovo-email-code">کد تأیید ایمیل</label>
							<input id="artnovo-email-code" name="artnovo_otp_code" type="text" dir="ltr" inputmode="numeric" autocomplete="one-time-code" maxlength="<?php echo esc_attr( (string) PlanCheckout\EMAIL_OTP_DIGITS ); ?>" aria-describedby="artnovo-email-code-hint artnovo-email-otp-message" <?php disabled( ! $otp_expires ); ?> required>
							<p id="artnovo-email-code-hint" class="artnovo-order-hint">کد شش‌رقمی ارسال‌شده به ایمیل را وارد کنید.</p>
							<div class="artnovo-order-otp-actions">
								<button type="button" data-artnovo-otp-edit>تغییر ایمیل</button>
								<button type="button" data-artnovo-otp-resend>ارسال دوبارهٔ کد</button>
							</div>
						</div>
						<p id="artnovo-email-otp-message" data-artnovo-otp-message role="status" hidden></p>
					<?php endif; ?>
					<div class="artnovo-order-actions">
						<p id="artnovo-order-status" class="artnovo-order-hint" data-artnovo-status-ready="<?php echo esc_attr( $checkout_hint ); ?>"><?php echo esc_html( $can_checkout ? $checkout_hint : 'خرید این پلن هنوز در دسترس نیست.' ); ?></p>
						<button type="submit" class="artnovo-order-submit" <?php disabled( ! $can_checkout ); ?>><?php echo $email_otp ? ( $otp_expires ? 'تأیید و ادامهٔ خرید' : 'ارسال کد تأیید' ) : 'ورود و ادامهٔ خرید'; ?></button>
					</div>
				</section>
				<section class="artnovo-order-panel" aria-labelledby="artnovo-order-payment-heading">
					<h3 id="artnovo-order-payment-heading">خلاصه و پرداخت</h3>
					<div class="artnovo-order-total" aria-live="polite" aria-atomic="true">
						<span>جمع سفارش</span>
						<strong data-artnovo-total><?php echo $formatted_total; ?></strong>
					</div>
					<?php if ( $coupons ) : ?>
						<details class="artnovo-order-coupon" <?php if ( ! empty( $draft['coupon'] ) ) : ?>open<?php endif; ?>>
							<summary>کد تخفیف دارید؟</summary>
							<div class="artnovo-order-field">
								<label for="artnovo-guest-coupon">کد تخفیف</label>
								<input id="artnovo-guest-coupon" name="artnovo_coupon" type="text" dir="ltr" autocomplete="off" aria-describedby="artnovo-guest-coupon-hint" maxlength="100" value="<?php echo esc_attr( $draft['coupon'] ?? '' ); ?>">
								<p id="artnovo-guest-coupon-hint" class="artnovo-order-hint">اعتبار کد بعد از ورود بررسی می‌شود.</p>
							</div>
						</details>
					<?php endif; ?>
					<p class="artnovo-order-hint">روش پرداخت بعد از ورود در همین بخش نمایش داده می‌شود.</p>
				</section>
			</form>
			<?php endif; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

add_action( 'elementor/frontend/after_render', static function ( $element ): void {
	if ( is_page() && isset( MARKERS[ $element->get_id() ] ) ) {
		echo MARKERS[ $element->get_id() ];
	}
} );

add_filter( 'elementor/frontend/the_content', static function ( string $content ): string {
	if ( ! is_page() || ! str_contains( $content, MARKERS['6ad23061'] ) ) {
		return $content;
	}
	[ $step, $month, $plan ] = state();
	$html = new \WP_HTML_Tag_Processor( str_replace( array_values( MARKERS ), [ progress( $step, $month, $plan ), duration_cards(), render_order( $plan ) ], $content ) );
	while ( $html->next_tag() ) {
		$id = $html->get_attribute( 'data-interaction-id' );
		if ( ! is_string( $id ) ) {
			continue;
		}
		if ( '3ffe5619' === $id ) {
			$html->set_attribute( 'data-artnovo-wizard', '' );
			$html->set_attribute( 'dir', 'rtl' );
		} elseif ( isset( PANELS[ $id ] ) ) {
			[ $panel, $anchor ] = PANELS[ $id ];
			$html->set_attribute( 'id', $anchor );
			$html->set_attribute( 'data-artnovo-panel', (string) $panel );
			if ( $step !== $panel ) {
				$html->set_attribute( 'hidden', true );
			}
		} elseif ( isset( GROUPS[ $id ] ) ) {
			$html->set_attribute( 'data-artnovo-group', (string) GROUPS[ $id ] );
			if ( $month !== GROUPS[ $id ] ) {
				$html->set_attribute( 'hidden', true );
			}
		}
	}
	return $html->get_updated_html();
} );

add_action( 'elementor/dynamic_tags/register', static function ( $manager ): void {
	$manager->register( new class extends \Elementor\Core\DynamicTags\Data_Tag {
		public function get_name() { return 'artnovo-wizard-plan'; }
		public function get_title() { return 'انتخاب پلن در خرید مرحله‌ای آرت‌نوو'; }
		public function get_group() { return 'post'; }
		public function get_categories() { return [ \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ]; }
		public function get_value( array $options = [] ) {
			$id = get_the_ID();
			foreach ( catalog() as $products ) {
				if ( in_array( $id, $products, true ) ) {
					return add_query_arg( 'artnovo_plan', $id, get_permalink( PAGE_ID ) ) . '#artnovo-order';
				}
			}
			return '';
		}
	} );
} );

add_action( 'send_headers', static function (): void {
	if ( is_page() ) {
		// Resolve WooCommerce queries before Elementor restores cached loop fragments.
		catalog();
		// Stock changes must be visible on the next request for the catalog.
		nocache_headers();
		header( 'X-Accel-Expires: 0' );
	}
} );

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( is_page() ) {
		$css = 'artnovo-home-wizard.css';
		$js = 'artnovo-home-wizard.js';
		$deps = function_exists( '\Artnovo\PlanCheckout\wizard_checkout_active' ) && PlanCheckout\wizard_checkout_active() ? [ 'wc-checkout' ] : [];
		wp_enqueue_style( 'artnovo-home-wizard', plugins_url( $css, __FILE__ ), [], (string) filemtime( __DIR__ . '/' . $css ) );
		wp_enqueue_script( 'artnovo-home-wizard', plugins_url( $js, __FILE__ ), $deps, (string) filemtime( __DIR__ . '/' . $js ), true );
	}
} );
