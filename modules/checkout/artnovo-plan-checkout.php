<?php

namespace Artnovo\PlanCheckout;

use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;
use Automattic\WooCommerce\StoreApi\SchemaController;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use Automattic\WooCommerce\StoreApi\SessionHandler as StoreSession;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Utilities\CartTokenUtils;
use WPVPNBridge\Commerce\Orders;
use WPVPNBridge\Payment\WalletPayment;
use WPVPNBridge\Settings;

defined( 'ABSPATH' ) || exit;

const WIZARD_DRAFT = 'artnovo_wizard_checkout';
const PURCHASE_META = '_artnovo_purchase_intent';

require_once dirname( __DIR__ ) . '/auth/artnovo-checkout-email.php';

function store_order_payment_id( \WP_REST_Request $request ): int {
	return 'POST' === $request->get_method()
		&& 1 === preg_match( '~\A/wc/store(?:/v[0-9]+)?/checkout/([0-9]+)\z~i', $request->get_route(), $matches )
		? (int) $matches[1] : 0;
}

add_action( 'woocommerce_before_pay_action', static function ( \WC_Order $order ): void {
	if ( ! \Artnovo\Core\bridge_available() && \Artnovo\Core\is_service_order( $order ) ) {
		wp_die( 'پرداخت سرویس موقتاً در دسترس نیست. لطفاً کمی بعد دوباره تلاش کنید.', '', [ 'response' => 503 ] );
	}
} );

// REST route permissions run before dispatch; stop before the callback can update or pay a retained service order.
add_filter( 'rest_dispatch_request', static function ( $result, \WP_REST_Request $request ) {
	if ( null !== $result || \Artnovo\Core\bridge_available() ) {
		return $result;
	}
	$order_id = store_order_payment_id( $request );
	$order = $order_id ? wc_get_order( $order_id ) : false;
	if ( $order instanceof \WC_Order && \Artnovo\Core\is_service_order( $order ) ) {
		return new \WP_Error( 'artnovo_bridge_unavailable', 'پرداخت سرویس موقتاً در دسترس نیست. لطفاً کمی بعد دوباره تلاش کنید.', [ 'status' => 503 ] );
	}
	return $result;
}, 10, 2 );

add_action( 'elementor/dynamic_tags/register', static function ( $manager ): void {
	$manager->register( new class extends \Elementor\Core\DynamicTags\Data_Tag {
		public function get_name() { return 'artnovo-plan-checkout'; }
		public function get_title() { return 'ادامهٔ خرید پلن آرت‌نوو'; }
		public function get_group() { return 'post'; }
		public function get_categories() { return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY ); }
		public function get_value( array $options = array() ) {
			if ( ! \Artnovo\Core\bridge_available() ) {
				return wc_get_page_permalink( 'myaccount' );
			}
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( get_the_ID() ) : null;
			return is_plan( $product ) ? wizard_url( $product->get_id() ) : '';
		}
	} );
} );

// Keep shared auth/helpers and retained-order guards, leaving ordinary WooCommerce journeys intact.
if ( ! \Artnovo\Core\bridge_available() ) {
	return;
}

add_filter( 'woocommerce_persistent_cart_enabled', static function ( bool $enabled ): bool {
	return wizard_cart_context() ? false : $enabled;
} );
add_filter( 'wc_add_to_cart_message_html', static function ( string $message ): string {
	return cart_has_plan() ? '' : $message;
} );
add_action( 'pinova/user_logged_in', static function (): void {
	// Pinova migrates WooCommerce's guest session at priority 20.
	$result = wizard_preserve_login_selection();
	if ( is_wp_error( $result ) ) {
		wp_die( $result->get_error_message(), '', [ 'response' => 409 ] );
	}
}, 5 );

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

function filter_payment_methods( array $gateways, \WC_Product $product, int $quantity, bool $preselection = false ): array {
	foreach ( $gateways as $method => $gateway ) {
		if ( $quantity < 1 || $quantity > method_quantity_limit( $product, $method )
			|| ( 'bacs' === $method && ! transfer_ready() )
			|| ( 'wpvpn_wallet' === $method && ! ( $preselection && ! is_user_logged_in() ? wallet_configured() : wallet_ready() ) ) ) {
			unset( $gateways[ $method ] );
		}
	}
	return $gateways;
}

function payment_candidates( \WC_Product $product, int $quantity ): array {
	if ( ! \Artnovo\Core\bridge_available() || $quantity < 1 || ! $product->is_in_stock() || ! $product->is_purchasable() || ! function_exists( 'WC' ) || ! WC()->session ) {
		return [];
	}
	// A gateway can require a prepared cart before is_available() becomes true.
	// These candidates permit preparation only; native availability authorizes the resulting cart.
	// Guest wallet candidates open sign-in; customer authorization still belongs to actual availability.
	$gateways = array_filter( WC()->payment_gateways()->payment_gateways(), static fn( $gateway ): bool => 'yes' === $gateway->enabled );
	return array_keys( filter_payment_methods( $gateways, $product, $quantity, true ) );
}

function payment_methods( \WC_Product $product, int $quantity ): array {
	if ( ! \Artnovo\Core\bridge_available() || $quantity < 1 || ! $product->is_in_stock() || ! $product->is_purchasable() || ! function_exists( 'WC' ) || ! WC()->session ) {
		return [];
	}
	return array_keys( filter_payment_methods( WC()->payment_gateways()->get_available_payment_gateways(), $product, $quantity ) );
}

function plan_configured( \WC_Product $product ): bool {
	return 'publish' === $product->get_status() && $product->is_type( 'simple' ) && $product->is_virtual()
		&& 'yes' === $product->get_meta( '_wpvpn_enabled', true )
		&& class_exists( Orders::class ) && ! is_wp_error( Orders::product_terms( $product ) )
		&& class_exists( Settings::class ) && ( new Settings() )->ready();
}

function plan_purchasable( bool $purchasable, \WC_Product $product ): bool {
	// Native gateway availability may ask whether a product is purchasable.
	return $purchasable && ( ! is_plan( $product ) || plan_configured( $product ) );
}
add_filter( 'woocommerce_is_purchasable', __NAMESPACE__ . '\\plan_purchasable', PHP_INT_MAX, 2 );

function method_quantity_limit( \WC_Product $product, ?string $method = null ): int {
	if ( ! \Artnovo\Core\bridge_available() ) {
		return 0;
	}
	$bridge_limit = 1;
	if ( is_callable( [ Orders::class, 'max_purchase_quantity' ] ) ) {
		$bridge_limit = Orders::max_purchase_quantity( $product, $method );
	}
	$stock_limit = (int) $product->get_max_purchase_quantity();
	return -1 === $stock_limit ? $bridge_limit : min( $bridge_limit, max( 0, $stock_limit ) );
}

