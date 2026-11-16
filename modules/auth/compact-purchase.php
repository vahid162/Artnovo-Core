<?php

namespace Artnovo\HomeOrderUI\Wizard;

use Artnovo\PlanCheckout as Checkout;
use Pinova\Services\OTPService;

defined( 'ABSPATH' ) || exit;

/** Render presentation only; Core and Pinova retain their own authentication engines. */
function render_compact_auth( int $product_id, int $quantity, string $selection_revision, bool $can_checkout ): void {
	$email_otp = Checkout\wizard_email_otp_enabled();
	$draft = Checkout\wizard_draft();
	$email = is_email( $draft['billing_email'] ?? '' ) ? $draft['billing_email'] : '';
	$expires = $email_otp && $email ? Checkout\wizard_email_otp_expiry( $email ) : 0;
	$id = wp_unique_id( 'artnovo-auth-' );
	$config = [
		'authenticateUrl' => rest_url( 'pinova/user/authenticate' ),
		'otpUrl' => rest_url( 'pinova/user/login/otp' ),
		'passwordUrl' => rest_url( 'pinova/user/login/password' ),
		'restNonce' => wp_create_nonce( 'wp_rest' ),
		'resumeUrl' => add_query_arg( 'artnovo_resume', '1', wc_get_page_permalink( 'myaccount' ) ),
		'refreshUrl' => Checkout\wizard_url( $product_id, true ),
		'emailOtp' => $email_otp,
		'emailCodeLength' => Checkout\EMAIL_OTP_DIGITS,
		'nativeCodeLength' => is_callable( [ OTPService::class, 'code_length' ] ) ? OTPService::code_length() : 4,
	];
	?>
	<form class="artnovo-order-form artnovo-compact-auth" method="post" action="<?php echo esc_url( get_permalink( PAGE_ID ) ); ?>"
		data-artnovo-auth-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
		data-artnovo-auth-expires="<?php echo esc_attr( (string) $expires ); ?>"
		data-artnovo-auth-available="<?php echo $can_checkout ? '1' : '0'; ?>">
		<?php wp_nonce_field( Checkout\WIZARD_DRAFT, 'artnovo_wizard_nonce', false ); ?>
		<input type="hidden" name="artnovo_product" value="<?php echo esc_attr( (string) $product_id ); ?>">
		<input type="hidden" name="artnovo_quantity" value="<?php echo esc_attr( (string) $quantity ); ?>">
		<input type="hidden" name="artnovo_selection_revision" value="<?php echo esc_attr( $selection_revision ); ?>">
		<input type="hidden" name="artnovo_login_method" value="pinova">
		<h3 class="artnovo-compact-auth-title">ورود سریع و ادامهٔ خرید</h3>
		<p class="artnovo-compact-auth-hint" id="<?php echo esc_attr( $id . '-hint' ); ?>">وارد شوید یا حساب بسازید؛ سپس روش پرداخت را همین‌جا انتخاب کنید.</p>
		<label class="artnovo-compact-auth-label" for="<?php echo esc_attr( $id . '-identifier' ); ?>">موبایل، ایمیل یا نام کاربری</label>
		<input class="artnovo-compact-auth-input" id="<?php echo esc_attr( $id . '-identifier' ); ?>" name="artnovo_auth_identifier" type="text" dir="ltr" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="100" value="<?php echo esc_attr( $expires ? $email : '' ); ?>" aria-describedby="<?php echo esc_attr( $id . '-hint ' . $id . '-message' ); ?>" required>
		<div data-artnovo-auth-proof hidden>
			<label class="artnovo-compact-auth-label" data-artnovo-auth-proof-label for="<?php echo esc_attr( $id . '-proof' ); ?>">کد تأیید</label>
			<input class="artnovo-compact-auth-input" id="<?php echo esc_attr( $id . '-proof' ); ?>" name="artnovo_auth_proof" type="text" dir="ltr" autocomplete="one-time-code" inputmode="numeric" aria-describedby="<?php echo esc_attr( $id . '-message' ); ?>" disabled>
			<div class="artnovo-compact-auth-secondary">
				<button type="button" data-artnovo-auth-edit>ویرایش شناسه</button>
				<button type="button" data-artnovo-auth-resend hidden>ارسال دوبارهٔ کد</button>
			</div>
		</div>
		<p class="artnovo-compact-auth-message" id="<?php echo esc_attr( $id . '-message' ); ?>" data-artnovo-auth-message role="status" aria-live="polite" aria-atomic="true"></p>
		<button type="submit" class="artnovo-order-submit artnovo-compact-auth-action" <?php disabled( ! $can_checkout ); ?>>ادامهٔ خرید</button>
		<noscript><p class="artnovo-compact-auth-hint">برای ورود و ادامهٔ همین خرید، دکمهٔ ادامهٔ خرید را بزنید.</p></noscript>
	</form>
	<?php
}
