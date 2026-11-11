<?php

namespace Artnovo\PlanCheckout;

use WPVPNBridge\Commerce\Orders;
use WPVPNBridge\Payment\WalletPayment;
use WPVPNBridge\Settings;

defined( 'ABSPATH' ) || exit;

const WIZARD_DRAFT = 'artnovo_wizard_checkout';

require_once dirname( __DIR__ ) . '/auth/artnovo-checkout-email.php';

add_filter( 'woocommerce_persistent_cart_enabled', '__return_false' );
add_filter( 'wc_add_to_cart_message_html', '__return_empty_string' );

function is_plan( $product ): bool {
	if ( ! $product instanceof \WC_Product ) {
		return false;
	}
	$id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
	return has_term( array( 'vpn-one-month', 'vpn-two-month' ), 'product_cat', $id );
}

function selected_plan(): ?\WC_Product {
	$id = $_GET['artnovo_plan'] ?? null;
	if ( ! is_string( $id ) || ! ctype_digit( $id ) || ! function_exists( 'WC' ) || ! is_account_page() || is_wc_endpoint_url() ) {
		return null;
	}
	$product = wc_get_product( (int) $id );
	return is_plan( $product ) && 'publish' === $product->get_status() ? $product : null;
}

function transfer_ready(): bool {
	$settings = get_option( 'woocommerce_bacs_settings', array() );
	if ( 'yes' !== ( $settings['enabled'] ?? '' ) ) {
		return false;
	}

	$accounts = get_option( 'woocommerce_bacs_accounts', array( $settings ) );
	foreach ( (array) $accounts as $account ) {
		foreach ( array( 'account_number', 'iban' ) as $field ) {
			$destination = $account[ $field ] ?? null;
			if ( is_string( $destination ) && '' !== trim( $destination ) ) {
				return true;
			}
		}
	}
	return false;
}

function wallet_configured(): bool {
	return class_exists( WalletPayment::class ) && WalletPayment::enabled();
}

function wallet_ready(): bool {
	return wallet_configured() && WalletPayment::allowed_customer( get_current_user_id() );
}

function payment_methods( \WC_Product $product, int $quantity ): array {
	$methods = [];
	if ( transfer_ready() ) {
		$methods[] = 'bacs';
	}
	if ( $quantity > 0 && wallet_ready() && $quantity <= quantity_limit( $product, WalletPayment::GATEWAY ) ) {
		$methods[] = WalletPayment::GATEWAY;
	}
	return $methods;
}

function plan_configured( \WC_Product $product ): bool {
	return 'publish' === $product->get_status() && $product->is_type( 'simple' ) && $product->is_virtual()
		&& 'yes' === $product->get_meta( '_wpvpn_enabled', true )
		&& class_exists( Orders::class ) && ! is_wp_error( Orders::product_terms( $product ) )
		&& class_exists( Settings::class ) && ( new Settings() )->ready();
}

function plan_purchasable( bool $purchasable, \WC_Product $product ): bool {
	return $purchasable && ( ! is_plan( $product ) || ( plan_configured( $product ) && [] !== payment_methods( $product, 1 ) ) );
}
add_filter( 'woocommerce_is_purchasable', __NAMESPACE__ . '\\plan_purchasable', PHP_INT_MAX, 2 );

function quantity_limit( \WC_Product $product, ?string $method = null ): int {
	$bridge_limit = 1;
	if ( is_callable( [ Orders::class, 'max_purchase_quantity' ] ) ) {
		$bridge_limit = null !== $method ? Orders::max_purchase_quantity( $product, $method ) : max(
			transfer_ready() ? Orders::max_purchase_quantity( $product, 'bacs' ) : 1,
			wallet_ready() ? Orders::max_purchase_quantity( $product, WalletPayment::GATEWAY ) : 1
		);
	}
	$stock_limit = (int) $product->get_max_purchase_quantity();
	return -1 === $stock_limit ? $bridge_limit : min( $bridge_limit, max( 0, $stock_limit ) );
}

function cart_has_plan(): bool {
	foreach ( cart_items() as $item ) {
		if ( is_plan( $item['data'] ?? null ) ) {
			return true;
		}
	}
	return false;
}