function quantity_limit( \WC_Product $product, ?string $method = null ): int {
	if ( null !== $method ) {
		return method_quantity_limit( $product, $method );
	}
	$methods = payment_candidates( $product, 1 );
	return $methods ? max( array_map( static fn( string $id ): int => method_quantity_limit( $product, $id ), $methods ) ) : 0;
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

function wizard_cart_context(): bool {
	$items = cart_items();
	if ( $items ) {
		return cart_has_plan();
	}
	$stored = function_exists( 'WC' ) ? WC()->session?->get( 'cart', [] ) : [];
	if ( is_array( $stored ) && $stored ) {
		foreach ( $stored as $item ) {
			if ( is_array( $item ) && is_plan( wc_get_product( (int) ( $item['product_id'] ?? 0 ) ) ) ) {
				return true;
			}
		}
		return false;
	}
	return [] !== wizard_draft();
}

function cart_selection( int $id, int $quantity = 1 ): string {
	$items = cart_items();
	if ( empty( $items ) ) {
		return 'empty';
	}
	$item = reset( $items );
	return 1 === count( $items ) && $id === (int) ( $item['product_id'] ?? 0 ) && (float) $quantity === (float) ( $item['quantity'] ?? 0 ) ? 'same' : 'different';
}

// Scope the cart-only guard to each REST request, including nested requests and batch siblings.
$artnovo_order_payment_requests = [];
add_filter( 'rest_request_before_callbacks', static function ( $response, $handler, \WP_REST_Request $request ) use ( &$artnovo_order_payment_requests ) {
	$artnovo_order_payment_requests[] = store_order_payment_id( $request ) > 0;
	return $response;
}, 10, 3 );
add_filter( 'rest_request_after_callbacks', static function ( $response ) use ( &$artnovo_order_payment_requests ) {
	array_pop( $artnovo_order_payment_requests );
	return $response;
}, PHP_INT_MAX );

add_filter( 'woocommerce_available_payment_gateways', static function ( array $gateways ) use ( &$artnovo_order_payment_requests ): array {
	if ( is_wc_endpoint_url( 'order-pay' ) || true === end( $artnovo_order_payment_requests ) ) {
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
		$quantity = $item['quantity'] ?? 0;
		if ( ! is_numeric( $quantity ) || (float) (int) $quantity !== (float) $quantity ) {
			return [];
		}
		return filter_payment_methods( $gateways, $product, (int) $quantity );
	}
	return $gateways;
}, PHP_INT_MAX );

function wizard_page(): bool {
	return \Artnovo\Core\bridge_available() && function_exists( '\\Artnovo\\HomeOrderUI\\Wizard\\is_page' ) && \Artnovo\HomeOrderUI\Wizard\is_page();
}

add_action( 'template_redirect', static function (): void {
	if ( ! wizard_page() || is_user_logged_in() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', [ 'GET', 'HEAD' ], true )
		|| ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	wc_nocache_headers();
	// The first form needs a stable guest identity before Elementor/theme output.
	// Retain it on a return visit even before a purchase draft or cart exists.
	remove_action( 'template_redirect', [ WC()->session, 'destroy_session_if_empty' ], 999 );
	WC()->session->set_customer_session_cookie( true );
}, 1 );

function wizard_url( int $product_id, bool $checkout = false ): string {
	$query = [ 'artnovo_plan' => $product_id ];
	if ( $checkout ) {
		$query['artnovo_checkout'] = '1';
	}
	return add_query_arg( $query, home_url( '/' ) ) . '#artnovo-order';
}

function wizard_draft(): array {
	$draft = function_exists( 'WC' ) ? WC()->session?->get( WIZARD_DRAFT ) : null;
	return is_array( $draft ) && ( ( $draft['expires'] ?? 0 ) >= time()
		|| ( ! empty( $draft['purchase_claimed'] ) && '' !== wizard_purchase_intent( $draft ) ) ) ? $draft : [];
}

function wizard_checkout_active(): bool {
	if ( ! wizard_page() || ! is_user_logged_in() ) {
		return false;
	}
	$draft = wizard_draft();
	if ( ! is_int( $draft['product'] ?? null ) || $draft['product'] < 1
		|| ! is_int( $draft['quantity'] ?? null ) || $draft['quantity'] < 1
		|| ! is_int( $draft['expires'] ?? null )
		|| ! empty( $draft['purchase_pending_login'] )
		|| (string) $draft['product'] !== ( $_GET['artnovo_plan'] ?? null )
		|| 'same' !== cart_selection( $draft['product'], $draft['quantity'] ) ) {
		return false;
	}
	$product = wc_get_product( $draft['product'] );
	return is_plan( $product ) && ! post_password_required( $draft['product'] ) && plan_configured( $product )
		&& $product->is_in_stock() && $product->is_purchasable()
		&& $draft['quantity'] <= method_quantity_limit( $product );
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
	if ( ! wizard_email_checkout() ) {
		return;
	}
	// Account authentication is complete before this contact-free checkout.
	foreach ( [ 'woocommerce_checkout_process', 'woocommerce_after_checkout_validation' ] as $hook_name ) {
		foreach ( $GLOBALS['wp_filter'][ $hook_name ]->callbacks[10] ?? [] as $hook ) {
			$callback = $hook['function'];
			if ( is_array( $callback ) && $callback[0] instanceof \Pinova\Integrations\Woocommerce\Checkout
				&& 'validate_checkout_phone' === $callback[1] ) {
				remove_action( $hook_name, $callback );
			}
		}
	}
}, 9 );

add_filter( 'woocommerce_checkout_fields', static function ( array $fields ): array {
	if ( ! wizard_email_checkout() ) {
		return $fields;
	}
	return [ 'billing' => [], 'shipping' => [], 'account' => [], 'order' => [] ];
}, PHP_INT_MAX );

add_filter( 'woocommerce_checkout_posted_data', static function ( array $data ): array {
	if ( wizard_email_checkout() ) {
		// Keep optional order mail with the account owner, never a hidden or stale input.
		$email = wp_get_current_user()->user_email;
		$data['billing_email'] = is_email( $email ) ? sanitize_email( $email ) : '';
	}
	return $data;
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
	if ( ! is_string( $posted ) || ! wp_verify_nonce( checkout_post_value( 'security' ), 'update-order-review' ) ) {
		return;
	}
	parse_str( $posted, $fields );
	if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart
		|| ( ! isset( $fields['artnovo_wizard_cart'] ) && ! isset( $fields['artnovo_purchase_intent'] ) && ! cart_has_plan() ) ) {
		return;
	}
	$result = wizard_selection_locked( static function () use ( $fields ) {
		if ( ! wizard_refresh_selection_session() ) {
			return false;
		}
		if ( ! isset( $fields['artnovo_wizard_cart'] ) && ! isset( $fields['artnovo_purchase_intent'] ) && ! wizard_session_has_purchase() ) {
			return true;
		}
		return isset( $fields['artnovo_wizard_cart'] ) && ( ! wizard_session_has_purchase() || '' !== wizard_purchase_intent( wizard_draft() ) )
			&& wizard_matches_purchase_request( wizard_draft(), $fields['artnovo_purchase_intent'] ?? '', $fields['artnovo_selection_revision'] ?? null );
	}, true );
	if ( true !== $result ) {
		// Stop before native review code can apply stale fields or save its old cart.
		wp_send_json( [ 'result' => 'failure', 'reload' => true ] );
	}
}, 1 );

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

function wizard_async_selection(): bool {
	return '1' === checkout_post_value( 'artnovo_wizard_ajax' )
		&& '1' === checkout_post_value( 'artnovo_wizard_select' )
		&& '' === checkout_post_value( 'artnovo_otp_action' );
}

