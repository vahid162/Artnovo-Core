<?php

namespace Artnovo\AccountUI;

defined( 'ABSPATH' ) || exit;

if ( ! ( ( $order ?? null ) instanceof \WC_Order ) || get_current_user_id() < 1
	|| get_current_user_id() !== $order->get_customer_id() || ! current_user_can( 'view_order', $order->get_id() )
	|| ! \Artnovo\Core\bridge_available() || ! is_array( $order->get_meta( '_wpvpn_snapshot', true ) ) ) {
	return;
}

$bank_payment = received_bank_payment( $order );
$purchase_state = received_state( $order, bank_payment: $bank_payment );
$order_id = $order->get_id();
$method = $order->get_payment_method();
$method_title = $order->get_payment_method_title() ?: ( 'bacs' === $method ? 'کارت‌به‌کارت' : '' );
$state = $purchase_state['state'];
if ( 'pending' === $state && 'bacs' === $method && received_receipt_can_upload( $order ) ) {
	$state = 'transfer';
}

$expired = 'closed' === $state && 4 === $purchase_state['step'];
$bank_cancelled = 'failed' === $state && received_bank_cancelled( $bank_payment ?? [] );
$bank_retry = $bank_cancelled && received_bank_retry( $order, $bank_payment );
$tracking = received_tracking_url( $order );
$support = add_query_arg( 'order', $order_id, account_url( 'support' ) );
$payment = match ( $state ) {
	'transfer' => 'در انتظار واریز و ارسال رسید',
	'receipt' => 'رسید ثبت شد، منتظر تأیید',
	'pending' => 'open' === ( $bank_payment['phase'] ?? '' ) ? $purchase_state['title'] : 'در انتظار تأیید پرداخت',
	'failed' => $bank_cancelled ? $purchase_state['title'] : 'پرداخت کامل نشده است',
	'wallet_pending', 'wallet_review' => $purchase_state['title'],
	'paid_waiting_fulfillment', 'issuing', 'ready', 'partial' => 'پرداخت تأیید شد',
	'closed' => $expired ? 'پرداخت تأیید شد' : (
		(float) $order->get_total_refunded() > 0 ? 'بازپرداخت ثبت شده است' : wc_get_order_status_name( $order->get_status() )
	),
	default => 'نیازمند بررسی',
};
[ $delivery, $action_url, $action_text ] = match ( $state ) {
	'transfer' => [ 'پس از تأیید پرداخت', $tracking, 'واریز و ارسال رسید' ],
	'receipt', 'pending' => [ 'پس از تأیید پرداخت', $tracking, 'بررسی وضعیت پرداخت' ],
	'failed' => $bank_retry
		? [ 'تحویل انجام نشده است', $order->get_checkout_payment_url(), 'پرداخت مجدد همین سفارش' ]
		: [ 'تحویل انجام نشده است', $bank_cancelled ? $support : $tracking, 'پیگیری پرداخت ناموفق' ],
	'wallet_pending' => [ 'در انتظار ثبت سهم کیف پول', $tracking . '#artnovo-received-status', 'بررسی وضعیت پرداخت' ],
	'wallet_review' => [ 'در انتظار بررسی سهم کیف پول', $support, 'پیگیری با پشتیبانی' ],
	'paid_waiting_fulfillment' => [ 'آماده‌سازی اکانت در انتظار شروع است', $tracking . '#artnovo-received-status', 'بررسی وضعیت اکانت' ],
	'issuing', 'partial' => [ $purchase_state['title'], $tracking . '#artnovo-received-status', 'بررسی وضعیت اکانت' ],
	'ready' => [ 'آمادهٔ اتصال', account_url(), 'مشاهدهٔ اکانت' ],
	'closed' => $expired
		? [ 'اعتبار اکانت به پایان رسیده است', home_url( '/#artnovo-categories' ), 'انتخاب اکانت جدید' ]
		: [ 'این خرید قابل دریافت نیست', $support, 'پیگیری با پشتیبانی' ],
	default => [ 'نیازمند بررسی', $support, 'پیگیری با پشتیبانی' ],
};

