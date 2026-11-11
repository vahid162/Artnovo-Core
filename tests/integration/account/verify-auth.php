<?php

require_once dirname( __DIR__ ) . '/fixture-paths.php';
artnovo_fixture_boot_core();

remove_action( 'shutdown', '_wp_cron' );
if ( WC()->session instanceof WC_Session_Handler ) {
	remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
}
if ( WC()->customer instanceof WC_Customer ) {
	remove_action( 'shutdown', [ WC()->customer, 'save' ], 10 );
}
add_filter( 'pre_wp_mail', static function (): never {
	throw new RuntimeException( 'Unexpected test email.' );
} );
add_filter( 'query', static function ( string $sql ): string {
	if ( preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $sql ) ) {
		throw new RuntimeException( 'Unexpected database write.' );
	}
	return $sql;
} );

$failures = 0;
$check = static function ( string $case, bool $passed ) use ( &$failures ): void {
	$failures += $passed ? 0 : 1;
	WP_CLI::log( ( $passed ? 'PASS ' : 'FAIL ' ) . $case );
};
$fallback = wc_get_account_endpoint_url( 'vpn-subscriptions' );
$check( 'empty redirect uses subscriptions', $fallback === Artnovo\AccountUI\account_auth_destination() );
$check( 'external redirect uses subscriptions', $fallback === Artnovo\AccountUI\account_auth_destination( 'https://example.com/my-account/orders/' ) );
$check( 'logout redirect uses subscriptions', $fallback === Artnovo\AccountUI\account_auth_destination( wc_get_account_endpoint_url( 'customer-logout' ) ) );
$check( 'unknown account endpoint uses subscriptions', $fallback === Artnovo\AccountUI\account_auth_destination( home_url( '/my-account/not-registered/' ) ) );
$check( 'encoded traversal uses subscriptions', $fallback === Artnovo\AccountUI\account_auth_destination( home_url( '/my-account/%2e%2e/checkout/' ) ) );
$deep = wc_get_endpoint_url( 'view-order', '123', wc_get_page_permalink( 'myaccount' ) );
$check( 'owned-account deep path is retained for later ownership checks', $deep === Artnovo\AccountUI\account_auth_destination( $deep ) );
$support = add_query_arg( [ 'artnovo_view' => 'support', 'order' => '123' ], wc_get_page_permalink( 'myaccount' ) );
$check( 'support view retains only its positive order reference', $support === Artnovo\AccountUI\account_auth_destination( $support . '&cancel_order=true' ) );
$profile = add_query_arg( 'artnovo_view', 'profile', wc_get_page_permalink( 'myaccount' ) );
$check( 'profile view drops action queries', $profile === Artnovo\AccountUI\account_auth_destination( $profile . '&customer-logout=1&add-to-cart=25' ) );
$check( 'action query on deep account endpoint uses subscriptions', $fallback === Artnovo\AccountUI\account_auth_destination( $deep . '?add-to-cart=25' ) );
$key = 'wc_order_ArtNovoTest123';
$pay_url = add_query_arg( [ 'pay_for_order' => 'true', 'key' => $key ], wc_get_endpoint_url( 'order-pay', '123', wc_get_page_permalink( 'checkout' ) ) );
$received_url = add_query_arg( 'key', $key, wc_get_endpoint_url( 'order-received', '123', wc_get_page_permalink( 'checkout' ) ) );
$check( 'financial payment return preserves its safe key and pay flag', $pay_url === Artnovo\AccountUI\account_auth_destination( $pay_url ) );
$check( 'financial receipt return preserves its safe key', $received_url === Artnovo\AccountUI\account_auth_destination( $received_url ) );
$check( 'financial return drops unrelated action queries', $pay_url === Artnovo\AccountUI\account_auth_destination( $pay_url . '&cancel_order=true&add-to-cart=25' ) );
$check( 'financial return without order key uses subscriptions', $fallback === Artnovo\AccountUI\account_auth_destination( wc_get_endpoint_url( 'order-pay', '123', wc_get_page_permalink( 'checkout' ) ) ) );
$check( 'financial return with zero order id uses subscriptions', $fallback === Artnovo\AccountUI\account_auth_destination( add_query_arg( 'key', $key, wc_get_endpoint_url( 'order-pay', '0', wc_get_page_permalink( 'checkout' ) ) ) ) );
$check( 'unknown checkout path uses subscriptions', $fallback === Artnovo\AccountUI\account_auth_destination( add_query_arg( 'key', $key, wc_get_endpoint_url( 'cancel-order', '123', wc_get_page_permalink( 'checkout' ) ) ) ) );

