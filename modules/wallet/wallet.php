<?php

namespace Artnovo\WalletUI;

use ArtNovo\PayBridge\Topup;
use function Artnovo\PlanCheckout\checkout_post_value;
use function Artnovo\PlanCheckout\wizard_otp_key;
use function Artnovo\PlanCheckout\wizard_otp_locked;

defined( 'ABSPATH' ) || exit;

const TOPUP_ORDER_ROUTE = '/terawallet/v1/me/topup';
const TOPUP_PAY_ROUTE = '/artnovo-pay-bridge/v1/wallet-topup/payment';

function account_screen(): string {
	global $wp;
	if ( ! is_user_logged_in() || ! function_exists( 'WC' ) || ! WC()->query || 'woo-wallet' !== WC()->query->get_current_endpoint()
		|| ! function_exists( 'is_wallet_account_locked' ) || is_wallet_account_locked() ) {
		return '';
	}
	$value = $_GET['wallet_action'] ?? $wp->query_vars['woo-wallet'] ?? '';
	$screen = is_string( $value ) ? sanitize_key( wp_unslash( $value ) ) : '';
	return in_array( $screen, [ 'add', 'transfer', 'statement' ], true ) ? $screen : ( '' === $screen || 'dashboard' === $screen ? 'dashboard' : '' );
}

function account_view(): bool {
	return '' !== account_screen() || (bool) routed_order( 'view-order' );
}

function wallet_url( string $screen = '' ): string {
	if ( '' === $screen && function_exists( 'woo_wallet_get_dashboard_url' ) ) {
		return woo_wallet_get_dashboard_url();
	}
	if ( 'add' === $screen && function_exists( 'woo_wallet_get_topup_url' ) ) {
		return woo_wallet_get_topup_url();
	}
	return wc_get_endpoint_url( 'woo-wallet', $screen, wc_get_page_permalink( 'myaccount' ) );
}

function has_recharge_product(): bool {
	$id = absint( get_option( '_woo_wallet_recharge_product' ) );
	// The native detection helpers would recreate a missing product when called.
	return $id > 0 && wc_get_product( $id ) instanceof \WC_Product;
}

function owns_topup( mixed $order ): bool {
	return $order instanceof \WC_Order && get_current_user_id() > 0
		&& get_current_user_id() === $order->get_customer_id()
		&& function_exists( 'is_wallet_rechargeable_order' )
		&& ! \Artnovo\Core\is_service_order( $order )
		&& ( in_array( $order->get_meta( '_wallet_rechargeable_order' ), [ true, 1, '1' ], true )
			|| ( has_recharge_product() && is_wallet_rechargeable_order( $order ) ) );
}

function direct_topup_available(): bool {
	if ( ! is_callable( [ Topup::class, 'can_start' ] ) || ! is_callable( [ Topup::class, 'minimum_amount' ] )
		|| ! defined( 'ArtNovo\\PayBridge\\METHOD' ) || ! Topup::can_start() ) {
		return false;
	}
	$routes = rest_get_server()->get_routes();
	return isset( $routes[ TOPUP_ORDER_ROUTE ], $routes[ TOPUP_PAY_ROUTE ] );
}

function wallet_result_url( \WC_Order $order ): string {
	return add_query_arg( [ 'artnovo_topup' => $order->get_id(), 'key' => $order->get_order_key() ], wallet_url() );
}

function keyed_wallet_topup_order( mixed $id, mixed $key ): \WC_Order|false {
	if ( ! is_string( $id ) || ! ctype_digit( $id ) || ! is_string( $key ) || '' === $key ) {
		return false;
	}
	$order = wc_get_order( (int) $id );
	return owns_topup( $order ) && hash_equals( $order->get_order_key(), wp_unslash( $key ) ) ? $order : false;
}

function wallet_result_order(): \WC_Order|false {
	return 'dashboard' === account_screen()
		? keyed_wallet_topup_order( $_GET['artnovo_topup'] ?? null, $_GET['key'] ?? null )
		: false;
}

add_filter( 'artnovo_gpante_topup_return_url', static function ( string $url, \WC_Order $order ): string {
	return owns_topup( $order ) ? wallet_result_url( $order ) : $url;
}, 10, 2 );

