<?php

defined( 'ABSPATH' ) || exit;

add_filter( 'hello_elementor_skip_link_url', static function ( string $url ): string {
	return function_exists( 'is_woocommerce' ) && is_woocommerce() ? '#main' : $url;
} );

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( 'publish' !== get_post_status( 75 ) ) {
		return;
	}
	$file = 'artnovo-header.js';
	wp_enqueue_script( 'artnovo-header-ui', plugins_url( $file, __FILE__ ), [], (string) filemtime( __DIR__ . '/' . $file ), true );
} );
