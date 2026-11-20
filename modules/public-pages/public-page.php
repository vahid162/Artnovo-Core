<?php

namespace Artnovo\PublicPages;

defined( 'ABSPATH' ) || exit;

$artnovo_public_view = view();
get_header();
?>
<main id="content" class="artnovo-public-page" dir="rtl">
	<?php if ( 'search' === $artnovo_public_view ) : ?>
		<header class="artnovo-public-heading"><h1>جست‌وجو در آرت‌نوو</h1></header>
		<form class="artnovo-public-search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" role="search">
			<label class="screen-reader-text" for="artnovo-public-query">عبارت جست‌وجو</label>
			<input id="artnovo-public-query" type="search" name="s" value="<?php echo esc_attr( get_search_query( false ) ); ?>" placeholder="نام پلن یا راهنما">
			<button type="submit" class="artnovo-public-button">جست‌وجو</button>
		</form>
		<?php search_results(); ?>
	<?php elseif ( 'blog' === $artnovo_public_view ) : ?>
		<header class="artnovo-public-heading">
			<h1>راهنماهای آرت‌نوو</h1>
			<p>از دریافت لینک اشتراک تا اولین اتصال.</p>
		</header>
		<?php if ( ! is_paged() ) : ?>
			<div class="artnovo-public-grid">
				<?php foreach ( connection_guides() as $guide ) : ?>
					<article class="artnovo-public-card artnovo-public-story">
						<h2><?php echo esc_html( $guide[0] ); ?></h2>
						<p><?php echo esc_html( $guide[1] ); ?></p>
						<details><summary>خواندن راهنما</summary><p><?php echo esc_html( $guide[2] ); ?></p></details>
					</article>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( have_posts() ) : ?>
			<section class="artnovo-public-results">
				<h2>نوشته‌های آرت‌نوو</h2>
				<?php
				post_cards( $GLOBALS['wp_query']->posts );
				the_posts_pagination( [ 'prev_text' => 'قبلی', 'next_text' => 'بعدی' ] );
				?>
			</section>
		<?php endif; ?>
	<?php else : ?>
		<?php if ( 'cart' === $artnovo_public_view ) { wc_print_notices(); } ?>
		<?php $landing = landing_copy( $artnovo_public_view ); ?>
		<section class="artnovo-public-card artnovo-public-empty">
			<?php if ( 'not-found' === $artnovo_public_view ) : ?><span class="artnovo-public-code" aria-hidden="true">۴۰۴</span><?php endif; ?>
			<h1><?php echo esc_html( $landing[0] ); ?></h1>
			<p><?php echo esc_html( $landing[1] ); ?></p>
			<a class="artnovo-public-button" href="<?php echo esc_url( purchase_url() ); ?>"><?php echo esc_html( $landing[2] ); ?></a>
		</section>
	<?php endif; ?>
</main>
<?php
get_footer();