function topup_pay( \WC_Order $order, string $amount, bool $terms ): mixed {
	$request = new \WP_REST_Request( 'POST', TOPUP_PAY_ROUTE );
	$request->set_body_params( [
		'order_id' => $order->get_id(), 'order_key' => $order->get_order_key(),
		'amount' => $amount, 'currency' => $order->get_currency(), 'terms' => $terms,
	] );
	return rest_do_request( $request );
}

function start_direct_topup( string $intent, string $amount, bool $terms ): mixed {
	$currency = get_woocommerce_currency();
	if ( ! wp_is_uuid( $intent, 4 ) || ! preg_match( '/^[1-9][0-9]{0,14}$/D', $amount )
		|| (int) $amount < Topup::minimum_amount( $currency ) ) {
		return new \WP_Error( 'artnovo_topup_amount', 'مبلغ شارژ را در محدودهٔ مجاز وارد کنید.' );
	}

	$key = hash( 'sha256', $intent . ':' . $currency . ':' . $amount );
	// The native idempotency cache handles replays. Serialize its initial check and insert.
	$created = wizard_otp_locked( wizard_otp_key( 'wallet-topup', get_current_user_id() . ':' . $key ), static function () use ( $key, $amount, $currency ) {
		$request = new \WP_REST_Request( 'POST', TOPUP_ORDER_ROUTE );
		$request->set_header( 'Idempotency-Key', $key );
		$request->set_body_params( [ 'amount' => $amount, 'currency' => $currency, 'payment_method' => \ArtNovo\PayBridge\METHOD ] );
		return rest_do_request( $request );
	} );
	if ( is_wp_error( $created ) || $created->is_error() ) {
		return $created;
	}
	$data = $created->get_data();
	$order = wc_get_order( absint( $data['order_id'] ?? 0 ) );
	if ( ! owns_topup( $order ) || $order->get_currency() !== $currency ) {
		return new \WP_Error( 'artnovo_topup_order', 'سفارش شارژ قابل پیگیری نیست.' );
	}
	return topup_pay( $order, $amount, $terms );
}

function render_direct_topup_form( \WC_Order|false $retry = false, string $retry_label = 'پرداخت دوباره' ): void {
	$currency = get_woocommerce_currency();
	$settings = woo_wallet()->settings_api;
	$minimum = max( 1, Topup::minimum_amount( $currency ), (int) ceil( (float) $settings->get_option( 'min_topup_amount', '_wallet_settings_general', 0 ) ) );
	$maximum = (int) floor( (float) $settings->get_option( 'max_topup_amount', '_wallet_settings_general', 0 ) );
	$intent = checkout_post_value( 'artnovo_topup_intent' );
	?>
	<div class="woo-wallet-form-wrapper">
		<form method="post" action="<?php echo esc_url( $retry ? wallet_result_url( $retry ) : wallet_url( 'add' ) ); ?>">
			<?php if ( $retry ) : ?>
				<input type="hidden" name="artnovo_topup_retry" value="<?php echo esc_attr( $retry->get_id() ); ?>">
			<?php else : ?>
				<input type="hidden" name="artnovo_topup_intent" value="<?php echo esc_attr( wp_is_uuid( $intent, 4 ) ? $intent : wp_generate_uuid4() ); ?>">
				<label for="woo_wallet_balance_to_add">مبلغ شارژ به <?php echo esc_html( get_woocommerce_currency_symbol() ); ?></label>
				<input type="number" id="woo_wallet_balance_to_add" name="artnovo_topup_amount" inputmode="numeric" step="1" min="<?php echo esc_attr( $minimum ); ?>" <?php if ( $maximum > 0 ) : ?>max="<?php echo esc_attr( $maximum ); ?>"<?php endif; ?> value="<?php echo esc_attr( checkout_post_value( 'artnovo_topup_amount' ) ); ?>" required>
			<?php endif; ?>
			<?php wp_nonce_field( 'artnovo_wallet_topup', 'artnovo_topup_nonce', false ); ?>
			<?php if ( wc_terms_and_conditions_checkbox_enabled() ) : ?>
				<label><input type="checkbox" name="artnovo_topup_terms" value="1" required> <?php wc_terms_and_conditions_checkbox_text(); ?></label>
			<?php endif; ?>
			<?php wc_checkout_privacy_policy_text(); ?>
			<button type="submit" class="artnovo-wallet-primary" <?php if ( ! $retry ) : ?>data-artnovo-topup-cta<?php endif; ?>><?php echo esc_html( $retry ? $retry_label : 'پرداخت شارژ' ); ?></button>
		</form>
	</div>
	<?php
}

