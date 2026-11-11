<?php

// Run via wp eval-file with Core and the four legacy custom plugins skipped.
require_once dirname( __DIR__ ) . '/fixture-paths.php';
artnovo_fixture_boot_core();

$prepare_function = new ReflectionFunction( 'Artnovo\\PlanCheckout\\prepare_wizard_cart' );
$legacy = 5 === $prepare_function->getNumberOfParameters();

add_filter( 'query', static function ( string $query ): string {
	if ( ! preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $query ) ) {
		$callers = array_column( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 14 ), 'function' );
		WP_CLI::error( 'A database write was blocked (' . implode( ' ← ', $callers ) . ').' );
	}
	return $query;
}, PHP_INT_MAX );
add_filter( 'pre_wp_mail', static function (): never {
	WP_CLI::error( 'An email send was blocked.' );
} );
add_filter( 'pre_http_request', static function (): never {
	WP_CLI::error( 'An outbound HTTP request was blocked.' );
} );
add_filter( 'woocommerce_persistent_cart_enabled', '__return_false' );

$user_id = 2147000000;
if ( get_user_by( 'id', $user_id ) ) {
	WP_CLI::error( 'The synthetic user ID is already in use.' );
}
$user = new WP_User();
$user->init( (object) [ 'ID' => $user_id, 'user_login' => 'artnovo-isolated-cart-test' ] );
$GLOBALS['current_user'] = $user;

if ( WC()->session instanceof WC_Session_Handler ) {
	remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
}
if ( WC()->customer instanceof WC_Customer ) {
	remove_action( 'shutdown', [ WC()->customer, 'save' ], 10 );
}
WC()->session = new class extends WC_Session {
	public function has_session(): bool {
		return false;
	}
};
WC()->customer = new WC_Customer( 0, false );
$cart = WC()->cart ?? new WC_Cart();
WC()->cart = $cart;
$cart->get_cart();

$failures = 0;
$check = static function ( string $case, bool $passed ) use ( &$failures ): void {
	if ( ! $passed ) {
		++$failures;
	}
	WP_CLI::log( ( $passed ? 'PASS ' : 'FAIL ' ) . $case );
};

$seed = static function ( int $product_id ) use ( $cart ): string {
	$cart->set_cart_contents( [] );
	$cart->set_removed_cart_contents( [] );
	$cart->set_applied_coupons( [] );
	wc_clear_notices();
	WC()->session->set( Artnovo\PlanCheckout\WIZARD_DRAFT, null );
	$key = $cart->add_to_cart( $product_id, 1 );
	if ( ! $key ) {
		WP_CLI::error( 'Could not seed the in-memory WooCommerce cart.' );
	}
	return $key;
};

$prepare = static function ( int $product_id, int $quantity = 1, string $owned_key = '' ) use ( $prepare_function, $legacy ): mixed {
	if ( $legacy ) {
		return $prepare_function->invoke( $product_id, $quantity, $owned_key ? [ 'owned_cart_key' => $owned_key ] : [], [ 'bacs' ], '' );
	}
	return $prepare_function->invoke( $product_id, $quantity, [ 'bacs' ], '' );
};

$seed( 25 );
WC()->session->set( 'order_awaiting_payment', 123 );
WC()->session->set( 'store_api_draft_order', 456 );
$result = $prepare( 26 );
$check( 'legacy cart 25 → 26 without draft', $legacy
	? is_wp_error( $result ) && 'artnovo_other_cart' === $result->get_error_code() && 'same' === Artnovo\PlanCheckout\cart_selection( 25 )
	: true === $result && 'same' === Artnovo\PlanCheckout\cart_selection( 26 ) );
if ( ! $legacy ) {
	$check( 'replacement clears only old session pointers', null === WC()->session->get( 'order_awaiting_payment' )
		&& null === WC()->session->get( 'store_api_draft_order' ) );
}

