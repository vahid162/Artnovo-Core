<?php

namespace Artnovo\AccountUI;

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_account_downloads', false );
?>
<div class="artnovo-account-card artnovo-account-gallery-card artnovo-account-gallery-empty">
	<span class="artnovo-account-gallery-symbol" aria-hidden="true">↓</span>
	<h2>هنوز فایلی برای دانلود ندارید</h2>
	<p>لینک اتصال اشتراک‌ها در «اکانت‌های من» در دسترس است.</p>
	<a class="artnovo-account-button artnovo-account-gallery-cta" href="<?php echo esc_url( account_url() ); ?>">رفتن به اکانت‌های من</a>
</div>
<?php
do_action( 'woocommerce_after_account_downloads', false );