function routed_order( string $endpoint ): \WC_Order|false {
	if ( ! get_current_user_id() || ! function_exists( 'is_wallet_rechargeable_order' )
		|| ! function_exists( 'WC' ) || ! WC()->query || $endpoint !== WC()->query->get_current_endpoint() ) {
		return false;
	}
	$id = get_query_var( $endpoint );
	if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id < 1 ) {
		return false;
	}
	$order = wc_get_order( (int) $id );
	if ( ! owns_topup( $order ) ) {
		return false;
	}
	if ( 'view-order' !== $endpoint ) {
		$key = $_GET['key'] ?? null;
		if ( ! is_string( $key ) || '' === $key || ! hash_equals( $order->get_order_key(), wp_unslash( $key ) ) ) {
			return false;
		}
	}
	if ( 'order-pay' === $endpoint && ! $order->needs_payment() ) {
		return false;
	}
	return $order;
}

function topup_checkout(): bool {
	return is_user_logged_in() && function_exists( 'is_wallet_rechargeable_cart' ) && function_exists( 'is_checkout' )
		&& is_checkout() && is_page( wc_get_page_id( 'checkout' ) ) && ! is_wc_endpoint_url()
		&& WC()->cart && 1 === count( WC()->cart->get_cart() )
		&& has_recharge_product() && is_wallet_rechargeable_cart();
}

function price( mixed $amount, string $currency = '' ): string {
	$args = [ 'price_format' => '%2$s&nbsp;%1$s' ];
	if ( '' !== $currency ) {
		$args['currency'] = $currency;
	}
	return \Artnovo\Core\display_number_html( wp_kses( wc_price( $amount, $args ), wp_kses_allowed_html( 'post' ) + [ 'bdi' => [] ] ), true );
}

function checkout_heading(): string {
	ob_start();
	?>
	<header class="artnovo-wallet-checkout-heading">
		<a class="artnovo-wallet-back" href="<?php echo esc_url( wallet_url( 'add' ) ); ?>">ویرایش مبلغ شارژ</a>
		<h2>پرداخت شارژ کیف پول</h2>
	</header>
	<?php
	return (string) ob_get_clean();
}

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( ! account_view() && ! topup_checkout() && ! routed_order( 'order-pay' ) && ! routed_order( 'order-received' ) ) {
		return;
	}
	wp_enqueue_style( 'artnovo-wallet-ui', plugins_url( 'wallet.css', __FILE__ ), [], (string) filemtime( __DIR__ . '/wallet.css' ) );
	if ( in_array( account_screen(), [ 'add', 'transfer' ], true ) || wallet_result_order() ) {
		wp_enqueue_script( 'artnovo-wallet-ui', plugins_url( 'wallet.js', __FILE__ ), [], (string) filemtime( __DIR__ . '/wallet.js' ), true );
	}
}, 30 );

