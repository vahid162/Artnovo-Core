<?php

namespace Artnovo\AccountUI;

function received_order(): \WC_Order|false {
	return $GLOBALS['artnovo_receipt_qa_access'] ? $GLOBALS['artnovo_receipt_qa_order'] : false;
}

function received_order_url( \WC_Order $order ): string {
	return add_query_arg( 'key', $order->get_order_key(), wc_get_endpoint_url( 'order-received', $order->get_id(), wc_get_checkout_url() ) );
}

require_once dirname( __DIR__ ) . '/fixture-paths.php';
require \artnovo_fixture_root() . '/modules/order-received/received-receipt.php';

remove_action( 'shutdown', '_wp_cron' );
if ( WC()->session instanceof \WC_Session_Handler ) {
	remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
}
if ( WC()->customer instanceof \WC_Customer ) {
	remove_action( 'shutdown', [ WC()->customer, 'save' ], 10 );
}
WC()->session = new class extends \WC_Session {
	public int $saved = 0;
	public function has_session(): bool {
		return false;
	}
	public function save_data(): void {
		++$this->saved;
	}
};
WC()->customer = new \WC_Customer( 0, false );
$user_id = 2147000081;
$order_id = 2147000082;
if ( get_user_by( 'id', $user_id ) || wc_get_order( $order_id ) ) {
	\WP_CLI::error( 'The receipt fixture identity is already in use.' );
}
$user = new \WP_User();
$user->init( (object) [ 'ID' => $user_id, 'user_login' => 'receipt-memory-test' ] );
$GLOBALS['current_user'] = $user;
$order = new \WC_Order();
$order->set_id( $order_id );
$order->set_customer_id( $user_id );
$order->set_payment_method( 'bacs' );
$order->set_status( 'on-hold' );
$order->set_order_key( 'wc_order_receipt_fixture_only' );
$order->set_currency( get_woocommerce_currency() );
$order->set_total( '100000' );
$item = new \WC_Order_Item_Product();
$item->set_product_id( 25 );
$item->set_quantity( 1 );
$item->set_total( '100000' );
$order->add_item( $item );
$order->update_meta_data( '_wpvpn_snapshot', [
	'product_id' => 25, 'customer_id' => $user_id, 'quantity' => 1,
	'total' => (string) $order->get_total(), 'currency' => $order->get_currency(), 'payment_method' => 'bacs',
] );
$GLOBALS['artnovo_receipt_qa_order'] = $order;
$GLOBALS['artnovo_receipt_qa_access'] = true;
$GLOBALS['artnovo_receipt_qa_present'] = false;

add_filter( 'query', static function ( string $sql ) use ( $user_id, $order_id ): string {
	if ( ! preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql ) ) {
		throw new \RuntimeException( 'A database write was blocked in receipt verification.' );
	}
	if ( $GLOBALS['artnovo_receipt_qa_present'] && str_contains( $sql, 'OCTET_LENGTH(body) AS body_size' )
		&& str_contains( $sql, (string) $order_id ) ) {
		return "SELECT $order_id AS order_id, $user_id AS customer_id, 'image/png' AS mime, 1700000000 AS created_at, 100 AS body_size";
	}
	return $sql;
}, PHP_INT_MAX );
add_filter( 'pre_wp_mail', static function (): never {
	throw new \RuntimeException( 'An email send was blocked in receipt verification.' );
} );
add_filter( 'pre_http_request', static function (): never {
	throw new \RuntimeException( 'An HTTP request was blocked in receipt verification.' );
} );
$failed = 0;
$check = static function ( string $name, bool $passed ) use ( &$failed ): void {
	$failed += $passed ? 0 : 1;
	\WP_CLI::log( ( $passed ? 'PASS ' : 'FAIL ' ) . $name );
};
$check( 'owned pending bank-transfer order permits upload', received_receipt_can_upload( $order ) );
$check( 'missing receipt metadata stays missing', ! received_receipt_exists( $order ) );
ob_start();
render_received_receipt( $order );
$html = ob_get_clean();
$tags = new \WP_HTML_Tag_Processor( $html );
$has_form = $tags->next_tag( [ 'tag_name' => 'FORM' ] );
$check( 'receipt form posts to exact received-order URL', $has_form && received_order_url( $order ) === $tags->get_attribute( 'action' ) );
$check( 'receipt form carries upload nonce and byte limit', str_contains( $html, 'artnovo_received_receipt_nonce' ) && str_contains( $html, 'value="2097152"' ) );
$GLOBALS['artnovo_receipt_qa_present'] = true;
$check( 'stored receipt belongs to exact current customer', received_receipt_exists( $order ) );
ob_start();
render_received_receipt( $order );
$html = ob_get_clean();
$check( 'stored receipt shows private native download action', str_contains( $html, 'wpvpn_download_receipt' ) && str_contains( $html, 'رسید این خرید ثبت شده است' ) );
$order->set_customer_id( $user_id + 1 );
$check( 'other customer cannot upload or see receipt presence', ! received_receipt_can_upload( $order ) && ! received_receipt_exists( $order ) );
ob_start();
render_received_receipt( $order );
$check( 'other customer receipt UI is empty', '' === ob_get_clean() );
$order->set_customer_id( $user_id );
$order->set_status( 'completed' );
$check( 'paid order does not permit replacing receipt', ! received_receipt_can_upload( $order ) );
$order->set_status( 'on-hold' );

$handler = null;
foreach ( $GLOBALS['wp_filter']['template_redirect']->callbacks[6] ?? [] as $callback ) {
	$function = $callback['function'];
	if ( $function instanceof \Closure && realpath( ( new \ReflectionFunction( $function ) )->getFileName() ) === realpath( \artnovo_fixture_root() . '/modules/order-received/received-receipt.php' ) ) {
		$handler = $function;
	}
}
if ( ! $handler ) {
	\WP_CLI::error( 'Candidate receipt handler was not registered.' );
}
$_SERVER['REQUEST_METHOD'] = 'POST';
$_FILES = [];
$capture = static function ( string $url, int $status ): never {
	throw new \RuntimeException( $status . ' ' . $url );
};
add_filter( 'wp_redirect', $capture, -100, 2 );
foreach ( [ 'invalid' => 'wrong', 'missing file' => wp_create_nonce( 'artnovo_received_receipt_' . $order_id ) ] as $case => $nonce ) {
	wc_clear_notices();
	$_POST = [
		'artnovo_received_receipt' => '1',
		'artnovo_received_receipt_nonce' => $nonce,
		'redirect' => 'https://example.invalid/attacker',
	];
	try {
		$handler();
		$redirect = '';
	} catch ( \RuntimeException $exception ) {
		$redirect = $exception->getMessage();
	}
	$check( $case . ' stays on the same private order page with 303', '303 ' . received_order_url( $order ) . '#artnovo-received-receipt' === $redirect );
	$check( $case . ' produces an inline error, never a success', 1 === wc_notice_count( 'error' ) && 0 === wc_notice_count( 'success' ) );
}
remove_filter( 'wp_redirect', $capture, -100 );
$check( 'inline notices persist before redirect', 2 === WC()->session->saved );
if ( $failed ) {
	\WP_CLI::error( $failed . ' receipt checks failed.' );
}
\WP_CLI::success( 'Receipt owner, form, native download and same-page error checks passed without writes, mail or HTTP.' );
