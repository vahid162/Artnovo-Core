<?php
require_once dirname( __DIR__ ) . '/fixture-paths.php';
artnovo_fixture_boot_core();
require __DIR__ . '/qa-bootstrap.php';

// Preview bank details are synthetic; the real gateway renderer is retained.
add_filter( 'woocommerce_bacs_accounts', static fn() => [ [
	'account_name' => 'به نام حساب آزمایشی رابط', 'account_number' => '0000000000000000',
	'bank_name' => 'بانک آزمایشی', 'sort_code' => '', 'iban' => 'IR000000000000000000000000', 'bic' => '',
] ] );

$failures = 0;
$check = static function ( string $case, bool $passed ) use ( &$failures ): void {
	$failures += $passed ? 0 : 1;
	WP_CLI::log( ( $passed ? 'PASS ' : 'FAIL ' ) . $case );
};
$set_route = static function ( WC_Order $order, ?string $key = null ): void {
	$GLOBALS['wp']->query_vars = [ 'order-received' => $order->get_id() ];
	$GLOBALS['wp_query']->query_vars['order-received'] = (string) $order->get_id();
	$_GET = [ 'key' => $key ?? $order->get_order_key() ];
	$_POST = [];
};

$cases = [
	'pending' => [ 'on-hold', null, false, 1, [] ],
	'receipt' => [ 'on-hold', null, true, 1, [] ],
	'issuing' => [ 'processing', 'pending', false, 1, [] ],
	'awaiting-authorization' => [ 'processing', 'awaiting_payment', false, 1, [] ],
	'ready' => [ 'processing', 'succeeded', false, 1, [] ],
	'failed' => [ 'failed', null, false, 1, [] ],
	'closed' => [ 'refunded', 'succeeded', false, 1, [] ],
	'review' => [ 'processing', 'needs_review', false, 1, [] ],
	'multi-ready' => [ 'processing', 'succeeded', false, 2, [ 'succeeded', 'succeeded' ] ],
	'multi-partial' => [ 'processing', 'succeeded', false, 2, [ 'succeeded', 'pending' ] ],
	'multi-mixed' => [ 'processing', 'succeeded', false, 3, [ 'succeeded', 'needs_review', 'succeeded' ] ],
	'receipt-with-stale-service' => [ 'on-hold', 'succeeded', true, 2, [ 'succeeded', 'pending' ] ],
	'missing-service' => [ 'processing', null, false, 1, [] ],
	'failed-service' => [ 'processing', 'failed', false, 1, [] ],
	'ready-with-receipt' => [ 'processing', 'succeeded', true, 1, [] ],
];
$id = 2147100010;
$orders = [];
foreach ( $cases as $name => $config ) {
	$order = artnovo_received_qa_order( $id++, ...$config );
	$orders[ $name ] = $order;
	$set_route( $order );
	$current = \Artnovo\AccountUI\received_order();
	$check( $name . ' is private to the owner with matching key', $current instanceof WC_Order && $current->get_id() === $order->get_id() );
	$delivery = \Artnovo\AccountUI\received_services( $current );
	$state = \Artnovo\AccountUI\received_state( $current, $delivery );
	$expected = match ( $name ) { 'multi-ready', 'ready-with-receipt' => 'ready', 'multi-partial', 'multi-mixed' => 'partial', 'receipt-with-stale-service' => 'receipt', 'missing-service', 'failed-service' => 'review', 'awaiting-authorization' => 'pending', default => $name };
	$check( $name . ' displays ' . $expected, $state['state'] === $expected );
	$expected_links = match ( $name ) { 'ready', 'multi-partial', 'ready-with-receipt' => 1, 'multi-ready', 'multi-mixed' => 2, default => 0 };
	$check( $name . ' has only authorized private service links', count( $delivery['links'] ) === $expected_links );
	if ( in_array( $name, [ 'pending', 'receipt', 'failed', 'closed', 'issuing', 'awaiting-authorization' ], true ) ) {
		$html = '<section class="wpvpn-subscription"><h3>اشتراک آزمایشی</h3><p><a href="' . esc_url( $order->get_view_order_url() ) . '">جزئیات خرید</a></p></section>';
		$tags = new WP_HTML_Tag_Processor( \Artnovo\AccountUI\decorate_subscriptions( $html ) );
		$tags->next_tag( 'SECTION' );
		$data = json_decode( $tags->get_attribute( 'data-artnovo-card' ) ?? '', true );
		$expected = match ( $name ) { 'pending' => 'transfer', 'failed', 'awaiting-authorization' => 'pending', default => $name };
		$check( $name . ' has the right account presentation and keeps the native purchase link',
			$expected === ( $data['presentation']['state'] ?? null ) && $order->get_view_order_url() === ( $data['purchase']['view_url'] ?? null ) );
		$destination = 'closed' === $name ? $order->get_view_order_url() : \Artnovo\AccountUI\received_tracking_url( $order );
		if ( 'receipt' === $name ) { $destination .= '#artnovo-received-status'; }
		$check( $name . ' account action opens the same authorized order', $destination === ( $data['presentation']['action_url'] ?? null ) );
	}
}