add_action( 'template_redirect', static function (): void {
	$screen = account_screen();
	$wallet_return = 'dashboard' === $screen && isset( $_GET['artnovo_topup'] );
	if ( $wallet_return ) {
		header( 'Referrer-Policy: strict-origin' );
	}
	if ( 'add' === $screen || $wallet_return || routed_order( 'order-pay' ) || topup_checkout() ) {
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	}
	if ( 'add' !== $screen && ! $wallet_return ) {
		return;
	}
	$available = direct_topup_available();
	if ( 'add' === $screen && $available && is_callable( [ '\Woo_Wallet_Frontend', 'instance' ] ) ) {
		$native = [ \Woo_Wallet_Frontend::instance(), 'woo_wallet_add_content' ];
		$priority = has_action( 'woo_wallet_add_content', $native );
		if ( false !== $priority && remove_action( 'woo_wallet_add_content', $native, $priority ) ) {
			add_action( 'woo_wallet_add_content', __NAMESPACE__ . '\\render_direct_topup_form', $priority, 0 );
		}
	}
	$intent = checkout_post_value( 'artnovo_topup_intent' );
	$retry = checkout_post_value( 'artnovo_topup_retry' );
	if ( ( 'add' !== $screen || '' === $intent ) && ( ! $wallet_return || '' === $retry ) ) {
		return;
	}
	if ( ! $available || ! wp_verify_nonce( checkout_post_value( 'artnovo_topup_nonce' ), 'artnovo_wallet_topup' )
		|| ! apply_filters( 'woo_wallet_is_enable_add', true ) ) {
		wc_add_notice( 'فرم شارژ را تازه کنید و دوباره تلاش کنید.', 'error' );
		return;
	}
	$terms = '1' === checkout_post_value( 'artnovo_topup_terms' );
	if ( wc_terms_and_conditions_checkbox_enabled() && ! $terms ) {
		wc_add_notice( 'برای ادامه، شرایط خرید را تأیید کنید.', 'error' );
		return;
	}
	if ( $wallet_return ) {
		$order = wallet_result_order();
		if ( ! $order || $retry !== (string) $order->get_id() ) {
			return;
		}
		$result = topup_pay( $order, preg_replace( '/\.0+$/D', '', (string) $order->get_total( 'edit' ) ), $terms );
	} else {
		$result = start_direct_topup( $intent, checkout_post_value( 'artnovo_topup_amount' ), $terms );
	}
	if ( is_wp_error( $result ) ) {
		wc_add_notice( $result->get_error_message(), 'error' );
		return;
	}
	$data = $result->get_data();
	if ( ! $result->is_error() && is_string( $data['redirect'] ?? null ) && '' !== $data['redirect'] ) {
		// Pay Bridge validates the bank handoff or returns the owned WooCommerce result URL.
		wp_redirect( $data['redirect'], 303 );
		exit;
	}
	wc_add_notice( $data['message'] ?? 'اتصال به درگاه ممکن نشد. همین درخواست شارژ را دوباره پیگیری کنید.', 'error' );
}, 6 );

add_filter( 'body_class', static function ( array $classes ): array {
	if ( routed_order( 'order-received' ) ) {
		$classes[] = 'artnovo-wallet-received-page';
	}
	return $classes;
} );
add_filter( 'document_title_parts', static function ( array $parts ): array {
	if ( routed_order( 'order-received' ) ) {
		$parts['title'] = 'نتیجهٔ شارژ کیف پول';
	}
	return $parts;
}, 20 );

$wallet_content = false;
add_action( 'woo_wallet_before_my_wallet_content', static function () use ( &$wallet_content ): void {
	$screen = account_screen();
	if ( '' === $screen ) {
		return;
	}
	$wallet_content = true;
	$titles = [ 'dashboard' => 'کیف پول', 'add' => 'شارژ کیف پول', 'transfer' => 'انتقال موجودی', 'statement' => 'صورت‌حساب کیف پول' ];
	?>
	<section class="artnovo-wallet-page" dir="rtl" data-artnovo-wallet-screen="<?php echo esc_attr( $screen ); ?>" data-currency="<?php echo esc_attr( get_woocommerce_currency() ); ?>" data-currency-label="<?php echo esc_attr( html_entity_decode( wp_strip_all_tags( get_woocommerce_currency_symbol() ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); ?>" aria-labelledby="artnovo-wallet-title">
		<header class="artnovo-wallet-heading">
			<?php if ( 'dashboard' !== $screen ) : ?><a class="artnovo-wallet-back" href="<?php echo esc_url( wallet_url() ); ?>">بازگشت به کیف پول</a><?php endif; ?>
			<h1 id="artnovo-wallet-title"><?php echo esc_html( $titles[ $screen ] ); ?></h1>
		</header>
	<?php
	if ( $topup = wallet_result_order() ) {
		render_topup_result( $topup, true );
	}
}, 5 );
add_action( 'woo_wallet_after_my_wallet_content', static function () use ( &$wallet_content ): void {
	if ( $wallet_content ) {
		echo '</section>';
		$wallet_content = false;
	}
}, PHP_INT_MAX );
add_filter( 'gettext_woo-wallet', static function ( string $translation, string $original ) use ( &$wallet_content ): string {
	if ( ! $wallet_content ) {
		return $translation;
	}
	$labels = [
		'Total Balance' => 'موجودی قابل استفاده',
		'Wallet topup' => 'شارژ کیف پول',
		'Wallet transfer' => 'انتقال موجودی',
		'Statement' => 'صورت‌حساب',
		'Account statement' => 'صورت‌حساب کیف پول',
		'Download CSV' => 'دریافت CSV',
		'Print' => 'چاپ صورت‌حساب',
		'Update' => 'نمایش',
		'Recent transactions' => 'تراکنش‌های اخیر',
		'No transactions found' => 'هنوز تراکنشی ثبت نشده است.',
		'Enter amount' => 'مبلغ شارژ',
		'Add Funds' => 'ادامهٔ پرداخت',
		'Select Recipient' => 'گیرنده',
		'(Email)' => '(ایمیل)',
		'Amount' => 'مبلغ',
		'What\'s this for?' => 'یادداشت (اختیاری)',
		'Optional note...' => 'یادداشت اختیاری',
		'Proceed to Transfer' => 'انتقال موجودی',
		'Date' => 'تاریخ',
		'Type' => 'نوع تراکنش',
		'Details' => 'شرح',
		'Credit' => 'واریز',
		'Debit' => 'برداشت',
		'Balance' => 'موجودی',
		'From' => 'از تاریخ',
		'To' => 'تا تاریخ',
		'Opening' => 'موجودی آغاز دوره',
		'Credited' => 'مجموع واریز',
		'Debited' => 'مجموع برداشت',
		'Closing' => 'موجودی پایان دوره',
		'Opening balance' => 'موجودی آغاز دوره',
		'Closing balance' => 'موجودی پایان دوره',
		'Brought forward' => 'موجودی از صفحهٔ قبل',
		'Carried forward' => 'موجودی برای صفحهٔ بعد',
		'No transactions in these dates. The balance stayed at %s throughout.' => 'در این بازه تراکنشی ثبت نشده است. موجودی در تمام بازه %s بود.',
		'Previous' => 'قبلی',
		'Next' => 'بعدی',
		'Page %1$s of %2$s — %3$s transactions in this period' => 'صفحهٔ %1$s از %2$s، %3$s تراکنش در این بازه',
		'Generated on %s' => 'تاریخ تهیهٔ صورت‌حساب %s',
	];
	return $labels[ $original ] ?? $translation;
}, 10, 2 );

