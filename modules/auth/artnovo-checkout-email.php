<?php

namespace Artnovo\PlanCheckout;

use Pinova\Channels\Email;
use Pinova\Helpers\IP;
use Pinova\Helpers\JWT;
use Pinova\Helpers\Number;
use Pinova\Pinova;
use Pinova\Services\FirewallService;
use Pinova\Services\UserService;

defined( 'ABSPATH' ) || exit;

const EMAIL_OTP_OPTION = 'artnovo_checkout_email_otp';
const EMAIL_OTP_DIGITS = 6;

function wizard_email_otp_enabled(): bool {
	return 'yes' === get_option( EMAIL_OTP_OPTION ) && is_callable( [ Email::class, 'send_code' ] )
		&& is_callable( [ UserService::class, 'login' ] );
}

function wizard_otp_same_origin(): bool {
	$site = wp_parse_url( home_url( '/' ) );
	$origin = $site['scheme'] . '://' . $site['host'] . ( isset( $site['port'] ) ? ':' . $site['port'] : '' );
	return get_http_origin() === $origin;
}

function wizard_otp_key( string $scope, string $value ): string {
	return 'artnovo_otp_' . substr( hash_hmac( 'sha256', $scope . ':' . $value, wp_salt( 'auth' ) ), 0, 40 );
}

function wizard_otp_challenge_key(): string {
	return wizard_otp_key( 'challenge', WC()->session->get_customer_id() );
}

function wizard_email_otp_expiry( string $email, ?string $key = null ): int {
	if ( ! is_email( $email ) || ! WC()->session || ! WC()->session->has_session() ) {
		return 0;
	}
	$challenge = get_transient( $key ?? wizard_otp_challenge_key() );
	return ( $challenge['email'] ?? '' ) === wizard_otp_key( 'address', strtolower( $email ) )
		&& ( $challenge['ip'] ?? '' ) === IP::get()
		&& ( $challenge['expires'] ?? 0 ) > time() ? $challenge['expires'] : 0;
}

function wizard_otp_locked( string $key, callable $callback ): mixed {
	global $wpdb;
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $key ) ) ) {
		return new \WP_Error( 'artnovo_otp_busy', 'درخواست دیگری در حال بررسی است. کمی بعد دوباره تلاش کنید.', 429 );
	}
	try {
		return $callback();
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
	}
}

function wizard_otp_charge( string $key, int $limit ): bool {
	$now = time();
	$rate = get_transient( $key );
	if ( ( $rate['until'] ?? 0 ) <= $now ) {
		$rate = [ 'count' => 0, 'until' => $now + HOUR_IN_SECONDS ];
	}
	if ( $rate['count'] >= $limit ) {
		return false;
	}
	++$rate['count'];
	return set_transient( $key, $rate, $rate['until'] - $now );
}

function wizard_otp_send( string $key, string $email, string $ip ): array|\WP_Error {
	$active = wizard_email_otp_expiry( $email, $key );
	if ( $active ) {
		return [ 'message' => 'کد قبلی هنوز معتبر است. پوشهٔ هرزنامه را هم بررسی کنید.', 'expires' => $active ];
	}
	$cooldown = wizard_otp_key( 'cooldown', $email );
	$ip_rate = wizard_otp_key( 'ip', $ip );
	$email_rate = wizard_otp_key( 'email', $email );
	if ( get_transient( $cooldown )
		|| true !== wizard_otp_locked( $ip_rate, static fn() => wizard_otp_charge( $ip_rate, 8 ) )
		|| ! wizard_otp_charge( $email_rate, 5 )
		|| ! set_transient( $cooldown, 1, MINUTE_IN_SECONDS ) ) {
		return new \WP_Error( 'artnovo_otp_rate', 'درخواست‌های ارسال کد زیاد است. کمی بعد دوباره تلاش کنید.', 429 );
	}

	// Keep checkout challenges separate from the OTPs accepted on Pinova's public login routes.
	$code = random_int( 100000, 999999 );
	$expires = time() + JWT::DEFAULT_TTL;
	$challenge = [
		'hash' => hash_hmac( 'sha256', $key . ':' . $code, wp_salt( 'auth' ) ),
		'email' => wizard_otp_key( 'address', $email ),
		'ip' => $ip,
		'expires' => $expires,
		'attempts' => 0,
	];
	if ( ! set_transient( $key, $challenge, JWT::DEFAULT_TTL ) ) {
		return new \WP_Error( 'artnovo_otp_storage', 'درخواست ارسال کد ثبت نشد. دوباره تلاش کنید.', 503 );
	}

	try {
		$sent = Email::send_code( $email, $code );
	} catch ( \Throwable $exception ) {
		delete_transient( $key );
		throw $exception;
	}
	if ( ! $sent ) {
		delete_transient( $key );
		return new \WP_Error( 'artnovo_otp_mail', 'ارسال ایمیل انجام نشد. کمی بعد دوباره تلاش کنید.', 503 );
	}
	return [ 'message' => 'کد تأیید به ایمیل شما ارسال شد.', 'expires' => $expires ];
}

