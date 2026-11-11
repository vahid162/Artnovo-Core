<?php
// WP-CLI fixture. All customer, order, ledger and service data stays synthetic.
remove_action( 'shutdown', '_wp_cron' );
if ( WC()->session instanceof WC_Session_Handler ) {
	remove_action( 'shutdown', [ WC()->session, 'save_data' ], 20 );
}
if ( WC()->customer instanceof WC_Customer ) {
	remove_action( 'shutdown', [ WC()->customer, 'save' ], 10 );
}
WC()->session = new class extends WC_Session {
	public function has_session(): bool { return false; }
};
WC()->customer = new WC_Customer( 0, false );
add_filter( 'pre_wp_mail', static function (): never { throw new RuntimeException( 'Unexpected test mail.' ); } );
add_filter( 'pre_http_request', static function (): never { throw new RuntimeException( 'Unexpected test HTTP.' ); } );
$GLOBALS['artnovo_received_cases'] = [];
$GLOBALS['artnovo_received_ledger'] = [];
$GLOBALS['artnovo_received_receipts'] = [];
const ARTNOVO_RECEIVED_TEST_USER = 2147100001;
if ( get_user_by( 'id', ARTNOVO_RECEIVED_TEST_USER ) ) {
	WP_CLI::error( 'Synthetic user ID is already in use.' );
}
$user = new WP_User();
$user->init( (object) [ 'ID' => ARTNOVO_RECEIVED_TEST_USER, 'user_login' => 'received-qa', 'user_email' => 'received-qa@example.invalid' ] );
$GLOBALS['current_user'] = $user;

class Artnovo_Received_QA_Item_Store extends WC_Order_Item_Product_Data_Store {
	public function read( &$item ) {
		if ( $item->get_id() < 2147100000 ) { parent::read( $item ); }
	}
	public function read_meta( &$item ) {
		return $item->get_id() >= 2147100000 ? [] : parent::read_meta( $item );
	}
}

class Artnovo_Received_QA_Order_Store extends WC_Order_Data_Store_CPT {
	public function get_order_type( $order_id ) {
		return isset( $GLOBALS['artnovo_received_cases'][ (int) $order_id ] ) ? 'shop_order' : parent::get_order_type( $order_id );
	}
	public function read( &$order ) {
		$case = $GLOBALS['artnovo_received_cases'][ $order->get_id() ] ?? null;
		if ( ! $case ) { parent::read( $order ); return; }
		$order->set_props( [
			'customer_id' => $case['owner'], 'status' => $case['status'], 'order_key' => 'wc_order_received_fixture_' . $order->get_id(),
			'payment_method' => 'bacs', 'payment_method_title' => 'کارت‌به‌کارت', 'currency' => get_woocommerce_currency(),
			'total' => '100000', 'billing_email' => 'received-qa@example.invalid', 'date_created' => time() - 120,
		] );
		$item = new WC_Order_Item_Product();
		$item->set_id( $order->get_id() + 100 );
		$item->set_props( [ 'name' => 'اشتراک آزمایشی', 'product_id' => 25, 'quantity' => $case['quantity'], 'total' => '100000' ] );
		$order->add_item( $item );
		$order->update_meta_data( '_wpvpn_snapshot', $case['snapshot'] );
		$order->set_object_read( true );
	}
	public function read_meta( &$order ) {
		$case = $GLOBALS['artnovo_received_cases'][ $order->get_id() ] ?? null;
		return $case ? [ (object) [ 'meta_id' => 2147100001, 'meta_key' => '_wpvpn_snapshot', 'meta_value' => maybe_serialize( $case['snapshot'] ) ] ] : parent::read_meta( $order );
	}
}
add_filter( 'woocommerce_order_data_store', static fn() => Artnovo_Received_QA_Order_Store::class, PHP_INT_MAX );
add_filter( 'woocommerce_order-item-product_data_store', static fn() => Artnovo_Received_QA_Item_Store::class, PHP_INT_MAX );