// Keep the original Checkout block and Store API. Only add product-specific context.
add_filter( 'render_block_woocommerce/checkout', static function ( string $html ): string {
	return topup_checkout() ? '<section class="artnovo-wallet-checkout" dir="rtl">' . checkout_heading() . $html . '</section>' : $html;
} );

$legacy_checkout = false;
add_action( 'woocommerce_before_checkout_form', static function () use ( &$legacy_checkout ): void {
	if ( topup_checkout() && ! has_block( 'woocommerce/checkout' ) ) {
		$legacy_checkout = true;
		echo '<section class="artnovo-wallet-checkout artnovo-wallet-legacy-checkout" dir="rtl">' . checkout_heading();
	}
}, 10 );
add_action( 'woocommerce_after_checkout_form', static function () use ( &$legacy_checkout ): void {
	if ( $legacy_checkout ) {
		echo '</section>';
		$legacy_checkout = false;
	}
}, 10 );

function credited_transaction( \WC_Order $order ): ?object {
	$id = absint( $order->get_meta( '_wallet_payment_transaction_id' ) );
	if ( ! $id || ! $order->get_meta( '_wc_wallet_purchase_credited' ) || ! function_exists( 'get_wallet_transaction' )
		|| ! $order->is_paid() || (float) $order->get_total_refunded() > 0 ) {
		return null;
	}
	$entry = get_wallet_transaction( $id );
	return is_object( $entry ) && $order->get_customer_id() === (int) ( $entry->user_id ?? 0 )
		&& 'credit' === ( $entry->type ?? '' ) && empty( $entry->deleted )
		&& (float) ( $entry->amount ?? 0 ) > 0 && ! empty( $entry->currency ) ? $entry : null;
}

