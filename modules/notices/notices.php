<?php

namespace Artnovo\Notices;

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( is_admin() ) {
		return;
	}
	wp_enqueue_style( 'artnovo-notices', plugins_url( 'notices.css', __FILE__ ), [], (string) filemtime( __DIR__ . '/notices.css' ) );
	wp_enqueue_script( 'artnovo-notices', plugins_url( 'notices.js', __FILE__ ), [ 'jquery' ], (string) filemtime( __DIR__ . '/notices.js' ), true );
}, 30 );
