<?php
// Invoke with Core and all four legacy custom plugins skipped; see ../README.md.
require_once dirname( __DIR__ ) . '/fixture-paths.php';
if ( function_exists( '\Artnovo\AccountUI\received_order' ) ) {
	WP_CLI::error( 'Core and legacy custom plugins must be skipped; this test requires candidate code.' );
}
require __DIR__ . '/qa-bootstrap.php';
require artnovo_fixture_root() . '/modules/order-received/received.php';

class Artnovo_Received_QA_Template_Store extends Artnovo_Received_QA_Order_Store {
	public function read( &$order ) {
		parent::read( $order );
		if ( ! empty( $GLOBALS['artnovo_received_cases'][ $order->get_id() ]['nonservice'] ) ) {
			$order->delete_meta_data( '_wpvpn_snapshot' );
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$item->set_product_id( 0 );
			}
		}
	}
	public function read_meta( &$order ) {
		return ! empty( $GLOBALS['artnovo_received_cases'][ $order->get_id() ]['nonservice'] ) ? [] : parent::read_meta( $order );
	}
}
add_filter( 'woocommerce_order_data_store', static fn() => Artnovo_Received_QA_Template_Store::class, PHP_INT_MAX );

$failed = 0;
$check = static function ( string $name, bool $passed ) use ( &$failed ): void {
	$failed += $passed ? 0 : 1;
	WP_CLI::log( ( $passed ? 'PASS ' : 'FAIL ' ) . $name );
};
$set_route = static function ( WC_Order $order, ?string $key = null ): void {
	$GLOBALS['wp']->query_vars = [ 'order-received' => $order->get_id() ];
	$GLOBALS['wp_query']->query_vars['order-received'] = (string) $order->get_id();
	$_GET = [ 'key' => $key ?? $order->get_order_key() ];
};
$native = '/synthetic/theme/page-checkout.php';
$custom = artnovo_fixture_root() . '/modules/order-received/received-page.php';
$service = artnovo_received_qa_order( 2147100400 );
$set_route( $service );
$check( 'owned keyed service order uses custom received template', apply_filters( 'template_include', $native ) === $custom );

$nonservice = artnovo_received_qa_order( 2147100401 );
$GLOBALS['artnovo_received_cases'][ $nonservice->get_id() ]['nonservice'] = true;
$nonservice = new WC_Order( $nonservice->get_id() );
$set_route( $nonservice );
$check( 'fixture has no bridge snapshot or service product', ! \WPVPNBridge\Commerce\Orders::is_service_order( $nonservice ) );
$check( 'owned keyed non-service order keeps native template', apply_filters( 'template_include', $native ) === $native );

$set_route( $nonservice, 'wc_order_wrong_fixture_key' );
$check( 'invalid key still uses custom denial template', apply_filters( 'template_include', $native ) === $custom
	&& false === \Artnovo\AccountUI\received_order() );
$nonservice_owner = $GLOBALS['artnovo_received_cases'][ $nonservice->get_id() ]['owner'];
$GLOBALS['artnovo_received_cases'][ $nonservice->get_id() ]['owner'] = ARTNOVO_RECEIVED_TEST_USER + 1;
$set_route( $nonservice );
$check( 'different owner still uses custom denial template', apply_filters( 'template_include', $native ) === $custom
	&& false === \Artnovo\AccountUI\received_order() );

if ( $failed ) {
	WP_CLI::error( $failed . ' template scope assertions failed.' );
}
WP_CLI::success( 'Candidate received template scope passed with writes/mail/HTTP blocked.' );