function topup_state( \WC_Order $order, ?object $entry, bool $checking_bank = false ): array {
	return match ( true ) {
		$order->has_status( 'cancelled' ) => [ 'closed', 'شارژ کیف پول لغو شد', 'وضعیت سفارش را در جزئیات خرید ببینید.' ],
		$order->has_status( 'refunded' ) || (float) $order->get_total_refunded() > 0 => [ 'closed', 'برای این شارژ بازپرداخت ثبت شد', 'ریز مبلغ و وضعیت سفارش در جزئیات خرید در دسترس است.' ],
		! $checking_bank && $order->has_status( 'failed' ) => [ 'failed', 'پرداخت شارژ ناموفق بود', 'پرداخت این سفارش تکمیل نشد.' ],
		null !== $entry => [ 'credited', 'شارژ کیف پول انجام شد', 'مبلغ شارژ به کیف پول اضافه شد.' ],
		$order->is_paid() && $order->get_meta( '_wc_wallet_purchase_credited' ) => [ 'closed', 'پرداخت شارژ ثبت شد', 'موجودی و تراکنش‌های ثبت‌شده را در کیف پول ببینید.' ],
		$order->is_paid() => [ 'pending', 'شارژ در انتظار ثبت در کیف پول است', 'پرداخت تأیید شده است. وضعیت ثبت شارژ را پیگیری کنید.' ],
		default => [ 'pending', 'شارژ در انتظار تأیید پرداخت است', 'پس از تأیید پرداخت، وضعیت شارژ به‌روز می‌شود.' ],
	};
}

function render_amount( string $label, string $html ): void {
	?>
	<div class="artnovo-wallet-amount"><span><?php echo esc_html( $label ); ?></span><strong><?php echo $html; ?></strong></div>
	<?php
}

add_action( 'woocommerce_before_thankyou', static function ( int $order_id ): void {
	$order = routed_order( 'order-received' );
	if ( ! $order || $order->get_id() !== $order_id ) {
		return;
	}
	render_topup_result( $order, false );
}, 20 );

add_action( 'wc_ajax_artnovo_wallet_topup_status', static function (): void {
	header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	header( 'Referrer-Policy: no-referrer' );
	$id = $_POST['order'] ?? null;
	$nonce = $_POST['_ajax_nonce'] ?? null;
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! is_user_logged_in()
		|| ! is_string( $id ) || ! ctype_digit( $id ) || ! is_string( $nonce )
		|| ! wp_verify_nonce( wp_unslash( $nonce ), 'artnovo_wallet_topup_status_' . (int) $id ) ) {
		wp_send_json_error( null, 403 );
	}
	$order = keyed_wallet_topup_order( $id, $_POST['key'] ?? null );
	if ( ! $order || ! function_exists( 'is_wallet_account_locked' ) || is_wallet_account_locked()
		|| ! defined( 'ArtNovo\\PayBridge\\METHOD' ) || \ArtNovo\PayBridge\METHOD !== $order->get_payment_method( 'edit' )
		|| ! has_recharge_product() ) {
		wp_send_json_error( null, 403 );
	}
	ob_start();
	render_topup_result( $order, true );
	wp_send_json_success( [ 'html' => ob_get_clean() ] );
} );

