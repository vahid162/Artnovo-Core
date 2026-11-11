<?php

namespace Artnovo\AccountUI;

use Artnovo\PlanCheckout as Checkout;
use Pinova\Helpers\IP;
use Pinova\Services\FirewallService;
use Pinova\Services\UserService;

defined( 'ABSPATH' ) || exit;

const ACCOUNT_AUTH_STATE = 'artnovo_account_email_auth';

function account_email_login_ready(): bool {
	return function_exists( '\Artnovo\PlanCheckout\wizard_email_otp_enabled' )
		&& Checkout\wizard_email_otp_enabled() && function_exists( 'WC' ) && WC()->session;
}

function account_auth_key(): string {
	return Checkout\wizard_otp_key( 'account-challenge', WC()->session->get_customer_id() );
}

function account_auth_destination( string $requested = '' ): string {
	$fallback = wc_get_account_endpoint_url( 'vpn-subscriptions' );
	$account = wp_parse_url( wc_get_page_permalink( 'myaccount' ) );
	$destination = wp_validate_redirect( $requested, $fallback );
	$target = wp_parse_url( $destination );
	$base = trailingslashit( $account['path'] ?? '/' );
	$path = rawurldecode( $target['path'] ?? '' );
	if ( ! $target || isset( $target['user'] ) || isset( $target['pass'] )
		|| ( $target['scheme'] ?? '' ) !== ( $account['scheme'] ?? '' )
		|| ( $target['host'] ?? '' ) !== ( $account['host'] ?? '' )
		|| ( $target['port'] ?? null ) !== ( $account['port'] ?? null )
		|| str_contains( $path, '\\' )
		|| preg_match( '#(?:^|/)\.{1,2}(?:/|$)#', $path ) ) {
		return $fallback;
	}
	parse_str( $target['query'] ?? '', $query );
	$checkout_url = wc_get_page_permalink( 'checkout' );
	$checkout_path = trailingslashit( wp_parse_url( $checkout_url, PHP_URL_PATH ) ?: '/' );
	if ( str_starts_with( $path, $checkout_path ) ) {
		$parts = explode( '/', trim( substr( $path, strlen( $checkout_path ) ), '/' ) );
		$order_id = count( $parts ) === 2 ? filter_var( $parts[1], FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] ) : false;
		$key = $query['key'] ?? null;
		if ( ! $order_id || ! is_string( $key ) || strlen( $key ) > 200 || ! preg_match( '/\A[a-zA-Z0-9_-]+\z/', $key ) ) {
			return $fallback;
		}
		$endpoints = WC()->query->get_query_vars();
		foreach ( [ 'order-pay', 'order-received' ] as $financial_endpoint ) {
			if ( $parts[0] !== ( $endpoints[ $financial_endpoint ] ?? '' ) ) {
				continue;
			}
			$safe_query = [];
			if ( 'order-pay' === $financial_endpoint && in_array( $query['pay_for_order'] ?? null, [ 'true', '1' ], true ) ) {
				$safe_query['pay_for_order'] = 'true';
			}
			$safe_query['key'] = $key;
			return add_query_arg( $safe_query, wc_get_endpoint_url( $financial_endpoint, (string) $order_id, $checkout_url ) );
		}
		return $fallback;
	}
	if ( ! str_starts_with( $path, $base ) ) {
		return $fallback;
	}
	$endpoint = explode( '/', trim( substr( $path, strlen( $base ) ), '/' ) )[0];
	if ( '' === $endpoint ) {
		$view = $query['artnovo_view'] ?? null;
		if ( ! is_string( $view ) || ! in_array( $view, [ 'help', 'support', 'profile' ], true ) ) {
			return $fallback;
		}
		$safe_query = [ 'artnovo_view' => $view ];
		$order = $query['order'] ?? null;
		if ( 'support' === $view && is_string( $order ) && ctype_digit( $order ) && (int) $order > 0 ) {
			$safe_query['order'] = (string) (int) $order;
		}
		return add_query_arg( $safe_query, wc_get_page_permalink( 'myaccount' ) );
	}
	if ( $query ) {
		return $fallback;
	}
	$allowed = array_diff( array_values( WC()->query->get_query_vars() ), [
		'order-pay', 'order-received', 'customer-logout', 'lost-password', 'add-payment-method', 'delete-payment-method', 'set-default-payment-method',
	] );
	return in_array( $endpoint, $allowed, true ) ? esc_url_raw( $destination ) : $fallback;
}