$tracking_bank = artnovo_received_qa_order( $id++, 'failed', method: 'artnovo_gpante' );
$check( 'bank result without a resolved financial journal stays pending even if Woo says failed',
	'pending' === \Artnovo\AccountUI\received_state( $tracking_bank, [ 'links' => [] ], [ 'phase' => 'open' ] )['state'] );
$check( 'an unavailable bank contract does not present failed or unpaid orders as payable',
	'unavailable' === \Artnovo\AccountUI\received_state( $tracking_bank, [ 'links' => [] ], [] )['state'] );
$check( 'signed final bank failure becomes a distinct failed result',
	'failed' === \Artnovo\AccountUI\received_state( $tracking_bank, [ 'links' => [] ], [ 'phase' => 'failed_final', 'can_retry' => true ] )['state'] );
$cancelled_payment = [ 'phase' => 'failed_final', 'reason' => 'cancelled_by_user', 'can_retry' => true ];
$cancelled_status = \Artnovo\AccountUI\received_state( $tracking_bank, [ 'links' => [] ], $cancelled_payment );
$check( 'verified cancellation names Zibal and directs the customer to retry the exact order',
	'failed' === $cancelled_status['state'] && 'پرداخت در زیبال لغو شد' === $cancelled_status['title']
	&& str_contains( $cancelled_status['message'], 'پرداخت همین سفارش را دوباره انجام دهید' )
	&& \Artnovo\AccountUI\received_bank_retry( $tracking_bank, $cancelled_payment ) );
$cancelled_payment['can_retry'] = false;
$blocked_status = \Artnovo\AccountUI\received_state( $tracking_bank, [ 'links' => [] ], $cancelled_payment );
$check( 'verified cancellation with an ineligible retry exposes no payment instruction',
	$cancelled_status['title'] === $blocked_status['title'] && ! str_contains( $blocked_status['message'], 'دوباره' )
	&& ! \Artnovo\AccountUI\received_bank_retry( $tracking_bank, $cancelled_payment ) );
$check( 'bank review shows the separate reconciliation state',
	'review' === \Artnovo\AccountUI\received_state( $tracking_bank, [ 'links' => [] ], [ 'phase' => 'review' ] )['state'] );
$bank_completion = artnovo_received_qa_order( $id++, 'processing', 'pending', method: 'artnovo_gpante' );
$check( 'Woo status can arrive before Bridge settlement without a false confirmed or review message',
	'pending' === \Artnovo\AccountUI\received_state( $bank_completion, [ 'links' => [] ], [ 'phase' => 'open' ] )['state'] );
$check( 'settled bank payment and ordinary issuance are distinct states',
	'issuing' === \Artnovo\AccountUI\received_state( $bank_completion, [ 'links' => [] ], [ 'phase' => 'paid' ] )['state'] );
$bank_waiting = artnovo_received_qa_order( $id++, 'processing', 'awaiting_payment', method: 'artnovo_gpante' );
$waiting_state = \Artnovo\AccountUI\received_state( $bank_waiting, [ 'links' => [] ], [ 'phase' => 'paid' ] );
$check( 'settled bank payment before Bridge promotion is at the confirmed delivery step',
	'paid_waiting_fulfillment' === $waiting_state['state'] && 3 === $waiting_state['step'] );
$check( 'Woo paid status and an awaiting operation alone do not establish bank confirmation',
	'unavailable' === \Artnovo\AccountUI\received_state( $bank_waiting, [ 'links' => [] ], [] )['state'] );
$check( 'a link already authorized by Bridge is still presented without the optional customer status',
	'ready' === \Artnovo\AccountUI\received_state( $bank_completion, [ 'links' => [ 'https://fixture.invalid/link' ] ], [] )['state'] );
$native_actions = [ 'pay' => [], 'cancel' => [], 'view' => [] ];
$actions_for = static fn( WC_Order $order ): array => apply_filters( 'woocommerce_my_account_my_orders_actions', $native_actions, $order );
$check( 'unavailable bank status retains only nonfinancial native actions for that order',
	[ [ 'view' => [] ], $native_actions ] === \Artnovo\AccountUI\received_account_actions( $tracking_bank, [], 'unavailable',
		static fn(): array => [ $actions_for( $tracking_bank ), $actions_for( $orders['pending'] ) ] ) );
