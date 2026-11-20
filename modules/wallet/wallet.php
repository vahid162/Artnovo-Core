<?php

namespace Artnovo\WalletUI;

defined( 'ABSPATH' ) || exit;

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
	if ( in_array( account_screen(), [ 'add', 'transfer' ], true ) ) {
		wp_enqueue_script( 'artnovo-wallet-ui', plugins_url( 'wallet.js', __FILE__ ), [], (string) filemtime( __DIR__ . '/wallet.js' ), true );
	}
}, 30 );

add_action( 'template_redirect', static function (): void {
	if ( routed_order( 'order-pay' ) || topup_checkout() ) {
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
	}
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

function topup_state( \WC_Order $order, ?object $entry ): array {
	return match ( true ) {
		$order->has_status( 'failed' ) => [ 'failed', 'پرداخت شارژ ناموفق بود', 'پرداخت این سفارش تکمیل نشد.' ],
		$order->has_status( 'cancelled' ) => [ 'closed', 'شارژ کیف پول لغو شد', 'وضعیت سفارش را در جزئیات خرید ببینید.' ],
		$order->has_status( 'refunded' ) || (float) $order->get_total_refunded() > 0 => [ 'closed', 'برای این شارژ بازپرداخت ثبت شد', 'ریز مبلغ و وضعیت سفارش در جزئیات خرید در دسترس است.' ],
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
	$entry = credited_transaction( $order );
	[ $state, $title, $copy ] = topup_state( $order, $entry );
	$show_balance = $entry && function_exists( 'woo_wallet' ) && is_callable( [ woo_wallet()->wallet, 'get_wallet_balance' ] );
	?>
	<section class="artnovo-wallet-panel artnovo-wallet-result" dir="rtl" data-state="<?php echo esc_attr( $state ); ?>">
		<?php if ( $entry ) : ?><span class="artnovo-wallet-success" aria-hidden="true">✓</span><?php endif; ?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<p><?php echo esc_html( $copy ); ?></p>
		<?php if ( $entry ) : render_amount( 'مبلغ ثبت‌شده در کیف پول', price( $entry->amount, (string) $entry->currency ) ); endif; ?>
		<?php if ( $show_balance ) : render_amount( 'موجودی فعلی کیف پول', \Artnovo\Core\display_number_html( wp_kses_post( woo_wallet()->wallet->get_wallet_balance( $order->get_customer_id() ) ), true ) ); endif; ?>
		<?php if ( 'pending' === $state ) : ?>
			<a class="artnovo-wallet-primary" href="<?php echo esc_url( $order->get_checkout_order_received_url() ); ?>">پیگیری وضعیت</a>
		<?php else : ?>
			<a class="artnovo-wallet-primary" href="<?php echo esc_url( wallet_url() ); ?>">بازگشت به کیف پول</a>
		<?php endif; ?>
		<a class="artnovo-wallet-back" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">جزئیات سفارش <?php echo esc_html( \Artnovo\Core\display_digits( $order->get_order_number() ) ); ?></a>
	</section>
	<?php
}, 20 );

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
			<a class="artnovo-wallet-primary" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">ادامهٔ پرداخت شارژ</a>
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
