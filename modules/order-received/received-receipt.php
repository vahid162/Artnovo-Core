<?php

namespace Artnovo\AccountUI;

use WPVPNBridge\Commerce\Receipts;
use WPVPNBridge\Commerce\Orders;
use WPVPNBridge\Persistence\Store;

defined( 'ABSPATH' ) || exit;

function received_receipt_exists( \WC_Order $order ): bool {
	$user = get_current_user_id();
	return $user > 0 && $user === $order->get_customer_id() && class_exists( Store::class )
		&& $user === ( new Store() )->receipt_owner( $order->get_id() );
}

function received_receipt_can_upload( \WC_Order $order ): bool {
	$user = get_current_user_id();
	$snapshot = $order->get_meta( '_wpvpn_snapshot', true );
	return $user > 0 && $user === $order->get_customer_id() && 'bacs' === $order->get_payment_method()
		&& $order->has_status( [ 'pending', 'on-hold' ] )
		&& 0.0 === (float) $order->get_total_refunded() && is_array( $snapshot )
		&& class_exists( Orders::class ) && Orders::matches_purchase( $order, $snapshot )
		&& class_exists( Store::class ) && class_exists( Receipts::class )
		&& null === ( new Store() )->get_by_order( $order->get_id() );
}

function render_received_receipt( \WC_Order $order ): void {
	if ( get_current_user_id() < 1 || get_current_user_id() !== $order->get_customer_id()
		|| 'bacs' !== $order->get_payment_method() || ! is_array( $order->get_meta( '_wpvpn_snapshot', true ) ) ) {
		return;
	}
	$exists = received_receipt_exists( $order );
	$can_upload = received_receipt_can_upload( $order );
	if ( ! $exists && ! $can_upload ) {
		return;
	}
	?>
	<section id="artnovo-received-receipt" class="artnovo-account-card artnovo-received-receipt" aria-labelledby="artnovo-received-receipt-heading">
		<h2 id="artnovo-received-receipt-heading">رسید پرداخت</h2>
		<?php if ( $exists ) : ?>
			<p class="artnovo-account-notice">رسید این خرید ثبت شده است. وضعیت تأیید پرداخت را بالای همین صفحه ببینید.</p>
			<?php
			$download = wp_nonce_url(
				add_query_arg( [ 'action' => 'wpvpn_download_receipt', 'order_id' => $order->get_id() ], admin_url( 'admin-post.php' ) ),
				'wpvpn_download_' . $order->get_id()
			);
			?>
			<p><a class="artnovo-account-button artnovo-account-button-secondary" href="<?php echo esc_url( $download ); ?>">دریافت رسید ثبت‌شده</a></p>
		<?php endif; ?>
		<?php if ( $can_upload ) : ?>
			<p class="artnovo-account-muted">تصویر رسید را با فرمت JPG یا PNG و حجم حداکثر ۲ مگابایت بفرستید. تأیید پرداخت پس از بررسی رسید انجام می‌شود.</p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( received_order_url( $order ) ); ?>" class="artnovo-received-receipt-form" data-artnovo-receipt-form>
				<?php wp_nonce_field( 'artnovo_received_receipt_' . $order->get_id(), 'artnovo_received_receipt_nonce', false ); ?>
				<input type="hidden" name="artnovo_received_receipt" value="1">
				<input type="hidden" name="MAX_FILE_SIZE" value="2097152">
				<label for="artnovo-received-receipt-file">تصویر رسید پرداخت</label>
				<input id="artnovo-received-receipt-file" type="file" name="wpvpn_receipt" accept="image/jpeg,image/png" required>
				<button type="submit" class="artnovo-account-button"><?php echo $exists ? 'جایگزینی رسید برای بررسی' : 'ارسال رسید برای بررسی'; ?></button>
				<p data-artnovo-upload-status role="status" hidden></p>
			</form>
		<?php endif; ?>
	</section>
	<?php
}

add_action( 'template_redirect', static function (): void {
	if ( ! isset( $_POST['artnovo_received_receipt'] ) ) {
		return;
	}
	$order = received_order();
	if ( ! $order instanceof \WC_Order ) {
		wp_die( 'برای ارسال رسید، با حساب صاحب این خرید وارد شوید و پیوند خرید را دوباره باز کنید.', '', [ 'response' => 403 ] );
	}
	nocache_headers();
	header( 'Cache-Control: private, no-store, max-age=0' );
	$nonce = isset( $_POST['artnovo_received_receipt_nonce'] ) && is_string( $_POST['artnovo_received_receipt_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['artnovo_received_receipt_nonce'] ) ) : '';
	$result = new \WP_Error( 'artnovo_receipt_request', 'نشست ارسال رسید معتبر نیست. صفحه را تازه کنید و دوباره تلاش کنید.' );
	$authorized = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && wp_verify_nonce( $nonce, 'artnovo_received_receipt_' . $order->get_id() );
	if ( $authorized && ! received_receipt_can_upload( $order ) ) {
		$result = new \WP_Error( 'wpvpn_receipt_access' );
	} elseif ( $authorized ) {
		$result = new \WP_Error( 'wpvpn_receipt_image', 'یک تصویر معتبر JPG یا PNG با حجم حداکثر ۲ مگابایت انتخاب کنید.' );
		$file = $_FILES['wpvpn_receipt'] ?? null;
		if ( is_array( $file ) && UPLOAD_ERR_OK === ( $file['error'] ?? null )
			&& is_string( $file['tmp_name'] ?? null ) && is_uploaded_file( $file['tmp_name'] ) ) {
			$body = file_get_contents( $file['tmp_name'], false, null, 0, 2097153 );
			if ( false !== $body && class_exists( Receipts::class ) && class_exists( Store::class ) ) {
				try {
					$result = ( new Receipts( new Store() ) )->store_image( $order->get_id(), $body );
				} catch ( \Throwable $exception ) {
					error_log( 'Artnovo receipt upload failed: ' . get_class( $exception ) );
					$result = new \WP_Error( 'wpvpn_receipt_storage', 'ثبت رسید انجام نشد. کمی بعد دوباره تلاش کنید.' );
				}
			}
		}
	}
	if ( is_wp_error( $result ) ) {
		$messages = [
			'wpvpn_receipt_access' => 'امکان تغییر رسید این خرید در وضعیت فعلی وجود ندارد. وضعیت خرید را تازه کنید.',
			'wpvpn_receipt_image' => 'یک تصویر معتبر JPG یا PNG با حجم حداکثر ۲ مگابایت انتخاب کنید.',
			'wpvpn_receipt_storage' => 'ثبت رسید انجام نشد. کمی بعد دوباره تلاش کنید.',
		];
		wc_add_notice( $messages[ $result->get_error_code() ] ?? $result->get_error_message(), 'error' );
	} else {
		wc_add_notice( 'رسید دریافت شد و برای بررسی پرداخت ثبت شد.', 'success' );
	}
	if ( is_callable( [ WC()->session, 'save_data' ] ) ) {
		WC()->session->save_data();
	}
	wp_safe_redirect( received_order_url( $order ) . '#artnovo-received-receipt', 303 );
	exit;
}, 6 );