$check( 'the scoped account action filter is restored after rendering',
	$native_actions === $actions_for( $tracking_bank ) );
$check( 'known no-attempt bank purchase retains the native account actions',
	$native_actions === \Artnovo\AccountUI\received_account_actions( $tracking_bank, [ 'phase' => 'none' ], 'pending',
		static fn(): array => $actions_for( $tracking_bank ) ) );

$own = $orders['pending'];
$set_route( $own, 'wc_order_wrong_key' );
$check( 'wrong order key denies private view', false === \Artnovo\AccountUI\received_order() );
WC()->session->set( 'order_awaiting_payment', 777 );
$cart_before = WC()->cart->get_cart_contents();
ob_start(); \Artnovo\AccountUI\render_received_page(); $denied_html = ob_get_clean();
$check( 'invalid link shows no customer/order details', ! str_contains( $denied_html, 'received-qa@example.invalid' )
	&& ! str_contains( $denied_html, 'artnovo-received-summary' ) );
$check( 'invalid link preserves unrelated purchase session', 777 === WC()->session->get( 'order_awaiting_payment' )
	&& $cart_before === WC()->cart->get_cart_contents() );
$set_route( $own );
$cart = WC()->cart;
$saved_cart = $cart->get_cart_contents();
$product = wc_get_product( 25 );
$cart->set_cart_contents( [ 'account-tracking-fixture' => [ 'product_id' => 25, 'quantity' => 1, 'data' => $product ] ] );
$matching_hash = $cart->get_cart_hash();
$cart_hash = static fn( string $hash, WC_Order $order ): string => $own->get_id() === $order->get_id() ? $matching_hash : $hash;
add_filter( 'woocommerce_order_get_cart_hash', $cart_hash, 10, 2 );
WC()->session->set( 'order_awaiting_payment', $own->get_id() );
$_GET['artnovo_status'] = '1';
try {
	$checkout_actions = [ did_action( 'woocommerce_before_thankyou' ), did_action( 'woocommerce_thankyou' ) ];
	wc_clear_cart_after_payment();
	ob_start(); \Artnovo\AccountUI\render_received_page(); $tracking_html = ob_get_clean();
	$check( 'account tracking keeps the matching cart selection and session',
		! $cart->is_empty() && $matching_hash === $cart->get_cart_hash() && $own->get_id() === WC()->session->get( 'order_awaiting_payment' ) );
	$tags = new WP_HTML_Tag_Processor( $tracking_html );
	$check( 'the tracking receipt form keeps the same order key and tracking flag',
		$tags->next_tag( [ 'tag_name' => 'FORM', 'class_name' => 'artnovo-received-receipt-form' ] )
		&& \Artnovo\AccountUI\received_tracking_url( $own ) === $tags->get_attribute( 'action' ) );
	$check( 'tracking renders native details without replaying checkout hooks',
		$checkout_actions === [ did_action( 'woocommerce_before_thankyou' ), did_action( 'woocommerce_thankyou' ) ]
		&& str_contains( $tracking_html, 'woocommerce-order-details' ) );
} finally {
	remove_filter( 'woocommerce_order_get_cart_hash', $cart_hash, 10 );
	$cart->set_cart_contents( $saved_cart );
	WC()->session->__unset( 'order_awaiting_payment' );
	$set_route( $own );
}
ob_start(); \Artnovo\AccountUI\render_received_page(); $email_html = ob_get_clean();
$check( 'real order email remains visible in the purchase summary', str_contains( $email_html, '<dt>ایمیل خرید</dt>' )
	&& str_contains( $email_html, 'received-qa@example.invalid' ) );
foreach ( [ '' => 'missing', 'invalid-email' => 'invalid' ] as $email => $name ) {
	$email_filter = static fn( $value, $order ) => $own->get_id() === $order->get_id() ? $email : $value;
	add_filter( 'woocommerce_order_get_billing_email', $email_filter, PHP_INT_MAX, 2 );
	try {
		ob_start(); \Artnovo\AccountUI\render_received_page(); $email_html = ob_get_clean();
		$check( $name . ' email leaves no empty purchase-summary field', ! str_contains( $email_html, '<dt>ایمیل خرید</dt>' ) );
	} finally {
		remove_filter( 'woocommerce_order_get_billing_email', $email_filter, PHP_INT_MAX );
	}
}
$other = artnovo_received_qa_order( 2147100090, 'on-hold', null, false, 1, [], ARTNOVO_RECEIVED_TEST_USER + 1 );
$set_route( $other );
$check( 'different owner denies private view', false === \Artnovo\AccountUI\received_order() );
$check( 'different owner receives no service output', [ 'html' => '', 'links' => [] ] === \Artnovo\AccountUI\received_services( $other ) );
$GLOBALS['wp']->query_vars = [ 'order-received' => 2147100999 ];
$GLOBALS['wp_query']->query_vars['order-received'] = '2147100999';
$_GET = [ 'key' => 'wc_order_unknown' ];
$check( 'unknown order denies private view', false === \Artnovo\AccountUI\received_order() );

