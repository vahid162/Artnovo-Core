<?php

namespace Artnovo\Core {
	function display_number_html( string $html, bool $price = false ): string {
		return ( $price ? 'price:' : '' ) . $html;
	}
}

namespace {
	define( 'ABSPATH', '/isolated/' );
	final class WP_Term {
		public function __construct( public string $slug ) {}
	}
	final class WP_Post {
		public function __construct( public string $post_name, public int $post_parent = 0 ) {}
	}
	final class PublicPagesCart {
		public function is_empty(): bool { return $GLOBALS['cart_empty']; }
	}

	function add_action( string $hook, callable $action, int $priority = 10, int $accepted = 1 ): void {
		$GLOBALS['hooks'][ $hook ][ $priority ] = $action;
	}
	function add_filter( string $hook, callable $filter, int $priority = 10, int $accepted = 1 ): void {
		add_action( $hook, $filter, $priority, $accepted );
	}
	function route_is( string $view ): bool { return ( $GLOBALS['route'] ?? '' ) === $view; }
	function is_admin(): bool { return route_is( 'admin' ); }
	function wp_doing_ajax(): bool { return route_is( 'ajax' ); }
	function is_preview(): bool { return route_is( 'preview' ); }
	function is_customize_preview(): bool { return route_is( 'customizer' ); }
	function is_feed(): bool { return route_is( 'feed' ); }
	function is_embed(): bool { return route_is( 'embed' ); }
	function is_shop(): bool { return route_is( 'shop' ); }
	function is_product_category( array $terms ): bool {
		return route_is( 'category' ) && in_array( get_queried_object()->slug, $terms, true );
	}
	function is_cart(): bool { return route_is( 'cart' ); }
	function WC(): object { return (object) [ 'cart' => new PublicPagesCart() ]; }
	function is_search(): bool { return route_is( 'search' ); }
	function is_home(): bool { return route_is( 'blog' ); }
	function is_front_page(): bool { return route_is( 'front' ); }
	function is_page(): bool { return route_is( 'front' ) || route_is( 'page' ); }
	function is_404(): bool { return route_is( '404' ); }
	function get_queried_object(): object { return $GLOBALS['queried']; }
	function post_password_required( WP_Post $post ): bool { return $GLOBALS['password_required'] ?? false; }
	function in_the_loop(): bool { return $GLOBALS['in_loop'] ?? false; }

	require dirname( __DIR__ ) . '/modules/public-pages/public-pages.php';

	function expect_view( string $route, string $wanted ): void {
		$GLOBALS['route'] = $route;
		$actual = \Artnovo\PublicPages\view();
		if ( $wanted !== $actual ) {
			throw new RuntimeException( "Incorrect view for {$route}: {$actual}; expected {$wanted}" );
		}
	}

	expect_view( 'shop', 'shop' );
	foreach ( [ 'vpn-one-month', 'vpn-two-month' ] as $slug ) {
		$GLOBALS['queried'] = new WP_Term( $slug );
		expect_view( 'category', $slug );
	}
	$GLOBALS['queried'] = new WP_Term( 'other' );
	expect_view( 'category', '' );

	$GLOBALS['cart_empty'] = true;
	foreach ( [ 'GET', 'HEAD' ] as $method ) {
		$_SERVER['REQUEST_METHOD'] = $method;
		expect_view( 'cart', 'cart' );
	}
	$_SERVER['REQUEST_METHOD'] = 'POST';
	expect_view( 'cart', '' );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$GLOBALS['cart_empty'] = false;
	expect_view( 'cart', '' );

	expect_view( 'search', 'search' );
	expect_view( 'blog', 'blog' );
	expect_view( '404', 'not-found' );
	$GLOBALS['queried'] = new WP_Post( 'home' );
	expect_view( 'page', 'old-home' );
	expect_view( 'front', '' );
	$GLOBALS['queried'] = new WP_Post( 'home', 12 );
	expect_view( 'page', '' );
	$GLOBALS['queried'] = new WP_Post( rawurlencode( 'خرید-اشتراک-vpn-آرتنوو' ) );
	expect_view( 'page', 'old-buy' );
	$GLOBALS['password_required'] = true;
	expect_view( 'page', '' );
	foreach ( [ 'admin', 'ajax', 'preview', 'customizer', 'feed', 'embed', 'payment', 'account' ] as $route ) {
		expect_view( $route, '' );
	}
	$_GET['elementor-preview'] = '2';
	expect_view( 'shop', '' );
	unset( $_GET['elementor-preview'] );

	$GLOBALS['route'] = 'shop';
	$choose_template = $GLOBALS['hooks']['template_include'][90];
	$price_html = $GLOBALS['hooks']['woocommerce_get_price_html'][30];
	if ( '/native/archive.php' !== $choose_template( '/native/archive.php' ) || '123 تومان' !== $price_html( '123 تومان' ) ) {
		throw new RuntimeException( 'A catalog page must retain the native archive template and unscoped price output.' );
	}
	$GLOBALS['in_loop'] = true;
	if ( 'price:123 تومان' !== $price_html( '123 تومان' ) ) {
		throw new RuntimeException( 'Only visible catalog prices should use the catalog number adapter.' );
	}
	$GLOBALS['route'] = 'search';
	if ( ! str_ends_with( $choose_template( '/native/search.php' ), '/public-page.php' ) || '123 تومان' !== $price_html( '123 تومان' ) ) {
		throw new RuntimeException( 'Search should use its own shell without globally altering WooCommerce prices.' );
	}
	echo "Public page routing and native fallbacks passed.\n";
}
