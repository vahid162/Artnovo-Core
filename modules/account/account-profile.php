<?php

namespace Artnovo\AccountUI;

defined( 'ABSPATH' ) || exit;

// WooCommerce owns the account save, validation and post-save redirect.
$errors = wc_get_notices( 'error' );
$nonce = $_POST['save-account-details-nonce'] ?? '';
$retry = $errors && 'save_account_details' === ( $_POST['action'] ?? '' ) && is_string( $nonce )
	&& wp_verify_nonce( wp_unslash( $nonce ), 'save_account_details' );

$required = apply_filters( 'woocommerce_save_account_details_required_fields', [
	'account_first_name'   => __( 'First name', 'woocommerce' ),
	'account_last_name'    => __( 'Last name', 'woocommerce' ),
	'account_display_name' => __( 'Display name', 'woocommerce' ),
	'account_email'        => __( 'Email address', 'woocommerce' ),
] );

$labels = [
	'identity' => 'مشخصات نمایش',
	'contact' => 'اطلاعات ورود',
	'password' => 'تغییر رمز عبور',
];
$field_sections = [
	'account_first_name' => 'identity',
	'account_last_name' => 'identity',
	'account_display_name' => 'identity',
	'account_email' => 'contact',
	'pinova_mobile' => 'contact',
	'password_current' => 'password',
	'password_1' => 'password',
	'password_2' => 'password',
];
$open = [];
if ( $retry ) {
	foreach ( $errors as $error ) {
		$id = $error['data']['id'] ?? '';
		if ( ! is_string( $id ) || ! isset( $field_sections[ $id ] ) ) {
			// Native password and extension errors do not always carry a field ID.
			$open = array_keys( $labels );
			break;
		}
		$open[] = $field_sections[ $id ];
	}
}
if ( ! $open ) {
	$open[] = 'identity';
}

$posted_value = static function ( string $name, string $default ) use ( $retry ): string {
	$posted = $_POST[ $name ] ?? null;
	return $retry && is_string( $posted ) ? wc_clean( wp_unslash( $posted ) ) : $default;
};
$field = static function (
	string $name, string $label, string $type, string $autocomplete, string $value = '', string $hint = ''
) use ( $required, $posted_value ): void {
	$is_required = isset( $required[ $name ] );
	if ( 'password' !== $type ) {
		$value = $posted_value( $name, $value );
	}
	?>
	<p class="woocommerce-form-row form-row artnovo-profile-field">
		<label for="<?php echo esc_attr( $name ); ?>">
			<?php echo esc_html( $label ); ?>
			<?php if ( $is_required ) : ?>
				<span class="required" aria-hidden="true">*</span>
			<?php elseif ( 'account_email' === $name ) : ?>
				<span class="artnovo-profile-hint">اختیاری</span>
			<?php endif; ?>
		</label>
		<input class="woocommerce-Input input-text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>"
			type="<?php echo esc_attr( $type ); ?>" autocomplete="<?php echo esc_attr( $autocomplete ); ?>"
			<?php if ( 'text' !== $type ) : ?>dir="ltr"<?php endif; ?>
			<?php if ( 'password' !== $type ) : ?>value="<?php echo esc_attr( $value ); ?>"<?php endif; ?>
			<?php if ( $hint ) : ?>aria-describedby="<?php echo esc_attr( $name . '_description' ); ?>"<?php endif; ?>
			<?php if ( $is_required ) : ?>required<?php endif; ?>>
		<?php if ( $hint ) : ?>
			<span class="artnovo-profile-hint" id="<?php echo esc_attr( $name . '_description' ); ?>"><?php echo esc_html( $hint ); ?></span>
		<?php endif; ?>
	</p>
	<?php
};

if ( $errors ) {
	echo '<div class="artnovo-profile-notices" data-artnovo-notice-permanent>';
	wc_print_notices();
	echo '</div>';
} else {
	wc_print_notices();
}
do_action( 'woocommerce_before_edit_account_form' );
?>

