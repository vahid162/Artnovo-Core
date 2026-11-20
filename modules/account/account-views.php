<?php

namespace Artnovo\AccountUI;

defined( 'ABSPATH' ) || exit;

function account_navigation(): array {
	$native = wc_get_account_menu_items();
	$view = account_view();
	$primary = [
		'subscriptions' => [ 'label' => 'اکانت‌های من', 'url' => account_url(), 'current' => 'subscriptions' === $view ],
		'buy' => [ 'label' => 'خرید اکانت جدید', 'url' => home_url( '/#artnovo-categories' ), 'current' => false ],
	];
	foreach ( [ 'orders' => 'خریدها و پرداخت‌ها', 'woo-wallet' => 'کیف پول' ] as $endpoint => $label ) {
		if ( isset( $native[ $endpoint ] ) ) {
			$primary[ $endpoint ] = [ 'label' => $label, 'url' => wc_get_account_endpoint_url( $endpoint ), 'current' => $view === $endpoint ];
		}
	}
	foreach ( [ 'help' => 'راهنمای اتصال', 'support' => 'پشتیبانی' ] as $key => $label ) {
		$primary[ $key ] = [ 'label' => $label, 'url' => account_url( $key ), 'current' => $view === $key ];
	}
	$secondary = [ 'profile' => [ 'label' => 'اطلاعات حساب', 'url' => account_url( 'profile' ), 'current' => 'profile' === $view ] ];
	foreach ( $native as $endpoint => $label ) {
		if ( in_array( $endpoint, [ 'dashboard', 'vpn-subscriptions', 'edit-account', 'edit-address', 'customer-logout', 'orders', 'woo-wallet' ], true ) ) { continue; }
		$secondary[ $endpoint ] = [ 'label' => 'downloads' === $endpoint ? 'فایل‌های قابل دریافت' : $label, 'url' => wc_get_account_endpoint_url( $endpoint ), 'current' => $view === $endpoint ];
	}
	$secondary['logout'] = [ 'label' => 'خروج از حساب', 'url' => wp_logout_url( wc_get_page_permalink( 'myaccount' ) ), 'current' => false ];
	return [ 'primary' => $primary, 'secondary' => $secondary ];
}