function cart_items(): array {
	return function_exists( 'WC' ) ? ( WC()->cart?->get_cart() ?? array() ) : array();
}

function cart_selection( int $id, int $quantity = 1 ): string {
	$items = cart_items();
	if ( empty( $items ) ) {
		return 'empty';
	}
	$item = reset( $items );
	return 1 === count( $items ) && $id === (int) ( $item['product_id'] ?? 0 ) && (float) $quantity === (float) ( $item['quantity'] ?? 0 ) ? 'same' : 'different';
}

add_filter( 'woocommerce_available_payment_gateways', static function ( array $gateways ): array {
	if ( is_wc_endpoint_url( 'order-pay' ) ) {
		return $gateways;
	}
	foreach ( cart_items() as $item ) {
		$product = $item['data'] ?? null;
		if ( ! is_plan( $product ) ) {
			continue;
		}
		if ( ! $product->is_in_stock() || ! $product->is_purchasable() ) {
			return array();
		}
		if ( ! transfer_ready() ) {
			unset( $gateways['bacs'] );
		}
		if ( ! wallet_ready() ) {
			unset( $gateways['wpvpn_wallet'] );
		}
		break;
	}
	return $gateways;
}, PHP_INT_MAX );

function wizard_page(): bool {
	return function_exists( '\\Artnovo\\HomeOrderUI\\Wizard\\is_page' ) && \Artnovo\HomeOrderUI\Wizard\is_page();
}

function wizard_url( int $product_id, bool $checkout = false ): string {
	$query = [ 'artnovo_plan' => $product_id ];
	if ( $checkout ) {
		$query['artnovo_checkout'] = '1';
	}
	return add_query_arg( $query, home_url( '/' ) ) . '#artnovo-order';
}

function wizard_draft(): array {
	$draft = function_exists( 'WC' ) ? WC()->session?->get( WIZARD_DRAFT ) : null;
	return is_array( $draft ) && ( $draft['expires'] ?? 0 ) >= time() ? $draft : [];
}

function wizard_checkout_active(): bool {
	if ( ! wizard_page() || '1' !== ( $_GET['artnovo_checkout'] ?? null ) || ! is_user_logged_in() ) {
		return false;
	}
	$draft = wizard_draft();
	return isset( $draft['product'], $draft['quantity'] )
		&& (string) $draft['product'] === ( $_GET['artnovo_plan'] ?? null )
		&& 'same' === cart_selection( $draft['product'], $draft['quantity'] );
}

add_filter( 'woocommerce_is_checkout', static function ( bool $checkout ): bool {
	return $checkout || wizard_checkout_active();
} );

function checkout_post_value( string $name ): string {
	return is_string( $_POST[ $name ] ?? null ) ? trim( wp_unslash( $_POST[ $name ] ) ) : '';
}

function wizard_email_checkout_marker(): string {
	if ( ! is_user_logged_in() || ! function_exists( 'WC' ) || wc_tax_enabled() ) {
		return '';
	}
	$draft = wizard_draft();
	if ( ! isset( $draft['product'], $draft['quantity'] ) ) {
		return '';
	}
	$items = cart_items();
	$item = 1 === count( $items ) ? reset( $items ) : false;
	$product = $item['data'] ?? null;
	if ( ! is_plan( $product ) || ! $product->is_virtual() || $draft['product'] !== (int) ( $item['product_id'] ?? 0 )
		|| (float) $draft['quantity'] !== (float) ( $item['quantity'] ?? 0 ) ) {
		return '';
	}
	return $draft['product'] . ':' . $draft['quantity'];
}

function wizard_email_checkout(): bool {
	$marker = wizard_email_checkout_marker();
	return '' !== $marker && ( wizard_checkout_active()
		|| ( $marker === checkout_post_value( 'artnovo_wizard_cart' )
			&& wp_verify_nonce( checkout_post_value( 'woocommerce-process-checkout-nonce' ), 'woocommerce-process_checkout' ) ) );
}