$set_route( $own );
$check( 'pending bank transfer allows receipt upload', \Artnovo\AccountUI\received_receipt_can_upload( $own ) );
$check( 'issued subscription prevents changing its receipt', ! \Artnovo\AccountUI\received_receipt_can_upload( $orders['ready'] ) );
$check( 'existing receipt is scoped to its owner', \Artnovo\AccountUI\received_receipt_exists( $orders['receipt'] ) && ! \Artnovo\AccountUI\received_receipt_exists( $other ) );

$expired = artnovo_received_qa_order( 2147100091, 'processing', 'succeeded' );
$row = $GLOBALS['artnovo_received_ledger'][ $expired->get_id() ]['operation'];
$plain = json_decode( \WPVPNBridge\Security\Crypto::open( $row['service'] ), true );
$plain['expires_at'] = time() - 60;
$row['service'] = \WPVPNBridge\Security\Crypto::seal( wp_json_encode( $plain ) );
$row['expires_at'] = $plain['expires_at'];
$GLOBALS['artnovo_received_ledger'][ $expired->get_id() ]['operation'] = $row;
$set_route( $expired );
$delivery = \Artnovo\AccountUI\received_services( $expired );
$state = \Artnovo\AccountUI\received_state( $expired, $delivery );
$check( 'expired issued subscription has no private link', [] === $delivery['links'] );
$check( 'expired issued subscription is closed rather than preparing', 'closed' === $state['state'] );

$checkout_page = $GLOBALS['wp_query']->queried_object;
$account_page = get_post( wc_get_page_id( 'myaccount' ) );
$GLOBALS['wp_query']->queried_object = $account_page;
$GLOBALS['wp_query']->queried_object_id = $account_page->ID;
$_GET = [];
$account_route = static function ( string $endpoint, int $value ): string {
	$GLOBALS['wp']->query_vars = $GLOBALS['wp_query']->query_vars = [ $endpoint => (string) $value ];
	ob_start();
	try {
		\Artnovo\AccountUI\render_account();
		return ob_get_contents();
	} finally {
		ob_end_clean();
	}
};
$account_preview = static function ( string $name, string $markup, string $style ): void {
	$account = artnovo_fixture_root() . '/modules/account/';
	$styles = file_get_contents( $account . 'artnovo-account.css' ) . file_get_contents( $account . $style . '.css' );
	artnovo_fixture_write( $name, '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">'
		. '<meta name="viewport" content="width=device-width,initial-scale=1"><style>html{background:#0c1723}body{margin:0;font-family:Vazir,Tahoma,sans-serif}'
		. artnovo_fixture_font_css() . $styles . '</style><body>' . $markup . '</body></html>' );
};
try {
	$actions_before = [ did_action( 'woocommerce_before_thankyou' ), did_action( 'woocommerce_thankyou' ) ];
	foreach ( [ 'pending' => 'transfer', 'receipt' => 'receipt', 'issuing' => 'issuing', 'ready' => 'ready' ] as $key => $state ) {
		$order = $orders[ $key ];
		$markup = $account_route( 'view-order', $order->get_id() );
		$check( $key . ' account detail shows the compact payment and delivery summary',
			str_contains( $markup, 'artnovo-account-order" data-state="' . $state . '"' )
			&& str_contains( $markup, 'artnovo-account-order-native' ) && str_contains( $markup, 'woocommerce-order-details' )
			&& 1 === substr_count( $markup, 'artnovo-account-order-primary' ) );
		$check( $key . ' account detail displays a private receipt download only if recorded',
			( 'receipt' === $key ) === str_contains( $markup, 'wpvpn_download_receipt' ) );
		if ( 'pending' === $key ) {
			$check( 'account detail opens the same guarded Quick Transfer route',
				str_contains( $markup, esc_url( \Artnovo\AccountUI\received_tracking_url( $order ) ) ) );
		}
		$account_preview( 'account-detail-' . $state . '.html', $markup, 'account-order' );
	}
	$foreign = $account_route( 'view-order', $other->get_id() );
	$check( 'another customer cannot see the compact summary or native details for this order',
		! str_contains( $foreign, 'artnovo-account-order-primary' ) && ! str_contains( $foreign, 'woocommerce-order-details' )
		&& ! str_contains( $foreign, $other->get_order_key() ) );
	$ordinary = artnovo_received_qa_order( 2147100092 );
	$GLOBALS['artnovo_received_cases'][ $ordinary->get_id() ]['snapshot'] = null;
	$ordinary_markup = $account_route( 'view-order', $ordinary->get_id() );
	$check( 'an owned order with no service snapshot keeps the native order template',
		str_contains( $ordinary_markup, 'woocommerce-order-details' ) && ! str_contains( $ordinary_markup, 'artnovo-account-order-primary' ) );

	$synthetic_orders = static fn( $result, array $query ) => ARTNOVO_RECEIVED_TEST_USER === ( $query['customer'] ?? null ) && ! empty( $query['paginate'] )
		? (object) [ 'orders' => [ $orders['ready'], $orders['pending'], $other, $orders['receipt'], $orders['issuing'] ], 'total' => 5, 'max_num_pages' => 3 ]
		: $result;
	add_filter( 'woocommerce_order_query', $synthetic_orders, 10, 2 );
	try {
		$markup = $account_route( 'orders', 2 );
		$check( 'quick follow-up puts open purchases ahead of past purchases', str_contains( $markup, 'artnovo-account-orders' )
			&& strpos( $markup, 'در حال پیگیری' ) < strpos( $markup, 'خریدهای گذشته' )
			&& 4 === substr_count( $markup, 'class="artnovo-orders-row' ) );
		$check( 'quick follow-up retains native previous and next page links and owned order destinations',
			str_contains( $markup, 'woocommerce-button--previous' ) && str_contains( $markup, 'woocommerce-button--next' )
			&& str_contains( $markup, esc_url( $orders['pending']->get_view_order_url() ) )
			&& ! str_contains( $markup, esc_url( $other->get_view_order_url() ) ) );
		$account_preview( 'account-quick-orders.html', $markup, 'account-orders' );
	} finally {
		remove_filter( 'woocommerce_order_query', $synthetic_orders, 10 );
	}
	$check( 'account pages did not replay checkout completion', $actions_before === [ did_action( 'woocommerce_before_thankyou' ), did_action( 'woocommerce_thankyou' ) ] );
} finally {
	$GLOBALS['wp_query']->queried_object = $checkout_page;
	$GLOBALS['wp_query']->queried_object_id = $checkout_page->ID;
}

