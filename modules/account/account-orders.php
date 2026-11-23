<?php

namespace Artnovo\AccountUI;

use Artnovo\Core;
use Artnovo\WalletUI;

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_orders', $has_orders );

$columns = wc_get_account_orders_columns();
$groups = [ 'followup' => [], 'past' => [] ];
$customer_id = get_current_user_id();
foreach ( $has_orders ? $customer_orders->orders : [] as $entry ) {
	$order = wc_get_order( $entry );
	if ( ! $order instanceof \WC_Order || $customer_id < 1 || $customer_id !== $order->get_customer_id() ) {
		continue;
	}

	$native_status = $order->get_status();
	$past = in_array( $native_status, [ 'completed', 'cancelled', 'refunded', 'trash' ], true );
	$view = [
		'state' => 'completed' === $native_status ? 'ready' : ( $past ? 'closed' : 'pending' ),
		'label' => wc_get_order_status_name( $native_status ),
	];
	$topup = WalletUI\owns_topup( $order ) ? WalletUI\topup_presentation( $order ) : null;
	$bank_payment = $topup ? ( $topup['managed'] ? [ 'phase' => $topup['phase'] ] : null ) : received_bank_payment( $order );
	if ( $topup ) {
		$view = [ 'state' => 'credited' === $topup['state'] ? 'ready' : $topup['state'], 'label' => $topup['title'] ];
		$past = in_array( $view['state'], [ 'ready', 'closed' ], true );
	} elseif ( Core\is_service_order( $order ) && Core\bridge_available() ) {
		$snapshot = $order->get_meta( '_wpvpn_snapshot', true );
		$payment = null === $bank_payment && ! $order->is_paid() && is_array( $snapshot ) ? subscription_payment_presentation( $order, $snapshot ) : null;
		if ( $payment ) {
			$view = $payment;
		} else {
			$delivery = received_state( $order, bank_payment: $bank_payment );
			$view = [ 'state' => $delivery['state'], 'label' => $delivery['title'] ];
		}
		$past = in_array( $view['state'], [ 'ready', 'closed' ], true );
	}
	$groups[ $past ? 'past' : 'followup' ][] = [ $order, $view, $bank_payment, $topup ];
}

$action_link = static function ( array $action, string $key, string $reference, bool $highlight = false ) use ( $wp_button_class ): void {
	$label = $action['aria-label'] ?? '';
	if ( '' === $label ) {
		/* translators: %1$s Action name, %2$s Order number. */
		$label = sprintf( __( '%1$s order number %2$s', 'woocommerce' ), $action['name'], $reference );
	}
	$class = 'woocommerce-button button artnovo-account-button' . $wp_button_class . ' ' . sanitize_html_class( $key );
	if ( ! $highlight ) {
		$class .= ' artnovo-account-button-secondary';
	}
	?>
	<a href="<?php echo esc_url( $action['url'] ); ?>" class="<?php echo esc_attr( $class ); ?>" aria-label="<?php echo esc_attr( Core\display_digits( $label ) ); ?>"><?php echo esc_html( Core\display_digits( $action['name'] ) ); ?></a>
	<?php
};