add_action( 'woocommerce_checkout_process', static function (): void {
	$wallet = class_exists( WalletPayment::class ) && WalletPayment::GATEWAY === checkout_post_value( 'payment_method' );
	if ( ! wizard_email_checkout() || ( ! wizard_email_otp_enabled() && ! $wallet ) ) {
		return;
	}
	// This authenticated checkout form collects only an email address.
	$hooks = $GLOBALS['wp_filter']['woocommerce_checkout_process']->callbacks[10] ?? [];
	foreach ( $hooks as $hook ) {
		$callback = $hook['function'];
		if ( is_array( $callback ) && $callback[0] instanceof \Pinova\Integrations\Woocommerce\Checkout
			&& 'validate_checkout_phone' === $callback[1] ) {
			remove_action( 'woocommerce_checkout_process', $callback );
		}
	}
}, 9 );

add_filter( 'woocommerce_checkout_fields', static function ( array $fields ): array {
	if ( ! wizard_email_checkout() ) {
		return $fields;
	}
	$email = array_replace( $fields['billing']['billing_email'] ?? [], [
		'type' => 'email', 'label' => 'ایمیل سفارش', 'required' => true, 'validate' => [ 'email' ],
		'autocomplete' => 'email', 'class' => [ 'form-row-wide' ],
	] );
	return [ 'billing' => [ 'billing_email' => $email ], 'shipping' => [], 'account' => [], 'order' => [] ];
}, PHP_INT_MAX );

add_filter( 'woocommerce_enable_order_notes_field', static function ( bool $enabled ): bool {
	return $enabled && ! wizard_email_checkout();
}, PHP_INT_MAX );

add_filter( 'default_checkout_billing_email', static function ( $email ) {
	return empty( $email ) && wizard_email_checkout() ? wp_get_current_user()->user_email : $email;
} );

add_filter( 'woocommerce_checkout_update_customer_data', static function ( bool $update ): bool {
	return $update && ! wizard_email_checkout();
}, PHP_INT_MAX );

add_action( 'woocommerce_checkout_update_order_review', static function ( $posted ): void {
	$marker = wizard_email_checkout_marker();
	if ( '' === $marker || ! is_string( $posted ) || ! wp_verify_nonce( checkout_post_value( 'security' ), 'update-order-review' ) ) {
		return;
	}
	parse_str( $posted, $fields );
	if ( $marker !== ( $fields['artnovo_wizard_cart'] ?? null ) ) {
		return;
	}
	// WooCommerce sends empty address placeholders for omitted inputs.
	foreach ( [ 'country', 'state', 'postcode', 'city', 'address', 'address_2' ] as $field ) {
		unset( $_POST[ $field ], $_POST[ 's_' . $field ] );
	}
} );

function wizard_reply( int $product_id, bool $ajax, string $error = '' ): never {
	$url = wizard_url( $product_id, '' === $error );
	if ( $ajax ) {
		if ( '' !== $error ) {
			wp_send_json_error( [ 'message' => $error ], 409 );
		}
		// Persist the cart before the browser starts WooCommerce's checkout refresh.
		if ( is_callable( [ WC()->session, 'save_data' ] ) ) {
			WC()->session->save_data();
		}
		$quantity = wizard_draft()['quantity'];
		wp_send_json_success( [ 'quantity' => $quantity, 'cart_identity' => $product_id . ':' . $quantity, 'redirect' => $url ] );
	}
	if ( '' !== $error ) {
		wc_add_notice( $error, 'error' );
		$url = add_query_arg( 'artnovo_prepare_failed', '1', $url );
	}
	wp_safe_redirect( $url, 303 );
	exit;
}