/** Print the registered native dependency queue after the validated cart exists. */
function wizard_checkout_fragment(): array {
	if ( ! is_user_logged_in() ) {
		return [ 'checkout_html' => '', 'checkout_assets' => '' ];
	}
	if ( ! wizard_checkout_active() || ! function_exists( '\Artnovo\HomeOrderUI\Wizard\render_native_checkout' ) ) {
		throw new \RuntimeException( 'Checkout context is unavailable.' );
	}
	if ( ! did_action( 'wp_enqueue_scripts' ) ) {
		do_action( 'wp_enqueue_scripts' );
	}
	$html = \Artnovo\HomeOrderUI\Wizard\render_native_checkout();
	ob_start();
	try {
		wp_print_styles();
		wp_print_head_scripts();
		wp_print_footer_scripts();
		$assets = (string) ob_get_contents();
	} finally {
		ob_end_clean();
	}
	return [ 'checkout_html' => $html, 'checkout_assets' => $assets ];
}

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
		$data = [ 'product' => $product_id, 'quantity' => $quantity, 'cart_identity' => $product_id . ':' . $quantity, 'redirect' => $url, 'selection_revision' => wizard_selection_revision() ];
		if ( wizard_async_selection() ) {
			try {
				$data += wizard_checkout_fragment();
			} catch ( \Throwable $exception ) {
				// The draft may already be saved. Recover via GET; never replay an ambiguous selection.
				wp_send_json_error( [ 'refresh_required' => true, 'redirect' => $url, 'message' => 'فرم پرداخت آماده نشد. انتخاب شما حفظ شده است؛ صفحه را تازه کنید.' ], 503 );
			}
			$data['wizard_nonce'] = wp_create_nonce( WIZARD_DRAFT );
			$data['rest_nonce'] = wp_create_nonce( 'wp_rest' );
		}
		wp_send_json_success( $data );
	}
	if ( '' !== $error ) {
		$url = add_query_arg( 'artnovo_prepare_failed', '1', $url );
	}
	wp_safe_redirect( $url, 303 );
	exit;
}

function prepare_wizard_cart( int $product_id, int $quantity, array $methods, string $requested_method, bool $keep_order_pointers = false ): true|\WP_Error {
	$cart         = WC()->cart;
	$items        = $cart->get_cart();
	$key          = array_key_first( $items );
	$item         = null !== $key ? ( $items[ $key ] ?? [] ) : [];
	$same_product = 1 === count( $items ) && $product_id === (int) ( $item['product_id'] ?? 0 );
	$changed      = ! $same_product || (float) $quantity !== (float) ( $item['quantity'] ?? 0 );
	$coupons      = $cart->get_applied_coupons();
	$prepared     = true;

	foreach ( $items as $previous ) {
		$product = $previous['data'] ?? null;
		if ( ! is_plan( $product ) || ! $product->is_virtual() ) {
			return new \WP_Error( 'artnovo_other_cart', 'این خرید آماده نشد. برای بررسی با پشتیبانی تماس بگیرید.' );
		}
		if ( ( $changed || $keep_order_pointers ) && class_exists( WalletPayment::class ) && WalletPayment::submitted( $previous['_wpvpn_wallet_key'] ?? null ) ) {
			return new \WP_Error( 'artnovo_pending_wallet', 'پرداخت قبلی هنوز در حال بررسی است. برای پیگیری با پشتیبانی تماس بگیرید.' );
		}
	}
	try {
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
	} catch ( \Throwable ) {
		$available = [];
	}

	if ( ! array_intersect( $methods, array_keys( $available ) ) || ( '' !== $requested_method && ! isset( $available[ $requested_method ] ) ) || 'same' !== cart_selection( $product_id, $quantity ) ) {
		$cart->set_cart_contents( $items );
		$cart->set_applied_coupons( $coupons );
		$cart->calculate_totals();
		return new \WP_Error( 'artnovo_cart_unavailable', 'آماده‌سازی سفارش یا روش پرداخت انجام نشد. دوباره تلاش کنید.' );
	}
	if ( $changed && ! $keep_order_pointers ) {
		WC()->session->__unset( 'order_awaiting_payment' );
		WC()->session->__unset( 'store_api_draft_order' );
	}
	return true;
}

function wizard_selection_revision(): string {
	$draft = wizard_draft();
	$revision = $draft['revision'] ?? '';
	return is_string( $revision ) && 1 === preg_match( '/\A[a-f0-9]{64}\z/', $revision ) ? $revision
		: hash_hmac( 'sha256', 'selection:' . WC()->session->get_customer_id() . ':' . ( $draft['product'] ?? 0 ) . ':' . ( $draft['quantity'] ?? 0 ), wp_salt( 'nonce' ) );
}

function wizard_current_selection_url(): string {
	$draft = wizard_draft();
	return is_int( $draft['product'] ?? null ) && $draft['product'] > 0 ? wizard_url( $draft['product'] ) : home_url( '/#artnovo-categories' );
}

