<?php

namespace Artnovo\PublicPages;

use Artnovo\Core;
use Artnovo\PlanCheckout;

defined( 'ABSPATH' ) || exit;

const DURATIONS = [
	'vpn-one-month' => [ 'یک‌ماهه', 'پلن‌های یک‌ماهه', 'حجم مناسب یک ماه استفاده را انتخاب کنید.' ],
	'vpn-two-month' => [ 'دوماهه', 'پلن‌های دوماهه', 'حجم مناسب دو ماه استفاده را انتخاب کنید.' ],
];

function catalog_view(): string {
	if ( is_shop() ) {
		return 'shop';
	}
	if ( is_product_category( array_keys( DURATIONS ) ) ) {
		$term = get_queried_object();
		return $term instanceof \WP_Term && isset( DURATIONS[ $term->slug ] ) ? $term->slug : '';
	}
	return '';
}

function view(): string {
	if ( is_admin() || wp_doing_ajax() || is_preview() || is_customize_preview() || is_feed() || is_embed() || isset( $_GET['elementor-preview'] ) ) {
		return '';
	}
	$catalog = catalog_view();
	if ( '' !== $catalog ) {
		return $catalog;
	}
	if ( is_cart() && WC()->cart && WC()->cart->is_empty() && in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', [ 'GET', 'HEAD' ], true ) ) {
		return 'cart';
	}
	if ( is_search() ) {
		return 'search';
	}
	if ( is_home() && ! is_front_page() ) {
		return 'blog';
	}
	if ( is_page() && ! is_front_page() ) {
		$page = get_queried_object();
		if ( $page instanceof \WP_Post && 0 === (int) $page->post_parent && ! post_password_required( $page ) ) {
			return match ( rawurldecode( $page->post_name ) ) {
				'home' => 'old-home',
				'خرید-اشتراک-vpn-آرتنوو' => 'old-buy',
				default => '',
			};
		}
	}
	return is_404() ? 'not-found' : '';
}

function purchase_url(): string {
	return home_url( '/#artnovo-categories' );
}

add_filter( 'body_class', static function ( array $classes ): array {
	if ( '' !== ( $view = view() ) ) {
		$classes[] = 'artnovo-public-view';
		if ( 'shop' === $view || isset( DURATIONS[ $view ] ) ) {
			$classes[] = 'artnovo-public-catalog';
		}
	}
	return $classes;
} );

add_action( 'wp_enqueue_scripts', static function (): void {
	if ( '' !== view() ) {
		wp_enqueue_style( 'artnovo-public-pages', plugins_url( 'public-pages.css', __FILE__ ), [], (string) filemtime( __DIR__ . '/public-pages.css' ) );
	}
}, 30 );

add_filter( 'template_include', static function ( string $template ): string {
	$view = view();
	return '' !== $view && 'shop' !== $view && ! isset( DURATIONS[ $view ] ) ? __DIR__ . '/public-page.php' : $template;
}, 90 );

add_filter( 'woocommerce_show_page_title', static function ( bool $show ): bool {
	$view = view();
	return 'shop' === $view || isset( DURATIONS[ $view ] ) ? false : $show;
} );

add_action( 'woocommerce_shop_loop_header', static function (): void {
	$view = view();
	if ( 'shop' !== $view && ! isset( DURATIONS[ $view ] ) ) {
		return;
	}
	$copy = DURATIONS[ $view ] ?? [ '', 'پلن‌های اتصال', 'مدت اشتراک را انتخاب کنید و حجم پلن‌ها را مقایسه کنید.' ];
	?>
	<header id="content" class="artnovo-public-heading">
		<h1><?php echo esc_html( $copy[1] ); ?></h1>
		<p><?php echo esc_html( $copy[2] ); ?></p>
	</header>
	<nav class="artnovo-public-filter" aria-label="مدت اشتراک">
		<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" <?php echo 'shop' === $view ? 'aria-current="page"' : ''; ?>>همهٔ پلن‌ها</a>
		<?php foreach ( DURATIONS as $slug => $duration ) :
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			$link = $term instanceof \WP_Term ? get_term_link( $term ) : '';
			if ( is_string( $link ) && '' !== $link ) :
				?>
				<a href="<?php echo esc_url( $link ); ?>" <?php echo $slug === $view ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $duration[0] ); ?></a>
				<?php
			endif;
		endforeach; ?>
	</nav>
	<?php
}, 5 );