function artnovo_received_qa_row( int $id, array $snapshot, string $status, int $unit = 0 ): array {
	$reference = 'received-fixture-' . $id . '-' . $unit;
	$expiry = time() + 86400 * 12;
	$service = '';
	if ( 'succeeded' === $status ) {
		$service = \WPVPNBridge\Security\Crypto::seal( wp_json_encode( [
			'id' => 'fixture-' . $id . '-' . $unit, 'reference' => $reference, 'quota_bytes' => 10737418240,
			'expires_at' => $expiry, 'profile_ids' => [ 1 ], 'subscription_url' => 'https://example.invalid/subscription/fixture-' . $id . '-' . $unit,
			'status' => 'active',
		] ) );
		if ( is_wp_error( $service ) ) { WP_CLI::error( 'Synthetic service encryption failed.' ); }
	}
	return [
		'id' => ( $unit ? 'u' : '' ) . 'received-fixture-' . $id . '-' . $unit, 'order_id' => $id, 'customer_id' => $snapshot['customer_id'],
		'connection_id' => 'fixture', 'reference' => $reference, 'status' => $status, 'claim_token' => '', 'service' => $service,
		'error_code' => '', 'notification_generation' => '', 'snapshot' => wp_json_encode( $snapshot ),
		'expires_at' => $expiry, 'submitted_at' => 0, 'attempts' => 1, 'next_attempt' => 0, 'lease_until' => 0,
		'approved_by' => 1, 'created_at' => time() - 120, 'updated_at' => time(), 'unit_index' => $unit,
	];
}

function artnovo_received_qa_order( int $id, string $status = 'on-hold', ?string $operation = null, bool $receipt = false, int $quantity = 1, array $unit_states = [], ?int $owner = null ): WC_Order {
	$owner ??= ARTNOVO_RECEIVED_TEST_USER;
	$snapshot = [ 'product_id' => 25, 'customer_id' => $owner, 'quantity' => $quantity, 'quota_bytes' => 10737418240, 'duration_days' => 30,
		'profile_ids' => [ 1 ], 'total' => '100000', 'currency' => get_woocommerce_currency(), 'payment_method' => 'bacs' ];
	$GLOBALS['artnovo_received_cases'][ $id ] = [ 'owner' => $owner, 'status' => $status, 'quantity' => $quantity, 'snapshot' => $snapshot ];
	$GLOBALS['artnovo_received_ledger'][ $id ] = [ 'operation' => $operation ? artnovo_received_qa_row( $id, $snapshot, $operation ) : null,
		'units' => array_map( static fn( string $state, int $n ) => artnovo_received_qa_row( $id, $snapshot, $state, $n + 1 ), $unit_states, array_keys( $unit_states ) ) ];
	if ( $receipt ) {
		$GLOBALS['artnovo_received_receipts'][ $id ] = [ 'order_id' => $id, 'customer_id' => $owner, 'mime' => 'image/png', 'created_at' => time() - 60, 'body_size' => 100 ];
	}
	return new WC_Order( $id );
}

add_filter( 'query', static function ( string $sql ): string {
	global $wpdb;
	if ( ! preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql ) ) {
		throw new RuntimeException( 'Unexpected database write during received-page test.' );
	}
	if ( ! preg_match( '/FROM\s+\S*wpvpn_(operations|units|receipts)\x60?\s+WHERE order_id = (\d+)/', $sql, $match )
		|| ! isset( $GLOBALS['artnovo_received_cases'][ (int) $match[2] ] ) ) {
		return $sql;
	}
	$id = (int) $match[2];
	$ledger = $GLOBALS['artnovo_received_ledger'][ $id ];
	$rows = match ( $match[1] ) {
		'operations' => $ledger['operation'] ? [ $ledger['operation'] ] : [],
		'units' => $ledger['units'],
		'receipts' => isset( $GLOBALS['artnovo_received_receipts'][ $id ] ) ? [ $GLOBALS['artnovo_received_receipts'][ $id ] ] : [],
	};
	if ( ! $rows ) { return 'SELECT NULL WHERE 1 = 0'; }
	$selects = [];
	foreach ( $rows as $row ) {
		$columns = [];
		foreach ( $row as $name => $value ) {
			$columns[] = $wpdb->prepare( is_int( $value ) ? '%d AS %i' : '%s AS %i', $value, $name );
		}
		$selects[] = 'SELECT ' . implode( ', ', $columns );
	}
	return implode( ' UNION ALL ', $selects );
}, PHP_INT_MAX );

$_GET = [];
$_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$query = new WP_Query();
$query->is_page = true;
$query->queried_object = get_post( wc_get_page_id( 'checkout' ) );
$query->queried_object_id = wc_get_page_id( 'checkout' );
$GLOBALS['wp_query'] = $query;
$GLOBALS['wp']->query_vars = [ 'order-received' => 0 ];