function render_topup_result( \WC_Order $order, bool $in_wallet ): void {
	$entry = credited_transaction( $order );
	$provider = [ \ArtNovo\PayBridge\Payments::class, 'customer_status' ];
	$bank = $in_wallet && is_callable( $provider ) ? $provider( $order ) : null;
	$phase = $bank['phase'] ?? '';
	[ $state, $title, $copy ] = topup_state( $order, $entry, in_array( $phase, [ 'open', 'review' ], true ) );
	$retry_label = '';
	if ( in_array( $state, [ 'pending', 'failed' ], true ) && ! $order->is_paid() && 'failed_final' === $phase ) {
		$state = 'failed';
		$title = 'cancelled_by_user' === ( $bank['reason'] ?? '' ) ? 'پرداخت شارژ لغو شد' : 'پرداخت شارژ ناموفق بود';
		$copy = 'پرداخت این سفارش تکمیل نشد.';
		$retry_label = ( $bank['can_retry'] ?? false ) === true && $order->needs_payment() ? 'پرداخت دوباره' : '';
	} elseif ( 'pending' === $state && 'none' === $phase && $order->needs_payment() ) {
		$state = 'unpaid';
		$title = 'پرداخت شارژ شروع نشده است';
		$copy = 'پرداخت را از همین سفارش ادامه دهید.';
		$retry_label = 'ادامهٔ پرداخت';
	} elseif ( 'pending' === $state && 'review' === $phase ) {
		$title = 'پرداخت شارژ نیاز به بررسی دارد';
		$copy = 'وضعیت همین شارژ را پیگیری کنید.';
	}
	$show_balance = $entry && function_exists( 'woo_wallet' ) && is_callable( [ woo_wallet()->wallet, 'get_wallet_balance' ] );
	$poll = $in_wallet && 'pending' === $state && ( 'open' === $phase || ( 'paid' === $phase && $order->is_paid() ) )
		&& has_recharge_product();
	$view = $state . ':' . $phase . ':' . ( $order->is_paid() ? 'paid' : 'unpaid' );
	?>
	<section class="artnovo-wallet-panel artnovo-wallet-result" dir="rtl" data-state="<?php echo esc_attr( $state ); ?>"
		<?php if ( $in_wallet ) : ?>data-artnovo-wallet-topup-view="<?php echo esc_attr( $view ); ?>"<?php endif; ?>
		<?php if ( $poll ) : ?>
			data-artnovo-wallet-topup-status-url="<?php echo esc_url( \WC_AJAX::get_endpoint( 'artnovo_wallet_topup_status' ) ); ?>"
			data-artnovo-wallet-topup-status-order="<?php echo esc_attr( (string) $order->get_id() ); ?>"
			data-artnovo-wallet-topup-status-key="<?php echo esc_attr( $order->get_order_key() ); ?>"
			data-artnovo-wallet-topup-status-nonce="<?php echo esc_attr( wp_create_nonce( 'artnovo_wallet_topup_status_' . $order->get_id() ) ); ?>"
		<?php endif; ?>>
		<?php if ( $entry ) : ?><span class="artnovo-wallet-success" aria-hidden="true">✓</span><?php endif; ?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<p><?php echo esc_html( $copy ); ?></p>
		<?php if ( $entry ) : render_amount( 'مبلغ ثبت‌شده در کیف پول', price( $entry->amount, (string) $entry->currency ) ); endif; ?>
		<?php if ( $show_balance ) : render_amount( 'موجودی فعلی کیف پول', \Artnovo\Core\display_number_html( wp_kses_post( woo_wallet()->wallet->get_wallet_balance( $order->get_customer_id() ) ), true ) ); endif; ?>
		<?php if ( 'pending' === $state ) : ?>
			<a class="artnovo-wallet-primary" href="<?php echo esc_url( $in_wallet ? wallet_result_url( $order ) : $order->get_checkout_order_received_url() ); ?>">پیگیری وضعیت</a>
			<?php if ( $poll ) : ?>
				<p data-artnovo-wallet-topup-poll-expired role="status" hidden>بررسی خودکار متوقف شد. وضعیت همین شارژ را دوباره پیگیری کنید.</p>
			<?php endif; ?>
		<?php elseif ( '' !== $retry_label && direct_topup_available() ) : ?>
			<?php render_direct_topup_form( $order, $retry_label ); ?>
		<?php elseif ( $in_wallet && 'credited' === $state ) : ?>
			<a class="artnovo-wallet-primary" href="<?php echo esc_url( wallet_url( 'statement' ) ); ?>">مشاهدهٔ تراکنش‌ها</a>
		<?php elseif ( ! $in_wallet ) : ?>
			<a class="artnovo-wallet-primary" href="<?php echo esc_url( wallet_url() ); ?>">بازگشت به کیف پول</a>
		<?php endif; ?>
		<a class="artnovo-wallet-back" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">جزئیات سفارش <?php echo esc_html( \Artnovo\Core\display_digits( $order->get_order_number() ) ); ?></a>
	</section>
	<?php
}

// Suppress only WooCommerce's stock confirmation copy, leaving gateway and extension messages intact.
add_filter( 'woocommerce_thankyou_order_received_title', static function ( string $title, mixed $order ): string {
	if ( ! $order instanceof \WC_Order || ! ( $current = routed_order( 'order-received' ) ) || $current->get_id() !== $order->get_id() ) {
		return $title;
	}
	return in_array( $title, [
		__( 'Order received', 'woocommerce' ),
		__( 'Order completed', 'woocommerce' ),
		__( 'Order cancelled', 'woocommerce' ),
		__( 'Order refunded', 'woocommerce' ),
		__( 'Order failed', 'woocommerce' ),
	], true ) ? '' : $title;
}, 20, 2 );
add_filter( 'woocommerce_thankyou_order_received_text', static function ( string $text, mixed $order ): string {
	if ( ! $order instanceof \WC_Order || ! ( $current = routed_order( 'order-received' ) ) || $current->get_id() !== $order->get_id() ) {
		return $text;
	}
	return in_array( $text, [
		__( 'Thank you. Your order has been received.', 'woocommerce' ),
		__( 'Thank you. Your order has been fulfilled.', 'woocommerce' ),
	], true ) ? '' : $text;
}, 20, 2 );