$receipt_url = 'bacs' === $method && received_receipt_exists( $order ) ? received_receipt_download_url( $order ) : '';
$date = $order->get_date_created();
$message = 'pending' === $state && 'bacs' === $method && ! $order->is_paid()
	? 'وضعیت همین سفارش را بررسی کنید. نتیجهٔ تأیید پرداخت از همین مسیر در دسترس است.'
	: $purchase_state['message'];
?>
<div class="artnovo-account-order" data-state="<?php echo esc_attr( $state ); ?>">
	<p class="artnovo-account-order-reference">سفارش <bdi><?php echo esc_html( \Artnovo\Core\display_digits( $order->get_order_number() ) ); ?></bdi></p>
	<div class="artnovo-account-order-summary">
		<section class="artnovo-account-card artnovo-account-order-status" aria-label="وضعیت خرید">
			<h2>وضعیت خرید</h2>
			<dl class="artnovo-account-order-facts">
				<div>
					<dt>پرداخت</dt>
					<dd><span class="artnovo-account-order-payment"><?php echo esc_html( $payment ); ?></span></dd>
				</div>
				<div>
					<dt>تحویل اکانت</dt>
					<dd><?php echo esc_html( \Artnovo\Core\display_digits( $delivery ) ); ?></dd>
				</div>
			</dl>
			<p><?php echo esc_html( \Artnovo\Core\display_digits( $message ) ); ?></p>
			<a class="artnovo-account-button artnovo-account-order-primary" href="<?php echo esc_url( $action_url ); ?>"><?php echo esc_html( $action_text ); ?></a>
			<?php if ( ! $order->is_paid() && in_array( $bank_payment['phase'] ?? '', [ 'open', 'review', 'failed_final' ], true )
				&& in_array( $state, [ 'pending', 'review', 'failed' ], true ) ) : ?>
				<a href="<?php echo esc_url( home_url( '/#artnovo-categories' ) ); ?>">مشاهدهٔ پلن‌ها</a>
			<?php endif; ?>
		</section>

		<section class="artnovo-account-order-invoice" aria-label="صورتحساب">
			<h2>صورتحساب</h2>
			<dl class="artnovo-account-order-facts">
				<div>
					<dt>پلن</dt>
					<dd><?php foreach ( $order->get_items() as $item ) : ?>
						<span><?php echo esc_html( \Artnovo\Core\display_digits( $item->get_name() ) ); ?>
							<?php if ( $item->get_quantity() > 1 ) : ?> · تعداد <?php echo esc_html( \Artnovo\Core\display_digits( number_format_i18n( $item->get_quantity() ) ) ); ?><?php endif; ?></span>
					<?php endforeach; ?></dd>
				</div>
				<div>
					<dt>مبلغ</dt>
					<dd><strong><?php echo \Artnovo\Core\display_number_html( wp_kses_post( $order->get_formatted_order_total() ), true ); ?></strong></dd>
				</div>
				<?php if ( $date ) : ?>
					<div>
						<dt>تاریخ</dt>
						<dd><?php echo esc_html( \Artnovo\Core\display_digits( wc_format_datetime( $date ) ) ); ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( $method_title ) : ?>
					<div>
						<dt>روش پرداخت</dt>
						<dd><?php echo esc_html( \Artnovo\Core\display_digits( $method_title ) ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>
			<?php if ( 'bacs' === $method ) : ?>
				<div class="artnovo-account-order-receipt">
					<strong>رسید پرداخت</strong>
					<?php if ( $receipt_url ) : ?>
						<a class="artnovo-account-button artnovo-account-button-secondary" href="<?php echo esc_url( $receipt_url ); ?>">دریافت رسید ثبت‌شده</a>
					<?php else : ?>
						<span>رسیدی ثبت نشده است.</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</section>
	</div>
	<details class="artnovo-account-order-native">
		<summary>فاکتور و جزئیات سفارش</summary>
		<?php
		// Keep the native invoice, order notes and extension hooks, without checkout-completion hooks.
		received_account_actions( $order, $bank_payment, $state, static function () use ( $order, $order_id ): void {
			$native_order_template = wc_locate_template( 'myaccount/view-order.php' );
			if ( is_readable( $native_order_template ) && __FILE__ !== realpath( $native_order_template ) ) {
				include $native_order_template;
			} else {
				do_action( 'woocommerce_view_order', $order_id );
			}
		} );
		?>
	</details>
</div>
