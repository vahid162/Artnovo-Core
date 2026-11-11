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

function received_order(): \WC_Order|false {
	if ( ! received_page() || get_current_user_id() < 1 ) {
		return false;
	}
	$id = get_query_var( 'order-received' );
	$key = $_GET['key'] ?? null;
	if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id < 1 || ! is_string( $key ) || '' === $key ) {
		return false;
	}
	$order = class_exists( Orders::class ) ? Orders::fresh_order( (int) $id ) : wc_get_order( (int) $id );
	return $order instanceof \WC_Order && get_current_user_id() === $order->get_customer_id()
		&& hash_equals( $order->get_order_key(), wp_unslash( $key ) ) ? $order : false;
}

add_action( 'template_redirect', static function (): void {
	if ( received_page() ) {
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	}
}, 5 );

add_filter( 'template_include', static function ( string $template ): string {
	if ( ! received_page() ) {
		return $template;
	}
	$order = received_order();
	if ( $order && class_exists( Orders::class ) && ! Orders::is_service_order( $order ) ) {
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
	return [ 'html' => $html, 'links' => $links ];
}

function received_state( \WC_Order $order, ?array $delivery = null ): array {
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
		return [ 'state' => 'ready', 'title' => $expected > 1 ? 'اشتراک‌هایت آماده‌اند' : 'اشتراکت آماده است', 'message' => 'لینک خصوصی اتصال را از همین صفحه دریافت کن. اشتراک‌ها در پنل کاربری‌ات هم در دسترس هستند.', 'step' => 4 ];
	}
	if ( ! $order->is_paid() ) {
		if ( received_receipt_exists( $order ) ) {
			return [ 'state' => 'receipt', 'title' => 'رسیدت دریافت شد', 'message' => 'رسید برای بررسی پرداخت ثبت شده است. بعد از تأیید پرداخت، آماده‌سازی اشتراک شروع می‌شود.', 'step' => 2 ];
		}
		return [ 'state' => 'pending', 'title' => 'سفارشت ثبت شد', 'message' => 'bacs' === $order->get_payment_method()
			? 'اطلاعات واریز و ارسال رسید همین‌جاست. اگر واریز را انجام داده‌ای، رسید آن را بفرست.'
			: 'پرداخت این خرید هنوز تأیید نشده است. وضعیت را از همین صفحه پیگیری کن.', 'step' => 2 ];
	}
	$store = new Store();
	$operation = $store->get_by_order( $order->get_id() );
	$units = $store->get_units( $order->get_id() );
	if ( ! $operation && ! $units ) {
		return [ 'state' => 'review', 'title' => 'پرداخت در انتظار بررسی است', 'message' => 'برای آغاز آماده‌سازی اشتراک، تأیید نهایی پرداخت توسط فروشگاه لازم است.', 'step' => 2 ];
	}
	$stages = $units ?: ( $operation ? [ $operation ] : [] );
	$issuing = false;
	$expired = [] !== $stages;
	foreach ( $stages as $stage ) {
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
		return [ 'state' => 'issuing', 'title' => 'اشتراک در حال آماده‌سازی است', 'message' => $delivery['links']
			? 'بخشی از اشتراک‌ها آماده شده است. وضعیت بقیه را همین‌جا پیگیری کن.'
			: 'پرداخت تأیید شده و آماده‌سازی در جریان است. با دکمهٔ به‌روزرسانی، وضعیت تازه را ببین.', 'step' => 3 ];
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
		do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
		do_action( 'woocommerce_thankyou', $order->get_id() );
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

function render_received_page(): void {
	$order = received_order();
	if ( ! $order ) {
		?>
		<main id="content" class="artnovo-account artnovo-received" dir="rtl">
			<section class="artnovo-account-card"><h1>این پیوند خرید قابل مشاهده نیست</h1><p>با حساب صاحب خرید وارد شوید و پیوند موجود در ایمیل خرید را دوباره باز کنید.</p><a class="artnovo-account-button" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">رفتن به حساب کاربری</a></section>
		</main>
		<?php
		return;
	}
	do_action( 'woocommerce_before_thankyou', $order->get_id() );
	if ( WC()->session && (int) WC()->session->get( 'order_awaiting_payment' ) === $order->get_id() ) {
		WC()->session->__unset( 'order_awaiting_payment' );
	}
	if ( WC()->cart && ! WC()->cart->is_empty() && $order->has_cart_hash( WC()->cart->get_cart_hash() ) ) {
		wc_empty_cart();
	}
	$delivery = received_services( $order );
	$state = received_state( $order, $delivery );
	$refresh = add_query_arg( 'artnovo_status', wp_generate_uuid4(), received_order_url( $order ) ) . '#artnovo-received-status';
	$support = add_query_arg( 'order', $order->get_id(), account_url( 'support' ) );
	$primary = match ( $state['state'] ) {
		'pending' => received_receipt_can_upload( $order ) ? [ '#artnovo-received-receipt', 'ارسال رسید پرداخت' ] : [ $refresh, 'به‌روزرسانی وضعیت' ],
		'receipt', 'issuing' => [ $refresh, 'به‌روزرسانی وضعیت' ],
		'ready' => [ '#artnovo-received-services', 'دریافت لینک اتصال' ],
		'failed' => $order->needs_payment() && 'wpvpn_wallet' !== $order->get_payment_method()
			? [ $order->get_checkout_payment_url(), 'ادامهٔ پرداخت همین خرید' ] : [ $support, 'پیگیری پرداخت با پشتیبانی' ],
		default => [ $support, 'پشتیبانی این خرید' ],
	};
	?>
	<main id="content" class="artnovo-account artnovo-received" dir="rtl" data-state="<?php echo esc_attr( $state['state'] ); ?>">
		<header class="artnovo-received-header" id="artnovo-received-status">
			<p class="artnovo-account-muted">پیگیری خرید و دریافت اشتراک</p>
			<div class="artnovo-received-status" data-state="<?php echo esc_attr( $state['state'] ); ?>"><h1><?php echo esc_html( $state['title'] ); ?></h1><p><?php echo esc_html( $state['message'] ); ?></p></div>
		</header>
		<ol class="artnovo-received-progress" aria-label="وضعیت خرید">
			<?php foreach ( [ 1 => 'ثبت خرید', 2 => 'تأیید پرداخت', 3 => 'آماده‌سازی', 4 => 'دریافت اشتراک' ] as $number => $label ) : ?>
				<li <?php if ( $number < $state['step'] || 'ready' === $state['state'] ) : ?>data-complete="true"<?php endif; ?> <?php if ( $number === $state['step'] ) : ?>aria-current="step"<?php endif; ?>><?php echo esc_html( $label ); ?></li>
			<?php endforeach; ?>
		</ol>
		<?php wc_print_notices(); ?>
		<div class="artnovo-received-layout">
			<div class="artnovo-received-main">
				<section class="artnovo-account-card">
					<h2>ادامهٔ این خرید</h2>
					<div class="artnovo-account-actions">
						<a class="artnovo-account-button" href="<?php echo esc_url( $primary[0] ); ?>"><?php echo esc_html( $primary[1] ); ?></a>
						<a class="artnovo-account-button artnovo-account-button-secondary" href="<?php echo esc_url( account_url() ); ?>">اشتراک‌های من</a>
						<?php if ( $primary[0] !== $refresh ) : ?><a href="<?php echo esc_url( $refresh ); ?>">به‌روزرسانی وضعیت</a><?php endif; ?>
						<?php if ( $primary[0] !== $support ) : ?><a href="<?php echo esc_url( $support ); ?>">پشتیبانی این خرید</a><?php endif; ?>
					</div>
				</section>
				<?php if ( 'pending' === $state['state'] && 'bacs' === $order->get_payment_method() && received_receipt_can_upload( $order ) ) : ?>
					<section class="artnovo-account-card artnovo-received-bank"><h2>اطلاعات واریز</h2><?php
					$gateways = WC()->payment_gateways()->payment_gateways();
					if ( isset( $gateways['bacs'] ) ) {
						$gateways['bacs']->thankyou_page( $order->get_id() );
					}
					?></section>
				<?php endif; ?>
				<?php if ( in_array( $state['state'], [ 'pending', 'receipt' ], true ) || received_receipt_exists( $order ) ) : ?>
					<?php render_received_receipt( $order ); ?>
				<?php endif; ?>
				<?php if ( $order->is_paid() && $delivery['html'] ) : ?>
					<section id="artnovo-received-services" class="artnovo-account-card"><?php echo $delivery['html']; ?>
						<p><a href="<?php echo esc_url( account_url( 'help' ) ); ?>">راهنمای اتصال</a></p>
					</section>
				<?php endif; ?>
				<details class="artnovo-account-card artnovo-received-details woocommerce"><summary>جزئیات خرید و پرداخت</summary><?php received_integrations( $order ); ?></details>
			</div>
			<aside class="artnovo-account-card artnovo-received-summary" aria-labelledby="artnovo-received-summary-heading">
				<h2 id="artnovo-received-summary-heading">خلاصهٔ خرید</h2>
				<?php foreach ( $order->get_items() as $item ) : ?>
					<p><strong><?php echo esc_html( $item->get_name() ); ?></strong><br><span class="artnovo-account-muted"><?php echo esc_html( 'تعداد ' . number_format_i18n( $item->get_quantity() ) ); ?></span></p>
				<?php endforeach; ?>
				<dl><dt>شمارهٔ پیگیری</dt><dd><?php echo esc_html( $order->get_order_number() ); ?></dd>
					<dt>مبلغ خرید</dt><dd class="artnovo-received-amount"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd>
					<dt>ایمیل خرید</dt><dd dir="ltr"><?php echo esc_html( $order->get_billing_email() ); ?></dd>
					<dt>روش پرداخت</dt><dd><?php echo esc_html( $order->get_payment_method_title() ); ?></dd>
					<?php if ( $order->get_date_created() ) : ?><dt>تاریخ خرید</dt><dd><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></dd><?php endif; ?>
				</dl>
			</aside>
		</div>
	</main>
	<?php
}