function account_auth_state(): array {
	$state = account_email_login_ready() ? WC()->session->get( ACCOUNT_AUTH_STATE, [] ) : [];
	if ( ! is_array( $state ) || ( $state['until'] ?? 0 ) < time() ) {
		$state = [];
	}
	$email = $state['email'] ?? '';
	return [
		'email' => $email,
		'expires' => $email && account_email_login_ready() ? Checkout\wizard_email_otp_expiry( $email, account_auth_key() ) : 0,
		'message' => $state['message'] ?? '',
		'error' => (bool) ( $state['error'] ?? false ),
		'redirect' => account_auth_destination( $state['redirect'] ?? '' ),
	];
}

function account_auth_reply( array|\WP_Error $result, bool $ajax ): never {
	if ( ! is_wp_error( $result ) && isset( $result['redirect'] ) ) {
		if ( $ajax ) {
			wp_send_json_success( $result );
		}
		wp_safe_redirect( $result['redirect'], 303 );
		exit;
	}
	if ( function_exists( 'WC' ) && WC()->session ) {
		$state = WC()->session->get( ACCOUNT_AUTH_STATE, [] );
		$state = is_array( $state ) ? $state : [];
		$state['message'] = is_wp_error( $result ) ? $result->get_error_message() : ( $result['message'] ?? '' );
		$state['error'] = is_wp_error( $result );
		$state['until'] = time() + 30 * MINUTE_IN_SECONDS;
		WC()->session->set( ACCOUNT_AUTH_STATE, $state );
		WC()->session->save_data();
	}
	if ( $ajax ) {
		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data();
			wp_send_json_error( [ 'message' => $result->get_error_message() ], is_int( $status ) ? $status : 400 );
		}
		wp_send_json_success( $result );
	}
	wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) . '#artnovo-account-login', 303 );
	exit;
}

add_action( 'template_redirect', static function (): void {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! isset( $_POST['artnovo_account_action'] ) ) {
		return;
	}
	wc_nocache_headers();
	$ajax = '1' === ( $_POST['artnovo_account_ajax'] ?? null );
	if ( ! account_email_login_ready() ) {
		account_auth_reply( new \WP_Error( 'artnovo_auth_unavailable', 'ورود با ایمیل موقتاً در دسترس نیست. کمی بعد دوباره تلاش کنید.', 503 ), $ajax );
	}
	$action = Checkout\checkout_post_value( 'artnovo_account_action' );
	if ( ! wp_verify_nonce( Checkout\checkout_post_value( 'artnovo_account_nonce' ), 'artnovo_account_email_otp' )
		|| ! Checkout\wizard_otp_same_origin() || ! in_array( $action, [ 'send', 'verify' ], true ) ) {
		account_auth_reply( new \WP_Error( 'artnovo_auth_request', 'نشست ورود معتبر نیست. صفحه را تازه کنید و دوباره تلاش کنید.', 403 ), $ajax );
	}
	$redirect = account_auth_destination( Checkout\checkout_post_value( 'artnovo_redirect' ) );
	if ( is_user_logged_in() ) {
		account_auth_reply( [ 'redirect' => $redirect ], $ajax );
	}
	WC()->session->set_customer_session_cookie( true );
	$email = strtolower( Checkout\checkout_post_value( 'artnovo_email' ) );
	if ( ! is_email( $email ) || strlen( $email ) > 100 ) {
		account_auth_reply( new \WP_Error( 'artnovo_auth_email', 'ایمیل معتبر وارد کنید.', 400 ), $ajax );
	}
	try {
		$ip = IP::get();
		if ( ! $ip || FirewallService::is_blocked( $ip ) || FirewallService::is_blocked( $email ) ) {
			account_auth_reply( new \WP_Error( 'artnovo_auth_blocked', 'در حال حاضر امکان دریافت یا تأیید کد وجود ندارد.', 403 ), $ajax );
		}
		$key = account_auth_key();
		WC()->session->set( ACCOUNT_AUTH_STATE, [ 'email' => $email, 'redirect' => $redirect ] );
		$process = static function () use ( $action, $email, $ip, $key, $redirect ) {
			if ( 'send' === $action ) {
				return Checkout\wizard_otp_send( $key, $email, $ip );
			}
			$user = Checkout\wizard_otp_verify( $key, $email, $ip );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
			WC()->session->__unset( ACCOUNT_AUTH_STATE );
			WC()->session->save_data();
			UserService::login( $user->ID, 'otp' );
			return [ 'redirect' => $redirect ];
		};
		$result = Checkout\wizard_otp_locked(
			Checkout\wizard_otp_key( 'session-lock', $key ),
			static fn() => Checkout\wizard_otp_locked( Checkout\wizard_otp_key( 'email-lock', $email ), $process )
		);
	} catch ( \Throwable $exception ) {
		error_log( 'Artnovo account email OTP failed: ' . get_class( $exception ) );
		$result = new \WP_Error( 'artnovo_auth_failed', 'ورود با ایمیل موقتاً در دسترس نیست. دوباره تلاش کنید.', 503 );
	}
	account_auth_reply( $result, $ajax );
}, 5 );
