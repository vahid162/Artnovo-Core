<?php

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( 'publish' !== get_post_status( 75 ) ) {
		return;
	}
	$file = 'artnovo-header.js';
	wp_enqueue_script( 'artnovo-header-ui', plugins_url( $file, __FILE__ ), [], (string) filemtime( __DIR__ . '/' . $file ), true );
} );
