<?php
require_once dirname( __DIR__ ) . '/fixture-paths.php';
artnovo_fixture_boot_core();
require __DIR__ . '/qa-bootstrap.php';

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
	'ready' => [ 'processing', 'succeeded', false, 1, [] ],
	'failed' => [ 'failed', null, false, 1, [] ],
	'closed' => [ 'refunded', 'succeeded', false, 1, [] ],
	'review' => [ 'processing', 'needs_review', false, 1, [] ],
	'multi-ready' => [ 'processing', 'succeeded', false, 2, [ 'succeeded', 'succeeded' ] ],
	'multi-partial' => [ 'processing', 'succeeded', false, 2, [ 'succeeded', 'pending' ] ],
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
	$expected = match ( $name ) { 'multi-ready' => 'ready', 'multi-partial' => 'issuing', default => $name };
	$check( $name . ' displays ' . $expected, $state['state'] === $expected );
	$expected_links = match ( $name ) { 'ready', 'multi-partial' => 1, 'multi-ready' => 2, default => 0 };
	$check( $name . ' has only authorized private service links', count( $delivery['links'] ) === $expected_links );
}

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

foreach ( [ 'pending', 'receipt', 'issuing', 'ready', 'failed', 'closed', 'review', 'multi-ready', 'multi-partial' ] as $name ) {
	$order = $orders[ $name ];
	$set_route( $order );
	ob_start();
	\Artnovo\AccountUI\render_received_page();
	$markup = ob_get_clean();
	$check( $name . ' renders status and new layout', str_contains( $markup, 'artnovo-received-layout' ) && ! str_contains( $markup, 'Fatal error' ) );
	$check( $name . ' avoids native cart interface', ! str_contains( $markup, 'woocommerce-cart-form' ) && ! str_contains( $markup, 'مشاهدهٔ سبد' ) );
	$base = artnovo_fixture_root() . '/modules/account/';
	$account_css = file_get_contents( $base . 'artnovo-account.css' );
	$account_js = file_get_contents( $base . 'artnovo-account.js' );
	$received_css = file_get_contents( artnovo_fixture_root() . '/modules/order-received/received.css' );
	$received_js = file_get_contents( artnovo_fixture_root() . '/modules/order-received/received.js' );
	$jquery = file_get_contents( ABSPATH . WPINC . '/js/jquery/jquery.min.js' );
	$qr = file_get_contents( $base . 'jquery.qrcode.min.js' );
	$copy = file_get_contents( WP_PLUGIN_DIR . '/wp-vpn-bridge/assets/subscriptions.js' );
	$document = '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
		. '<title>ArtNovo received synthetic fixture</title><style>html{background:#0c1723}body{margin:0;font-family:Tahoma,sans-serif}</style><style>'
		. $account_css . $received_css . '</style><body>' . $markup . '<script>' . $jquery . '</script><script>' . $qr . '</script><script>'
		. $received_js . '</script><script>' . $copy . '</script><script>' . $account_js . '</script></body></html>';
	artnovo_fixture_write( 'received-' . $name . '.html', $document );
}

if ( $failures ) {
	WP_CLI::error( $failures . ' received-page checks failed.' );
}
WP_CLI::success( 'Received states and views passed with synthetic records and writes/mail/HTTP blocked.' );