function catalog_card(): bool {
	$view = view();
	return in_the_loop() && ( 'shop' === $view || isset( DURATIONS[ $view ] ) );
}

function product_duration( \WC_Product $product ): string {
	foreach ( DURATIONS as $slug => $duration ) {
		if ( has_term( $slug, 'product_cat', $product->get_id() ) ) {
			return $duration[0];
		}
	}
	return '';
}

add_filter( 'woocommerce_post_class', static function ( array $classes, \WC_Product $product ): array {
	if ( catalog_card() && PlanCheckout\is_plan( $product ) ) {
		$classes[] = 'artnovo-public-plan';
	}
	return $classes;
}, 10, 2 );

add_filter( 'the_title', static function ( string $title, int $id ): string {
	return catalog_card() && 'product' === get_post_type( $id ) ? Core\display_digits( $title ) : $title;
}, 30, 2 );

add_filter( 'woocommerce_get_price_html', static function ( string $price ): string {
	return catalog_card() ? Core\display_number_html( $price, true ) : $price;
}, 30 );

add_action( 'woocommerce_shop_loop_item_title', static function (): void {
	global $product;
	if ( catalog_card() && PlanCheckout\is_plan( $product ) && '' !== ( $period = product_duration( $product ) ) ) {
		echo '<p class="artnovo-public-period">' . esc_html( $period ) . '</p>';
	}
}, 15 );

add_action( 'woocommerce_after_shop_loop_item', static function (): void {
	global $product;
	if ( catalog_card() && PlanCheckout\is_plan( $product ) ) {
		echo '<a class="artnovo-public-details" href="' . esc_url( $product->get_permalink() ) . '">جزئیات پلن</a>';
	}
}, 15 );

function product_card( \WC_Product $product ): void {
	$is_plan = PlanCheckout\is_plan( $product );
	$purchasable = $is_plan && $product->is_in_stock() && $product->is_purchasable() && $product->is_virtual();
	$period = product_duration( $product );
	$price = $product->get_price_html();
	?>
	<article class="artnovo-public-card artnovo-public-product">
		<h3><?php echo esc_html( Core\display_digits( $product->get_name() ) ); ?></h3>
		<?php if ( '' !== $period ) : ?><p class="artnovo-public-period"><?php echo esc_html( $period ); ?></p><?php endif; ?>
		<?php if ( '' !== $price ) : ?><div class="artnovo-public-price"><?php echo Core\display_number_html( wp_kses( $price, wp_kses_allowed_html( 'post' ) + [ 'bdi' => [] ] ), true ); ?></div><?php endif; ?>
		<?php if ( $purchasable ) : ?>
			<a class="artnovo-public-button" href="<?php echo esc_url( PlanCheckout\wizard_url( $product->get_id() ) ); ?>">انتخاب پلن</a>
		<?php endif; ?>
		<a class="artnovo-public-details" href="<?php echo esc_url( $product->get_permalink() ); ?>"><?php echo $is_plan ? 'جزئیات پلن' : 'جزئیات محصول'; ?></a>
	</article>
	<?php
}