function wizard_otp_verify( string $key, string $email, string $ip ): \WP_User|\WP_Error {
	$challenge = get_transient( $key );
	$remaining = ( $challenge['expires'] ?? 0 ) - time();
	if ( $remaining <= 0 || $challenge['ip'] !== $ip || $challenge['email'] !== wizard_otp_key( 'address', $email ) ) {
		return new \WP_Error( 'artnovo_otp_expired', 'کد معتبر نیست یا منقضی شده است. دوباره کد بگیرید.', 400 );
	}
	if ( $challenge['attempts'] >= 5 ) {
		return new \WP_Error( 'artnovo_otp_attempts', 'تعداد تلاش‌ها تمام شد. بعد از پایان مهلت، کد تازه بگیرید.', 429 );
	}
	$code = Number::en( checkout_post_value( 'artnovo_otp_code' ) );
	if ( EMAIL_OTP_DIGITS !== strlen( $code ) || ! ctype_digit( $code ) ) {
		return new \WP_Error( 'artnovo_otp_format', 'کد تأیید شش‌رقمی را وارد کنید.', 400 );
	}
	if ( ! hash_equals( $challenge['hash'], hash_hmac( 'sha256', $key . ':' . $code, wp_salt( 'auth' ) ) ) ) {
		++$challenge['attempts'];
		if ( ! set_transient( $key, $challenge, $remaining ) ) {
			delete_transient( $key );
		}
		return new \WP_Error( 'artnovo_otp_invalid', 'کد تأیید درست نیست. دوباره بررسی کنید.', 400 );
	}
	if ( ! delete_transient( $key ) ) {
		return new \WP_Error( 'artnovo_otp_used', 'این کد دیگر قابل استفاده نیست. دوباره کد بگیرید.', 400 );
	}

	$user = get_user_by( 'email', $email );
	if ( ! $user && Pinova::users_can_register() ) {
		$id = wc_create_new_customer( $email, '', wp_generate_password( 32 ) );
		$user = is_wp_error( $id ) ? get_user_by( 'email', $email ) : get_user_by( 'id', $id );
	}
	if ( ! $user || 0 !== strcasecmp( $user->user_email, $email )
		|| ! $user->roles || array_diff( $user->roles, [ 'customer', 'subscriber' ] )
		|| is_super_admin( $user->ID ) || array_diff_key( array_filter( $user->caps ), array_flip( $user->roles ) ) ) {
		return new \WP_Error( 'artnovo_otp_account', 'ورود به حساب مشتری با این ایمیل امکان‌پذیر نشد.', 403 );
	}
	return $user;
}

function wizard_email_otp_request( string $action, array $draft ): never {
	$email = strtolower( $draft['billing_email'] );
	$ip = IP::get();
	if ( ! is_email( $email ) || strlen( $email ) > 100 ) {
		wp_send_json_error( [ 'message' => 'ایمیل معتبر وارد کنید.' ], 400 );
	}

	try {
		if ( ! $ip || FirewallService::is_blocked( $ip ) || FirewallService::is_blocked( $email ) ) {
			wp_send_json_error( [ 'message' => 'در حال حاضر امکان دریافت یا تأیید کد وجود ندارد.' ], 403 );
		}
		$key = wizard_otp_challenge_key();
		$draft['billing_email'] = $email;
		$process = static function () use ( $action, $draft, $email, $ip, $key ) {
			if ( 'send' === $action ) {
				$response = wizard_otp_send( $key, $email, $ip );
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				WC()->session->set( WIZARD_DRAFT, $draft );
				WC()->session->save_data();
				return $response;
			}

			$user = wizard_otp_verify( $key, $email, $ip );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
			// Pinova's login hook migrates the persisted WooCommerce session in this request.
			WC()->session->set( WIZARD_DRAFT, $draft );
			WC()->session->save_data();
			UserService::login( $user->ID, 'otp' );
			return [ 'redirect' => wizard_url( $draft['product'] ) ];
		};
		$result = wizard_otp_locked(
			wizard_otp_key( 'session-lock', $key ),
			static fn() => wizard_otp_locked( wizard_otp_key( 'email-lock', $email ), $process )
		);
	} catch ( \Throwable $exception ) {
		error_log( 'Artnovo checkout email OTP failed: ' . get_class( $exception ) );
		wp_send_json_error( [ 'message' => 'ورود با ایمیل موقتاً در دسترس نیست. دوباره تلاش کنید.' ], 503 );
	}
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [ 'message' => $result->get_error_message() ], $result->get_error_data() );
	}
	wp_send_json_success( $result );
}