function render_topup_order( \WC_Order $order ): void {
	$entry = credited_transaction( $order );
	[ $state, $title, $copy ] = topup_state( $order, $entry );
	?>
	<header class="artnovo-wallet-heading"><a class="artnovo-wallet-back" href="<?php echo esc_url( wallet_url() ); ?>">بازگشت به کیف پول</a><h1>جزئیات سفارش شارژ</h1></header>
	<section class="artnovo-wallet-panel" data-state="<?php echo esc_attr( $state ); ?>">
		<div class="artnovo-wallet-order-heading">
			<h2>سفارش <bdi><?php echo esc_html( \Artnovo\Core\display_digits( $order->get_order_number() ) ); ?></bdi></h2>
			<span class="artnovo-wallet-status"><?php echo esc_html( $title ); ?></span>
		</div>
		<dl class="artnovo-wallet-facts">
			<div><dt>مبلغ سفارش شارژ</dt><dd><?php echo price( $order->get_total(), $order->get_currency() ); ?></dd></div>
			<?php if ( $entry ) : ?><div><dt>مبلغ ثبت‌شده در کیف پول</dt><dd><?php echo price( $entry->amount, (string) $entry->currency ); ?></dd></div><?php endif; ?>
			<?php if ( $order->get_payment_method_title() ) : ?><div><dt>روش پرداخت</dt><dd><?php echo esc_html( wp_strip_all_tags( $order->get_payment_method_title() ) ); ?></dd></div><?php endif; ?>
		</dl>
		<p><?php echo esc_html( $copy ); ?></p>
		<?php if ( $entry ) : ?>
			<a class="artnovo-wallet-primary" href="<?php echo esc_url( wallet_url( 'statement' ) ); ?>">مشاهدهٔ تراکنش‌های کیف پول</a>
		<?php elseif ( $order->needs_payment() ) : ?>
			<?php $wallet_payment = direct_topup_available() && \ArtNovo\PayBridge\METHOD === $order->get_payment_method(); ?>
			<a class="artnovo-wallet-primary" href="<?php echo esc_url( $wallet_payment ? wallet_result_url( $order ) : $order->get_checkout_payment_url() ); ?>"><?php echo esc_html( $wallet_payment ? 'پیگیری وضعیت شارژ' : 'ادامهٔ پرداخت شارژ' ); ?></a>
		<?php endif; ?>
	</section>
	<?php
}

// Wrap exact native templates after WooCommerce has validated the route. Native hooks and
// fields stay in the same form, and original order details remain available in a disclosure.
$wallet_templates = [];
add_action( 'woocommerce_before_template_part', static function ( string $name, string $path, string $located, array $args ) use ( &$wallet_templates ): void {
	$endpoint = match ( $name ) {
		'myaccount/view-order.php' => 'view-order',
		'checkout/form-pay.php' => 'order-pay',
		default => null,
	};
	if ( ! $endpoint ) {
		return;
	}
	$order = routed_order( $endpoint );
	$native = $args['order'] ?? null;
	if ( ! $order || ! $native instanceof \WC_Order || $native->get_id() !== $order->get_id() ) {
		return;
	}
	$wallet_templates[ $name ] = true;
	if ( 'view-order' === $endpoint ) {
		render_topup_order( $order );
		echo '<details class="artnovo-wallet-details"><summary>ریز مبلغ و اطلاعات سفارش</summary>';
		return;
	}
	?>
	<section class="artnovo-wallet-panel artnovo-wallet-pay" dir="rtl">
		<header class="artnovo-wallet-checkout-heading"><h2>ادامهٔ پرداخت شارژ کیف پول</h2><p>سفارش <bdi><?php echo esc_html( \Artnovo\Core\display_digits( $order->get_order_number() ) ); ?></bdi></p></header>
		<?php render_amount( 'مبلغ قابل پرداخت', price( $order->get_total(), $order->get_currency() ) ); ?>
	<?php
}, 10, 4 );
add_action( 'woocommerce_after_template_part', static function ( string $name ) use ( &$wallet_templates ): void {
	if ( isset( $wallet_templates[ $name ] ) ) {
		unset( $wallet_templates[ $name ] );
		echo 'myaccount/view-order.php' === $name ? '</details>' : '</section>';
	}
} );