function wizard_recovery_url(): string {
	$draft = wizard_draft();
	if ( WC()->session instanceof \WC_Session_Handler ) {
		// Read the latest stored choice without hydrating or saving a rejected POST's cart.
		wp_cache_delete( \WC_Cache_Helper::get_cache_prefix( WC_SESSION_CACHE_GROUP ) . WC()->session->get_customer_id(), WC_SESSION_CACHE_GROUP );
		$stored = WC()->session->get_session_data();
		$draft = maybe_unserialize( $stored[ WIZARD_DRAFT ] ?? null );
	}
	$current = is_array( $draft ) && ( $draft['expires'] ?? 0 ) >= time()
		&& is_int( $draft['product'] ?? null ) && is_int( $draft['quantity'] ?? null ) && $draft['quantity'] > 0 ? $draft['product'] : 0;
	$posted = filter_var( checkout_post_value( 'artnovo_product' ), FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
	foreach ( array_unique( [ $current, $posted ] ) as $id ) {
		$product = $id ? wc_get_product( $id ) : false;
		if ( is_plan( $product ) && 'publish' === $product->get_status() && $product->is_virtual()
			&& ! post_password_required( $id ) && $product->is_in_stock() && $product->is_purchasable()
			&& ( $id !== $current || $draft['quantity'] <= quantity_limit( $product ) ) ) {
			return wizard_url( $id );
		}
	}
	return home_url( '/#artnovo-categories' );
}

function wizard_refresh_selection_session(): bool {
	$session = WC()->session;
	if ( ! ( $session instanceof \WC_Session_Handler || $session instanceof StoreSession ) || ! class_exists( '\WC_Cart_Session' ) ) {
		return false;
	}
	// A request may have loaded its cart before another tab finished its selection.
	wp_cache_delete( \WC_Cache_Helper::get_cache_prefix( WC_SESSION_CACHE_GROUP ) . $session->get_customer_id(), WC_SESSION_CACHE_GROUP );
	$fresh = $session->get_session_data();
	$keys = array_unique( array_merge( array_keys( $fresh ), [ WIZARD_DRAFT, 'cart', 'cart_totals', 'applied_coupons',
		'coupon_discount_totals', 'coupon_discount_tax_totals', 'removed_cart_contents', 'order_awaiting_payment', 'store_api_draft_order' ] ) );
	foreach ( $keys as $key ) {
		$session->set( $key, isset( $fresh[ $key ] ) ? maybe_unserialize( $fresh[ $key ] ) : null );
	}
	( new \WC_Cart_Session( WC()->cart ) )->get_cart_from_session();
	return true;
}

function wizard_selection_locked( callable $callback, bool $until_shutdown = false ): mixed {
	$result = wizard_otp_locked( wizard_otp_key( 'purchase-selection', WC()->session->get_customer_id() ), $callback, $until_shutdown );
	if ( is_wp_error( $result ) && 'artnovo_otp_busy' === $result->get_error_code() ) {
		// A denied request must never persist the snapshot it loaded before the winner.
		remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
	}
	return $result;
}

function wizard_owns_bank_purchase( \WC_Order $order ): bool {
	return is_user_logged_in() && get_current_user_id() === $order->get_customer_id()
		&& 'artnovo_gpante' === $order->get_payment_method( 'edit' )
		&& \Artnovo\Core\is_service_order( $order );
}

function wizard_new_purchase_order( int $id ): ?\WC_Order {
	$order = $id > 0 && is_user_logged_in() ? Orders::fresh_order( $id ) : false;
	return $order instanceof \WC_Order && wizard_owns_bank_purchase( $order ) ? $order : null;
}

function wizard_purchase_intent( array $draft ): string {
	$id = $draft['purchase_intent'] ?? '';
	return is_string( $id ) && preg_match( '/\A[a-f0-9]{64}\z/D', $id ) ? $id : '';
}

function wizard_session_has_purchase(): bool {
	// A submitted online order can outlive its cart; scope ordinary checkout by the current cart separately.
	return '' !== wizard_purchase_intent( (array) WC()->session->get( WIZARD_DRAFT ) );
}

function wizard_purchase_action(): string {
	$action = checkout_post_value( 'artnovo_purchase_action' );
	return preg_match( '/\A[a-f0-9]{32}\z/D', $action ) ? $action : '';
}

function wizard_matches_purchase_request( array $draft, mixed $intent, mixed $revision ): bool {
	return is_string( $intent ) && hash_equals( wizard_purchase_intent( $draft ), $intent )
		&& is_string( $revision ) && hash_equals( wizard_selection_revision(), $revision );
}

/** Read under the selection lock. A settled order can be detached just like a pending one. */
function wizard_previous_online_purchase( array $draft ): int|\WP_Error {
	$pointed = array_filter( [ (int) WC()->session->get( 'order_awaiting_payment' ), (int) WC()->session->get( 'store_api_draft_order' ) ] );
	$orders = array_unique( array_filter( [ (int) ( $draft['fresh_order'] ?? 0 ), ...$pointed ] ) );
	foreach ( $orders as $id ) {
		$order = wizard_new_purchase_order( $id );
		if ( ! $order || $order->has_status( 'checkout-draft' ) ) {
			continue;
		}
		if ( array_diff( $pointed, [ $id ] ) || ( isset( $draft['fresh_order'] ) && (int) $draft['fresh_order'] !== $id ) ) {
			return new \WP_Error( 'artnovo_new_purchase_active', 'خرید دیگری در این نشست باز است. به فرم فعلی خرید برگردید.' );
		}
		return $id;
	}
	return 0;
}

// Keep previously rendered forms valid during an upgrade, with their original nonce and revision guards.
function wizard_begin_new_purchase( \WC_Order $order, string $revision ): string|\WP_Error {
	$snapshot = $order->get_meta( '_wpvpn_snapshot', true );
	$id = is_array( $snapshot ) ? (int) ( $snapshot['product_id'] ?? 0 ) : 0;
	$quantity = $id ? Orders::snapshot_quantity( $snapshot ) : 0;
	$product = $id ? wc_get_product( $id ) : false;
	if ( ! is_plan( $product ) || ! $product->is_purchasable() || ! $product->is_in_stock()
		|| post_password_required( $id ) || $quantity < 1 || $quantity > quantity_limit( $product ) ) {
		return new \WP_Error( 'artnovo_new_purchase_plan', 'این انتخاب در دسترس نیست. پلن خود را دوباره انتخاب کنید.' );
	}
	return wizard_selection_locked( static function () use ( $order, $revision, $id, $quantity, $product ) {
		if ( ! wizard_refresh_selection_session() ) {
			return new \WP_Error( 'artnovo_selection_session', 'صفحه را تازه کنید و دوباره تلاش کنید.' );
		}
		$previous = $order->get_id();
		$draft = wizard_draft();
		// A repeated form returns to the same fresh selection. An older tab cannot replace it.
		if ( $previous === ( $draft['fresh_from'] ?? 0 ) && hash_equals( (string) ( $draft['fresh_source_revision'] ?? '' ), $revision ) ) {
			return wizard_url( $id, true );
		}
		if ( ! hash_equals( wizard_selection_revision(), $revision ) ) {
			return new \WP_Error( 'artnovo_selection_changed', 'انتخاب خرید تغییر کرده است. صفحه را تازه کنید.' );
		}
		foreach ( [ 'order_awaiting_payment', 'store_api_draft_order' ] as $pointer ) {
			$pointed = (int) WC()->session->get( $pointer );
			if ( $pointed > 0 && $pointed !== $previous ) {
				return new \WP_Error( 'artnovo_new_purchase_active', 'خرید دیگری در این نشست باز است. به فرم فعلی خرید برگردید.' );
			}
		}
		$prepared = prepare_wizard_cart( $id, $quantity, payment_candidates( $product, $quantity ), '' );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		foreach ( [ 'order_awaiting_payment', 'store_api_draft_order' ] as $pointer ) {
			if ( $previous === (int) WC()->session->get( $pointer ) ) {
				WC()->session->__unset( $pointer );
			}
		}
		WC()->session->set( WIZARD_DRAFT, [
			'product' => $id, 'quantity' => $quantity, 'billing_email' => sanitize_email( wp_get_current_user()->user_email ),
			'expires' => time() + 30 * MINUTE_IN_SECONDS, 'revision' => bin2hex( random_bytes( 32 ) ),
			'fresh_from' => $previous, 'fresh_source_revision' => $revision,
		] );
		WC()->session->save_data();
		return wizard_url( $id, true );
	}, true );
}

add_action( 'template_redirect', static function (): void {
	if ( ! wizard_page() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['artnovo_new_purchase_order'] ) ) {
		return;
	}
	wc_nocache_headers();
	$id = checkout_post_value( 'artnovo_new_purchase_order' );
	if ( ! ctype_digit( $id ) || ! WC()->session || ! WC()->cart || ! wizard_otp_same_origin()
		|| ! wp_verify_nonce( checkout_post_value( 'artnovo_new_purchase_nonce' ), 'artnovo_new_purchase_' . $id )
		|| ! ( $order = wizard_new_purchase_order( (int) $id ) ) ) {
		if ( WC()->session ) {
			remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
		}
		wp_die( 'درخواست خرید معتبر نیست.', '', [ 'response' => 403 ] );
	}
	$result = wizard_begin_new_purchase( $order, checkout_post_value( 'artnovo_selection_revision' ) );
	if ( is_wp_error( $result ) ) {
		wp_die( $result->get_error_message(), '', [ 'response' => 409, 'back_link' => true ] );
	}
	wp_safe_redirect( $result, 303 );
	exit;
}, 6 );

/** A lost response may leave the saved WooCommerce order ahead of the session pointer. */
function wizard_find_claimed_purchase( array $draft ): ?\WC_Order {
	$owner = get_current_user_id();
	if ( $owner < 1 || '' === wizard_purchase_intent( $draft ) || empty( $draft['purchase_claimed'] ) ) {
		return null;
	}
	foreach ( wc_get_orders( [ 'customer_id' => $owner, 'date_created' => '>=' . ( $draft['purchase_started'] ?? $draft['purchase_claimed'] ),
		'limit' => 20, 'status' => [ ...array_keys( wc_get_order_statuses() ), 'checkout-draft' ] ] ) as $order ) {
		if ( ( wizard_owns_bank_purchase( $order ) || wizard_unplaced_store_purchase( $order ) )
			&& hash_equals( wizard_purchase_intent( $draft ), (string) $order->get_meta( PURCHASE_META, true ) ) ) {
			return $order;
		}
	}
	return null;
}