function prepare_wizard_cart( int $product_id, int $quantity, array $methods, string $requested_method ): true|\WP_Error {
	$cart         = WC()->cart;
	$items        = $cart->get_cart();
	$key          = array_key_first( $items );
	$item         = $items[ $key ] ?? [];
	$same_product = 1 === count( $items ) && $product_id === (int) ( $item['product_id'] ?? 0 );
	$changed      = ! $same_product || (float) $quantity !== (float) ( $item['quantity'] ?? 0 );
	$coupons      = $cart->get_applied_coupons();
	$prepared     = true;

	foreach ( $items as $previous ) {
		$product = $previous['data'] ?? null;
		if ( ! is_plan( $product ) || ! $product->is_virtual() ) {
			return new \WP_Error( 'artnovo_other_cart', 'این خرید آماده نشد. برای بررسی با پشتیبانی تماس بگیرید.' );
		}
		if ( $changed && class_exists( WalletPayment::class ) && WalletPayment::submitted( $previous['_wpvpn_wallet_key'] ?? null ) ) {
			return new \WP_Error( 'artnovo_pending_wallet', 'پرداخت قبلی هنوز در حال بررسی است. برای پیگیری با پشتیبانی تماس بگیرید.' );
		}
	}
	if ( $same_product ) {
		$prepared = ! $changed || ( apply_filters( 'woocommerce_update_cart_validation', true, $key, $item, $quantity )
			&& $cart->set_quantity( $key, $quantity ) );
	} else {
		// The VPN bridge requires an empty cart for add validation. Keep the old selection for rollback.
		$cart->set_cart_contents( [] );
		$prepared = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity )
			&& $cart->add_to_cart( $product_id, $quantity );
		if ( $prepared && $coupons ) {
			$discounts = new \WC_Discounts( $cart );
			$valid_coupons = array_values( array_filter( $coupons, static fn( string $code ): bool =>
				true === $discounts->is_coupon_valid( new \WC_Coupon( $code ) ) ) );
			if ( $valid_coupons !== $coupons ) {
				$cart->set_applied_coupons( $valid_coupons );
				$cart->calculate_totals();
			}
		}
	}

	$available = $prepared ? WC()->payment_gateways()->get_available_payment_gateways() : [];
	if ( ! array_intersect( $methods, array_keys( $available ) ) || ( '' !== $requested_method && ! isset( $available[ $requested_method ] ) ) || 'same' !== cart_selection( $product_id, $quantity ) ) {
		$cart->set_cart_contents( $items );
		$cart->set_applied_coupons( $coupons );
		$cart->calculate_totals();
		return new \WP_Error( 'artnovo_cart_unavailable', 'آماده‌سازی سفارش یا روش پرداخت انجام نشد. دوباره تلاش کنید.' );
	}
	if ( $changed ) {
		WC()->session->__unset( 'order_awaiting_payment' );
		WC()->session->__unset( 'store_api_draft_order' );
	}
	return true;
}

