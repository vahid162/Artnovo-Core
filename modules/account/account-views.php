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

function render_account(): void {
	?>
	<main id="content" class="artnovo-account" dir="rtl">
		<?php if ( ! is_user_logged_in() ) : ?>
			<?php render_account_login(); ?>
		<?php else :
			$navigation = account_navigation();
			$current = account_view();
			$title = 'اکانت‌های من';
			foreach ( array_merge( $navigation['primary'], $navigation['secondary'] ) as $item ) {
				if ( $item['current'] ) { $title = $item['label']; break; }
			}
			if ( is_wc_endpoint_url( 'view-order' ) ) { $title = 'جزئیات خرید و رسید'; }
			?>
			<header class="artnovo-account-header">
				<div><p class="artnovo-account-muted">حساب آرت‌نوو</p><h1><?php echo esc_html( $title ); ?></h1><p class="artnovo-account-muted"><?php echo 'subscriptions' === $current ? 'لینک اتصال و اعتبار اکانت‌هایت همیشه اینجاست.' : 'اکانت‌ها، خریدها و راهنمای استفاده در یک حساب.'; ?></p></div>
				<a class="artnovo-account-button" href="<?php echo esc_url( home_url( '/#artnovo-categories' ) ); ?>">خرید اکانت جدید</a>
			</header>
			<div class="artnovo-account-layout">
				<nav class="artnovo-account-nav" aria-label="بخش‌های حساب آرت‌نوو">
					<?php foreach ( $navigation['primary'] as $item ) : ?>
						<a href="<?php echo esc_url( $item['url'] ); ?>" <?php if ( $item['current'] ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( $item['label'] ); ?></a>
					<?php endforeach; ?>
					<details class="artnovo-account-more" <?php if ( array_filter( $navigation['secondary'], static fn( array $item ): bool => $item['current'] ) ) : ?>open<?php endif; ?>>
						<summary>بخش‌های دیگر حساب</summary>
						<?php foreach ( $navigation['secondary'] as $item ) : ?>
							<a href="<?php echo esc_url( $item['url'] ); ?>" <?php if ( $item['current'] ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( $item['label'] ); ?></a>
						<?php endforeach; ?>
					</details>
				</nav>
				<div class="artnovo-account-main">
					<?php
					wc_print_notices();
					$endpoint = WC()->query->get_current_endpoint();
					if ( ! in_array( $endpoint, [ '', 'dashboard', 'vpn-subscriptions' ], true ) ) {
						echo '<section class="artnovo-account-card artnovo-account-native woocommerce">';
						do_action( 'woocommerce_account_content' );
						echo '</section>';
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
			<?php if ( $complete ) : ?><p class="artnovo-account-muted">خوش آمدید به آرت‌نوو</p><?php endif; ?>
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
			<span class="artnovo-account-badge">حساب آرت‌نوو</span>
			<h2>لینک اتصال و اشتراک‌ها همیشه در دسترس</h2>
			<ul><li>دریافت و کپی لینک خصوصی اتصال</li><li>مشاهدهٔ اعتبار و وضعیت آماده‌سازی</li><li>ارسال رسید و پیگیری خرید</li></ul>
			<p class="artnovo-account-muted">برای دیدن خریدهای قبلی، با همان حسابی وارد شوید که سفارش را با آن ثبت کرده‌اید.</p>
		</aside><?php endif; ?>
	</div>
	<?php
}

function render_account_subscriptions(): void {
	if ( ! has_action( 'woocommerce_account_vpn-subscriptions_endpoint' ) ) {
		echo '<section class="artnovo-account-card"><h2>اشتراک‌های من</h2><p>دریافت اشتراک‌ها موقتاً در دسترس نیست. کمی بعد دوباره تلاش کنید.</p></section>';
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
	if ( ! $has_cards && ! $has_error && 1 === $page && str_contains( $html, esc_html( $empty ) ) && ! str_contains( $html, esc_html( $error ) ) ) {
		?>
		<section class="artnovo-account-card artnovo-account-empty">
			<span class="artnovo-account-badge">اکانت‌های من</span>
			<h2>هنوز اکانتی نخریده‌ای</h2>
			<p>یک پلن انتخاب کن؛ پس از تأیید پرداخت و آماده‌شدن اکانت، لینک اتصال را همین‌جا دریافت می‌کنی.</p>
			<a class="artnovo-account-button" href="<?php echo esc_url( home_url( '/#artnovo-categories' ) ); ?>">انتخاب اکانت</a>
		</section>
		<?php
		return;
	}
	echo '<div class="artnovo-account-subscriptions">' . decorate_subscriptions( $html ) . '</div>';
}

function render_account_profile(): void {
	$user = wp_get_current_user();
	$email_state = '' === $user->user_email ? account_auth_state( true ) : [];
	$email_open = ( $email_state['expires'] ?? 0 ) > time() || ! empty( $email_state['message'] ) || ! empty( $email_state['error'] );
	?>
	<section class="artnovo-account-card">
		<h2>اطلاعات حساب</h2>
		<p>برای دیدن اکانت‌ها و خریدهای قبلی، همیشه با همان حساب وارد شوید.</p>
		<?php if ( '' !== $user->user_email ) : ?>
			<p>ایمیل ثبت‌شده در این حساب</p>
			<p dir="ltr" class="artnovo-account-email"><?php echo esc_html( $user->user_email ); ?></p>
		<?php else : ?>
			<p>ثبت ایمیل برای خرید و دریافت اکانت الزامی نیست.</p>
			<details class="artnovo-account-email-completion" <?php if ( $email_open ) : ?>open<?php endif; ?>><summary>افزودن ایمیل برای اطلاع‌رسانی خریدها</summary><?php render_account_login( true ); ?></details>
		<?php endif; ?>
		<div class="artnovo-account-actions"><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>">ویرایش اطلاعات حساب</a><a href="<?php echo esc_url( account_url( 'support' ) ); ?>">کمک برای ورود یا اطلاعات حساب</a><a href="<?php echo esc_url( wp_logout_url( wc_get_page_permalink( 'myaccount' ) ) ); ?>">خروج از حساب</a></div>
	</section>
	<?php
}

function render_account_help(): void {
	?>
	<section class="artnovo-account-card">
		<h2>راهنمای اتصال</h2>
		<p>لینک اشتراک را از کارت همان اشتراک در «اکانت‌های من» دریافت کنید.</p>
		<ol><li>در کارت اشتراکِ آماده، «کپی لینک اتصال» را بزنید.</li><li>در برنامهٔ اتصال خود، گزینهٔ افزودن اشتراک یا واردکردن از کلیپ‌بورد را باز کنید.</li><li>لینک را وارد و فهرست سرورها را به‌روزرسانی کنید.</li><li>یکی از سرورها را انتخاب و اتصال را برقرار کنید.</li></ol>
		<details><summary>با کد QR چطور وصل شوم؟</summary><p>در کارت اشتراک، «نمایش QR» را بزنید و آن را با برنامهٔ اتصال دستگاه دیگر اسکن کنید.</p></details>
		<details><summary>لینک اتصال را نمی‌بینم</summary><p>وضعیت کارت را بررسی کنید. در خرید کارت‌به‌کارت، لینک پس از تأیید پرداخت و آماده‌سازی اشتراک نمایش داده می‌شود. رسید را از «جزئیات خرید و رسید» بفرستید.</p></details>
		<details><summary>اتصال برقرار نمی‌شود</summary><p>فهرست سرورها را به‌روزرسانی کنید، تاریخ اعتبار را بررسی کنید و در صورت ادامهٔ مشکل با شماره پیگیری خرید به پشتیبانی پیام بدهید.</p></details>
		<p class="artnovo-account-muted">لینک و کد QR خصوصی‌اند. آن‌ها را فقط روی دستگاه‌های خود استفاده کنید.</p>
		<a href="<?php echo esc_url( account_url() ); ?>">بازگشت به اکانت‌های من</a>
	</section>
	<?php
}

function render_account_support(): void {
	$id = isset( $_GET['order'] ) && is_string( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
	$order = $id ? wc_get_order( $id ) : false;
	$owned = $order instanceof \WC_Order && get_current_user_id() === $order->get_customer_id();
	$subject = $owned ? 'پشتیبانی خرید ' . $order->get_order_number() : 'پشتیبانی آرت‌نوو';
	$email = 'orders@artnovo.ir';
	?>
	<section class="artnovo-account-card">
		<h2>پشتیبانی</h2>
		<p>مشکل اتصال، پرداخت یا دریافت اشتراک را همراه با شماره پیگیری خرید بفرستید.</p>
		<?php if ( $owned ) : ?>
			<p>شماره پیگیری خرید <strong><?php echo esc_html( $order->get_order_number() ); ?></strong></p>
			<p><a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">جزئیات خرید و رسید</a></p>
		<?php endif; ?>
		<a class="artnovo-account-button" href="<?php echo esc_url( 'mailto:' . $email . '?subject=' . rawurlencode( $subject ) ); ?>">ارسال پیام به پشتیبانی</a>
		<p dir="ltr" class="artnovo-account-email"><?php echo esc_html( $email ); ?></p>
		<p class="artnovo-account-muted">برای پیگیری، شماره خرید و شرح خطا کافی است. لینک خصوصی اتصال را در پیام نفرستید.</p>
	</section>
	<?php
}
