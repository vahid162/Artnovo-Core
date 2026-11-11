<?php

defined( 'ABSPATH' ) || exit;

add_filter( 'wp_mail_from', static function ( string $address ): string {
	return 0 === strcasecmp( $address, 'wordpress@artnovo.ir' ) ? 'orders@artnovo.ir' : $address;
} );