foreach ( array_keys( $cases ) as $name ) {
	$order = $orders[ $name ];
	$set_route( $order );
	$frozen = [ $order->get_status(), $order->get_total(), $order->get_payment_method(), $order->get_meta( '_wpvpn_snapshot', true ) ];
	$before_thankyou = did_action( 'woocommerce_thankyou' );
	ob_start();
	\Artnovo\AccountUI\render_received_page();
	$markup = ob_get_clean();
	$check( $name . ' renders status and new layout', str_contains( $markup, 'artnovo-received-layout' ) && ! str_contains( $markup, 'Fatal error' ) );
	$check( $name . ' avoids native cart interface', ! str_contains( $markup, 'woocommerce-cart-form' ) && ! str_contains( $markup, 'مشاهدهٔ سبد' ) );
	$check( $name . ' rendering preserves payment state, amount, method and frozen purchase', $frozen === [ $order->get_status(), $order->get_total(), $order->get_payment_method(), $order->get_meta( '_wpvpn_snapshot', true ) ] );
	$summary_position = strpos( $markup, 'class="artnovo-received-summary"' );
	$status_position = strpos( $markup, 'id="artnovo-received-status"' );
	$receipt_review = in_array( $name, [ 'receipt', 'receipt-with-stale-service' ], true );
	$check( $name . ' uses Quick Transfer only before a bank receipt is recorded', ( 'pending' === $name ) === str_contains( $markup, 'data-artnovo-quick-transfer' ) );
	if ( 'pending' !== $name && ! $receipt_review ) {
		$check( $name . ' puts its compact plan summary before the persistent state', false !== $summary_position
			&& false !== $status_position && $summary_position < $status_position && str_contains( $markup, 'artnovo-received-total' ) );
		preg_match( '/<ol class="artnovo-received-progress"[^>]*>(.*?)<\/ol>/s', $markup, $progress );
		$check( $name . ' keeps the three shared purchase steps', 3 === substr_count( $progress[1] ?? '', '<li ' )
			&& str_contains( $progress[1] ?? '', 'ورود' ) && str_contains( $progress[1] ?? '', 'پرداخت' ) && str_contains( $progress[1] ?? '', 'دریافت اکانت' ) );
	}
	$tags = new WP_HTML_Tag_Processor( $markup );
	$invoice = $tags->next_tag( [ 'tag_name' => 'DETAILS', 'class_name' => 'artnovo-received-details' ] );
	$check( $name . ' keeps its native invoice available in an initially closed disclosure', $invoice && null === $tags->get_attribute( 'open' ) );
	$check( $name . ' uses the compact review card only while awaiting payment review',
		$receipt_review === str_contains( $markup, 'artnovo-receipt-review-reference' ) );
	if ( $receipt_review ) {
		$before_disclosure = explode( '<details class="artnovo-received-details', $markup, 2 )[0];
		$check( $name . ' shows a single order reference and no plan, price, or receipt controls in the visible status',
			1 === substr_count( $before_disclosure, 'شمارهٔ پیگیری' )
			&& str_contains( $before_disclosure, 'رسید ثبت شد؛ منتظر تأیید پرداخت' )
			&& false === $summary_position && ! str_contains( $markup, 'artnovo-received-progress' )
			&& ! str_contains( $before_disclosure, 'woocommerce-Price-amount' )
			&& ! str_contains( $markup, 'wc-bacs-bank-details' )
			&& ! str_contains( $before_disclosure, 'wpvpn_download_receipt' )
			&& ! str_contains( $before_disclosure, 'data-artnovo-receipt-form' ) );
		$check( $name . ' preserves the private receipt link and native integrations inside the disclosure',
			str_contains( $markup, 'wpvpn_download_receipt' ) && str_contains( $markup, 'id="artnovo-received-receipt"' )
			&& $before_thankyou + 1 === did_action( 'woocommerce_thankyou' ) );
	}
	$tags = new WP_HTML_Tag_Processor( $markup );
	$primary = [];
	while ( $tags->next_tag() ) {
		if ( null !== $tags->get_attribute( 'data-artnovo-received-primary' ) ) { $primary[] = $tags->get_attribute( 'href' ); }
	}
	$expected_primary = in_array( $name, [ 'ready', 'ready-with-receipt' ], true ) ? 0 : 1;
	$check( $name . ' has one state action without a duplicate receipt or service CTA', $expected_primary === count( $primary ) );
	if ( in_array( $name, [ 'receipt', 'issuing', 'receipt-with-stale-service' ], true ) ) {
		parse_str( wp_parse_url( $primary[0] ?? '', PHP_URL_QUERY ) ?: '', $refresh_query );
		$check( $name . ' refresh retains the same private order key', ( $refresh_query['key'] ?? null ) === $order->get_order_key()
			&& isset( $refresh_query['artnovo_status'] ) && str_contains( $primary[0], '#artnovo-received-status' ) );
	}
	if ( 'failed' === $name ) {
		$check( 'failed retry points to the native payment URL of the same order', [ $order->get_checkout_payment_url() ] === $primary );
	}
	$delivery = \Artnovo\AccountUI\received_services( $order );
	preg_match( '/<textarea[^>]*id="artnovo-received-ready-links"[^>]*>(.*?)<\/textarea>/s', $markup, $batch );
	$expected_batch = $order->is_paid() && $order->get_item_count() > 1 && count( $delivery['links'] ) > 0;
	$check( $name . ' exposes batch copying only for ready units in a multi-account purchase', $expected_batch === isset( $batch[1] ) );
	if ( $expected_batch ) {
		$batch_links = explode( "\n", html_entity_decode( $batch[1], ENT_QUOTES, 'UTF-8' ) );
		$check( $name . ' batch contains exactly the ready links authorized by Bridge', $delivery['links'] === $batch_links );
	}
	if ( 'receipt' === $name || 'receipt-with-stale-service' === $name ) {
		$check( $name . ' remains unpaid after repeated refresh with a stored receipt', ! $order->is_paid()
			&& 'receipt' === \Artnovo\AccountUI\received_state( $order )['state'] && ! str_contains( $markup, 'data-artnovo-copy-ready' ) );
	}
	if ( 'pending' === $name ) {
		$bank_position = strpos( $markup, 'wc-bacs-bank-details order_details' );
		$receipt_position = strpos( $markup, 'id="artnovo-received-receipt"' );
		$check( 'Quick Transfer retains native bank fields before the receipt action', false !== $status_position
			&& false !== $summary_position && $status_position < $summary_position
			&& false !== $bank_position && $bank_position > $summary_position
			&& false !== $receipt_position && $receipt_position > $bank_position
			&& str_contains( $markup, '0000000000000000' ) && str_contains( $markup, 'IR000000000000000000000000' ) );
		$tags = new WP_HTML_Tag_Processor( $markup );
		$form = $tags->next_tag( [ 'tag_name' => 'FORM', 'class_name' => 'artnovo-received-receipt-form' ] );
		$check( 'compact pending form retains the real private POST destination', $form
			&& 'post' === $tags->get_attribute( 'method' ) && 'multipart/form-data' === $tags->get_attribute( 'enctype' )
			&& \Artnovo\AccountUI\received_order_url( $order ) === $tags->get_attribute( 'action' ) );
		$tags = new WP_HTML_Tag_Processor( $markup );
		$fields = [];
		$file = false;
		while ( $tags->next_tag( 'INPUT' ) ) {
			$field_name = $tags->get_attribute( 'name' );
			if ( is_string( $field_name ) ) { $fields[ $field_name ] = $tags->get_attribute( 'value' ); }
			if ( 'wpvpn_receipt' === $field_name ) {
				$file = 'file' === $tags->get_attribute( 'type' ) && 'image/jpeg,image/png' === $tags->get_attribute( 'accept' )
					&& true === $tags->get_attribute( 'required' );
			}
		}
		$check( 'compact receipt preserves file policy and owner nonce', $file && '2097152' === ( $fields['MAX_FILE_SIZE'] ?? null )
			&& '1' === ( $fields['artnovo_received_receipt'] ?? null )
			&& wp_verify_nonce( $fields['artnovo_received_receipt_nonce'] ?? '', 'artnovo_received_receipt_' . $order->get_id() ) );
		$check( 'amount copy preserves the exact underlying order amount',
			preg_match( '/id="artnovo-transfer-amount-value" value="([0-9]+(?:\.[0-9]+)?)"/', $markup, $copy_amount )
			&& (string) $order->get_total( 'edit' ) === $copy_amount[1] );
		$changed_display = static fn() => '<span>۱ ریال</span>';
		add_filter( 'woocommerce_get_formatted_order_total', $changed_display, PHP_INT_MAX );
		try {
			ob_start();
			\Artnovo\AccountUI\render_received_transfer_amount( $order, true );
			$converted = ob_get_clean();
		} finally {
			remove_filter( 'woocommerce_get_formatted_order_total', $changed_display, PHP_INT_MAX );
		}
		$check( 'display conversion does not offer a different value for copying', str_contains( $converted, '۱ ریال' )
			&& ! str_contains( $converted, 'data-artnovo-copy=' ) );
		$no_bank = static fn() => [];
		$check( 'pending transfer shows the wait notice, current Woo holder and receipt upload heading',
			str_contains( $markup, '<p class="artnovo-transfer-wait">حداکثر انتظار ۱۰ دقیقه</p>' )
			&& str_contains( $markup, 'شماره کارت به نام: حساب آزمایشی رابط</h3>' )
			&& str_contains( $markup, 'id="artnovo-received-receipt-heading">تصویر رسید پرداختی خود را ارسال کنید</h2>' ) );
		$several_accounts = static fn( array $accounts ): array => array_merge( $accounts, [
			[ 'account_name' => 'دارندهٔ دوم & شریک', 'account_number' => '۰۰۰۰ ۰۰۰۰ ۰۰۰۰ ۰۰۰۱', 'bank_name' => 'بانک دوم', 'sort_code' => '', 'iban' => '', 'bic' => '' ],
			[ 'account_name' => 'دارندهٔ شبا', 'account_number' => '', 'bank_name' => 'بانک سوم', 'sort_code' => '', 'iban' => 'IR000000000000000000000001', 'bic' => '' ],
		] );
		add_filter( 'woocommerce_bacs_accounts', $several_accounts, PHP_INT_MAX );
		try {
			ob_start();
			\Artnovo\AccountUI\render_received_page();
			$several_destinations = ob_get_clean();
		} finally {
			remove_filter( 'woocommerce_bacs_accounts', $several_accounts, PHP_INT_MAX );
		}
		$check( 'each card displays its own current Woo holder, with the IBAN-only heading and fields preserved',
			2 === substr_count( $several_destinations, 'شماره کارت به نام:' )
			&& str_contains( $several_destinations, 'شماره کارت به نام: دارندهٔ دوم &amp; شریک</h3>' )
			&& str_contains( $several_destinations, 'دارندهٔ شبا:</h3>' )
			&& str_contains( $several_destinations, '<strong>IR000000000000000000000001</strong>' ) );
		add_filter( 'woocommerce_bacs_accounts', $no_bank, PHP_INT_MAX );
		try {
			ob_start();
			\Artnovo\AccountUI\render_received_page();
			$without_destination = ob_get_clean();
		} finally {
			remove_filter( 'woocommerce_bacs_accounts', $no_bank, PHP_INT_MAX );
		}
		$check( 'missing bank details give support and keep receipt access without prompting a transfer',
			str_contains( $without_destination, 'اطلاعات واریز در دسترس نیست' )
			&& ! str_contains( $without_destination, 'واریز کنید، رسید را بفرستید' )
			&& ! str_contains( $without_destination, 'حداکثر انتظار ۱۰ دقیقه' )
			&& ! str_contains( $without_destination, 'artnovo-transfer-amount-value' )
			&& str_contains( $without_destination, 'data-artnovo-receipt-preview' ) );
	}
	$base = artnovo_fixture_root() . '/modules/account/';
	$account_css = file_get_contents( $base . 'artnovo-account.css' );
	$account_js = file_get_contents( $base . 'artnovo-account.js' );
	$received_css = file_get_contents( artnovo_fixture_root() . '/modules/order-received/received.css' );
	$received_js = file_get_contents( artnovo_fixture_root() . '/modules/order-received/received.js' );
	$jquery = file_get_contents( ABSPATH . WPINC . '/js/jquery/jquery.min.js' );
	$qr = file_get_contents( $base . 'jquery.qrcode.min.js' );
	$copy = file_get_contents( WP_PLUGIN_DIR . '/wp-vpn-bridge/assets/subscriptions.js' );
	$notices_css = file_get_contents( artnovo_fixture_root() . '/modules/notices/notices.css' );
	$notices_js = file_get_contents( artnovo_fixture_root() . '/modules/notices/notices.js' );
	$document = '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>ArtNovo received synthetic fixture</title><style>' . artnovo_fixture_font_css() . 'html{background:#0c1723}body{margin:0;font-family:Vazir,Tahoma,sans-serif}</style><style>'
		. $account_css . $received_css . $notices_css . '</style><body>' . $markup . '<script>' . $jquery . '</script><script>' . $qr . '</script><script>'
		. $received_js . '</script><script>' . $copy . '</script><script>' . $account_js . '</script><script>' . $notices_js . '</script></body></html>';
	artnovo_fixture_write( 'received-' . $name . '.html', $document );
}

