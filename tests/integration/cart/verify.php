<?php

// Run via wp eval-file with Core and the four legacy custom plugins skipped.
require_once dirname( __DIR__ ) . '/fixture-paths.php';
artnovo_fixture_boot_core();

$prepare_function = new ReflectionFunction( 'Artnovo\\PlanCheckout\\prepare_wizard_cart' );
$legacy = 5 === $prepare_function->getNumberOfRequiredParameters();

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
$check( 'explicit selection replaces every old row without notices', $legacy
	? is_wp_error( $result ) && $mixed === $cart->get_cart()
	: true === $result && 'same' === Artnovo\PlanCheckout\cart_selection( 26 ) && 0 === wc_notice_count() );

if ( ! $legacy ) {
	$old_key = $seed( 25 );
	$old_item = $cart->get_cart_item( $old_key );
	$second_key = $seed( 26 );
	$cart->set_cart_contents( [ $old_key => $old_item, $second_key => $cart->get_cart_item( $second_key ) ] );
	$result = $prepare( 27 );
	$check( 'two eligible legacy plans collapse to the new plan', true === $result && 'same' === Artnovo\PlanCheckout\cart_selection( 27 ) );
}

// Exercise the public purchase/policy markup using WordPress's actual HTML processor.
$markup_filter = null;
foreach ( $GLOBALS['wp_filter']['elementor/frontend/the_content']->callbacks[10] ?? [] as $hook ) {
	$callback = $hook['function'];
	if ( $callback instanceof Closure && realpath( ( new ReflectionFunction( $callback ) )->getFileName() )
		=== realpath( artnovo_fixture_root() . '/modules/home/artnovo-home-wizard.php' ) ) {
		$markup_filter = $callback;
	}
}
if ( ! $markup_filter ) {
	WP_CLI::error( 'The candidate public markup filter is missing.' );
}
$original_query = $GLOBALS['wp_query'];
$original_get = $_GET;
$GLOBALS['wp_query'] = new WP_Query();
$_GET = [];
try {
	$route = static function ( string $type, int $id ): void {
		$query = new WP_Query();
		$query->queried_object_id = $id;
		if ( in_array( $type, [ 'category', 'tag', 'product taxonomy' ], true ) ) {
			$taxonomy = [ 'category' => 'category', 'tag' => 'post_tag', 'product taxonomy' => 'product_cat' ][ $type ];
			$query->queried_object = new WP_Term( (object) [ 'term_id' => $id, 'term_taxonomy_id' => $id, 'taxonomy' => $taxonomy, 'name' => 'Memory fixture', 'slug' => 'memory-fixture' ] );
			$query->is_archive = true;
			$query->is_category = 'category' === $type;
			$query->is_tag = 'tag' === $type;
			$query->is_tax = 'product taxonomy' === $type;
		} elseif ( 'author' === $type ) {
			$query->queried_object = (object) [ 'ID' => $id, 'user_login' => 'memory-fixture' ];
			$query->is_author = $query->is_archive = true;
		} else {
			$query->queried_object = new WP_Post( (object) [ 'ID' => $id, 'post_type' => 'page' === $type ? 'page' : 'artnovo_qa_record', 'post_title' => 'Memory fixture', 'post_name' => 'memory-fixture' ] );
			$query->is_singular = true;
			$query->is_page = 'page' === $type;
		}
		$GLOBALS['wp_query'] = $query;
	};
	$home_callbacks = static function ( string $hook, string $file ): array {
		$callbacks = [];
		foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks[10] ?? [] as $entry ) {
			$callback = $entry['function'];
			if ( $callback instanceof Closure && realpath( ( new ReflectionFunction( $callback ) )->getFileName() )
				=== realpath( artnovo_fixture_root() . '/modules/home/' . $file ) ) {
				$callbacks[] = $callback;
			}
		}
		return $callbacks;
	};
	foreach ( [ 61, 68 ] as $id ) {
		foreach ( [ 'category', 'tag', 'product taxonomy', 'author', 'custom post type' ] as $type ) {
			$route( $type, $id );
			$check( "$type ID $id remains outside both purchase pages", $id === get_queried_object_id() && ! is_page()
				&& ! Artnovo\HomeOrderUI\on_workflow_page() && ! Artnovo\HomeOrderUI\Wizard\is_page() && ! Artnovo\PlanCheckout\wizard_page() );
			foreach ( [ 'artnovo-home-order-ui.php' => '68deff07', 'artnovo-home-wizard.php' => '6ad23061' ] as $file => $element_id ) {
				$source = '<!--artnovo-home-order--><!--artnovo-wizard-progress-->';
				foreach ( $home_callbacks( 'elementor/frontend/the_content', $file ) as $callback ) {
					$check( "$type ID $id preserves $file content", $source === $callback( $source ) );
				}
				$element = new class( $element_id ) {
					public function __construct( private string $id ) {}
					public function get_id(): string { return $this->id; }
				};
				ob_start();
				try {
					foreach ( $home_callbacks( 'elementor/frontend/after_render', $file ) as $callback ) { $callback( $element ); }
					$marker = ob_get_contents();
				} finally { ob_end_clean(); }
				$check( "$type ID $id emits no purchase marker from $file", '' === $marker );
			}
			$scripts = wp_scripts()->queue;
			$styles = wp_styles()->queue;
			try {
				foreach ( [ 'artnovo-home-order-ui.php', 'artnovo-home-wizard.php' ] as $file ) {
					foreach ( $home_callbacks( 'wp_enqueue_scripts', $file ) as $callback ) { $callback(); }
				}
				$check( "$type ID $id enqueues no purchase scripts", $scripts === wp_scripts()->queue );
				$check( "$type ID $id preserves only legitimate native catalog styling", is_product_taxonomy()
					? in_array( 'artnovo-home-wizard', wp_styles()->queue, true ) : $styles === wp_styles()->queue );
			} finally {
				wp_scripts()->queue = $scripts;
				wp_styles()->queue = $styles;
			}
		}
		$route( 'page', $id );
		$check( "actual page $id retains its native purchase context", is_page( $id )
			&& ( 61 === $id ) === Artnovo\HomeOrderUI\on_workflow_page()
			&& ( 68 === $id ) === Artnovo\HomeOrderUI\Wizard\is_page()
			&& ( 68 === $id ) === Artnovo\PlanCheckout\wizard_page() );
	}
	$GLOBALS['wp_query'] = new WP_Query();
	$GLOBALS['wp_query']->is_singular = true;
	$GLOBALS['wp_query']->queried_object = (object) [ 'ID' => 25, 'post_type' => 'product' ];
	$GLOBALS['wp_query']->queried_object_id = 25;
	$check( 'synthetic product route uses the native WooCommerce product conditional', is_product() );
	foreach ( [ 'section', 'main' ] as $tag ) {
		$source = '<' . $tag . ' class="kept" data-interaction-id="fcd870e"><p>متن محصول</p></' . $tag . '>';
		$result = $markup_filter( $source );
		$html = new WP_HTML_Tag_Processor( $result );
		$check( 'product template ' . $tag . ' keeps its content and gains the native skip target', $html->next_tag( strtoupper( $tag ) )
			&& 'main' === $html->get_attribute( 'id' ) && 'fcd870e' === $html->get_attribute( 'data-interaction-id' )
			&& $html->has_class( 'kept' ) && str_contains( $result, '<p>متن محصول</p>' ) );
	}
	$unrelated = '<main id="main" class="site-main"><p>قالب بومی محصول دیگر</p></main>';
	$check( 'unrelated native product roots stay unchanged', $unrelated === $markup_filter( $unrelated ) );
	$unrelated = '<div data-interaction-id="fcd870e">ناحیهٔ غیرمرتبط</div>';
	$check( 'unexpected product element tags stay unchanged', $unrelated === $markup_filter( $unrelated ) );
	$_GET = [ 'elementor-preview' => '35' ];
	$check( 'product Elementor preview stays unchanged', $source === $markup_filter( $source ) );
	$_GET = [];
	$GLOBALS['wp_query']->is_preview = true;
	$check( 'product WordPress preview stays unchanged', $source === $markup_filter( $source ) );
	$GLOBALS['wp_query']->is_preview = false;
	$GLOBALS['wp_query']->is_singular = false;
	$GLOBALS['wp_query']->queried_object = (object) [ 'ID' => 999, 'post_type' => 'page' ];
	$GLOBALS['wp_query']->queried_object_id = 999;
	$check( 'non-product pages keep matching product element IDs unchanged', ! is_product() && $source === $markup_filter( $source ) );
	foreach ( [ 132 => '1e5d7dad', 134 => '1af5b109', 136 => '3844fe47' ] as $page_id => $root_id ) {
		$GLOBALS['wp_query']->queried_object = (object) [ 'ID' => $page_id ];
		$GLOBALS['wp_query']->queried_object_id = $page_id;
		foreach ( [ 'article', 'main' ] as $tag ) {
			$source = '<' . $tag . ' class="kept" data-interaction-id="' . $root_id . '"><p>متن موجود</p></' . $tag . '>';
			$result = $markup_filter( $source );
			$html = new WP_HTML_Tag_Processor( $result );
			$check( $page_id . ' ' . $tag . ' keeps its root/content and gains the skip target', $html->next_tag( strtoupper( $tag ) )
				&& 'content' === $html->get_attribute( 'id' ) && $root_id === $html->get_attribute( 'data-interaction-id' )
				&& $html->has_class( 'kept' ) && str_contains( $result, '<p>متن موجود</p>' ) );
		}
		$unrelated = '<header data-interaction-id="shared-header">سرصفحه</header>';
		$check( $page_id . ' leaves unrelated components unchanged', $unrelated === $markup_filter( $unrelated ) );
		$_GET = [ 'elementor-preview' => (string) $page_id ];
		$check( $page_id . ' leaves the Elementor preview unchanged', $source === $markup_filter( $source ) );
		$_GET = [];
		$GLOBALS['wp_query']->is_preview = true;
		$check( $page_id . ' leaves the WordPress preview unchanged', $source === $markup_filter( $source ) );
		$GLOBALS['wp_query']->is_preview = false;
	}
	$GLOBALS['wp_query']->queried_object = (object) [ 'ID' => 999 ];
	$GLOBALS['wp_query']->queried_object_id = 999;
	$check( 'other public pages stay unchanged even with a matching policy element ID', $source === $markup_filter( $source ) );
	$route( 'page', 68 );
	$source = '<main data-interaction-id="3ffe5619"><!--artnovo-wizard-progress--><section data-interaction-id="599de042"></section>'
		. '<section data-interaction-id="4ef8e004"></section><section data-interaction-id="ad7be96"></section></main>';
	$html = new WP_HTML_Tag_Processor( $markup_filter( $source ) );
	$ids = [];
	while ( $html->next_tag() ) {
		if ( $html->get_attribute( 'id' ) ) { $ids[] = $html->get_attribute( 'id' ); }
	}
	$check( 'purchase skip target preserves all three purchase anchors', [ 'content', 'artnovo-categories', 'artnovo-plans', 'artnovo-order-stage' ] === $ids );
} finally {
	$GLOBALS['wp_query'] = $original_query;
	$_GET = $original_get;
}

remove_action( 'shutdown', '_wp_cron' );
// Persisting the Blocks asset cache is outside this in-memory cart contract.
foreach ( $GLOBALS['wp_filter']['shutdown']->callbacks ?? [] as $priority => $callbacks ) {
	foreach ( $callbacks as $hook ) {
		$callback = $hook['function'];
		if ( is_array( $callback ) && $callback[0] instanceof \Automattic\WooCommerce\Blocks\Assets\Api
			&& 'update_script_data_cache' === $callback[1] ) {
			remove_action( 'shutdown', $callback, $priority );
		}
	}
}

if ( $failures ) {
	WP_CLI::error( $failures . ' in-memory cart checks failed.' );
}

WP_CLI::success( ( $legacy ? 'Baseline' : 'Candidate' ) . ' in-memory cart checks passed.' );