add_action( 'template_redirect', static function (): void {
	if ( ! wizard_page() || ! isset( $_POST['artnovo_wizard_nonce'] ) || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
		return;
	}
	$otp_action = checkout_post_value( 'artnovo_otp_action' );
	$ajax = '' !== $otp_action || '1' === checkout_post_value( 'artnovo_wizard_ajax' );
	if ( ! wp_verify_nonce( checkout_post_value( 'artnovo_wizard_nonce' ), WIZARD_DRAFT ) ) {
		if ( $ajax ) {
			wp_send_json_error( [ 'message' => 'نشست خرید منقضی شده است. صفحه را دوباره باز کنید.' ], 403 );
		}
		wp_die( 'نشست خرید منقضی شده است. صفحه را دوباره باز کنید.', '', [ 'response' => 403 ] );
	}
	if ( '' !== $otp_action && ( ! wizard_email_otp_enabled() || is_user_logged_in()
		|| ! wizard_otp_same_origin() || ! in_array( $otp_action, [ 'send', 'verify' ], true ) ) ) {
		wp_send_json_error( [ 'message' => 'درخواست ورود معتبر نیست. صفحه را دوباره باز کنید.' ], 403 );
	}
	WC()->session->set_customer_session_cookie( true );
	wc_nocache_headers();

	$product_id       = filter_var( checkout_post_value( 'artnovo_product' ), FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
	$quantity         = filter_var( checkout_post_value( 'artnovo_quantity' ), FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
	$requested_method = checkout_post_value( 'artnovo_payment' );
	$product          = $product_id ? wc_get_product( $product_id ) : false;
	if ( ! is_plan( $product ) || post_password_required( $product_id ) || ! $product->is_in_stock() || ! $product->is_purchasable() ) {
		wizard_reply( $product_id ?: 0, $ajax, 'این پلن در حال حاضر قابل خرید نیست.' );
	}
	$methods = $quantity ? payment_methods( $product, $quantity ) : [];
	if ( ! $quantity || $quantity > quantity_limit( $product ) || ! $methods || ( '' !== $requested_method && ! in_array( $requested_method, $methods, true ) ) ) {
		wizard_reply( $product_id, $ajax, 'تعداد یا روش پرداخت انتخاب‌شده در دسترس نیست.' );
	}

	$previous = wizard_draft();
	if ( $ajax && '' === $otp_action ) {
		$previous_quantity = $previous['quantity'] ?? 0;
		$marker            = checkout_post_value( 'artnovo_wizard_cart' );
		if ( ! is_user_logged_in() || $product_id !== ( $previous['product'] ?? null )
			|| 'same' !== cart_selection( $product_id, $previous_quantity )
			|| ( $marker !== $product_id . ':' . $previous_quantity && $quantity !== $previous_quantity ) ) {
			wizard_reply( $product_id, true, 'انتخاب سفارش تغییر کرده است. صفحه را دوباره باز کنید.' );
		}
	}
	$email  = isset( $_POST['artnovo_email'] ) ? checkout_post_value( 'artnovo_email' ) : ( $previous['billing_email'] ?? '' );
	$coupon = isset( $_POST['artnovo_coupon'] ) ? sanitize_text_field( checkout_post_value( 'artnovo_coupon' ) ) : ( $previous['coupon'] ?? '' );
	if ( strlen( $email ) > 254 || strlen( $coupon ) > 200 || ( '' !== $email && ! is_email( $email ) ) ) {
		wizard_reply( $product_id, $ajax, 'ایمیل یا کد تخفیف را بررسی کنید.' );
	}
	$draft = [
		'product' => $product_id, 'quantity' => $quantity, 'billing_email' => sanitize_email( $email ),
		'coupon' => $coupon, 'expires' => time() + 30 * MINUTE_IN_SECONDS,
	];
	if ( '' !== $otp_action ) {
		wizard_email_otp_request( $otp_action, $draft );
	}
	if ( ! is_user_logged_in() ) {
		if ( wizard_email_otp_enabled() ) {
			wizard_reply( $product_id, $ajax, 'برای ادامه، کد تأیید ایمیل را از همین مرحله دریافت کنید.' );
		}
		WC()->session->set( WIZARD_DRAFT, $draft );
		wp_safe_redirect( add_query_arg( 'artnovo_resume', '1', wc_get_page_permalink( 'myaccount' ) ), 303 );
		exit;
	}

	$prepared = prepare_wizard_cart( $product_id, $quantity, $methods, $requested_method );
	if ( is_wp_error( $prepared ) ) {
		wizard_reply( $product_id, $ajax, $prepared->get_error_message() );
	}
	unset( $draft['coupon'] );
	WC()->session->set( WIZARD_DRAFT, $draft );
	if ( '' !== $requested_method ) {
		WC()->session->set( 'chosen_payment_method', $requested_method );
	}
	if ( '' !== $coupon && wc_coupons_enabled() && ! WC()->cart->has_discount( $coupon ) ) {
		WC()->cart->apply_coupon( $coupon );
	}
	wizard_reply( $product_id, $ajax );
}, 7 );

function wizard_login_return( string $redirect ): string {
	$draft = '1' === ( $_GET['artnovo_resume'] ?? null ) ? wizard_draft() : [];
	return isset( $draft['product'] ) ? wizard_url( $draft['product'] ) : $redirect;
}
add_filter( 'woocommerce_login_redirect', __NAMESPACE__ . '\\wizard_login_return' );
add_filter( 'woocommerce_registration_redirect', __NAMESPACE__ . '\\wizard_login_return' );

add_action( 'woocommerce_cart_emptied', static function (): void {
	WC()->session?->__unset( WIZARD_DRAFT );
} );

add_filter( 'woocommerce_checkout_get_value', static function ( $value, string $field ) {
	if ( ! wizard_checkout_active() ) {
		return $value;
	}
	$draft = wizard_draft();
	return '' !== ( $draft[ $field ] ?? '' ) ? $draft[ $field ] : $value;
}, 10, 2 );

add_action( 'woocommerce_checkout_after_order_review', static function (): void {
	if ( wizard_checkout_active() ) {
		$draft = wizard_draft();
		printf( '<input type="hidden" name="artnovo_wizard_cart" value="%s">', esc_attr( $draft['product'] . ':' . $draft['quantity'] ) );
	}
} );

add_action( 'woocommerce_after_checkout_validation', static function ( array $data, \WP_Error $errors ): void {
	if ( cart_has_plan() && (float) WC()->cart->get_total( 'edit' ) <= 0 ) {
		$errors->add( 'artnovo_zero_total', 'مبلغ قابل پرداخت اشتراک باید بیشتر از صفر باشد. کد تخفیف را بررسی کنید.' );
	}
	if ( ! isset( $_POST['artnovo_wizard_cart'] ) ) {
		return;
	}
	$draft = wizard_draft();
	$product = isset( $draft['product'] ) ? wc_get_product( $draft['product'] ) : false;
	if ( ! $product instanceof \WC_Product || ! isset( $draft['quantity'] )
		|| checkout_post_value( 'artnovo_wizard_cart' ) !== $draft['product'] . ':' . $draft['quantity']
		|| 'same' !== cart_selection( $draft['product'], $draft['quantity'] )
		|| ! in_array( $data['payment_method'] ?? '', payment_methods( $product, $draft['quantity'] ), true ) ) {
		$errors->add( 'artnovo_wizard_cart', 'انتخاب سفارش تغییر کرده است. از صفحهٔ خرید دوباره بررسی کنید.' );
	}
}, 10, 2 );

add_action( 'wp_loaded', static function (): void {
	$id = $_POST['add-to-cart'] ?? $_GET['add-to-cart'] ?? null;
	if ( is_admin() || wp_doing_ajax() || isset( $_GET['wc-ajax'] ) || ! is_string( $id ) || ! ctype_digit( $id ) || ! function_exists( 'WC' ) ) {
		return;
	}
	$product = wc_get_product( (int) $id );
	if ( ! is_plan( $product ) || 'publish' !== $product->get_status() ) {
		return;
	}
	wc_nocache_headers();
	wp_safe_redirect( wizard_url( $product->get_id() ), 303 );
	exit;
}, 19 );

add_action( 'template_redirect', static function (): void {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}
	if ( ( is_preview() || isset( $_GET['elementor-preview'] ) ) && current_user_can( 'edit_post', get_queried_object_id() ) ) {
		return;
	}
	$read = in_array( $_SERVER['REQUEST_METHOD'] ?? '', [ 'GET', 'HEAD' ], true );
	$product = selected_plan();
	$nonce = $_POST['artnovo_plan_nonce'] ?? null;
	if ( $product && ( $read || ( is_user_logged_in() && is_string( $nonce )
		&& wp_verify_nonce( wp_unslash( $nonce ), 'artnovo_plan_' . $product->get_id() ) ) ) ) {
		wc_nocache_headers();
		wp_safe_redirect( wizard_url( $product->get_id() ), $read ? 302 : 303 );
		exit;
	}

	$cart_page = is_page( wc_get_page_id( 'cart' ) );
	$checkout_page = is_page( wc_get_page_id( 'checkout' ) );
	if ( ! $cart_page && ( ! $checkout_page || ! $read || is_wc_endpoint_url() ) ) {
		return;
	}

	$url = home_url( '/#artnovo-categories' );
	$draft = wizard_draft();
	$product = isset( $draft['product'] ) ? wc_get_product( $draft['product'] ) : null;
	if ( is_plan( $product ) && $product->is_virtual() && $product->is_in_stock() && $product->is_purchasable() ) {
		$id = $product->get_id();
		$prepared = is_user_logged_in() && 'same' === cart_selection( $id, $draft['quantity'] ?? 1 );
		$url = wizard_url( $id, $prepared );
	}
	wc_nocache_headers();
	wp_safe_redirect( $url, $read ? 302 : 303 );
	exit;
}, 9 );

add_filter( 'wc_get_template', static function ( string $path, string $name ): string {
	return 'checkout/cart-errors.php' === $name && wizard_page() ? __DIR__ . '/artnovo-checkout-errors.php' : $path;
}, 10, 2 );

function button_link( string $url, string $label ): string {
	return sprintf( '<a class="artnovo-plan-button" href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
}

add_filter( 'elementor/widget/render_content', static function ( string $content, $widget ): string {
	if ( 'woocommerce-product-add-to-cart' !== $widget->get_name() || '7b2f25c0' !== $widget->get_id() || ! is_singular( 'product' ) ) {
		return $content;
	}
	$product = wc_get_product( get_queried_object_id() );
	return is_plan( $product ) ? '<div class="artnovo-plan-flow">' . button_link( wizard_url( $product->get_id() ), 'ادامهٔ خرید' ) . '</div>' : $content;
}, 10, 2 );

add_action( 'elementor/dynamic_tags/register', static function ( $manager ): void {
	$manager->register( new class extends \Elementor\Core\DynamicTags\Data_Tag {
		public function get_name() { return 'artnovo-plan-checkout'; }
		public function get_title() { return 'ادامهٔ خرید پلن آرت‌نوو'; }
		public function get_group() { return 'post'; }
		public function get_categories() { return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ); }
		public function get_value( array $options = array() ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
			return is_plan( $product ) ? wizard_url( $product->get_id() ) : '';
		}
	} );
} );

add_filter( 'woocommerce_loop_add_to_cart_link', static function ( string $html, \WC_Product $product ): string {
	if ( ! is_plan( $product ) || ! $product->is_in_stock() ) {
		return $html;
	}
	return sprintf(
		'<a class="button" href="%s" aria-label="%s">انتخاب اشتراک</a>',
		esc_url( wizard_url( $product->get_id() ) ),
		esc_attr( sprintf( 'انتخاب %s', $product->get_name() ) )
	);
}, 10, 2 );

add_filter( 'render_block_woocommerce/checkout', static function ( string $content ): string {
	if ( ! cart_has_plan() ) {
		return $content;
	}
	return '<section class="artnovo-plan-flow artnovo-plan-panel" aria-label="تسویه‌حساب پلن"><p class="artnovo-plan-step">مرحلهٔ ۳ از ۳ · اطلاعات و پرداخت</p><h2>تکمیل خرید پلن</h2><p>خلاصهٔ سفارش و مبلغ نهایی را قبل از پرداخت بررسی کن.</p></section>' . $content;
} );

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( ! function_exists( 'WC' ) || ( ! ( is_singular( 'product' ) && is_plan( wc_get_product( get_queried_object_id() ) ) ) && ! ( is_checkout() && cart_has_plan() ) ) ) {
		return;
	}
	wp_register_style( 'artnovo-plan-checkout', false );
	wp_enqueue_style( 'artnovo-plan-checkout' );
	wp_add_inline_style( 'artnovo-plan-checkout', '.artnovo-plan-panel{background:#152735;color:#BDD1D0;border-radius:16px;box-shadow:0 8px 28px #0003;padding:clamp(16px,4vw,28px);margin-block-end:24px}.artnovo-plan-panel h2{color:#F0F9F7;margin-block:4px 8px}.artnovo-plan-step{color:#9ADAF0;font-size:.9rem}.artnovo-plan-price{font-weight:700;color:#F0F9F7}.artnovo-plan-flow .artnovo-plan-button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 18px;border:0;border-radius:10px;background:#46D7A6;color:#06261B;font-weight:700;text-decoration:none}.artnovo-plan-flow .artnovo-plan-button:hover{background:#9ADAF0;color:#06261B}.artnovo-plan-flow .artnovo-plan-button:focus-visible,.artnovo-plan-flow .artnovo-plan-back:focus-visible{outline:2px solid #9ADAF0;outline-offset:4px}.artnovo-plan-back{display:inline-block;margin-block-start:12px;color:#9ADAF0}.artnovo-plan-button + .artnovo-plan-back{margin-inline-start:12px}.artnovo-plan-panel+.wp-block-woocommerce-checkout{color:#F0F9F7}.artnovo-plan-panel+.wp-block-woocommerce-checkout .wc-block-components-checkout-place-order-button{min-height:44px;background:#46D7A6;color:#06261B}' );
} );