function render_account_links( array $links ): void {
	foreach ( $links as $item ) : ?>
		<a href="<?php echo esc_url( $item['url'] ); ?>" <?php if ( $item['current'] ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( \Artnovo\Core\display_digits( $item['label'] ) ); ?></a>
	<?php endforeach;
}

function render_account_navigation( array $navigation, bool $compact ): void {
	if ( $compact ) {
		unset( $navigation['primary']['buy'] );
	}
	?>
	<nav class="artnovo-account-nav" aria-label="بخش‌های حساب پانتکس">
		<?php render_account_links( $navigation['primary'] ); ?>
		<details class="artnovo-account-more" <?php if ( array_filter( $navigation['secondary'], static fn( array $item ): bool => $item['current'] ) ) : ?>open<?php endif; ?>>
			<summary>بخش‌های دیگر حساب</summary>
			<div class="artnovo-account-more-links"><?php render_account_links( $navigation['secondary'] ); ?></div>
		</details>
	</nav>
	<?php if ( $compact ) : ?>
		<details class="artnovo-account-mobile-nav">
			<summary>منوی حساب</summary>
			<nav class="artnovo-account-nav" aria-label="بخش‌های حساب پانتکس">
				<?php render_account_links( array_merge( $navigation['primary'], $navigation['secondary'] ) ); ?>
			</nav>
		</details>
	<?php endif;
}

function account_endpoint_layout( string $endpoint ): ?array {
	return match ( $endpoint ) {
		'orders' => [ 'file' => 'account-orders', 'native' => 'myaccount/orders.php', 'title' => 'خریدها و پرداخت‌ها' ],
		'view-order' => [ 'file' => 'account-order', 'native' => 'myaccount/view-order.php', 'title' => 'جزئیات خرید و رسید' ],
		'edit-account' => [ 'file' => 'account-profile', 'native' => 'myaccount/form-edit-account.php', 'title' => 'ویرایش اطلاعات حساب' ],
		'downloads' => [ 'file' => 'account-downloads', 'native' => 'myaccount/downloads.php', 'title' => 'فایل‌های قابل دریافت' ],
		default => null,
	};
}

function render_account_endpoint( string $endpoint, ?array $layout ): void {
	$defer_notices = 'edit-account' === $endpoint;
	$replace = static function ( string $template, string $name, array $args ) use ( $layout, $endpoint, &$defer_notices ): string {
		if ( ! $layout || $name !== $layout['native'] ) {
			return $template;
		}
		$file = __DIR__ . '/' . $layout['file'] . '.php';
		if ( ! is_readable( $file ) ) {
			return $template;
		}
		if ( 'view-order' === $endpoint ) {
			$order = $args['order'] ?? null;
			if ( ! $order instanceof \WC_Order || get_current_user_id() !== $order->get_customer_id()
				|| ! \Artnovo\Core\bridge_available() || ! is_array( $order->get_meta( '_wpvpn_snapshot', true ) ) ) {
				return $template;
			}
		}
		if ( 'downloads' === $endpoint && ( ! WC()->customer || WC()->customer->get_downloadable_products() ) ) {
			return $template;
		}
		$defer_notices = false;
		return $file;
	};
	add_filter( 'wc_get_template', $replace, 20, 3 );
	ob_start();
	try {
		do_action( 'woocommerce_account_content' );
		$content = (string) ob_get_contents();
	} finally {
		ob_end_clean();
		remove_filter( 'wc_get_template', $replace, 20 );
	}
	if ( $defer_notices ) {
		wc_print_notices();
	}
	echo '<section class="artnovo-account-native woocommerce' . ( $layout ? '' : ' artnovo-account-card' ) . ( 'downloads' === $endpoint ? ' artnovo-account-downloads' : '' ) . '">'
		. localize_subscription_display( $content, in_array( $endpoint, [ 'orders', 'view-order' ], true ) ) . '</section>';
}

function render_account(): void {
	$logged_in = is_user_logged_in();
	$current = $logged_in ? account_view() : '';
	$endpoint = $logged_in ? (string) WC()->query->get_current_endpoint() : '';
	$layout = account_endpoint_layout( $endpoint );
	$wallet_view = $logged_in && function_exists( '\\Artnovo\\WalletUI\\account_view' ) && \Artnovo\WalletUI\account_view();
	$secondary_title = match ( $current ) {
		'help' => 'راهنمای اتصال',
		'support' => 'پشتیبانی',
		'profile' => 'اطلاعات حساب',
		default => '',
	};
	if ( '' !== $endpoint && ! in_array( $endpoint, [ 'dashboard', 'vpn-subscriptions' ], true ) ) {
		$secondary_title = '';
	}
	$gallery = $wallet_view || 'downloads' === $endpoint || '' !== $secondary_title;
	$compact = 'subscriptions' === $current || null !== $layout || $gallery;
	?>
	<main id="content" class="artnovo-account<?php if ( $compact ) : ?> artnovo-account--compact<?php endif; ?><?php if ( $gallery ) : ?> artnovo-account--gallery<?php endif; ?><?php if ( 'subscriptions' === $current ) : ?> artnovo-account--subscriptions<?php endif; ?>" dir="rtl" data-artnovo-price-thousand-separator="<?php echo esc_attr( wc_get_price_thousand_separator() ); ?>" data-artnovo-price-decimal-separator="<?php echo esc_attr( wc_get_price_decimal_separator() ); ?>">
		<?php if ( ! $logged_in ) : ?>
			<?php render_account_login(); ?>
		<?php else :
			$navigation = account_navigation();
			if ( ! $compact ) :
				$title = 'اکانت‌های من';
				foreach ( array_merge( $navigation['primary'], $navigation['secondary'] ) as $item ) {
					if ( $item['current'] ) { $title = $item['label']; break; }
				}
			?>
				<header class="artnovo-account-header">
					<div><p class="artnovo-account-muted">حساب پانتکس</p><h1><?php echo esc_html( \Artnovo\Core\display_digits( $title ) ); ?></h1><p class="artnovo-account-muted">اکانت‌ها، خریدها و راهنمای استفاده در یک حساب.</p></div>
					<a class="artnovo-account-button" href="<?php echo esc_url( home_url( '/#artnovo-categories' ) ); ?>">خرید اکانت جدید</a>
				</header>
			<?php endif; ?>
			<div class="artnovo-account-layout">
				<?php render_account_navigation( $navigation, $compact ); ?>
				<div class="artnovo-account-main">
					<?php
					if ( ! $wallet_view && ( $layout || '' !== $secondary_title ) ) {
						?>
						<header class="artnovo-account-header">
							<h1><?php echo esc_html( $layout['title'] ?? $secondary_title ); ?></h1>
							<?php if ( 'view-order' === $endpoint ) : ?>
								<a class="artnovo-account-back" href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">بازگشت به خریدها</a>
							<?php elseif ( 'edit-account' === $endpoint ) : ?>
								<a class="artnovo-account-back" href="<?php echo esc_url( account_url( 'profile' ) ); ?>">بازگشت به اطلاعات حساب</a>
							<?php endif; ?>
						</header>
						<?php
					}
					if ( 'edit-account' !== $endpoint ) {
						wc_print_notices();
					}
					if ( ! in_array( $endpoint, [ '', 'dashboard', 'vpn-subscriptions' ], true ) ) {
						render_account_endpoint( $endpoint, $layout );
					} elseif ( 'profile' === $current ) {
						render_account_profile();
					} elseif ( 'help' === $current ) {
						render_account_help();
					} elseif ( 'support' === $current ) {
						render_account_support();
					} else {
						render_account_subscriptions();
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	</main>
	<?php
}

function render_account_login( bool $complete = false ): void {
	if ( ! $complete && native_login_ready() && ! account_email_login_page() ) {
		render_standalone_login();
		return;
	}
	$state = account_auth_state( $complete );
	$active = $state['expires'] > time();
	$destination = account_login_destination( $state, $complete );
	?>
	<div class="<?php echo $complete ? 'artnovo-account-auth-layout' : 'artnovo-standalone-login artnovo-email-login'; ?>">
		<section class="<?php echo $complete ? 'artnovo-account-card' : 'artnovo-email-panel'; ?>">
			<?php if ( $complete ) : ?><p class="artnovo-account-muted">خوش آمدید به پانتکس</p><?php endif; ?>
			<?php if ( $complete ) : ?><h2 id="artnovo-account-login">تأیید ایمیل همین حساب</h2><?php else : ?><h1 id="artnovo-account-login">ورود و ثبت‌نام با ایمیل</h1><?php endif; ?>
			<p><?php echo $complete ? 'برای دریافت اعلان آماده‌شدن اشتراک، ایمیل خود را با کد تأیید به همین حساب اضافه کنید.' : 'ایمیل خریدتان را وارد کنید تا کد ورود برایتان ارسال شود. با ایمیل تازه، حساب مشتری ساخته می‌شود.'; ?></p>
			<form class="artnovo-account-auth" method="post" action="<?php echo esc_url( $complete ? wc_get_page_permalink( 'myaccount' ) : add_query_arg( 'artnovo_email_login', '1', wc_get_page_permalink( 'myaccount' ) ) ); ?>" data-artnovo-expires="<?php echo esc_attr( (string) $state['expires'] ); ?>">
				<?php wp_nonce_field( 'artnovo_account_email_otp', 'artnovo_account_nonce', false ); ?>
				<input type="hidden" name="artnovo_account_action" value="<?php echo $active ? 'verify' : 'send'; ?>">
				<input type="hidden" name="artnovo_redirect" value="<?php echo esc_attr( $destination ); ?>">
				<?php if ( $complete ) : ?><input type="hidden" name="artnovo_complete_email" value="1"><?php endif; ?>
				<label for="artnovo-account-email">ایمیل حساب</label>
				<input id="artnovo-account-email" type="email" name="artnovo_email" dir="ltr" autocomplete="email" maxlength="100" placeholder="you@example.com" value="<?php echo esc_attr( $state['email'] ); ?>" <?php wp_readonly( $active ); ?> required>
				<div data-artnovo-code-fields <?php echo $active ? '' : 'hidden'; ?>>
					<label for="artnovo-account-code">کد تأیید ایمیل</label>
					<input id="artnovo-account-code" type="text" name="artnovo_otp_code" dir="ltr" inputmode="numeric" autocomplete="one-time-code" maxlength="6" aria-describedby="artnovo-account-code-hint" <?php disabled( ! $active ); ?> <?php echo $active ? 'required' : ''; ?>>
					<p id="artnovo-account-code-hint" class="artnovo-account-muted">کد شش‌رقمی ارسال‌شده به ایمیل را وارد کنید.</p>
					<div class="artnovo-account-actions">
						<button type="button" data-artnovo-auth-edit>تغییر ایمیل</button>
						<button type="button" data-artnovo-auth-resend>ارسال دوبارهٔ کد</button>
					</div>
				</div>
				<p data-artnovo-auth-message role="status" <?php echo $state['message'] ? '' : 'hidden'; ?> <?php echo $state['error'] ? 'data-error' : ''; ?>><?php echo esc_html( $state['message'] ); ?></p>
				<button type="submit" class="artnovo-account-button" data-artnovo-auth-submit <?php disabled( ! account_email_login_ready() ); ?>><?php echo $active ? ( $complete ? 'تأیید و ثبت ایمیل' : 'تأیید و ورود به حساب' ) : 'ارسال کد تأیید'; ?></button>
				<?php if ( ! account_email_login_ready() ) : ?><p role="status">ورود ایمیلی موقتاً در دسترس نیست. کمی بعد دوباره تلاش کنید.</p><?php endif; ?>
			</form>
			<?php if ( ! $complete && native_login_ready() ) : ?><a class="artnovo-login-alternative" href="<?php echo esc_url( account_native_login_url( $destination ) ); ?>">ورود با موبایل یا نام کاربری</a><?php endif; ?>
			<a href="<?php echo esc_url( home_url( '/#artnovo-categories' ) ); ?>">بازگشت به انتخاب اشتراک</a>
			<p class="artnovo-account-muted"><a href="mailto:orders@artnovo.ir?subject=Account%20login%20help">برای ورود به حساب کمک می‌خواهم</a></p>
		</section>
		<?php if ( $complete ) : ?><aside class="artnovo-account-feature">
			<span class="artnovo-account-badge">حساب پانتکس</span>
			<h2>لینک اتصال و اشتراک‌ها همیشه در دسترس</h2>
			<ul><li>دریافت و کپی لینک خصوصی اتصال</li><li>مشاهدهٔ اعتبار و وضعیت آماده‌سازی</li><li>ارسال رسید و پیگیری خرید</li></ul>
			<p class="artnovo-account-muted">برای دیدن خریدهای قبلی، با همان حسابی وارد شوید که سفارش را با آن ثبت کرده‌اید.</p>
		</aside><?php endif; ?>
	</div>
	<?php
}

function render_account_subscriptions(): void {
	if ( ! has_action( 'woocommerce_account_vpn-subscriptions_endpoint' ) ) {
		render_subscriptions_title( false );
		echo '<section class="artnovo-account-card"><p>دریافت اکانت‌ها موقتاً در دسترس نیست. کمی بعد دوباره تلاش کنید.</p></section>';
		return;
	}
	$page = max( 1, absint( get_query_var( 'vpn-subscriptions', 1 ) ) );
	ob_start();
	do_action( 'woocommerce_account_vpn-subscriptions_endpoint', $page );
	$html = ob_get_clean();
	$probe = new \WP_HTML_Tag_Processor( $html );
	$has_cards = false;
	$has_error = false;
	while ( $probe->next_tag() ) {
		$has_cards = $has_cards || $probe->has_class( 'wpvpn-subscription' );
		$has_error = $has_error || $probe->has_class( 'woocommerce-error' ) || 'alert' === $probe->get_attribute( 'role' );
	}
	$error = __( 'Subscriptions could not be loaded.', 'wp-vpn-bridge' );
	$empty = __( 'No subscriptions on this page.', 'wp-vpn-bridge' );
	$is_empty = ! $has_cards && ! $has_error && 1 === $page && str_contains( $html, esc_html( $empty ) ) && ! str_contains( $html, esc_html( $error ) );
	render_subscriptions_title( ! $is_empty );
	if ( $is_empty ) {
		?>
		<section class="artnovo-account-card artnovo-account-empty">
			<h2>هنوز اکانتی ندارید</h2>
			<p>پس از خرید، وضعیت اکانت و لینک اتصال را همین‌جا می‌بینید.</p>
			<a class="artnovo-account-button" href="<?php echo esc_url( home_url( '/#artnovo-categories' ) ); ?>">خرید اولین اکانت</a>
		</section>
		<?php
		return;
	}
	echo '<div class="artnovo-account-subscriptions">' . decorate_subscriptions( localize_subscription_display( $html ) ) . '</div>';
}

function render_subscriptions_title( bool $show_buy ): void {
	?>
	<header class="artnovo-account-header">
		<h1>اکانت‌های من</h1>
		<?php if ( $show_buy ) : ?>
			<a class="artnovo-account-button" href="<?php echo esc_url( home_url( '/#artnovo-categories' ) ); ?>">خرید اکانت جدید</a>
		<?php endif; ?>
	</header>
	<?php
}

function render_account_profile(): void {
	$user = wp_get_current_user();
	$email_state = '' === $user->user_email ? account_auth_state( true ) : [];
	$email_open = ( $email_state['expires'] ?? 0 ) > time() || ! empty( $email_state['message'] ) || ! empty( $email_state['error'] );
	?>
	<section class="artnovo-account-card artnovo-account-gallery-card">
		<header class="artnovo-account-gallery-row">
			<h2>مشخصات ثبت‌شده</h2>
			<a class="artnovo-account-gallery-link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">ویرایش اطلاعات حساب</a>
		</header>
		<dl class="artnovo-account-gallery-facts">
			<div><dt>نام نمایشی</dt><dd><?php echo esc_html( $user->display_name ); ?></dd></div>
			<div><dt>ایمیل</dt><dd><?php if ( '' !== $user->user_email ) : ?><bdi dir="ltr"><?php echo esc_html( $user->user_email ); ?></bdi><?php else : ?>ثبت نشده<?php endif; ?></dd></div>
		</dl>
		<?php if ( '' === $user->user_email ) : ?>
			<p class="artnovo-account-muted">ثبت ایمیل برای خرید و دریافت اکانت الزامی نیست. با تأیید ایمیل می‌توانید پیام‌های مربوط به خرید را دریافت کنید.</p>
			<details class="artnovo-account-email-completion" <?php if ( $email_open ) : ?>open<?php endif; ?>><summary>افزودن ایمیل برای اطلاع‌رسانی خریدها</summary><?php render_account_login( true ); ?></details>
		<?php endif; ?>
	</section>
	<?php
}

function render_account_help(): void {
	?>
	<section class="artnovo-account-card artnovo-account-gallery-card">
		<h2>اتصال در سه قدم</h2>
		<ol class="artnovo-account-gallery-steps" role="list">
			<li><span aria-hidden="true">۱</span><div><strong>لینک اشتراک را کپی کنید</strong><p>در «اکانت‌های من»، روی «کپی لینک اتصال» بزنید.</p></div></li>
			<li><span aria-hidden="true">۲</span><div><strong>اشتراک را به برنامه اضافه کنید</strong><p>در برنامهٔ اتصال، افزودن اشتراک از کلیپ‌بورد را انتخاب کنید.</p></div></li>
			<li><span aria-hidden="true">۳</span><div><strong>یک سرور را انتخاب کنید</strong><p>فهرست را به‌روزرسانی کنید و متصل شوید.</p></div></li>
		</ol>
		<a class="artnovo-account-button artnovo-account-gallery-cta" href="<?php echo esc_url( account_url() ); ?>">رفتن به اکانت‌های من</a>
	</section>
	<section class="artnovo-account-card artnovo-account-gallery-card">
		<details><summary>چطور با کد QR وصل شوم؟</summary><p>«نمایش QR» را در کارت اشتراک بزنید و با دستگاه دیگر اسکن کنید. لینک اشتراک و QR خصوصی‌اند.</p></details>
		<details><summary>لینک اتصال را نمی‌بینم</summary><p>وضعیت خرید را بررسی کنید. اگر پرداخت تأیید شده و لینک هنوز در دسترس نیست، با پشتیبانی تماس بگیرید.</p></details>
		<details><summary>اتصال برقرار نمی‌شود</summary><p>فهرست سرورها را به‌روزرسانی کنید، تاریخ اعتبار را بررسی کنید و در صورت ادامهٔ مشکل با شماره پیگیری خرید به پشتیبانی پیام بدهید.</p></details>
	</section>
	<?php
}

function render_account_support(): void {
	$id = isset( $_GET['order'] ) && is_string( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
	$order = $id ? wc_get_order( $id ) : false;
	$owned = $order instanceof \WC_Order && get_current_user_id() === $order->get_customer_id();
	$subject = $owned ? 'پشتیبانی خرید ' . $order->get_order_number() : 'پشتیبانی پانتکس';
	$email = 'orders@artnovo.ir';
	?>
	<section class="artnovo-account-card artnovo-account-gallery-card">
		<div class="artnovo-account-gallery-support">
			<span class="artnovo-account-gallery-symbol" aria-hidden="true">✉</span>
			<div><h2>برای کدام خرید کمک می‌خواهید؟</h2><p>شمارهٔ سفارش و توضیح مشکل را در ایمیل بنویسید.</p></div>
			<a class="artnovo-account-button artnovo-account-gallery-cta" href="<?php echo esc_url( 'mailto:' . $email . '?subject=' . rawurlencode( $subject ) ); ?>">ارسال ایمیل به پشتیبانی</a>
		</div>
		<?php if ( $owned ) : ?>
			<div class="artnovo-account-gallery-row">
				<p>شماره پیگیری خرید <bdi><?php echo esc_html( \Artnovo\Core\display_digits( $order->get_order_number() ) ); ?></bdi></p>
				<a class="artnovo-account-gallery-link" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">جزئیات خرید و رسید</a>
			</div>
		<?php endif; ?>
		<p class="artnovo-account-muted">آدرس پشتیبانی <bdi dir="ltr"><?php echo esc_html( $email ); ?></bdi></p>
		<p class="artnovo-account-muted">لینک خصوصی اتصال را در ایمیل نفرستید.</p>
	</section>
	<section class="artnovo-account-card artnovo-account-gallery-card artnovo-account-gallery-row">
		<div><h2>مشکل در اتصال دارید؟</h2><p class="artnovo-account-muted">مراحل افزودن لینک و استفاده از QR را ببینید.</p></div>
		<a class="artnovo-account-gallery-link" href="<?php echo esc_url( account_url( 'help' ) ); ?>">راهنمای اتصال</a>
	</section>
	<?php
}
