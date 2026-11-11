<?php
// Local PHP-server fixture. No receipt or other row is written to the live database.
declare( strict_types=1 );

ini_set( 'display_errors', '0' );
define( 'DISABLE_WP_CRON', true );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
require_once dirname( __DIR__ ) . '/fixture-paths.php';
$wordpress_root = realpath( (string) getenv( 'ARTNOVO_WP_ROOT' ) );
if ( false === $wordpress_root || ! is_file( $wordpress_root . '/wp-load.php' ) ) {
	throw new RuntimeException( 'Set ARTNOVO_WP_ROOT to the WordPress installation used by the fixture.' );
}
require $wordpress_root . '/wp-includes/plugin.php';

add_action( 'muplugins_loaded', static function (): void {
	global $wp_object_cache;
	if ( ! is_object( $wp_object_cache ) || 'WP_Object_Cache' !== get_class( $wp_object_cache ) ) {
		throw new RuntimeException( 'The multipart fixture requires the standard in-memory object cache.' );
	}
	wp_using_ext_object_cache( true );
}, -100 );

add_filter( 'option_active_plugins', static function ( array $plugins ): array {
	return array_values( array_filter( $plugins, static fn( string $plugin ): bool =>
		in_array( $plugin, [ 'woocommerce/woocommerce.php', 'wp-vpn-bridge/wp-vpn-bridge.php' ], true ) ) );
} );
$GLOBALS['artnovo_test_receipt_saved'] = false;
$GLOBALS['artnovo_test_receipt_stage'] = false;
add_filter( 'query', static function ( string $sql ): string {
	if ( $GLOBALS['artnovo_test_receipt_stage'] && in_array( trim( strtoupper( $sql ) ), [ 'START TRANSACTION', 'COMMIT', 'ROLLBACK' ], true ) ) {
		return 'SELECT 1';
	}
	if ( $GLOBALS['artnovo_test_receipt_stage'] && preg_match( '/^\s*REPLACE\s+INTO\s+[^\s]*wpvpn_receipts[^\s]*\s/i', $sql )
		&& str_contains( $sql, '2147000082' ) && str_contains( $sql, '2147000081' ) && str_contains( $sql, 'wpvpn:v1:' ) ) {
		$GLOBALS['artnovo_test_receipt_saved'] = true;
		return 'SELECT 1';
	}
	if ( ! preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql ) ) {
		throw new RuntimeException( 'A real database write was blocked in the multipart fixture.' );
	}
	return $sql;
}, PHP_INT_MAX );
add_filter( 'pre_wp_mail', static function (): never {
	throw new RuntimeException( 'Mail was blocked in the multipart fixture.' );
} );
add_filter( 'pre_http_request', static function (): never {
	throw new RuntimeException( 'External HTTP was blocked in the multipart fixture.' );
} );
require $wordpress_root . '/wp-load.php';

remove_action( 'shutdown', '_wp_cron' );
if ( WC()->session instanceof WC_Session_Handler ) {
	remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
}
if ( WC()->customer instanceof WC_Customer ) {
	remove_action( 'shutdown', [ WC()->customer, 'save' ], 10 );
}
WC()->session = new class extends WC_Session {
	public function has_session(): bool { return false; }
	public function save_data(): void {}
};
WC()->customer = new WC_Customer( 0, false );
if ( get_user_by( 'id', 2147000081 ) || wc_get_order( 2147000082 ) ) {
	throw new RuntimeException( 'The multipart synthetic identity is already in use.' );
}
$user = new WP_User();
$user->init( (object) [ 'ID' => 2147000081, 'user_login' => 'receipt-http-fixture' ] );
$GLOBALS['current_user'] = $user;

