<?php

namespace Artnovo\AccountUI;

use WPVPNBridge\Commerce\Account;
use WPVPNBridge\Commerce\Orders;
use WPVPNBridge\Commerce\Receipts;
use WPVPNBridge\Persistence\Store;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/received-receipt.php';

function received_page(): bool {
	return function_exists( 'is_order_received_page' ) && is_order_received_page();
}

function received_order_url( \WC_Order $order ): string {
	return add_query_arg( 'key', $order->get_order_key(),
		wc_get_endpoint_url( 'order-received', (string) $order->get_id(), wc_get_page_permalink( 'checkout' ) ) );
}

function received_tracking_url( \WC_Order $order ): string {
	return add_query_arg( 'artnovo_status', '1', received_order_url( $order ) );
}

// A valid key identifies a route; private Core output additionally requires its owner below.
function received_keyed_order(): \WC_Order|false {
	if ( ! received_page() ) {
		return false;
	}
	$id = get_query_var( 'order-received' );
	$key = $_GET['key'] ?? null;
	if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id < 1 || ! is_string( $key ) || '' === $key ) {
		return false;
	}
	$order = class_exists( Orders::class ) ? Orders::fresh_order( (int) $id ) : wc_get_order( (int) $id );
	return $order instanceof \WC_Order && hash_equals( $order->get_order_key(), wp_unslash( $key ) ) ? $order : false;
}

function received_order(): \WC_Order|false {
	if ( get_current_user_id() < 1 ) {
		return false;
	}
	$order = received_keyed_order();
	return $order && get_current_user_id() === $order->get_customer_id() ? $order : false;
}

add_filter( 'woocommerce_should_clear_cart_after_payment', static function ( bool $clear ): bool {
	if ( ! $clear || ! isset( $_GET['artnovo_status'] ) ) {
		return $clear;
	}
	$order = received_order();
	return ! $order || ! \Artnovo\Core\is_service_order( $order );
}, PHP_INT_MAX );

function received_payment_order(): \WC_Order|false {
	if ( ! \Artnovo\Core\bridge_available() || ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-pay' ) || get_current_user_id() < 1 ) {
		return false;
	}
	$id = get_query_var( 'order-pay' );
	$key = $_GET['key'] ?? null;
	if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id < 1 || ! is_string( $key ) || '' === $key ) {
		return false;
	}
	$order = Orders::fresh_order( (int) $id );
	$snapshot = $order instanceof \WC_Order ? $order->get_meta( '_wpvpn_snapshot', true ) : null;
	return $order instanceof \WC_Order && get_current_user_id() === $order->get_customer_id()
		&& hash_equals( $order->get_order_key(), wp_unslash( $key ) ) && \Artnovo\Core\is_service_order( $order )
		&& is_array( $snapshot ) && Orders::matches_purchase( $order, $snapshot ) && $order->needs_payment() ? $order : false;
}

add_action( 'template_redirect', static function (): void {
	if ( received_page() || received_payment_order() ) {
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	}
}, 5 );

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( ! received_payment_order() ) {
		return;
	}
	wp_enqueue_style( 'artnovo-account-ui', plugins_url( '../account/artnovo-account.css', __FILE__ ), [], (string) filemtime( dirname( __DIR__ ) . '/account/artnovo-account.css' ) );
	wp_enqueue_style( 'artnovo-received-ui', plugins_url( 'received.css', __FILE__ ), [ 'artnovo-account-ui' ], (string) filemtime( __DIR__ . '/received.css' ) );
	wp_enqueue_script( 'artnovo-received-ui', plugins_url( 'received.js', __FILE__ ), [], (string) filemtime( __DIR__ . '/received.js' ), true );
}, 20 );

function received_pay_amount( \WC_Order $order ): string {
	return \Artnovo\Core\display_number_html( wp_kses_post( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ) ), true );
}