if ( $legacy ) {
	$seed( 26 );
}
$current_key = array_key_first( $cart->get_cart() );
$result = $prepare( 26 );
$check( 'retry keeps the chosen item and quantity', ! is_wp_error( $result ) && $current_key === array_key_first( $cart->get_cart() ) && 'same' === Artnovo\PlanCheckout\cart_selection( 26 ) );
$result = $prepare( 26, 2 );
$check( 'quantity update keeps the chosen item', ! is_wp_error( $result ) && $current_key === array_key_first( $cart->get_cart() ) && 'same' === Artnovo\PlanCheckout\cart_selection( 26, 2 ) );

$old_key = $seed( 25 );
add_filter( 'woocommerce_add_to_cart_validation', '__return_false', PHP_INT_MAX );
$result = $prepare( 26, 1, $old_key );
remove_filter( 'woocommerce_add_to_cart_validation', '__return_false', PHP_INT_MAX );
$check( 'rejected native add restores old plan', is_wp_error( $result ) && $old_key === array_key_first( $cart->get_cart() ) && 'same' === Artnovo\PlanCheckout\cart_selection( 25 ) );

$old_key = $seed( 25 );
WC()->session->set( 'order_awaiting_payment', 123 );
WC()->session->set( 'store_api_draft_order', 456 );
add_filter( 'woocommerce_available_payment_gateways', '__return_empty_array', PHP_INT_MAX );
$result = $prepare( 26, 1, $old_key );
remove_filter( 'woocommerce_available_payment_gateways', '__return_empty_array', PHP_INT_MAX );
$check( 'unavailable gateway restores old plan', is_wp_error( $result ) && $old_key === array_key_first( $cart->get_cart() ) && 'same' === Artnovo\PlanCheckout\cart_selection( 25 ) );
if ( ! $legacy ) {
	$check( 'unavailable gateway preserves the old session pointers', 123 === WC()->session->get( 'order_awaiting_payment' )
		&& 456 === WC()->session->get( 'store_api_draft_order' ) );
}

if ( ! $legacy ) {
	$old_key = $seed( 25 );
	$fail_after_add = static function ( string $key, int $product_id ): void {
		if ( 26 === $product_id ) {
			throw new RuntimeException( 'Intentional late add failure' );
		}
	};
	add_action( 'woocommerce_add_to_cart', $fail_after_add, PHP_INT_MAX, 2 );
	$result = $prepare( 26 );
	remove_action( 'woocommerce_add_to_cart', $fail_after_add, PHP_INT_MAX );
	$check( 'late native add exception restores exactly the old plan', is_wp_error( $result ) && $old_key === array_key_first( $cart->get_cart() ) && 'same' === Artnovo\PlanCheckout\cart_selection( 25 ) );
}

$old_key = $seed( 25 );
$item = $cart->get_cart_item( $old_key );
$unrelated = new WC_Product_Simple();
$unrelated->set_name( 'In-memory unrelated item' );
$unrelated->set_price( '10' );
$unrelated->set_virtual( true );
$other_item = array_replace( $item, [ 'data' => $unrelated, 'product_id' => 0, 'key' => 'unrelated' ] );
$mixed = [ $old_key => $item, 'unrelated' => $other_item ];
$cart->set_cart_contents( $mixed );
$result = $prepare( 26 );
$check( 'mixed cart stays untouched', is_wp_error( $result ) && $mixed === $cart->get_cart() );

if ( ! $legacy ) {
	$old_key = $seed( 25 );
	$old_item = $cart->get_cart_item( $old_key );
	$second_key = $seed( 26 );
	$cart->set_cart_contents( [ $old_key => $old_item, $second_key => $cart->get_cart_item( $second_key ) ] );
	$result = $prepare( 27 );
	$check( 'two eligible legacy plans collapse to the new plan', true === $result && 'same' === Artnovo\PlanCheckout\cart_selection( 27 ) );
}

remove_action( 'shutdown', '_wp_cron' );

if ( $failures ) {
	WP_CLI::error( $failures . ' in-memory cart checks failed.' );
}

WP_CLI::success( ( $legacy ? 'Baseline' : 'Candidate' ) . ' in-memory cart checks passed.' );