$show_column = static function ( string $name, \WC_Order $order, string $html ) use ( $columns ): void {
	if ( ! isset( $columns[ $name ] ) ) {
		return;
	}
	$hook = 'woocommerce_my_account_my_orders_column_' . $name;
	if ( has_action( $hook ) ) {
		do_action( $hook, $order );
	} else {
		echo wp_kses_post( $html );
	}
};
?>
<div class="artnovo-account-orders">
	<?php foreach ( [ 'followup' => 'در حال پیگیری', 'past' => 'خریدهای گذشته' ] as $group => $heading ) : ?>
		<?php if ( ! $groups[ $group ] ) { continue; } ?>
		<section class="artnovo-orders-group" aria-label="<?php echo esc_attr( $heading ); ?>">
			<h2><?php echo esc_html( $heading ); ?></h2>
			<ul class="artnovo-orders-list" role="list">
				<?php foreach ( $groups[ $group ] as [ $order, $view, $bank_payment, $topup ] ) : ?>
					<?php
					$number = Core\display_digits( (string) $order->get_order_number() );
					$reference = 'سفارش ' . $number;
					$url = $order->get_view_order_url();
					$date = $order->get_date_created();
					$title = 'شارژ کیف پول';
					if ( ! $topup ) {
						$items = $order->get_items();
						$item = reset( $items );
						$title = $item ? Core\display_digits( $item->get_name() ) : $reference;
						if ( count( $items ) > 1 ) {
							$title .= ' و ' . Core\display_digits( (string) ( count( $items ) - 1 ) ) . ' مورد دیگر';
						}
					}
					$item_count = $order->get_item_count() - $order->get_item_count_refunded();
					$topup_action = $topup['action'] ?? ( 'wallet_insufficient' === $view['state'] ? received_wallet_topup_action() : null );
					?>
					<li class="artnovo-orders-row woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?> order">
						<div class="artnovo-orders-identity">
							<h3><?php echo esc_html( $title ); ?></h3>
							<div class="artnovo-orders-meta">
								<?php
								$show_column( 'order-number', $order, '<a href="' . esc_url( $url ) . '">' . esc_html( $reference ) . '</a>' );
								$show_column( 'order-date', $order, $date ? '<time datetime="' . esc_attr( $date->date( 'c' ) ) . '">' . esc_html( Core\display_digits( wc_format_datetime( $date ) ) ) . '</time>' : '' );
								?>
							</div>
						</div>
						<div class="artnovo-orders-progress">
							<?php $show_column( 'order-status', $order, '<span class="artnovo-orders-status" data-state="' . esc_attr( $view['state'] ) . '">' . esc_html( Core\display_digits( $view['label'] ) ) . '</span>' ); ?>
						</div>
						<div class="artnovo-orders-total">
							<?php
							$total = Core\display_number_html( $order->get_formatted_order_total(), true );
							if ( 1 !== $item_count ) {
								$total .= ' <small>' . esc_html( sprintf( '%s مورد', Core\display_digits( (string) $item_count ) ) ) . '</small>';
							}
							$show_column( 'order-total', $order, $total );
							?>
						</div>
						<?php if ( isset( $columns['order-actions'] ) ) : ?>
							<div class="artnovo-orders-actions">
								<?php if ( $topup_action ) { $action_link( $topup_action, 'wallet-topup', $number, true ); } ?>
								<?php if ( has_action( 'woocommerce_my_account_my_orders_column_order-actions' ) ) : ?>
									<?php received_account_actions( $order, $bank_payment, $view['state'], static fn() => do_action( 'woocommerce_my_account_my_orders_column_order-actions', $order ) ); ?>
								<?php else : ?>
									<?php
									$actions = received_account_actions( $order, $bank_payment, $view['state'], static fn() => wc_get_account_orders_actions( $order ) );
									if ( isset( $actions['view'] ) ) {
										$primary = $actions['view'];
										$canonical = $url === $primary['url'] && __( 'View', 'woocommerce' ) === $primary['name'];
										if ( $canonical ) {
											$primary['name'] = $topup ? 'جزئیات شارژ' : match ( $view['state'] ) {
												'transfer' => 'پیگیری واریز',
												'receipt' => 'پیگیری رسید',
												default => 'جزئیات خرید',
											};
											$primary['aria-label'] = $primary['name'] . ' ' . $reference;
										}
										if ( $topup && $topup_action ) {
											$actions['view'] = $primary;
										} else {
											$action_link( $primary, 'view', $number, $canonical && 'transfer' === $view['state'] );
											unset( $actions['view'] );
										}
									}
									if ( $actions ) :
										?>
										<details class="artnovo-orders-extra">
											<summary><?php echo esc_html( 'اقدام‌های دیگر' ); ?></summary>
											<div>
												<?php foreach ( $actions as $key => $action ) { $action_link( $action, $key, $number ); } ?>
											</div>
										</details>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php
						foreach ( array_diff_key( $columns, array_flip( [ 'order-number', 'order-date', 'order-status', 'order-total', 'order-actions' ] ) ) as $column => $label ) {
							if ( has_action( 'woocommerce_my_account_my_orders_column_' . $column ) ) {
								echo '<div class="artnovo-orders-extension"><span>' . esc_html( $label ) . '</span> ';
								do_action( 'woocommerce_my_account_my_orders_column_' . $column, $order );
								echo '</div>';
							}
						}
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endforeach; ?>

	<?php if ( ! $groups['followup'] && ! $groups['past'] ) : ?>
		<section class="artnovo-account-card artnovo-orders-empty">
			<h2><?php echo esc_html( $has_orders ? 'خریدی برای نمایش در این صفحه وجود ندارد' : 'هنوز خریدی ثبت نکرده‌اید' ); ?></h2>
			<a class="woocommerce-Button wc-forward button artnovo-account-button<?php echo esc_attr( $wp_button_class ); ?>" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>"><?php echo esc_html( 'مشاهدهٔ محصولات' ); ?></a>
		</section>
	<?php endif; ?>

	<?php if ( $has_orders ) : ?>
		<?php do_action( 'woocommerce_before_account_orders_pagination' ); ?>
		<?php if ( 1 < $customer_orders->max_num_pages ) : ?>
			<nav class="artnovo-orders-pages woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination" aria-label="<?php echo esc_attr( 'صفحه‌های خریدها' ); ?>">
				<?php if ( 1 < $current_page ) : ?>
					<a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button artnovo-account-button artnovo-account-button-secondary<?php echo esc_attr( $wp_button_class ); ?>" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page - 1 ) ); ?>"><?php echo esc_html( 'صفحهٔ قبل' ); ?></a>
				<?php endif; ?>
				<?php if ( intval( $customer_orders->max_num_pages ) > $current_page ) : ?>
					<a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button artnovo-account-button artnovo-account-button-secondary<?php echo esc_attr( $wp_button_class ); ?>" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', $current_page + 1 ) ); ?>"><?php echo esc_html( 'صفحهٔ بعد' ); ?></a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
<?php do_action( 'woocommerce_after_account_orders', $has_orders ); ?>