<form class="woocommerce-EditAccountForm edit-account artnovo-profile-form" action="" method="post" novalidate <?php do_action( 'woocommerce_edit_account_form_tag' ); ?>>
	<div class="artnovo-profile-layout">
		<nav class="artnovo-profile-navigation" aria-label="بخش‌های ویرایش حساب" hidden>
			<?php foreach ( $labels as $key => $label ) : ?>
				<button type="button" aria-controls="artnovo-profile-<?php echo esc_attr( $key ); ?>" aria-expanded="<?php echo in_array( $key, $open, true ) ? 'true' : 'false'; ?>">
					<?php echo esc_html( $label ); ?>
				</button>
			<?php endforeach; ?>
		</nav>
		<div class="artnovo-profile-sections">
			<details class="artnovo-profile-section" id="artnovo-profile-identity" <?php if ( in_array( 'identity', $open, true ) ) : ?>open<?php endif; ?>>
				<summary>مشخصات نمایش</summary>
				<div class="artnovo-profile-fields">
					<h2>مشخصات نمایش</h2>
					<?php
					$field( 'account_first_name', 'نام', 'text', 'given-name', (string) $user->first_name );
					$field( 'account_last_name', 'نام خانوادگی', 'text', 'family-name', (string) $user->last_name );
					$field( 'account_display_name', 'نام نمایشی', 'text', 'nickname', (string) $user->display_name,
						wc_reviews_enabled() ? 'این نام در حساب و دیدگاه‌های شما نمایش داده می‌شود.' : 'این نام در حساب شما نمایش داده می‌شود.' );
					?>
				</div>
			</details>
			<details class="artnovo-profile-section" id="artnovo-profile-contact" <?php if ( in_array( 'contact', $open, true ) ) : ?>open<?php endif; ?>>
				<summary>اطلاعات ورود</summary>
				<div class="artnovo-profile-fields">
					<h2>اطلاعات ورود</h2>
					<?php
					ob_start();
					do_action( 'woocommerce_edit_account_form_start' );
					$contact = new \WP_HTML_Tag_Processor( (string) ob_get_clean() );
					while ( $contact->next_tag( 'INPUT' ) ) {
						if ( 'pinova_mobile' !== $contact->get_attribute( 'name' ) ) {
							continue;
						}
						$contact->set_attribute( 'autocomplete', 'tel' );
						$contact->set_attribute( 'dir', 'ltr' );
						$contact->set_attribute( 'inputmode', 'tel' );
						if ( null === $contact->get_attribute( 'aria-describedby' ) ) {
							$contact->set_attribute( 'aria-describedby', 'pinova_mobile_description' );
						}
						$contact->set_attribute( 'value', $posted_value( 'pinova_mobile', (string) $contact->get_attribute( 'value' ) ) );
						break;
					}
					echo $contact->get_updated_html(); // Trusted native and extension form fields.
					$field( 'account_email', 'ایمیل', 'email', 'email', (string) $user->user_email );
					do_action( 'woocommerce_edit_account_form_fields' );
					?>
				</div>
			</details>
			<details class="artnovo-profile-section" id="artnovo-profile-password" <?php if ( in_array( 'password', $open, true ) ) : ?>open<?php endif; ?>>
				<summary>تغییر رمز عبور <span class="artnovo-profile-hint">اختیاری</span></summary>
				<div class="artnovo-profile-fields">
					<h2>تغییر رمز عبور</h2>
					<p class="artnovo-profile-hint">برای تغییر رمز حساب، هر سه فیلد زیر را پر کنید.</p>
					<?php
					$field( 'password_current', 'رمز فعلی', 'password', 'current-password' );
					$field( 'password_1', 'رمز جدید', 'password', 'new-password' );
					$field( 'password_2', 'تکرار رمز جدید', 'password', 'new-password' );
					?>
				</div>
			</details>
		</div>
	</div>
	<?php do_action( 'woocommerce_edit_account_form' ); ?>
	<div class="artnovo-profile-actions">
		<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
		<input type="hidden" name="action" value="save_account_details">
		<button type="submit" class="woocommerce-Button button artnovo-account-button" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>">ذخیرهٔ تغییرات</button>
	</div>
	<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>
<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