function wizard_unplaced_store_purchase( \WC_Order $order ): bool {
	return is_user_logged_in() && get_current_user_id() === $order->get_customer_id()
		&& 'store-api' === $order->get_created_via() && $order->has_status( 'checkout-draft' ) && '' === $order->get_transaction_id( 'edit' );
}

function wizard_fresh_purchase_checkout( bool $online = false, bool $allow_store_draft = false, bool $protect_store_pointer = false ): true|\WC_Order|\WP_Error {
	$draft = wizard_draft();
	$intent = wizard_purchase_intent( $draft );
	if ( '' === $intent && empty( $draft['fresh_from'] ) ) {
		return true;
	}
	$bound = (int) ( $draft['fresh_order'] ?? 0 );
	$order = $bound ? wc_get_order( $bound ) : false;
	if ( $bound && ( ! $order instanceof \WC_Order || get_current_user_id() !== $order->get_customer_id()
		|| ( ! empty( $draft['purchase_claimed'] ) && ! hash_equals( $intent, (string) $order->get_meta( PURCHASE_META, true ) ) )
		|| ( $online && empty( $draft['purchase_claimed'] ) && ! wizard_owns_bank_purchase( $order ) ) ) ) {
		return new \WP_Error( 'artnovo_new_purchase_active', 'سفارش این خرید در دسترس نیست.', [ 'status' => 409 ] );
	}
	if ( '' !== $intent && ! empty( $draft['purchase_claimed'] ) ) {
		$order = $order ?: wizard_find_claimed_purchase( $draft );
		if ( $order && $allow_store_draft && wizard_unplaced_store_purchase( $order ) ) {
			$pointed = array_filter( [ (int) WC()->session->get( 'order_awaiting_payment' ), (int) WC()->session->get( 'store_api_draft_order' ) ] );
			if ( ! array_diff( $pointed, [ $order->get_id() ] ) ) {
				$draft['fresh_order'] = $order->get_id();
				WC()->session->set( WIZARD_DRAFT, $draft );
				WC()->session->set( 'store_api_draft_order', $order->get_id() );
				return true;
			}
		}
		if ( ! $order || $order->has_status( 'checkout-draft' ) ) {
			return new \WP_Error( 'artnovo_purchase_recovery', 'ثبت این خرید هنوز روشن نیست. برای پیگیری از بخش سفارش‌ها یا پشتیبانی استفاده کنید.', [ 'status' => 409 ] );
		}
		return $order;
	}
	// A placed bank order follows the native tracking or order-pay route without rebuilding its saved items.
	if ( $order && ( $order->is_paid() || in_array( \Artnovo\AccountUI\received_bank_payment( $order )['phase'] ?? '',
		[ 'open', 'review', 'failed_final', 'paid' ], true ) ) ) {
		return $order;
	}
	foreach ( [ 'order_awaiting_payment', 'store_api_draft_order' ] as $pointer ) {
		$pointed = (int) WC()->session->get( $pointer );
		if ( $pointed && $pointed !== $bound && ( $online || $protect_store_pointer && 'store_api_draft_order' === $pointer
			|| '' === $intent && ! empty( $draft['fresh_from'] ) || wizard_new_purchase_order( $pointed ) ) ) {
			return new \WP_Error( 'artnovo_new_purchase_active', 'خرید دیگری در این نشست باز است. صفحه را تازه کنید.', [ 'status' => 409 ] );
		}
	}
	return true;
}

function wizard_bind_fresh_purchase_order( \WC_Order $order ): void {
	if ( ! WC()->session ) {
		return;
	}
	$draft = wizard_draft();
	$intent = wizard_purchase_intent( $draft );
	$online = 'artnovo_gpante' === $order->get_payment_method( 'edit' );
	if ( ( '' === $intent && empty( $draft['fresh_from'] ) ) || $order->get_id() < 1 || get_current_user_id() !== $order->get_customer_id()
		|| ( $online && '' !== $intent && ! hash_equals( $intent, (string) $order->get_meta( PURCHASE_META, true ) ) )
		|| ( $draft['fresh_order'] ?? $order->get_id() ) !== $order->get_id()
		|| 'same' !== cart_selection( $draft['product'], $draft['quantity'] ) ) {
		return;
	}
	$draft['fresh_order'] = $order->get_id();
	WC()->session->set( WIZARD_DRAFT, $draft );
	if ( $online || '' === $intent ) {
		WC()->session->set( 'order_awaiting_payment', $order->get_id() );
		WC()->session->set( 'store_api_draft_order', $order->get_id() );
	}
	WC()->session->save_data();
}

