<?php

namespace Artnovo\AccountUI;

defined( 'ABSPATH' ) || exit;

function native_login_ready(): bool {
	return class_exists( '\\Pinova\\Pinova' ) && defined( 'PINOVA_DIR' )
		&& is_readable( PINOVA_DIR . '/templates/login-partial.php' );
}

function account_login_is_customer_return( string $destination ): bool {
	$path = rawurldecode( wp_parse_url( $destination, PHP_URL_PATH ) ?: '/' );
	$account = trailingslashit( wp_parse_url( wc_get_page_permalink( 'myaccount' ), PHP_URL_PATH ) ?: '/' );
	$checkout = trailingslashit( wp_parse_url( wc_get_page_permalink( 'checkout' ), PHP_URL_PATH ) ?: '/' );
	if ( rtrim( $path, '/' ) === rtrim( wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ?: '/', '/' )
		|| rtrim( $path, '/' ) === rtrim( $account, '/' ) ) {
		return true;
	}
	$endpoints = WC()->query->get_query_vars();
	if ( str_starts_with( $path, $account ) && ( str_contains( $path, '\\' ) || preg_match( '#(?:^|/)\.{1,2}(?:/|$)#', $path ) ) ) {
		return true;
	}
	foreach ( [ 'dashboard', 'vpn-subscriptions', 'view-order', 'customer-logout' ] as $endpoint ) {
		$prefix = $account . ( $endpoints[ $endpoint ] ?? $endpoint );
		if ( rtrim( $path, '/' ) === $prefix || str_starts_with( $path, $prefix . '/' ) ) {
			return true;
		}
	}
	foreach ( [ 'order-pay', 'order-received' ] as $endpoint ) {
		if ( empty( $endpoints[ $endpoint ] ) ) {
			continue;
		}
		$prefix = $checkout . $endpoints[ $endpoint ];
		if ( rtrim( $path, '/' ) === $prefix || str_starts_with( $path, $prefix . '/' ) ) {
			return true;
		}
	}
	return false;
}

add_filter( 'pinova/login_back_url', static function ( string $destination ): string {
	$resume = add_query_arg( 'artnovo_resume', '1', wc_get_page_permalink( 'myaccount' ) );
	// Native REST has no Woo session; the fresh account GET validates the draft after Woo boots.
	if ( $destination === $resume ) {
		return $resume;
	}
	// Native proof requests also validate customer returns; admin and third-party paths stay native.
	return account_login_is_customer_return( $destination ) ? account_auth_destination( $destination ) : $destination;
}, 11 );

function standalone_login_page(): bool {
	global $wp;
	if ( is_user_logged_in() || ! native_login_ready() || 'login' !== ( $wp->request ?? '' ) ) {
		return false;
	}
	$back = is_string( $_GET['back_url'] ?? null ) ? wp_unslash( $_GET['back_url'] ) : '';
	$target = wp_parse_url( $back );
	$site = wp_parse_url( home_url( '/' ) );
	if ( ! $target || isset( $target['user'] ) || isset( $target['pass'] )
		|| ( $target['scheme'] ?? '' ) !== ( $site['scheme'] ?? '' )
		|| ( $target['host'] ?? '' ) !== ( $site['host'] ?? '' )
		|| ( $target['port'] ?? null ) !== ( $site['port'] ?? null ) ) {
		return true;
	}
	// Pinova continues to own administrator and third-party destinations.
	return account_login_is_customer_return( $back );
}

function native_login_page(): bool {
	return ! is_user_logged_in() && ! account_email_login_page() && native_login_ready() && ( standalone_login_page() || custom_account_page() );
}

function account_email_login_page(): bool {
	return ! is_user_logged_in() && '1' === ( $_GET['artnovo_email_login'] ?? null )
		&& ( standalone_login_page() || custom_account_page() );
}

add_action( 'template_redirect', static function (): void {
	$email = account_email_login_page();
	if ( ! $email && ! native_login_page() ) {
		return;
	}
	global $wp_query;
	if ( standalone_login_page() ) {
		$wp_query->is_404 = false;
		status_header( 200 );
	}
	nocache_headers();
	header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	if ( class_exists( '\\Pinova\\Pinova' ) ) {
		remove_action( 'template_redirect', [ \Pinova\Pinova::instance(), 'handle_urls' ] );
	}
	if ( $email ) {
		return;
	}
	$state = account_auth_state();
	$destination = account_login_destination( $state );
	$back = is_string( $_GET['back_url'] ?? null ) ? wp_unslash( $_GET['back_url'] ) : '';
	if ( 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && $back !== $destination ) {
		$page = standalone_login_page() ? home_url( '/login/' ) : wc_get_page_permalink( 'myaccount' );
		wp_safe_redirect( add_query_arg( 'back_url', $destination, $page ) );
		exit;
	}
}, 3 );

