<?php

namespace Artnovo\AccountUI;

defined( 'ABSPATH' ) || exit;

function render_account(): void {
	?>
	<main id="content" class="artnovo-account" dir="rtl">
		<?php if ( ! is_user_logged_in() ) : ?>
			<?php render_account_login(); ?>
		<?php else : ?>
			<header class="artnovo-account-header">
				<div><p class="artnovo-account-muted">پنل کاربری آرت‌نوو</p><h1>اشتراک‌ها و اتصال، یک‌جا</h1></div>
				<a class="artnovo-account-button" href="<?php echo esc_url( home_url( '/#artnovo-categories' ) ); ?>">خرید اشتراک جدید</a>
			</header>
			<div class="artnovo-account-layout">
				<nav class="artnovo-account-nav" aria-label="منوی حساب کاربری">
					<?php foreach ( [ 'subscriptions' => 'اشتراک‌های من', 'help' => 'راهنمای اتصال', 'support' => 'پشتیبانی', 'profile' => 'حساب من' ] as $view => $label ) : ?>
						<a href="<?php echo esc_url( account_url( $view ) ); ?>" <?php if ( account_view() === $view ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
					<a href="<?php echo esc_url( wp_logout_url( wc_get_page_permalink( 'myaccount' ) ) ); ?>">خروج از حساب</a>
				</nav>
				<div class="artnovo-account-main">
					<?php
					wc_print_notices();
					if ( is_wc_endpoint_url( 'view-order' ) ) {
						echo '<section class="artnovo-account-card woocommerce">';
						do_action( 'woocommerce_account_content' );
						echo '</section>';
					} elseif ( is_wc_endpoint_url( 'edit-account' ) || 'profile' === account_view() ) {
						render_account_profile();
					} elseif ( 'help' === account_view() ) {
						render_account_help();
					} elseif ( 'support' === account_view() ) {
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

function render_account_login(): void {
	$state = account_auth_state();
	$active = $state['expires'] > time();
	$destination = $state['redirect'];
	if ( isset( $_GET['artnovo_redirect'] ) && is_string( $_GET['artnovo_redirect'] ) ) {
		$destination = account_auth_destination( wp_unslash( $_GET['artnovo_redirect'] ) );
	} elseif ( ( empty( $state['email'] ) || is_wc_endpoint_url() || isset( $_GET['artnovo_view'] ) )
		&& ! empty( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ) {
		$destination = account_auth_destination( home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
	}
	?>
	<div class="artnovo-account-auth-layout">
		<section class="artnovo-account-card">
			<p class="artnovo-account-muted">خوش آمدید به آرت‌نوو</p>
			<h1 id="artnovo-account-login">ورود با ایمیل</h1>
			<p>ایمیل خریدتان را وارد کنید تا کد ورود برایتان ارسال شود. با ایمیل تازه، حساب مشتری ساخته می‌شود.</p>
			<form class="artnovo-account-auth" method="post" action="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" data-artnovo-expires="<?php echo esc_attr( (string) $state['expires'] ); ?>">
				<?php wp_nonce_field( 'artnovo_account_email_otp', 'artnovo_account_nonce', false ); ?>
				<input type="hidden" name="artnovo_account_action" value="<?php echo $active ? 'verify' : 'send'; ?>">
				<input type="hidden" name="artnovo_redirect" value="<?php echo esc_attr( $destination ); ?>">
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
				<button type="submit" class="artnovo-account-button" data-artnovo-auth-submit <?php disabled( ! account_email_login_ready() ); ?>><?php echo $active ? 'تأیید و ورود به حساب' : 'ارسال کد ورود'; ?></button>
				<?php if ( ! account_email_login_ready() ) : ?><p role="status">ورود ایمیلی موقتاً در دسترس نیست. کمی بعد دوباره تلاش کنید.</p><?php endif; ?>
			</form>
			<a href="<?php echo esc_url( home_url( '/#artnovo-categories' ) ); ?>">بازگشت به انتخاب اشتراک</a>
			<p class="artnovo-account-muted"><a href="mailto:orders@artnovo.ir?subject=Account%20login%20help">برای ورود به حساب کمک می‌خواهم</a></p>
		</section>
		<aside class="artnovo-account-feature">
			<span class="artnovo-account-badge">حساب آرت‌نوو</span>
			<h2>لینک اتصال و اشتراک‌ها همیشه در دسترس</h2>
			<ul><li>دریافت و کپی لینک خصوصی اتصال</li><li>مشاهدهٔ اعتبار و وضعیت آماده‌سازی</li><li>ارسال رسید و پیگیری خرید</li></ul>
			<p class="artnovo-account-muted">برای دیدن خریدهای قبلی، با همان ایمیلی وارد شوید که موقع خرید استفاده کرده‌اید.</p>
		</aside>
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
	$has_cards = $probe->next_tag( [ 'class_name' => 'wpvpn-subscription' ] );
	$error = __( 'Subscriptions could not be loaded.', 'wp-vpn-bridge' );
	if ( ! $has_cards && 1 === $page && ! str_contains( $html, esc_html( $error ) ) ) {
		?>
		<section class="artnovo-account-card artnovo-account-empty">
			<span class="artnovo-account-badge">اشتراک‌های من</span>
			<h2>هنوز اشتراکی نداری</h2>
			<p>با انتخاب یک پلن، اشتراکت اینجا نمایش داده می‌شود و لینک اتصال را از همین‌جا دریافت می‌کنی.</p>
			<a class="artnovo-account-button" href="<?php echo esc_url( home_url( '/#artnovo-categories' ) ); ?>">خرید اشتراک</a>
		</section>
		<?php
		return;
	}
	echo '<div class="artnovo-account-subscriptions">' . decorate_subscriptions( $html ) . '</div>';
}

function render_account_profile(): void {
	$user = wp_get_current_user();
	?>
	<section class="artnovo-account-card">
		<h2>حساب من</h2>
		<p>ایمیل ورود و دریافت اطلاع‌رسانی خریدها</p>
		<p dir="ltr" class="artnovo-account-email"><?php echo esc_html( $user->user_email ); ?></p>
		<p class="artnovo-account-muted">هر بار با کد یک‌بارمصرفِ همین ایمیل وارد حساب می‌شوید.</p>
		<div class="artnovo-account-actions"><a href="<?php echo esc_url( account_url( 'support' ) ); ?>">درخواست بررسی اطلاعات حساب</a><a href="<?php echo esc_url( wp_logout_url( wc_get_page_permalink( 'myaccount' ) ) ); ?>">خروج از حساب</a></div>
	</section>
	<?php
}

function render_account_help(): void {
	?>
	<section class="artnovo-account-card">
		<h2>راهنمای اتصال</h2>
		<p>لینک اشتراک را از کارت همان اشتراک در «اشتراک‌های من» دریافت کنید.</p>
		<ol><li>در کارت اشتراکِ آماده، «کپی لینک اتصال» را بزنید.</li><li>در برنامهٔ اتصال خود، گزینهٔ افزودن اشتراک یا واردکردن از کلیپ‌بورد را باز کنید.</li><li>لینک را وارد و فهرست سرورها را به‌روزرسانی کنید.</li><li>یکی از سرورها را انتخاب و اتصال را برقرار کنید.</li></ol>
		<details><summary>با کد QR چطور وصل شوم؟</summary><p>در کارت اشتراک، «نمایش QR» را بزنید و آن را با برنامهٔ اتصال دستگاه دیگر اسکن کنید.</p></details>
		<details><summary>لینک اتصال را نمی‌بینم</summary><p>وضعیت کارت را بررسی کنید. در خرید کارت‌به‌کارت، لینک پس از تأیید پرداخت و آماده‌سازی اشتراک نمایش داده می‌شود. رسید را از «جزئیات خرید و رسید» بفرستید.</p></details>
		<details><summary>اتصال برقرار نمی‌شود</summary><p>فهرست سرورها را به‌روزرسانی کنید، تاریخ اعتبار را بررسی کنید و در صورت ادامهٔ مشکل با شماره پیگیری خرید به پشتیبانی پیام بدهید.</p></details>
		<p class="artnovo-account-muted">لینک و کد QR خصوصی‌اند. آن‌ها را فقط روی دستگاه‌های خود استفاده کنید.</p>
		<a href="<?php echo esc_url( account_url() ); ?>">رفتن به اشتراک‌های من</a>
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