WC()->session = new class extends WC_Session {
	public function has_session(): bool {
		return true;
	}
	public function get_customer_id(): string {
		return 'artnovo-memory-auth-test';
	}
};
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
$email = 'auth-test@example.com';
$account_key = Artnovo\AccountUI\account_auth_key();
$checkout_key = Artnovo\PlanCheckout\wizard_otp_challenge_key();
$expires = time() + 120;
$challenge = [
	'email' => Artnovo\PlanCheckout\wizard_otp_key( 'address', $email ),
	'ip' => Pinova\Helpers\IP::get(),
	'expires' => $expires,
];
add_filter( 'pre_transient_' . $account_key, static fn() => $challenge );
$check( 'account and checkout challenge keys differ', $account_key !== $checkout_key );
$check( 'account expiry reads only the account challenge', $expires === Artnovo\PlanCheckout\wizard_email_otp_expiry( $email, $account_key ) );
$check( 'default checkout expiry does not read the account challenge', 0 === Artnovo\PlanCheckout\wizard_email_otp_expiry( $email ) );
$response = Artnovo\PlanCheckout\wizard_otp_send( $account_key, $email, Pinova\Helpers\IP::get() );
$check( 'active account challenge resumes without sending or charging again', is_array( $response ) && $expires === $response['expires'] );
WC()->session->set( Artnovo\AccountUI\ACCOUNT_AUTH_STATE, [
	'email' => $email, 'until' => time() + 1800, 'redirect' => $deep, 'message' => 'test', 'error' => false,
] );
$state = Artnovo\AccountUI\account_auth_state();
$check( 'guest state resumes account email/code/deep path', $email === $state['email'] && $expires === $state['expires'] && $deep === $state['redirect'] );

$cache_class = new ReflectionClass( 'WP_Object_Cache' );
if ( realpath( $cache_class->getFileName() ) !== realpath( ABSPATH . WPINC . '/class-wp-object-cache.php' ) ) {
	WP_CLI::error( 'OTP proof requires the standard process-local WordPress object cache.' );
}
$synthetic_id = 2147483000;
if ( get_user_by( 'id', $synthetic_id ) ) {
	WP_CLI::error( 'Synthetic test user ID is already allocated.' );
}
$original_cache = $GLOBALS['wp_object_cache'];
$original_ext_cache = wp_using_ext_object_cache();
$GLOBALS['wp_object_cache'] = new WP_Object_Cache();
wp_using_ext_object_cache( true );
remove_all_filters( 'pre_transient_' . $account_key );
remove_all_filters( 'pre_wp_mail' );
add_filter( 'pre_http_request', static function (): never {
	throw new RuntimeException( 'Unexpected outbound HTTP request.' );
} );
$proof_email = 'artnovo-otp-proof@example.invalid';
$captured_code = null;
$mail_count = 0;
add_filter( 'pre_wp_mail', static function ( mixed $return, array $mail ) use ( $proof_email, &$captured_code, &$mail_count ): bool {
	if ( $proof_email !== $mail['to'] || ! preg_match( '/>\s*(\d{6})\s*</', $mail['message'], $matches ) ) {
		throw new RuntimeException( 'Unexpected mail target or OTP template.' );
	}
	$captured_code = $matches[1];
	++$mail_count;
	return true;
}, PHP_INT_MAX, 2 );
try {
	global $wpdb;
	wp_cache_set( $synthetic_id, (object) [
		'ID' => $synthetic_id, 'user_login' => 'artnovo-memory-otp-proof', 'user_pass' => 'unused-by-otp',
		'user_nicename' => 'artnovo-memory-otp-proof', 'user_email' => $proof_email, 'user_url' => '',
		'user_registered' => '2026-01-01 00:00:00', 'user_activation_key' => '', 'user_status' => 0,
		'display_name' => 'In-memory OTP proof',
	], 'users' );
	wp_cache_set( $proof_email, $synthetic_id, 'useremail' );
	wp_cache_set( $synthetic_id, [ $wpdb->prefix . 'capabilities' => [ serialize( [ 'subscriber' => true ] ) ] ], 'user_meta' );
	$ip = Pinova\Helpers\IP::get();
	$sent = Artnovo\PlanCheckout\wizard_otp_send( $account_key, $proof_email, $ip );
	$check( 'native OTP send stores a challenge and captures one mail only in memory', is_array( $sent ) && 1 === $mail_count && is_string( $captured_code ) );
	$_POST['artnovo_otp_code'] = '111111' === $captured_code ? '222222' : '111111';
	$wrong = Artnovo\PlanCheckout\wizard_otp_verify( $account_key, $proof_email, $ip );
	$check( 'native OTP rejects an incorrect code', is_wp_error( $wrong ) && 'artnovo_otp_invalid' === $wrong->get_error_code() );
	$_POST['artnovo_otp_code'] = $captured_code;
	$cross_context = Artnovo\PlanCheckout\wizard_otp_verify( $checkout_key, $proof_email, $ip );
	$check( 'account code is rejected in checkout challenge scope', is_wp_error( $cross_context ) && 'artnovo_otp_expired' === $cross_context->get_error_code() );
	$verified = Artnovo\PlanCheckout\wizard_otp_verify( $account_key, $proof_email, $ip );
	$check( 'native OTP verifies the synthetic subscriber without creating a user', $verified instanceof WP_User && $synthetic_id === $verified->ID && [ 'subscriber' ] === $verified->roles );
	$replay = Artnovo\PlanCheckout\wizard_otp_verify( $account_key, $proof_email, $ip );
	$check( 'native OTP rejects replay after successful verification', is_wp_error( $replay ) && 'artnovo_otp_expired' === $replay->get_error_code() );
} finally {
	unset( $_POST['artnovo_otp_code'], $captured_code );
	$GLOBALS['wp_object_cache'] = $original_cache;
	wp_using_ext_object_cache( $original_ext_cache );
}

if ( $failures ) {
	WP_CLI::error( $failures . ' account-auth checks failed.' );
}
WP_CLI::success( 'Account auth checks passed with database writes and mail blocked.' );
