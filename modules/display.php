<?php

namespace Artnovo\Core;

defined( 'ABSPATH' ) || exit;

/** Display only: never use this for identifiers, form values or financial arithmetic. */
function display_digits( string $text ): string {
	return strtr( $text, array_combine(
		preg_split( '//u', '0123456789٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY ),
		preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY )
	) );
}

/** Format controlled human-readable fragments, keeping markup/attributes and technical text intact. */
function display_number_html( string $html, bool $price = false ): string {
	if ( ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}
	$processor = new \WP_HTML_Tag_Processor( $html );
	$protected = [];
	$tags = [ 'SCRIPT', 'STYLE', 'TEXTAREA', 'INPUT', 'CODE', 'PRE', 'KBD', 'SAMP', 'SVG' ];
	while ( $processor->next_token() ) {
		$tag = $processor->get_tag();
		if ( $tag && in_array( $tag, $tags, true ) ) {
			if ( $processor->is_tag_closer() ) {
				array_pop( $protected );
			} elseif ( 'INPUT' !== $tag ) {
				$protected[] = $tag;
			}
		}
		if ( $protected || '#text' !== $processor->get_token_name() ) {
			continue;
		}
		$text = $processor->get_modifiable_text();
		$parts = preg_split( '~((?:https?://|www\.)[^\s<>]+|[^\s<>]+@[^\s<>]+)~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $parts as $index => &$part ) {
			if ( 1 === $index % 2 ) {
				continue;
			}
			if ( $price ) {
				$separators = [ wc_get_price_thousand_separator() => '٬', wc_get_price_decimal_separator() => '٫' ];
				foreach ( $separators as $separator => $replacement ) {
					if ( '' !== (string) $separator ) {
						$part = preg_replace( '/(?<=[0-9٠-٩۰-۹])' . preg_quote( (string) $separator, '/' ) . '(?=[0-9٠-٩۰-۹])/u', $replacement, $part );
					}
				}
			}
			$part = display_digits( $part );
		}
		unset( $part );
		$processor->set_modifiable_text( implode( '', $parts ) );
	}
	return $processor->get_updated_html();
}

function display_customer_context(): bool {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	return ( function_exists( '\Artnovo\HomeOrderUI\Wizard\is_page' ) && \Artnovo\HomeOrderUI\Wizard\is_page() )
		|| ( function_exists( 'is_account_page' ) && is_account_page() )
		|| ( function_exists( '\Artnovo\PlanCheckout\wizard_cart_context' ) && \Artnovo\PlanCheckout\wizard_cart_context() );
}

// wc_price returns a display fragment; native amounts, validation and totals stay numeric.
add_filter( 'wc_price', static function ( string $html ): string {
	return display_customer_context() ? display_number_html( $html, true ) : $html;
}, 30 );

// Native Elementor product-title bindings use the_title; never filter WC_Product's stored name.
add_filter( 'the_title', static function ( string $title, int $post_id ): string {
	if ( function_exists( '\Artnovo\HomeOrderUI\Wizard\is_page' ) && \Artnovo\HomeOrderUI\Wizard\is_page()
		&& function_exists( '\Artnovo\PlanCheckout\is_plan' ) && \Artnovo\PlanCheckout\is_plan( wc_get_product( $post_id ) ) ) {
		return display_digits( $title );
	}
	return $title;
}, 30, 2 );