/** Persist a claim before WooCommerce can save an online order. The marker is saved with the order. */
function wizard_claim_online_purchase( \WC_Order $order ): void {
	$draft = wizard_draft();
	$intent = wizard_purchase_intent( $draft );
	if ( '' === $intent || ! is_user_logged_in() || get_current_user_id() !== $order->get_customer_id() ) {
		throw new \RuntimeException( 'خرید آنلاین با انتخاب فعلی تطبیق ندارد.' );
	}
	if ( ! empty( $draft['purchase_claimed'] ) ) {
		if ( ! hash_equals( $intent, (string) $order->get_meta( PURCHASE_META, true ) ) ) {
			throw new \RuntimeException( 'این خرید در حال ثبت یا پیگیری است.' );
		}
		return;
	}
	if ( $order->get_id() && ! $order->has_status( 'checkout-draft' ) ) {
		throw new \RuntimeException( 'انتخاب فعلی به سفارش دیگری تعلق دارد. صفحه را تازه کنید.' );
	}
	$draft['purchase_claimed'] = time();
	WC()->session->set( WIZARD_DRAFT, $draft );
	WC()->session->save_data();
	wp_cache_delete( \WC_Cache_Helper::get_cache_prefix( WC_SESSION_CACHE_GROUP ) . WC()->session->get_customer_id(), WC_SESSION_CACHE_GROUP );
	$saved = maybe_unserialize( WC()->session->get_session_data()[ WIZARD_DRAFT ] ?? null );
	if ( ! is_array( $saved ) || ! hash_equals( $intent, wizard_purchase_intent( $saved ) ) || ( $saved['purchase_claimed'] ?? null ) !== $draft['purchase_claimed'] ) {
		throw new \RuntimeException( 'وضعیت ثبت خرید قابل تأیید نیست. از حساب کاربری پیگیری کنید.' );
	}
	$order->update_meta_data( PURCHASE_META, $intent );
}
add_filter( 'rest_dispatch_request', static function ( mixed $response, \WP_REST_Request $request ): mixed {
	$method = $request->get_method();
	if ( null !== $response || ! in_array( $method, [ 'GET', 'HEAD', 'POST', 'PATCH', 'PUT' ], true )
		|| ! preg_match( '~\A/wc/store(?:/v[0-9]+)?/checkout/?\z~i', $request->get_route() ) ) {
		return $response;
	}
	$owner = get_current_user_id();
	if ( ! $owner && ( ! is_callable( [ CartTokenUtils::class, 'get_request_cart_token' ] )
		|| ! CartTokenUtils::validate_cart_token( CartTokenUtils::get_request_cart_token() ) ) ) {
		return $response;
	}
	WC()->initialize_session();
	$session = WC()->session;
	wc_load_cart();
	$submitted = $request->get_header( 'X-Artnovo-Purchase-Intent' );
	$has_plan = cart_has_plan();
	if ( ! $submitted && ! $has_plan ) {
		// Woo can reuse an unplaced draft even if the cart no longer matches it.
		$pointed = (int) $session->get( 'store_api_draft_order' );
		$order = $pointed && wizard_session_has_purchase() ? wc_get_order( $pointed ) : false;
		if ( ! $order instanceof \WC_Order || ! $order->has_status( 'checkout-draft' ) || ! $order->get_meta( PURCHASE_META, true ) ) {
			return $response;
		}
	}
	if ( $has_plan && ! $owner && empty( wizard_draft()['fresh_from'] ) && ! wizard_session_has_purchase() && ! $submitted ) {
		return $response;
	}
	if ( ! $owner || (string) $session->get_customer_id() !== (string) $owner ) {
		remove_action( 'shutdown', [ $session, 'save_data' ], 20 );
		return new \WP_Error( 'artnovo_new_purchase_owner', 'برای ادامهٔ این خرید دوباره وارد حساب خود شوید.', [ 'status' => 403 ] );
	}
	if ( in_array( $method, [ 'POST', 'PATCH', 'PUT' ], true ) && ! $session instanceof StoreSession
		&& ! wp_verify_nonce( $request->get_header( 'Nonce' ), 'wc_store_api' ) ) {
		remove_action( 'shutdown', [ $session, 'save_data' ], 20 );
		return new \WP_Error( 'artnovo_new_purchase_nonce', 'صفحهٔ خرید را تازه کنید.', [ 'status' => 403 ] );
	}
	$result = wizard_selection_locked( static function () use ( $request ) {
		if ( ! wizard_refresh_selection_session() ) {
			return new \WP_Error( 'artnovo_selection_session', 'صفحهٔ خرید را تازه کنید.', [ 'status' => 409 ] );
		}
		$draft = wizard_draft();
		$submitted = $request->get_header( 'X-Artnovo-Purchase-Intent' );
		if ( ( null !== $submitted && '' !== $submitted || wizard_session_has_purchase() ) && ( '' === wizard_purchase_intent( $draft )
			|| ! wizard_matches_purchase_request( $draft, $submitted, $request->get_header( 'X-Artnovo-Selection-Revision' ) ) ) ) {
			return new \WP_Error( 'artnovo_selection_changed', 'برای ادامهٔ خرید، انتخاب فعلی را دوباره باز کنید.', [ 'status' => 409 ] );
		}
		$method = $request->get_method();
		$gateway = $request->get_param( 'payment_method' );
		return wizard_fresh_purchase_checkout(
			online: 'POST' === $method && 'artnovo_gpante' === $gateway,
			allow_store_draft: 'artnovo_gpante' === $gateway || 'POST' !== $method && ( null === $gateway || '' === $gateway ),
			protect_store_pointer: 'POST' !== $method,
		);
	}, true );
	if ( $session instanceof StoreSession && ! is_wp_error( $result ) ) {
		add_action( 'shutdown', static fn() => wp_cache_delete(
			\WC_Cache_Helper::get_cache_prefix( WC_SESSION_CACHE_GROUP ) . $session->get_customer_id(), WC_SESSION_CACHE_GROUP
		), 21 );
	}
	if ( $result instanceof \WC_Order ) {
		// Native checkout GET and PATCH also rebuild pending drafts. Use the native response shape without saving the order.
		$payment = new PaymentResult();
		if ( 'POST' === $method ) {
			$payment->set_status( 'success' );
			$payment->set_redirect_url( \Artnovo\AccountUI\received_tracking_url( $result ) );
		}
		$schema = StoreApi::container()->get( SchemaController::class )->get( CheckoutSchema::IDENTIFIER );
		return new \WP_REST_Response( $schema->get_item_response( (object) [
			'order' => $result, 'cart' => WC()->cart, 'payment_result' => $payment,
		] ), 200, [ 'Cache-Control' => 'private, no-store', 'Nonce' => wp_create_nonce( 'wc_store_api' ),
			'Nonce-Timestamp' => time(), 'User-ID' => get_current_user_id(), 'Cart-Hash' => WC()->cart->get_cart_hash(),
			'Cart-Token' => CartTokenUtils::get_cart_token( (string) WC()->session->get_customer_id() ),
		] );
	}
	if ( true === $result && 'POST' === $method && 'artnovo_gpante' === $request->get_param( 'payment_method' ) ) {
		if ( '' !== wizard_purchase_intent( wizard_draft() ) ) {
			// Store API saves its initial draft while populating the order from the cart.
			add_action( 'woocommerce_before_order_object_save', static function ( \WC_Order $order ): void {
				if ( 'store-api' === $order->get_created_via() && ! $order->get_id() ) {
					wizard_claim_online_purchase( $order );
				}
			}, 1 );
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', __NAMESPACE__ . '\\wizard_claim_online_purchase', 1 );
		}
		add_action( 'woocommerce_store_api_checkout_order_processed', __NAMESPACE__ . '\\wizard_bind_fresh_purchase_order', 100 );
	} elseif ( true === $result && 'POST' === $method && ! empty( wizard_draft()['fresh_from'] ) ) {
		add_action( 'woocommerce_store_api_checkout_order_processed', __NAMESPACE__ . '\\wizard_bind_fresh_purchase_order', 100 );
	}
	return is_wp_error( $result ) ? $result : null;
}, 20, 2 );

function wizard_preserve_login_selection(): true|\WP_Error {
	if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart || ! wizard_cart_context() ) {
		return true;
	}
	return wizard_selection_locked( static function () {
		return wizard_refresh_selection_session() ? true
			: new \WP_Error( 'artnovo_selection_session', 'ادامهٔ خرید آماده نشد. صفحه را تازه کنید و دوباره تلاش کنید.', 503 );
	}, true );
}

add_action( 'woocommerce_checkout_process', static function (): void {
	if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart
		|| ! wp_verify_nonce( checkout_post_value( 'woocommerce-process-checkout-nonce' ), 'woocommerce-process_checkout' )
		|| ( ! isset( $_POST['artnovo_wizard_cart'] ) && ! isset( $_POST['artnovo_purchase_intent'] ) && ! cart_has_plan() ) ) {
		return;
	}
	$result = wizard_selection_locked( static function () {
		if ( ! wizard_refresh_selection_session() ) {
			return new \WP_Error( 'artnovo_selection_session', 'ادامهٔ خرید آماده نشد. صفحه را تازه کنید و دوباره تلاش کنید.' );
		}
		if ( ! isset( $_POST['artnovo_wizard_cart'] ) && ! isset( $_POST['artnovo_purchase_intent'] ) && ! wizard_session_has_purchase() ) {
			return true;
		}
		return isset( $_POST['artnovo_wizard_cart'] ) && ( ! wizard_session_has_purchase() || '' !== wizard_purchase_intent( wizard_draft() ) )
			&& wizard_matches_purchase_request( wizard_draft(), checkout_post_value( 'artnovo_purchase_intent' ), checkout_post_value( 'artnovo_selection_revision' ) ) ? wizard_fresh_purchase_checkout( 'artnovo_gpante' === checkout_post_value( 'payment_method' ) )
			: new \WP_Error( 'artnovo_selection_changed', 'برای ادامهٔ خرید، انتخاب تازهٔ خود را باز کنید.' );
	}, true );
	if ( $result instanceof \WC_Order ) {
		$url = \Artnovo\AccountUI\received_tracking_url( $result );
		if ( wp_doing_ajax() ) {
			wp_send_json( [ 'result' => 'success', 'order_id' => $result->get_id(), 'redirect' => $url ] );
		}
		wp_safe_redirect( $url, 303 );
		exit;
	}
	if ( is_wp_error( $result ) ) {
		wc_add_notice( $result->get_error_message(), 'error' );
	} elseif ( 'artnovo_gpante' === checkout_post_value( 'payment_method' ) && '' !== wizard_purchase_intent( wizard_draft() ) ) {
		add_action( 'woocommerce_checkout_create_order', __NAMESPACE__ . '\\wizard_claim_online_purchase', 100 );
		add_action( 'woocommerce_checkout_order_created', __NAMESPACE__ . '\\wizard_bind_fresh_purchase_order', 100 );
	} elseif ( ! empty( wizard_draft()['fresh_from'] ) ) {
		add_action( 'woocommerce_checkout_order_created', __NAMESPACE__ . '\\wizard_bind_fresh_purchase_order', 100 );
	}
}, 1 );