add_filter( 'wp_robots', static function ( array $robots ): array {
	if ( native_login_page() || account_email_login_page() ) {
		$robots['noindex'] = true;
		$robots['nofollow'] = true;
	}
	return $robots;
} );

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( ! is_user_logged_in() && custom_account_page() ) {
		wp_enqueue_style( 'artnovo-standalone-login', plugins_url( 'standalone-login.css', __FILE__ ), [ 'artnovo-account-ui' ], (string) filemtime( __DIR__ . '/standalone-login.css' ) );
	}
} );

function native_login_markup( string $html ): string {
	$tags = new \WP_HTML_Tag_Processor( $html );
	$step = '';
	$label = false;
	$button = false;
	$policy = false;
	$policy_link = 0;
	while ( $tags->next_token() ) {
		if ( '#tag' === $tags->get_token_type() && ! $tags->is_tag_closer() ) {
			if ( 'DIV' === $tags->get_tag() && is_string( $tags->get_attribute( 'id' ) ) ) {
				$step = $tags->get_attribute( 'id' );
			}
			if ( 'signIn' === $step && 'A' === $tags->get_tag() && '#' === $tags->get_attribute( 'href' ) && $policy_link < 2 ) {
				$url = 0 === $policy_link ? wc_get_page_permalink( 'terms' ) : get_privacy_policy_url();
				if ( $url ) {
					$tags->set_attribute( 'href', $url );
				} else {
					$tags->remove_attribute( 'href' );
				}
				$policy = 1 === $policy_link++;
			}
			if ( 'authenticate' !== $step ) {
				continue;
			}
			if ( 'LABEL' === $tags->get_tag() ) {
				$tags->set_attribute( 'for', 'artnovo-native-identifier' );
				$label = true;
			} elseif ( 'INPUT' === $tags->get_tag() && 'forms.authenticate.inputs.identifier.value' === $tags->get_attribute( 'pinova-model' ) ) {
				$tags->set_attribute( 'id', 'artnovo-native-identifier' );
				$tags->set_attribute( 'required', true );
				$tags->set_attribute( 'autocomplete', 'username' );
				$tags->set_attribute( 'dir', 'ltr' );
				$tags->set_attribute( 'placeholder', '' );
			} elseif ( 'BUTTON' === $tags->get_tag() && $tags->has_class( 'artnovo-native-primary' ) ) {
				$button = true;
			}
		} elseif ( '#text' === $tags->get_token_type() && '' !== trim( $tags->get_modifiable_text() ) ) {
			if ( $policy ) {
				$tags->set_modifiable_text( 'حریم خصوصی' );
				$policy = false;
			} elseif ( $label ) {
				$tags->set_modifiable_text( 'شماره موبایل، ایمیل یا نام کاربری را وارد کنید ' );
				$label = false;
			} elseif ( $button ) {
				$tags->set_modifiable_text( 'ادامه' );
				$button = false;
			}
		}
	}
	return $tags->get_updated_html();
}

function render_standalone_login(): void {
	$state = account_auth_state();
	$email_url = add_query_arg( [ 'artnovo_email_login' => '1', 'back_url' => account_login_destination( $state ) ], wc_get_page_permalink( 'myaccount' ) );
	$form_wrapper_classes = 'artnovo-native-step';
	$submit_button_classes = 'artnovo-native-primary';
	ob_start();
	include PINOVA_DIR . '/templates/login-partial.php';
	$native = ob_get_clean();
	?>
	<section class="artnovo-standalone-login" aria-labelledby="artnovo-account-login" dir="rtl">
		<div class="artnovo-login-symbol" aria-hidden="true">آ</div>
		<h1 id="artnovo-account-login">ورود به حساب آرت‌نوو</h1>
		<p class="artnovo-login-hint">اشتراک‌ها و اطلاعات اتصال شما در این حساب نگهداری می‌شود.</p>
		<?php echo native_login_markup( $native ); ?>
		<a class="artnovo-login-alternative" href="<?php echo esc_url( $email_url ); ?>">ورود و ثبت‌نام با کد ایمیلی</a>
		<a class="artnovo-login-return" href="<?php echo esc_url( home_url( '/#artnovo-categories' ) ); ?>">بازگشت به انتخاب اشتراک</a>
	</section>
	<?php
}