function post_cards( array $posts ): void {
	if ( ! $posts ) {
		return;
	}
	?>
	<div class="artnovo-public-grid">
		<?php foreach ( $posts as $post ) : ?>
			<article class="artnovo-public-card artnovo-public-story">
				<h3><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( Core\display_digits( get_the_title( $post ) ?: 'بدون عنوان' ) ); ?></a></h3>
				<?php if ( '' !== ( $excerpt = wp_strip_all_tags( get_the_excerpt( $post ) ) ) ) : ?>
					<p><?php echo esc_html( Core\display_digits( wp_trim_words( $excerpt, 25 ) ) ); ?></p>
				<?php endif; ?>
				<a class="artnovo-public-details" href="<?php echo esc_url( get_permalink( $post ) ); ?>">خواندن مطلب</a>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
}

function search_results(): void {
	$plans = $articles = [];
	foreach ( $GLOBALS['wp_query']->posts as $result ) {
		if ( ! $result instanceof \WP_Post || post_password_required( $result ) ) {
			continue;
		}
		if ( 'product' === $result->post_type ) {
			$product = wc_get_product( $result->ID );
			if ( $product instanceof \WC_Product && $product->is_visible() ) {
				$plans[] = $product;
			}
		} else {
			$articles[] = $result;
		}
	}
	if ( ! $plans && ! $articles ) {
		?>
		<section class="artnovo-public-card artnovo-public-results">
			<h2>نتیجه‌ای پیدا نشد</h2>
			<a class="artnovo-public-details" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">مشاهدهٔ همهٔ پلن‌ها</a>
		</section>
		<?php
	} else {
		if ( $plans ) {
			?>
			<section class="artnovo-public-results">
				<h2>پلن‌ها</h2>
				<div class="artnovo-public-grid"><?php foreach ( $plans as $product ) { product_card( $product ); } ?></div>
			</section>
			<?php
		}
		if ( $articles ) {
			?>
			<section class="artnovo-public-results">
				<h2>راهنماها و مطالب</h2>
				<?php post_cards( $articles ); ?>
			</section>
			<?php
		}
	}
	the_posts_pagination( [ 'prev_text' => 'قبلی', 'next_text' => 'بعدی' ] );
}

function connection_guides(): array {
	return [
		[ 'اتصال با لینک اشتراک', 'لینک را از حساب کاربری بردارید و در برنامهٔ اتصال وارد کنید.', 'در بخش «اکانت‌های من»، لینک اشتراک آماده را کپی کنید. در برنامهٔ اتصال، افزودن اشتراک از کلیپ‌بورد را بزنید، فهرست را به‌روزرسانی کنید و یک سرور را انتخاب کنید.' ],
		[ 'اتصال با کد QR', 'کد اشتراک را با برنامهٔ اتصال دستگاه دیگر اسکن کنید.', 'در کارت اشتراک، «نمایش QR» را بزنید. در برنامهٔ اتصال دستگاه دوم، گزینهٔ اسکن کد را باز کنید و دوربین را رو‌به‌روی کد بگیرید.' ],
		[ 'اگر لینک اشتراک را نمی‌بینید', 'وضعیت خرید و آماده‌شدن اشتراک را در حساب کاربری بررسی کنید.', 'جزئیات سفارش را در حساب کاربری باز کنید. اگر پرداخت تأیید شده و اشتراک هنوز آماده نیست، شمارهٔ سفارش را برای پشتیبانی بفرستید.' ],
	];
}

function landing_copy( string $view ): array {
	return match ( $view ) {
		'cart' => [ 'سبد خرید خالی است', 'برای خرید اکانت، از انتخاب مدت اشتراک شروع کنید.', 'انتخاب مدت اشتراک' ],
		'old-buy' => [ 'خرید اشتراک پانتکس', 'برای انتخاب مدت و حجم اکانت، وارد مسیر فعلی خرید شوید.', 'ادامه در مسیر خرید' ],
		'old-home' => [ 'اشتراک‌های پانتکس', 'پلن‌های فعال و قیمت‌ها را در صفحهٔ خرید ببینید.', 'مشاهدهٔ پلن‌ها' ],
		default => [ 'صفحه پیدا نشد', 'برای مشاهدهٔ پلن‌ها به صفحهٔ خرید برگردید.', 'بازگشت به خرید اشتراک' ],
	};
}