function wizard_process_selection( bool $ajax, string $otp_action ): array {
	$product_id       = filter_var( checkout_post_value( 'artnovo_product' ), FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
	$quantity         = filter_var( checkout_post_value( 'artnovo_quantity' ), FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
	$requested_method = checkout_post_value( 'artnovo_payment' );
	$product          = $product_id ? wc_get_product( $product_id ) : false;
	if ( ! is_plan( $product ) || post_password_required( $product_id ) || ! $product->is_in_stock() || ! $product->is_purchasable() ) {
		return [ 'product' => $product_id ?: 0, 'error' => 'این پلن در حال حاضر قابل خرید نیست.' ];
	}
	$methods = $quantity ? payment_candidates( $product, $quantity ) : [];
	if ( ! $quantity || $quantity > quantity_limit( $product ) || ! $methods || ( '' !== $requested_method && ! in_array( $requested_method, $methods, true ) ) ) {
		return [ 'product' => $product_id, 'error' => 'تعداد یا روش پرداخت انتخاب‌شده در دسترس نیست.' ];
	}

	$login_method = checkout_post_value( 'artnovo_login_method' );
	if ( ! in_array( $login_method, [ '', 'pinova', 'email' ], true ) ) {
		return [ 'product' => $product_id, 'error' => 'روش ورود معتبر نیست.' ];
	}
	$previous = wizard_draft();
	$action = wizard_purchase_action();
	if ( isset( $_POST['artnovo_purchase_action'] ) && '' === $action ) {
		return [ 'product' => $product_id, 'error' => 'درخواست خرید معتبر نیست. صفحه را دوباره باز کنید.' ];
	}
	$detach = 0;
	if ( is_user_logged_in() && ( '' !== $action || ! empty( $previous['purchase_pending_login'] ) ) ) {
		if ( ! empty( $previous['purchase_claimed'] ) && empty( $previous['fresh_order'] ) ) {
			$order = wizard_find_claimed_purchase( $previous );
			if ( ! $order || $order->has_status( 'checkout-draft' ) ) {
				return [ 'product' => $product_id, 'error' => 'ثبت خرید قبلی هنوز روشن نیست. از بخش سفارش‌ها یا پشتیبانی پیگیری کنید.' ];
			}
			$previous['fresh_order'] = $order->get_id();
		}
		$detach = wizard_previous_online_purchase( $previous );
		if ( is_wp_error( $detach ) ) {
			return [ 'product' => $product_id, 'error' => $detach->get_error_message() ];
		}
	}
	if ( '' === $action && empty( $previous['purchase_pending_login'] ) && ! empty( $previous['purchase_claimed'] )
		&& ( ( $previous['product'] ?? 0 ) !== $product_id || ( $previous['quantity'] ?? 0 ) !== $quantity ) ) {
		return [ 'redirect' => wizard_current_selection_url(), 'selection_changed' => true ];
	}
	$native_prepare = ! is_user_logged_in() && 'pinova' === $login_method && '1' === checkout_post_value( 'artnovo_wizard_ajax' );
	if ( $ajax && '' === $otp_action && ! $native_prepare && ! wizard_async_selection() ) {
		$previous_quantity = $previous['quantity'] ?? 0;
		$marker            = checkout_post_value( 'artnovo_wizard_cart' );
		if ( ! is_user_logged_in() || $product_id !== ( $previous['product'] ?? null )
			|| 'same' !== cart_selection( $product_id, $previous_quantity )
			|| ( $marker !== $product_id . ':' . $previous_quantity && $quantity !== $previous_quantity ) ) {
			return [ 'redirect' => wizard_current_selection_url(), 'selection_changed' => true ];
		}
	}
	$email  = 'pinova' === $login_method ? '' : ( isset( $_POST['artnovo_email'] ) ? checkout_post_value( 'artnovo_email' ) : ( $previous['billing_email'] ?? '' ) );
	$coupon = isset( $_POST['artnovo_coupon'] ) ? sanitize_text_field( checkout_post_value( 'artnovo_coupon' ) ) : ( $previous['coupon'] ?? '' );
	if ( strlen( $email ) > 254 || strlen( $coupon ) > 200 || ( '' !== $email && ! is_email( $email ) ) ) {
		return [ 'product' => $product_id, 'error' => 'ایمیل یا کد تخفیف را بررسی کنید.' ];
	}
	$draft = [
		'product' => $product_id, 'quantity' => $quantity, 'billing_email' => sanitize_email( $email ),
		'coupon' => $coupon, 'expires' => time() + 30 * MINUTE_IN_SECONDS,
		'revision' => '' === $action && ! $detach && ( $previous['product'] ?? null ) === $product_id && ( $previous['quantity'] ?? null ) === $quantity
			? wizard_selection_revision() : bin2hex( random_bytes( 32 ) ),
	];
	if ( $draft['revision'] === ( $previous['revision'] ?? null ) ) {
		$draft += array_intersect_key( $previous, array_flip( [ 'fresh_from', 'fresh_source_revision', 'fresh_order' ] ) );
	}
	if ( $detach ) {
		$resuming_guest_purchase = '' === $action && ! empty( $previous['purchase_pending_login'] );
		$guest_intent = $resuming_guest_purchase ? wizard_purchase_intent( $previous ) : '';
		$draft['purchase_intent'] = $guest_intent ?: bin2hex( random_bytes( 32 ) );
		$draft['purchase_started'] = '' !== $guest_intent ? ( $previous['purchase_started'] ?? time() ) : time();
		$draft['fresh_from'] = $detach;
		$draft['purchase_action'] = $action ?: ( $previous['purchase_action'] ?? '' );
	} elseif ( '' !== wizard_purchase_intent( $previous ) || '' !== $action ) {
		$draft += array_intersect_key( $previous, array_flip( [ 'purchase_intent', 'purchase_started', 'purchase_action', 'fresh_from', 'fresh_order', 'purchase_claimed' ] ) );
		$draft['purchase_intent'] ??= bin2hex( random_bytes( 32 ) );
		$draft['purchase_started'] ??= time();
		if ( empty( $draft['purchase_claimed'] ) && $draft['revision'] !== ( $previous['revision'] ?? null )
			&& ( ( $previous['product'] ?? 0 ) !== $product_id || ( $previous['quantity'] ?? 0 ) !== $quantity ) ) {
			unset( $draft['fresh_order'] );
		}
		if ( '' !== $action ) {
			$draft['purchase_action'] = $action;
		}
	}
	if ( ! is_user_logged_in() && '' !== wizard_purchase_intent( $draft ) ) {
		$draft['purchase_pending_login'] = true;
	}
	if ( '' !== $otp_action ) {
		return [ 'otp' => wizard_email_otp_request( $otp_action, $draft ) ];
	}
	if ( ! is_user_logged_in() ) {
		WC()->session->set( WIZARD_DRAFT, $draft );
		WC()->session->save_data();
		if ( wizard_async_selection() ) {
			return [ 'product' => $product_id ];
		}
		$resume = add_query_arg( 'artnovo_resume', '1', wc_get_page_permalink( 'myaccount' ) );
		$destination = 'pinova' === $login_method ? \Artnovo\AccountUI\account_native_login_url( $resume ) : $resume;
		return [ 'redirect' => $destination, 'selection_revision' => wizard_selection_revision() ];
	}

	$prepared = prepare_wizard_cart( $product_id, $quantity, $methods, $requested_method, $detach > 0 || '' !== $action );
	if ( is_wp_error( $prepared ) ) {
		return [ 'product' => $product_id, 'error' => $prepared->get_error_message() ];
	}
	if ( $detach ) {
		foreach ( [ 'order_awaiting_payment', 'store_api_draft_order' ] as $pointer ) {
			if ( $detach === (int) WC()->session->get( $pointer ) ) {
				WC()->session->__unset( $pointer );
			}
		}
	}
	unset( $draft['coupon'] );
	WC()->session->set( WIZARD_DRAFT, $draft );
	if ( '' !== $requested_method ) {
		WC()->session->set( 'chosen_payment_method', $requested_method );
	}
	if ( '' !== $coupon && wc_coupons_enabled() && ! WC()->cart->has_discount( $coupon ) ) {
		WC()->cart->apply_coupon( $coupon );
	}
	return [ 'product' => $product_id ];
}

function wizard_update_selection( bool $ajax, string $otp_action ): array|\WP_Error {
	return wizard_selection_locked( static function () use ( $ajax, $otp_action ) {
		if ( ! wizard_refresh_selection_session() ) {
			return new \WP_Error( 'artnovo_selection_session', 'ادامهٔ خرید آماده نشد. صفحه را تازه کنید و دوباره تلاش کنید.', 503 );
		}
		$action = wizard_purchase_action();
		$draft = wizard_draft();
		if ( '' !== $action && hash_equals( (string) ( $draft['purchase_action'] ?? '' ), $action ) ) {
			return [ 'redirect' => wizard_url( $draft['product'], is_user_logged_in() ), 'selection_changed' => true ];
		}
		if ( ! hash_equals( wizard_selection_revision(), checkout_post_value( 'artnovo_selection_revision' ) ) ) {
			$result = [ 'redirect' => wizard_current_selection_url(), 'selection_changed' => true ];
		} else {
			$result = wizard_process_selection( $ajax, $otp_action );
		}
		if ( ! $ajax && ! empty( $result['error'] ) ) {
			wc_add_notice( $result['error'], 'error' );
		}
		WC()->session->save_data();
		return $result;
	}, wizard_async_selection() );
}

add_action( 'template_redirect', static function (): void {
	if ( ! wizard_page() || ! isset( $_POST['artnovo_wizard_nonce'] ) || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
		return;
	}
	$otp_action = checkout_post_value( 'artnovo_otp_action' );
	$ajax = '' !== $otp_action || '1' === checkout_post_value( 'artnovo_wizard_ajax' );
	if ( ! wp_verify_nonce( checkout_post_value( 'artnovo_wizard_nonce' ), WIZARD_DRAFT ) ) {
		wc_nocache_headers();
		// A stale snapshot must not overwrite a newer tab's selection at shutdown.
		remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
		$redirect = wizard_recovery_url();
		if ( $ajax ) {
			wp_send_json_error( [ 'refresh_required' => true, 'redirect' => $redirect,
				'message' => 'فرم خرید تازه می‌شود؛ انتخاب فعلی شما حفظ می‌شود.' ], 403 );
		}
		wp_safe_redirect( $redirect, 303 );
		exit;
	}
	$native_prepare = $ajax && '' === $otp_action && ! is_user_logged_in() && 'pinova' === checkout_post_value( 'artnovo_login_method' );
	if ( ( $native_prepare || wizard_async_selection() || isset( $_POST['artnovo_purchase_action'] ) ) && ! wizard_otp_same_origin() ) {
		remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
		wp_send_json_error( [ 'message' => 'درخواست ورود معتبر نیست. صفحه را دوباره باز کنید.' ], 403 );
	}
	if ( '' !== $otp_action && ( ! wizard_email_otp_enabled() || is_user_logged_in()
		|| ! wizard_otp_same_origin() || ! in_array( $otp_action, [ 'send', 'verify' ], true ) ) ) {
		remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
		wp_send_json_error( [ 'message' => 'درخواست ورود معتبر نیست. صفحه را دوباره باز کنید.' ], 403 );
	}
	WC()->session->set_customer_session_cookie( true );
	wc_nocache_headers();

	$result = wizard_update_selection( $ajax, $otp_action );
	if ( is_wp_error( $result ) ) {
		if ( $ajax ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 409 );
		}
		wp_die( $result->get_error_message(), '', [ 'response' => 409 ] );
	}
	if ( isset( $result['otp'] ) ) {
		$otp = $result['otp'];
		if ( is_wp_error( $otp ) ) {
			wp_send_json_error( [ 'message' => $otp->get_error_message() ], $otp->get_error_data() );
		}
		wp_send_json_success( $otp );
	}
	if ( isset( $result['redirect'] ) ) {
		if ( $ajax ) {
			wp_send_json_success( $result );
		}
		wp_safe_redirect( $result['redirect'], 303 );
		exit;
	}
	wizard_reply( $result['product'], $ajax, $result['error'] ?? '' );
}, 7 );

function wizard_login_return( string $redirect ): string {
	$draft = '1' === ( $_GET['artnovo_resume'] ?? null ) ? wizard_draft() : [];
	return isset( $draft['product'] ) ? wizard_url( $draft['product'] ) : $redirect;
}
add_filter( 'woocommerce_login_redirect', __NAMESPACE__ . '\\wizard_login_return' );
add_filter( 'woocommerce_registration_redirect', __NAMESPACE__ . '\\wizard_login_return' );

add_action( 'woocommerce_cart_emptied', static function (): void {
	if ( WC()->session && empty( wizard_draft()['purchase_claimed'] ) ) {
		WC()->session->__unset( WIZARD_DRAFT );
	}
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
		printf( '<input type="hidden" name="artnovo_wizard_cart" value="%s"><input type="hidden" name="artnovo_selection_revision" value="%s">', esc_attr( $draft['product'] . ':' . $draft['quantity'] ), esc_attr( wizard_selection_revision() ) );
		printf( '<input type="hidden" name="artnovo_purchase_intent" value="%s">', esc_attr( wizard_purchase_intent( $draft ) ) );
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
	if ( ! hash_equals( wizard_selection_revision(), checkout_post_value( 'artnovo_selection_revision' ) ) ) {
		$errors->add( 'artnovo_selection_changed', 'برای ادامهٔ خرید، انتخاب تازهٔ خود را باز کنید.' );
		return;
	}
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
	if ( $read && wizard_page() && '1' === ( $_GET['artnovo_checkout'] ?? null )
		&& (string) ( wizard_draft()['product'] ?? '' ) !== ( $_GET['artnovo_plan'] ?? null ) ) {
		wc_nocache_headers();
		wp_safe_redirect( wizard_current_selection_url(), 302 );
		exit;
	}
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
	if ( ! cart_has_plan() && ( cart_items() || ! wizard_draft() ) ) {
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