add_filter( 'woocommerce_pay_order_button_text', static function ( string $label ): string {
	if ( __( 'Pay for order', 'woocommerce' ) !== $label ) {
		return $label;
	}
	$order = received_payment_order();
	return $order ? 'پرداخت ' . html_entity_decode( wp_strip_all_tags( received_pay_amount( $order ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : $label;
} );

$received_payment_skin = false;
add_action( 'woocommerce_before_template_part', static function ( string $name, string $path, string $located, array $args ) use ( &$received_payment_skin ): void {
	if ( 'checkout/form-pay.php' !== $name || ! ( ( $args['order'] ?? null ) instanceof \WC_Order ) ) {
		return;
	}
	$order = received_payment_order();
	if ( ! $order || $order->get_id() !== $args['order']->get_id() ) {
		return;
	}
	$received_payment_skin = true;
	?>
	<section class="artnovo-account artnovo-received artnovo-order-pay" dir="rtl" data-artnovo-native-order-pay aria-labelledby="artnovo-order-pay-title">
		<div class="artnovo-account-card artnovo-order-pay-card">
			<header class="artnovo-order-pay-heading">
				<h2 id="artnovo-order-pay-title">ادامهٔ پرداخت سفارش</h2>
				<p class="artnovo-order-pay-status"><?php echo $order->has_status( 'failed' ) ? 'پرداخت ناموفق' : 'پرداخت تکمیل نشده'; ?></p>
			</header>
			<div class="artnovo-order-pay-amount"><span>مبلغ قابل پرداخت</span><strong><?php echo received_pay_amount( $order ); ?></strong></div>
			<div class="artnovo-order-pay-plans">
				<?php foreach ( $order->get_items() as $item ) : ?>
					<?php if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) { continue; } ?>
					<div>
						<strong><?php echo esc_html( \Artnovo\Core\display_digits( $item->get_name() ) ); ?></strong>
						<span><?php echo esc_html( \Artnovo\Core\display_digits( (string) $item->get_quantity() ) ); ?> اکانت</span>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="artnovo-order-pay-reference">سفارش <bdi><?php echo esc_html( \Artnovo\Core\display_digits( $order->get_order_number() ) ); ?></bdi></p>
	<?php
}, 10, 4 );
add_action( 'woocommerce_pay_order_before_payment', static function () use ( &$received_payment_skin ): void {
	if ( $received_payment_skin ) {
		echo '<h3 class="artnovo-order-pay-method-heading">روش پرداخت</h3>';
	}
} );
add_action( 'woocommerce_after_template_part', static function ( string $name ) use ( &$received_payment_skin ): void {
	if ( 'checkout/form-pay.php' === $name && $received_payment_skin ) {
		$received_payment_skin = false;
		echo '</div></section>';
	}
}, 10 );

add_filter( 'template_include', static function ( string $template ): string {
	if ( ! received_page() ) {
		return $template;
	}
	$order = received_order();
	if ( ! $order && ! \Artnovo\Core\bridge_available() ) {
		$guest = received_keyed_order();
		// Native WooCommerce owns guest email/key verification; Core never renders its private data.
		if ( $guest && 0 === $guest->get_customer_id() ) {
			$order = $guest;
		}
	}
	if ( $order && ! \Artnovo\Core\is_service_order( $order ) ) {
		return $template;
	}
	return __DIR__ . '/received-page.php';
}, 99 );

add_filter( 'body_class', static function ( array $classes ): array {
	if ( received_page() ) {
		$classes[] = 'artnovo-received-page';
	}
	return $classes;
} );

add_filter( 'document_title_parts', static function ( array $parts ): array {
	if ( received_page() ) {
		$parts['title'] = 'پیگیری خرید و دریافت اشتراک';
	}
	return $parts;
} );

function received_services( \WC_Order $order ): array {
	if ( get_current_user_id() !== $order->get_customer_id() || ! class_exists( Account::class ) || ! class_exists( Store::class ) ) {
		return [ 'html' => '', 'links' => [] ];
	}
	ob_start();
	( new Account( new Store() ) )->render( $order );
	$html = ob_get_clean();
	$links = [];
	$tags = new \WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( [ 'tag_name' => 'A' ] ) ) {
		$url = $tags->get_attribute( 'href' );
		$rel = $tags->get_attribute( 'rel' );
		if ( is_string( $url ) && is_string( $rel ) && in_array( 'noreferrer', explode( ' ', $rel ), true )
			&& 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			$links[] = $url;
		}
	}
	return [ 'html' => localize_subscription_display( $html ), 'links' => $links ];
}

function received_state( \WC_Order $order, ?array $delivery = null ): array {
	if ( ! \Artnovo\Core\bridge_available() ) {
		return [ 'state' => 'unavailable', 'title' => 'پیگیری سرویس موقتاً در دسترس نیست', 'message' => 'برای بررسی این خرید با شمارهٔ پیگیری به پشتیبانی پیام بدهید. تا فعال‌شدن سرویس، پرداخت تازه انجام ندهید.', 'step' => 1 ];
	}
	if ( $order->has_status( [ 'cancelled', 'refunded', 'trash' ] ) || (float) $order->get_total_refunded() > 0 ) {
		return [ 'state' => 'closed', 'title' => 'این خرید قابل دریافت نیست', 'message' => 'وضعیت خرید را در جزئیات ببینید و برای پیگیری با شمارهٔ خرید به پشتیبانی پیام بدهید.', 'step' => 1 ];
	}
	if ( $order->has_status( 'failed' ) ) {
		return [ 'state' => 'failed', 'title' => 'پرداخت کامل نشده است', 'message' => 'وضعیت پرداخت این خرید را بررسی کنید. در صورت امکان می‌توانید پرداخت همین خرید را ادامه دهید.', 'step' => 2 ];
	}
	$snapshot = $order->get_meta( '_wpvpn_snapshot', true );
	if ( ! is_array( $snapshot ) || ! class_exists( Orders::class ) || ! Orders::matches_purchase( $order, $snapshot ) ) {
		return [ 'state' => 'review', 'title' => 'اطلاعات خرید نیاز به بررسی دارد', 'message' => 'برای ادامه، شمارهٔ پیگیری این خرید را به پشتیبانی بفرستید.', 'step' => 1 ];
	}
	$delivery ??= received_services( $order );
	$expected = Orders::snapshot_quantity( $snapshot );
	if ( $order->is_paid() && count( $delivery['links'] ) === $expected ) {
		return [ 'state' => 'ready', 'title' => $expected > 1 ? 'اکانت‌های شما آماده‌اند' : 'اکانت شما آماده است', 'message' => 'لینک اتصال را کپی کنید و در برنامهٔ خود اضافه کنید. اکانت در «اکانت‌های من» هم در دسترس است.', 'step' => 4 ];
	}
	if ( $order->is_paid() && count( $delivery['links'] ) > 0 && count( $delivery['links'] ) < $expected ) {
		return [ 'state' => 'partial', 'title' => sprintf( '%s از %s اکانت آماده است', number_format_i18n( count( $delivery['links'] ) ), number_format_i18n( $expected ) ),
			'message' => 'از اکانت‌های آماده استفاده کنید. وضعیت باقی اکانت‌های همین سفارش را از این صفحه پیگیری کنید.', 'step' => 3 ];
	}
	if ( ! $order->is_paid() ) {
		if ( received_receipt_exists( $order ) ) {
			return [ 'state' => 'receipt', 'title' => 'رسید ثبت شد؛ منتظر تأیید پرداخت', 'message' => 'رسید شما برای بررسی دریافت شد. پس از تأیید پرداخت، آماده‌سازی اکانت انجام می‌شود.', 'step' => 2 ];
		}
		return [ 'state' => 'pending', 'title' => 'bacs' === $order->get_payment_method() ? 'واریز کنید و رسید را بفرستید' : 'تأیید پرداخت هنوز دریافت نشده است', 'message' => 'bacs' === $order->get_payment_method()
			? 'سفارش ثبت شده است. پس از واریز مبلغ خرید، تصویر رسید را ارسال کنید.'
			: 'پرداخت این سفارش هنوز تأیید نشده است. وضعیت را تازه کنید یا در صورت امکان، پرداخت همین سفارش را ادامه دهید.', 'step' => 2 ];
	}
	$store = new Store();
	$operation = $store->get_by_order( $order->get_id() );
	$units = $store->get_units( $order->get_id() );
	if ( ! $operation && ! $units ) {
		return [ 'state' => 'review', 'title' => 'پرداخت در انتظار بررسی است', 'message' => 'برای آغاز آماده‌سازی اشتراک، تأیید نهایی پرداخت توسط فروشگاه لازم است.', 'step' => 2 ];
	}
	$stages = $units ?: ( $operation ? [ $operation ] : [] );
	$issuing = false;
	$awaiting_payment = false;
	$expired = [] !== $stages;
	foreach ( $stages as $stage ) {
		$awaiting_payment = $awaiting_payment || 'awaiting_payment' === ( $stage['status'] ?? '' );
		if ( in_array( $stage['status'] ?? '', [ 'pending', 'running', 'retry_wait', 'reconcile' ], true ) ) {
			$issuing = true;
		}
		if ( 'succeeded' !== ( $stage['status'] ?? '' ) || ( $stage['expires_at'] ?? 0 ) <= 0 || $stage['expires_at'] > time() ) {
			$expired = false;
		}
	}
	if ( $expired ) {
		return [ 'state' => 'closed', 'title' => 'اعتبار این اشتراک به پایان رسیده است', 'message' => 'می‌توانید اشتراک تازه‌ای از صفحهٔ خرید انتخاب کنید یا خریدهای دیگر را در پنل ببینید.', 'step' => 4 ];
	}
	if ( $issuing ) {
		return [ 'state' => 'issuing', 'title' => 'اکانت در حال آماده‌سازی است', 'message' => 'پرداخت تأیید شده است. لینک اتصال پس از آماده‌شدن در همین بخش نمایش داده می‌شود.', 'step' => 3 ];
	}
	if ( $awaiting_payment ) {
		return [ 'state' => 'pending', 'title' => 'در انتظار تأیید پرداخت', 'message' => 'تأیید پرداخت هنوز دریافت نشده است. وضعیت همین سفارش را دوباره بررسی کنید.', 'step' => 2 ];
	}
	return [ 'state' => 'review', 'title' => 'آماده‌سازی نیاز به بررسی دارد', 'message' => 'برای پیگیری وضعیت پرداخت یا تحویل اشتراک، شمارهٔ خرید را به پشتیبانی بفرست.', 'step' => 3 ];
}

function received_integrations( \WC_Order $order ): void {
	global $wp_filter;
	$removed = [];
	foreach ( $wp_filter['woocommerce_order_details_after_order_table']->callbacks ?? [] as $priority => $rows ) {
		foreach ( $rows as $row ) {
			$callback = $row['function'];
			if ( is_array( $callback ) && ( $callback[0] instanceof Account || $callback[0] instanceof Receipts )
				&& 'render' === $callback[1] ) {
				remove_action( 'woocommerce_order_details_after_order_table', $callback, $priority );
				$removed[] = [ $callback, $priority, $row['accepted_args'] ];
			}
		}
	}
	$again = remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
	$gateways = WC()->payment_gateways()->payment_gateways();
	$bacs = $gateways['bacs'] ?? null;
	$bank_priority = $bacs ? has_action( 'woocommerce_thankyou_bacs', [ $bacs, 'thankyou_page' ] ) : false;
	if ( false !== $bank_priority ) {
		remove_action( 'woocommerce_thankyou_bacs', [ $bacs, 'thankyou_page' ], $bank_priority );
	}
	try {
		if ( isset( $_GET['artnovo_status'] ) ) {
			woocommerce_order_details_table( $order->get_id() );
		} else {
			do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
			do_action( 'woocommerce_thankyou', $order->get_id() );
		}
	} finally {
		foreach ( $removed as [ $callback, $priority, $args ] ) {
			add_action( 'woocommerce_order_details_after_order_table', $callback, $priority, $args );
		}
		if ( $again ) {
			add_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
		}
		if ( false !== $bank_priority ) {
			add_action( 'woocommerce_thankyou_bacs', [ $bacs, 'thankyou_page' ], $bank_priority );
		}
	}
}

function render_received_summary( \WC_Order $order, bool $quick_transfer = false ): void {
	?>
	<section class="artnovo-received-summary" aria-label="خلاصهٔ خرید">
		<div class="artnovo-received-plans"><?php foreach ( $order->get_items() as $item ) : ?>
			<p><strong><?php echo esc_html( \Artnovo\Core\display_digits( $item->get_name() ) ); ?></strong><span><?php echo esc_html( \Artnovo\Core\display_digits( 'تعداد ' . number_format_i18n( $item->get_quantity() ) ) ); ?></span></p>
		<?php endforeach; ?></div>
		<?php if ( $quick_transfer ) : ?>
			<div class="artnovo-transfer-reference">
				<span class="artnovo-transfer-state">در انتظار پرداخت</span>
				<span>شمارهٔ پیگیری <bdi><?php echo esc_html( \Artnovo\Core\display_digits( $order->get_order_number() ) ); ?></bdi></span>
			</div>
		<?php else : ?>
			<div class="artnovo-received-total"><span>مبلغ خرید</span><strong class="artnovo-received-amount"><?php echo \Artnovo\Core\display_number_html( wp_kses_post( $order->get_formatted_order_total() ), true ); ?></strong></div>
		<?php endif; ?>
	</section>
	<?php
}

function render_received_transfer_amount( \WC_Order $order, bool $has_destination ): void {
	$formatted = \Artnovo\Core\display_number_html( wp_kses_post( $order->get_formatted_order_total() ), true );
	$total = (string) $order->get_total( 'edit' );
	$normalize = static function ( string $html ): string {
		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return preg_replace( '/[\s\p{Z}\p{Cf}٬]+/u', '', $text ) ?? '';
	};
	$currency_symbol = get_woocommerce_currency_symbol( $order->get_currency() );
	$unit = $normalize( $currency_symbol );
	$can_copy = false;
	// Copy the exact order value only when the displayed amount still uses its original number and unit.
	if ( $has_destination && '' !== $unit && preg_match( '/\A[0-9]+(?:\.[0-9]+)?\z/', $total ) ) {
		[ $whole, $fraction ] = array_pad( explode( '.', $total, 2 ), 2, '' );
		$number = \Artnovo\Core\display_digits( $whole );
		$fraction = \Artnovo\Core\display_digits( rtrim( $fraction, '0' ) );
		$number .= '' === $fraction ? '(?:٫۰+)?' : '٫' . $fraction . '۰*';
		$symbol = preg_quote( $unit, '/' );
		$can_copy = (bool) preg_match( '/\A(?:' . $number . $symbol . '|' . $symbol . $number . ')\z/u', $normalize( $formatted ) );
	}
	?>
	<div class="artnovo-transfer-amount">
		<div>
			<p class="artnovo-transfer-label">مبلغ قابل پرداخت</p>
			<strong class="artnovo-received-amount"><?php echo $formatted; ?></strong>
		</div>
		<?php if ( $can_copy ) : ?>
			<button type="button" class="artnovo-account-button-secondary" data-artnovo-copy="artnovo-transfer-amount-value" hidden>کپی مبلغ</button>
			<span data-artnovo-copy-status role="status" hidden></span>
			<div class="artnovo-transfer-copy-fallback" data-artnovo-copy-fallback hidden>
				<label for="artnovo-transfer-amount-value">مبلغ قابل کپی (<?php echo esc_html( html_entity_decode( wp_strip_all_tags( $currency_symbol ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); ?>)</label>
				<input id="artnovo-transfer-amount-value" value="<?php echo esc_attr( $total ); ?>" dir="ltr" readonly>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

function render_received_quick_transfer( \WC_Order $order, string $support ): void {
	$gateways = WC()->payment_gateways()->payment_gateways();
	ob_start();
	try {
		if ( isset( $gateways['bacs'] ) ) {
			$gateways['bacs']->thankyou_page( $order->get_id() );
		}
	} finally {
		$bank_details = ob_get_clean();
	}
	$bank_fields = new \WP_HTML_Tag_Processor( $bank_details );
	$has_destination = false;
	while ( $bank_fields->next_tag( 'LI' ) ) {
		if ( $bank_fields->has_class( 'account_number' ) || $bank_fields->has_class( 'iban' ) ) {
			$has_destination = true;
			break;
		}
	}
	?>
	<div class="artnovo-transfer-intro" id="artnovo-received-status" data-state="pending">
		<h1><?php echo $has_destination ? 'واریز کنید، رسید را بفرستید' : 'پیگیری پرداخت کارت‌به‌کارت'; ?></h1>
		<p>پس از تأیید پرداخت، اکانت در حساب شما قرار می‌گیرد.</p>
	</div>
	<?php render_received_summary( $order, true ); ?>
	<?php wc_print_notices(); ?>
	<div class="artnovo-account-card artnovo-received-focus artnovo-received-payment">
		<section class="artnovo-received-bank" aria-labelledby="artnovo-transfer-bank-heading">
			<h2 id="artnovo-transfer-bank-heading">اطلاعات واریز</h2>
			<?php render_received_transfer_amount( $order, $has_destination ); ?>
			<?php if ( $has_destination ) : ?>
				<div class="artnovo-transfer-destination"><?php echo $bank_details; ?></div>
			<?php else : ?>
				<p class="artnovo-transfer-label">اطلاعات واریز در دسترس نیست. اگر هنوز واریز نکرده‌اید، برای دریافت اطلاعات با پشتیبانی تماس بگیرید.</p>
				<a class="artnovo-account-button artnovo-account-button-secondary" href="<?php echo esc_url( $support ); ?>">پشتیبانی این خرید</a>
			<?php endif; ?>
		</section>
		<?php render_received_receipt( $order, true ); ?>
	</div>
	<?php
}

function render_received_steps( int $current, bool $ready = false ): void {
	?>
	<ol class="artnovo-received-progress" aria-label="مراحل خرید">
		<?php foreach ( [ 1 => 'ورود', 2 => 'پرداخت', 3 => 'دریافت اکانت' ] as $number => $label ) : ?>
			<li <?php if ( $number < $current || $ready ) : ?>data-complete="true"<?php endif; ?> <?php if ( $number === $current ) : ?>aria-current="step"<?php endif; ?>><?php echo esc_html( $label ); ?></li>
		<?php endforeach; ?>
	</ol>
	<?php
}

function render_received_receipt_review( \WC_Order $order, string $title, string $refresh, string $support, bool $show_receipt_tools ): void {
	?>
	<section class="artnovo-account-card artnovo-received-focus artnovo-receipt-review" aria-labelledby="artnovo-receipt-review-title">
		<div class="artnovo-transfer-intro" id="artnovo-received-status" data-state="receipt">
			<span class="artnovo-received-state-mark" aria-hidden="true">◷</span>
			<h1 id="artnovo-receipt-review-title"><?php echo esc_html( $title ); ?></h1>
			<p>پس از تأیید پرداخت، اکانت در بخش «اکانت‌های من» در دسترس خواهد بود.</p>
		</div>
		<?php wc_print_notices(); ?>
		<dl class="artnovo-receipt-review-reference">
			<dt>شمارهٔ پیگیری</dt>
			<dd><bdi><?php echo esc_html( \Artnovo\Core\display_digits( $order->get_order_number() ) ); ?></bdi></dd>
		</dl>
		<div class="artnovo-received-primary"><a class="artnovo-account-button" data-artnovo-received-primary href="<?php echo esc_url( $refresh ); ?>">بررسی وضعیت پرداخت</a></div>
	</section>
	<nav class="artnovo-received-links" aria-label="پیگیری پرداخت">
		<a href="<?php echo esc_url( $support ); ?>">پشتیبانی این خرید</a>
	</nav>
	<details class="artnovo-received-details woocommerce" <?php if ( $show_receipt_tools ) : ?>open<?php endif; ?>>
		<summary>رسید و جزئیات خرید</summary>
		<?php render_received_receipt( $order, review: true ); ?>
		<?php received_integrations( $order ); ?>
	</details>
	<?php
}

function received_before_thankyou( int $order_id ): void {
	$hook = 'woocommerce_before_thankyou';
	$detached = [];
	// The order ID is truthy for wc_print_notices($return), so a direct callback can clear notices without printing them.
	foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? [] as $priority => $callbacks ) {
		$renderer = $callbacks['wc_print_notices'] ?? null;
		if ( $renderer && $renderer['accepted_args'] > 0 && remove_action( $hook, 'wc_print_notices', $priority ) ) {
			$detached[ $priority ] = $renderer['accepted_args'];
		}
	}
	try {
		do_action( $hook, $order_id );
	} finally {
		foreach ( $detached as $priority => $accepted_args ) {
			add_action( $hook, 'wc_print_notices', $priority, $accepted_args );
		}
	}
}

function render_received_page(): void {
	$order = received_order();
	if ( ! $order ) {
		?>
		<main id="content" class="artnovo-account artnovo-received" dir="rtl">
			<section class="artnovo-account-card"><h1>این پیوند خرید قابل مشاهده نیست</h1><p>با حساب صاحب خرید وارد شوید و این خرید را از بخش خریدهای حساب کاربری باز کنید.</p><a class="artnovo-account-button" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">رفتن به حساب کاربری</a></section>
		</main>
		<?php
		return;
	}
	// A gateway can print and clear queued errors before Core renders the receipt tools.
	$show_receipt_tools = WC()->session && array_filter( wc_get_notices( 'error' ),
		static fn( $notice ): bool => $order->get_id() === ( $notice['data']['artnovo_receipt_order'] ?? null ) );
	// Tracking a previous order must not clear a new selection with the same cart hash.
	if ( ! isset( $_GET['artnovo_status'] ) ) {
		received_before_thankyou( $order->get_id() );
		if ( WC()->session && (int) WC()->session->get( 'order_awaiting_payment' ) === $order->get_id() ) {
			WC()->session->__unset( 'order_awaiting_payment' );
		}
		if ( WC()->cart && ! WC()->cart->is_empty() && $order->has_cart_hash( WC()->cart->get_cart_hash() ) ) {
			wc_empty_cart();
		}
	}
	$delivery = received_services( $order );
	$billing_email = $order->get_billing_email();
	$state = received_state( $order, $delivery );
	$receipt_review = 'receipt' === $state['state'] && 'bacs' === $order->get_payment_method();
	$can_upload = received_receipt_can_upload( $order );
	$quick_transfer = 'pending' === $state['state'] && $can_upload && 'bacs' === $order->get_payment_method();
	$has_receipt = received_receipt_exists( $order );
	$has_services = $order->is_paid() && '' !== $delivery['html'];
	$snapshot = $order->get_meta( '_wpvpn_snapshot', true );
	$expected = is_array( $snapshot ) && class_exists( Orders::class ) ? Orders::snapshot_quantity( $snapshot ) : 1;
	$ready_count = $order->is_paid() ? count( $delivery['links'] ) : 0;
	$multi = $expected > 1;
	$journey_step = $state['step'] >= 3 ? 3 : 2;
	$refresh = add_query_arg( 'artnovo_status', wp_generate_uuid4(), received_order_url( $order ) ) . '#artnovo-received-status';
	$support = add_query_arg( 'order', $order->get_id(), account_url( 'support' ) );
	$primary = match ( $state['state'] ) {
		'pending' => $order->needs_payment() && ! in_array( $order->get_payment_method(), [ 'bacs', 'wpvpn_wallet' ], true )
			? [ $order->get_checkout_payment_url(), 'ادامهٔ پرداخت همین سفارش' ] : [ $refresh, 'به‌روزرسانی وضعیت' ],
		'receipt', 'issuing', 'partial' => [ $refresh, 'به‌روزرسانی وضعیت' ],
		'ready' => [ '#artnovo-received-services', 'دریافت لینک اتصال' ],
		'failed' => $order->needs_payment() && 'wpvpn_wallet' !== $order->get_payment_method()
			? [ $order->get_checkout_payment_url(), 'ادامهٔ پرداخت همین خرید' ] : [ $support, 'پیگیری پرداخت با پشتیبانی' ],
		default => [ $support, 'پشتیبانی این خرید' ],
	};
	?>
		<main id="content" class="artnovo-account artnovo-received" dir="rtl" data-state="<?php echo esc_attr( $state['state'] ); ?>" data-artnovo-received-multi="<?php echo $multi ? '1' : '0'; ?>" <?php if ( $quick_transfer ) : ?>data-artnovo-quick-transfer<?php endif; ?>>
		<header class="artnovo-received-header">
			<p class="artnovo-account-muted"><?php if ( $quick_transfer || $receipt_review ) : ?>پرداخت کارت‌به‌کارت<?php else : ?>خرید <bdi><?php echo esc_html( \Artnovo\Core\display_digits( $order->get_order_number() ) ); ?></bdi><?php endif; ?></p>
			<a href="<?php echo esc_url( account_url() ); ?>">اکانت‌های من</a>
		</header>
			<div class="artnovo-received-layout">
				<div class="artnovo-received-main">
					<?php if ( $receipt_review ) : ?>
						<?php render_received_receipt_review( $order, $state['title'], $refresh, $support, $show_receipt_tools ); ?>
					<?php elseif ( $quick_transfer ) : ?>
						<?php render_received_quick_transfer( $order, $support ); ?>
					<?php else : ?>
					<section class="artnovo-account-card artnovo-received-focus">
						<?php render_received_summary( $order ); ?>
						<?php render_received_steps( $journey_step, 'ready' === $state['state'] ); ?>
					<div class="artnovo-received-status" id="artnovo-received-status" data-state="<?php echo esc_attr( $state['state'] ); ?>">
						<span class="artnovo-received-state-mark" aria-hidden="true"><?php echo 'ready' === $state['state'] ? '✓' : '•'; ?></span>
						<div><h1><?php echo esc_html( \Artnovo\Core\display_digits( $state['title'] ) ); ?></h1><p><?php echo esc_html( $state['message'] ); ?></p></div>
					</div>
						<p class="artnovo-received-fact" data-artnovo-notice-permanent><?php echo esc_html( \Artnovo\Core\display_digits( in_array( $state['state'], [ 'issuing', 'partial', 'ready' ], true ) ? 'پرداخت تأیید شده است.' : ( 'receipt' === $state['state'] ? 'پرداخت در انتظار بررسی است.' : 'شمارهٔ پیگیری: ' . $order->get_order_number() ) ) ); ?></p>
					<?php wc_print_notices(); ?>
						<?php if ( 0 === $ready_count ) : ?>
						<div class="artnovo-received-primary"><a class="artnovo-account-button" data-artnovo-received-primary href="<?php echo esc_url( $primary[0] ); ?>"><?php echo esc_html( $primary[1] ); ?></a></div>
					<?php endif; ?>
					<?php if ( $has_services ) : ?>
							<?php if ( $multi && $ready_count > 0 ) : ?>
								<div class="artnovo-received-batch">
									<button type="button" class="artnovo-account-button" data-artnovo-copy-ready data-artnovo-received-primary hidden>کپی لینک‌های آماده · <?php echo esc_html( \Artnovo\Core\display_digits( number_format_i18n( $ready_count ) . ' از ' . number_format_i18n( $expected ) ) ); ?></button>
									<div data-artnovo-ready-fallback hidden><label for="artnovo-received-ready-links">لینک اکانت‌های آماده</label><textarea id="artnovo-received-ready-links" dir="ltr" readonly><?php echo esc_textarea( implode( "\n", $delivery['links'] ) ); ?></textarea></div>
									<p data-artnovo-copy-status role="status" hidden></p>
								</div>
							<?php endif; ?>
						<section id="artnovo-received-services" class="artnovo-received-services" aria-label="اکانت‌های این خرید"><?php echo $delivery['html']; ?></section>
					<?php endif; ?>
				</section>
				<?php endif; ?>
				<?php if ( ! $receipt_review ) : ?>
				<nav class="artnovo-received-links" aria-label="پیگیری و راهنمای خرید">
						<?php if ( $quick_transfer || $primary[0] !== $refresh || $ready_count > 0 ) : ?><a href="<?php echo esc_url( $refresh ); ?>"><?php echo 'partial' === $state['state'] ? 'بررسی باقی اکانت‌ها' : 'به‌روزرسانی وضعیت'; ?></a><?php endif; ?>
					<?php if ( ! $quick_transfer ) : ?><a href="<?php echo esc_url( account_url( 'help' ) ); ?>">راهنمای اتصال</a><?php endif; ?>
					<?php if ( $primary[0] !== $support ) : ?><a href="<?php echo esc_url( $support ); ?>">پشتیبانی این خرید</a><?php endif; ?>
				</nav>
				<details class="artnovo-received-details woocommerce"><summary>جزئیات خرید و فاکتور</summary>
					<dl><dt>شمارهٔ پیگیری</dt><dd><bdi><?php echo esc_html( \Artnovo\Core\display_digits( $order->get_order_number() ) ); ?></bdi></dd>
						<dt>روش پرداخت</dt><dd><?php echo esc_html( \Artnovo\Core\display_digits( $order->get_payment_method_title() ) ); ?></dd>
						<?php if ( is_email( $billing_email ) ) : ?><dt>ایمیل خرید</dt><dd dir="ltr"><?php echo esc_html( $billing_email ); ?></dd><?php endif; ?>
						<?php if ( $order->get_date_created() ) : ?><dt>تاریخ خرید</dt><dd><?php echo esc_html( \Artnovo\Core\display_digits( wc_format_datetime( $order->get_date_created() ) ) ); ?></dd><?php endif; ?>
					</dl>
					<?php if ( $has_receipt && 'pending' !== $state['state'] ) : ?><?php render_received_receipt( $order ); ?><?php endif; ?>
					<?php received_integrations( $order ); ?>
				</details>
					<?php endif; ?>
			</div>
		</div>
	</main>
	<?php
}