class Artnovo_Receipt_HTTP_Order_Store extends WC_Order_Data_Store_CPT {
	public function get_order_type( $order_id ) {
		return 2147000082 === (int) $order_id ? 'shop_order' : parent::get_order_type( $order_id );
	}
	public function read( &$order ) {
		if ( 2147000082 !== $order->get_id() ) {
			parent::read( $order );
			return;
		}
		$order->set_customer_id( 2147000081 );
		$order->set_status( 'on-hold' );
		$order->set_payment_method( 'bacs' );
		$order->set_order_key( 'wc_order_receipt_qa' );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_total( '100000' );
		$item = new WC_Order_Item_Product();
		$item->set_id( 2147000083 );
		$item->set_order_id( 2147000082 );
		$item->set_product_id( 25 );
		$item->set_quantity( 1 );
		$item->set_total( '100000' );
		$order->add_item( $item );
		$order->update_meta_data( '_wpvpn_snapshot', [
			'product_id' => 25, 'customer_id' => 2147000081, 'quantity' => 1,
			'total' => (string) $order->get_total(), 'currency' => $order->get_currency(), 'payment_method' => 'bacs',
		] );
		$order->set_object_read( true );
	}
	public function read_meta( &$object ) {
		if ( 2147000082 !== $object->get_id() ) {
			return parent::read_meta( $object );
		}
		return [ (object) [
			'meta_id' => 2147000084, 'meta_key' => '_wpvpn_snapshot',
			'meta_value' => [ 'product_id' => 25, 'customer_id' => 2147000081, 'quantity' => 1,
				'total' => '100000', 'currency' => get_woocommerce_currency(), 'payment_method' => 'bacs' ],
		] ];
	}
}
class Artnovo_Receipt_HTTP_Item_Store extends WC_Order_Item_Product_Data_Store {
	public function read( &$item ) {
		if ( 2147000083 !== $item->get_id() ) {
			parent::read( $item );
			return;
		}
		$item->set_order_id( 2147000082 );
		$item->set_product_id( 25 );
		$item->set_quantity( 1 );
		$item->set_total( '100000' );
		$item->set_object_read( true );
	}
	public function read_meta( &$object ) {
		return 2147000083 === $object->get_id() ? [] : parent::read_meta( $object );
	}
}
add_filter( 'woocommerce_order-item-product_data_store', static fn() => Artnovo_Receipt_HTTP_Item_Store::class, PHP_INT_MAX );
add_filter( 'woocommerce_order_data_store', static fn() => Artnovo_Receipt_HTTP_Order_Store::class, PHP_INT_MAX );
$query = new WP_Query();
$query->is_page = true;
$query->queried_object = get_post( wc_get_page_id( 'checkout' ) );
$query->queried_object_id = wc_get_page_id( 'checkout' );
$query->query_vars['order-received'] = '2147000082';
$GLOBALS['wp_query'] = $query;
$GLOBALS['wp']->query_vars['order-received'] = '2147000082';
require artnovo_fixture_root() . '/modules/order-received/received.php';

if ( 'GET' === $_SERVER['REQUEST_METHOD'] ) {
	header( 'Content-Type: application/json' );
	$probe = \WPVPNBridge\Commerce\Orders::fresh_order( 2147000082 );
	echo wp_json_encode( [
		'nonce' => wp_create_nonce( 'artnovo_received_receipt_2147000082' ),
		'return' => Artnovo\AccountUI\received_order_url( wc_get_order( 2147000082 ) ) . '#artnovo-received-receipt',
		'fixture_authorization' => [ Artnovo\AccountUI\received_page(), get_current_user_id(), $probe ? $probe->get_customer_id() : null, $probe ? $probe->get_order_key() === ( $_GET['key'] ?? '' ) : false, (bool) Artnovo\AccountUI\received_order() ],
	] );
	exit;
}
$GLOBALS['artnovo_test_receipt_stage'] = true;
header( 'X-ArtNovo-Test-Uploaded: ' . ( is_uploaded_file( $_FILES['wpvpn_receipt']['tmp_name'] ?? '' ) ? '1' : '0' ) );
add_filter( 'wp_redirect', static function ( string $location ): string {
	header( 'X-ArtNovo-Test-Saved: ' . ( $GLOBALS['artnovo_test_receipt_saved'] ? '1' : '0' ) );
	header( 'X-ArtNovo-Test-Notice: ' . ( wc_notice_count( 'success' ) ? 'success' : 'error' ) );
	return $location;
}, -100 );
foreach ( $GLOBALS['wp_filter']['template_redirect']->callbacks[6] ?? [] as $callback ) {
	$function = $callback['function'];
	if ( $function instanceof Closure
		&& realpath( ( new ReflectionFunction( $function ) )->getFileName() ) === realpath( artnovo_fixture_root() . '/modules/order-received/received-receipt.php' ) ) {
		$function();
	}
}
throw new RuntimeException( 'The multipart handler did not finish its request.' );