$set_route( $orders['receipt'] );
wc_add_notice( 'Synthetic replacement error.', 'error', [ 'artnovo_receipt_order' => $orders['receipt']->get_id() ] );
$print_early = static function (): void { wc_print_notices(); };
add_action( 'woocommerce_before_thankyou', $print_early, 1 );
try {
	ob_start();
	\Artnovo\AccountUI\render_received_page();
	$retry_markup = ob_get_clean();
} finally {
	remove_action( 'woocommerce_before_thankyou', $print_early, 1 );
}
$retry_tags = new WP_HTML_Tag_Processor( $retry_markup );
$check( 'replacement error remains visible and opens receipt tools even when a gateway prints the notice',
	str_contains( $retry_markup, 'Synthetic replacement error.' )
	&& $retry_tags->next_tag( [ 'tag_name' => 'DETAILS', 'class_name' => 'artnovo-received-details' ] )
	&& true === $retry_tags->get_attribute( 'open' ) && str_contains( $retry_markup, 'data-artnovo-receipt-form' ) );
wc_add_notice( 'Synthetic unrelated error.', 'error', [ 'artnovo_receipt_order' => $orders['pending']->get_id() ] );
$direct_notice_callback = false === has_action( 'woocommerce_before_thankyou', 'wc_print_notices' );
if ( $direct_notice_callback ) {
	add_action( 'woocommerce_before_thankyou', 'wc_print_notices', 10, 1 );
}
try {
	ob_start();
	\Artnovo\AccountUI\render_received_page();
	$unrelated_markup = ob_get_clean();
	$check( 'direct gateway notice callback is restored after rendering', false !== has_action( 'woocommerce_before_thankyou', 'wc_print_notices' ) );
} finally {
	if ( $direct_notice_callback ) {
		remove_action( 'woocommerce_before_thankyou', 'wc_print_notices', 10 );
	}
}
$unrelated_tags = new WP_HTML_Tag_Processor( $unrelated_markup );
$check( 'another order notice stays visible without opening these receipt tools',
	str_contains( $unrelated_markup, 'Synthetic unrelated error.' )
	&& $unrelated_tags->next_tag( [ 'tag_name' => 'DETAILS', 'class_name' => 'artnovo-received-details' ] )
	&& null === $unrelated_tags->get_attribute( 'open' ) );

if ( $failures ) {
	WP_CLI::error( $failures . ' received-page checks failed.' );
}
WP_CLI::success( 'Received states and views passed with synthetic records and writes/mail/HTTP blocked.' );
