<?php
// Actual account routes and safe returns with process-local state; no WordPress, mail or network.
namespace Artnovo\Core {
	function bridge_available(): bool { return $GLOBALS['bridge_ready']; }
}
namespace Artnovo\PlanCheckout {
	function wizard_draft(): array { return $GLOBALS['cold_session'] ? [] : $GLOBALS['draft']; }
	function wizard_url( int $id ): string { return 'https://example.test/?artnovo_plan=' . $id . '#artnovo-order'; }
}
namespace Pinova {
	if ( 'no-pinova' !== ( $argv[1] ?? '' ) ) {
		class Pinova {
			public static function instance(): self { static $instance; return $instance ??= new self(); }
			public static function get_option( string $key, string $default ): string { return $default; }
		}
	}
}
namespace Pinova\Services {
	class OTPService { public static function code_length(): int { return 4; } }
}
namespace {
if ( PHP_VERSION_ID < 80500 || PHP_VERSION_ID >= 80600 ) { exit( 1 ); }
if ( ! isset( $argv[1] ) ) {
	foreach ( [ 'native', 'no-pinova', 'missing-partial' ] as $case ) {
		$process = proc_open( [ PHP_BINARY, __FILE__, $case ], [ 0 => STDIN, 1 => STDOUT, 2 => STDERR ], $pipes );
		if ( ! is_resource( $process ) || 0 !== proc_close( $process ) ) { exit( 1 ); }
	}
	exit;
}
$temporary = sys_get_temp_dir() . '/artnovo-native-login-' . bin2hex( random_bytes( 6 ) );
mkdir( $temporary, 0700 );
mkdir( $temporary . '/templates', 0700 );
if ( 'missing-partial' !== $argv[1] ) { file_put_contents( $temporary . '/templates/login-partial.php', '' ); }
register_shutdown_function( static function () use ( $temporary ): void {
	if ( is_file( $temporary . '/templates/login-partial.php' ) ) { unlink( $temporary . '/templates/login-partial.php' ); }
	rmdir( $temporary . '/templates' );
	rmdir( $temporary );
} );
define( 'ABSPATH', $temporary . '/unused-wordpress/' );
$wordpress = isset( $argv[2] ) ? rtrim( $argv[2], '/' ) : '';
define( 'PINOVA_DIR', $wordpress ? $wordpress . '/wp-content/plugins/pinova' : $temporary );
define( 'PINOVA_URL', 'https://example.test/wp-content/plugins/pinova/' );
define( 'PINOVA_VERSION', 'fixture' );
$hooks = $removed = $styles = [];
$bridge_ready = true;
$logged_in = false;
$account_page = false;
$endpoint = '';
$draft = [];
$cold_session = false;
$failures = $assertions = $cache_headers = 0;
$wp = (object) [ 'request' => 'login' ];
$wp_query = (object) [ 'is_404' => true ];
$_GET = $_POST = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/login/';
class LoginRedirect extends RuntimeException {}
function is_wp_error( mixed $value ): bool { return false; }
function check( string $name, bool $condition ): void {
	++$GLOBALS['assertions'];
	if ( ! $condition ) { ++$GLOBALS['failures']; fwrite( STDERR, "FAIL $name\n" ); }
}
function add_action( string $name, callable $callback, int $priority = 10, int $accepted = 1 ): void { $GLOBALS['hooks'][$name][$priority][] = $callback; }
function add_filter( ...$args ): void { add_action( ...$args ); }
function remove_action( string $name, mixed $callback, int $priority = 10 ): void { $GLOBALS['removed'][] = [ $name, $callback, $priority ]; }
function do_action( string $name ): void {
	$priorities = $GLOBALS['hooks'][$name] ?? []; ksort( $priorities );
	foreach ( $priorities as $callbacks ) { foreach ( $callbacks as $callback ) { $callback(); } }
}
function apply_filters( string $name, mixed $value ): mixed {
	$priorities = $GLOBALS['hooks'][$name] ?? []; ksort( $priorities );
	foreach ( $priorities as $callbacks ) { foreach ( $callbacks as $callback ) { $value = $callback( $value ); } }
	return $value;
}
function is_user_logged_in(): bool { return $GLOBALS['logged_in']; }
function is_account_page(): bool { return $GLOBALS['account_page']; }
function is_wc_endpoint_url( string|false $name = false ): bool { return false === $name ? '' !== $GLOBALS['endpoint'] : $name === $GLOBALS['endpoint']; }
function home_url( string $path = '' ): string { return 'https://example.test' . $path; }
function wc_get_page_permalink( string $page ): string { return home_url( '/' . ( 'myaccount' === $page ? 'my-account' : $page ) . '/' ); }
function get_privacy_policy_url(): string { return $GLOBALS['policy_url'] ?? home_url( '/privacy-policy/' ); }
function wc_get_account_endpoint_url( string $endpoint ): string { return wc_get_endpoint_url( $endpoint, '', wc_get_page_permalink( 'myaccount' ) ); }
function wc_get_endpoint_url( string $endpoint, string $value, string $base ): string { return $base . $endpoint . '/' . ( '' === $value ? '' : $value . '/' ); }
function wp_parse_url( string $value, int $component = -1 ): mixed { return parse_url( $value, $component ); }
function wp_validate_redirect( string $value, string $fallback ): string { return str_starts_with( $value, home_url( '/' ) ) ? $value : $fallback; }
function trailingslashit( string $value ): string { return rtrim( $value, '/' ) . '/'; }
function wp_unslash( string $value ): string { return stripslashes( $value ); }
function esc_url_raw( string $value ): string { return $value; }
function esc_url( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function rest_url(): string { return 'https://example.test/wp-json/'; }
function wp_create_nonce( string $scope ): string { return 'fixture-nonce'; }
function admin_url( string $path ): string { return 'https://example.test/wp-admin/' . $path; }
function wp_nonce_field( ...$args ): void {}
function wp_readonly( bool $condition ): void {}
function disabled( bool $condition ): void {}
function nocache_headers(): void { ++$GLOBALS['cache_headers']; }
function status_header( int $status ): void { $GLOBALS['status'] = $status; }
function wp_safe_redirect( string $url ): never { throw new LoginRedirect( $url ); }
function wp_enqueue_style( string $name, string $url, array $dependencies, string $version ): void { $GLOBALS['styles'][$name] = [ $url, $dependencies ]; }
function plugins_url( string $path, string $file = '' ): string { return 'https://example.test/wp-content/plugins/artnovo-core/' . $path; }
function add_query_arg( string|array $key, mixed $value, ?string $url = null ): string {
	$base = is_array( $key ) ? $value : $url;
	$parts = parse_url( $base ); parse_str( $parts['query'] ?? '', $query );
	$query = array_merge( $query, is_array( $key ) ? $key : [ $key => $value ] );
	return explode( '?', $base )[0] . '?' . http_build_query( $query );
}
function WC(): object {
	static $woo;
	return $woo ??= (object) [ 'session' => null, 'query' => new class {
		public function get_current_endpoint(): string { return $GLOBALS['endpoint']; }
		public function get_query_vars(): array { return [ 'orders' => 'orders', 'view-order' => 'view-order', 'vpn-subscriptions' => 'vpn-subscriptions', 'woo-wallet' => 'woo-wallet', 'order-pay' => 'order-pay', 'order-received' => 'order-received', 'customer-logout' => 'customer-logout', 'lost-password' => 'lost-password' ]; }
	} ];
}
require dirname( __DIR__ ) . '/modules/account/artnovo-account-ui.php';

if ( 'native' !== $argv[1] ) {
	$account_page = true;
	check( 'unavailable native component never enables its wrapper', ! Artnovo\AccountUI\native_login_ready() && ! Artnovo\AccountUI\native_login_page() );
	foreach ( $hooks['template_redirect'][3] ?? [] as $callback ) { $callback(); }
	check( 'unavailable native component does not replace or redirect native login', [] === $removed );
	ob_start(); Artnovo\AccountUI\render_account_login(); $fallback = ob_get_clean();
	check( 'existing Core email fallback renders without a native template', str_contains( $fallback, 'name="artnovo_email"' ) && str_contains( $fallback, 'artnovo-email-login' ) && ! str_contains( $fallback, 'pinovaLoginForm()' ) && ! str_contains( $fallback, '<aside' ) );
	echo "{$argv[1]}: {$assertions} account login assertions, {$failures} failures\n";
	exit( $failures ? 1 : 0 );
}
check( 'public native login uses the Core guest template', Artnovo\AccountUI\custom_account_page() && Artnovo\AccountUI\native_login_page() );
check( 'login body gets scoped account styles', in_array( 'artnovo-account-page', apply_filters( 'body_class', [] ), true ) );
check( 'login route uses the account template', str_ends_with( apply_filters( 'template_include', '/theme/page.php' ), '/account/account-page.php' ) );
check( 'login page is excluded from indexing', [ 'noindex' => true, 'nofollow' => true ] === apply_filters( 'wp_robots', [] ) );
try { do_action( 'template_redirect' ); check( 'missing return is canonicalized', false ); }
catch ( LoginRedirect $redirect ) { check( 'missing return is canonicalized', $redirect->getMessage() === add_query_arg( 'back_url', Artnovo\AccountUI\account_url(), home_url( '/login/' ) ) ); }
check( 'public login responds successfully and bypasses the full native HTML handler', false === $wp_query->is_404 && 200 === $status && count( $removed ) >= 1 );

foreach ( [
	[ 'https://example.test/my-account/vpn-subscriptions/', 'https://example.test/my-account/vpn-subscriptions/' ],
	[ 'https://example.test/my-account/?artnovo_view=support&order=31&delete=1', 'https://example.test/my-account/?artnovo_view=support&order=31' ],
	[ 'https://example.test/my-account/woo-wallet/', 'https://example.test/my-account/woo-wallet/' ],
	[ 'https://example.test/checkout/order-pay/31/?pay_for_order=1&key=wc_order_fixture&delete=1', 'https://example.test/checkout/order-pay/31/?pay_for_order=true&key=wc_order_fixture' ],
	[ 'https://example.test/checkout/order-received/31/?key=wc_order_fixture', 'https://example.test/checkout/order-received/31/?key=wc_order_fixture' ],
	[ 'https://example.test/checkout/order-pay/0/?key=wc_order_fixture', Artnovo\AccountUI\account_url() ],
	[ 'https://example.test/checkout/order-pay/31/?key[]=invalid', Artnovo\AccountUI\account_url() ],
	[ 'https://example.test/checkout/order-pay/31/?key=invalid%2Fkey', Artnovo\AccountUI\account_url() ],
	[ 'https://example.test/my-account/customer-logout/?_wpnonce=synthetic', Artnovo\AccountUI\account_url() ],
	[ 'https://example.test/my-account/%2e%2e/wp-admin/', Artnovo\AccountUI\account_url() ],
	[ 'https://external.test/steal', Artnovo\AccountUI\account_url() ],
	[ 'https://example.test:444/my-account/', Artnovo\AccountUI\account_url() ],
	[ 'https://user:pass@example.test/my-account/', Artnovo\AccountUI\account_url() ],
] as [ $requested, $expected ] ) {
	$_GET = [ 'back_url' => $requested ];
	$state = Artnovo\AccountUI\account_auth_state();
	check( 'customer return is canonical: ' . $requested, $expected === Artnovo\AccountUI\account_login_destination( $state ) );
	if ( Artnovo\AccountUI\account_login_is_customer_return( $requested ) ) {
		check( 'native proof cannot bypass the customer return contract', $expected === apply_filters( 'pinova/login_back_url', $requested ) );
	}
}
foreach ( [ 'https://example.test/wp-admin/', 'https://example.test/other-plugin/return/', 'https://example.test/checkout/', 'https://example.test/checkout/?woo_wallet_topup=1&amount=100000', 'https://example.test/checkout/other-plugin/?action=funding', 'https://example.test/my-account/woo-wallet/?action=add' ] as $destination ) {
	$_GET = [ 'back_url' => $destination ]; $removed = [];
	check( 'administrator/third-party route remains native: ' . $destination, ! Artnovo\AccountUI\standalone_login_page() );
	do_action( 'template_redirect' );
	check( 'administrator/third-party handler and destination stay untouched', [] === $removed && $destination === apply_filters( 'pinova/login_back_url', $destination ) );
}
$_GET = [ 'artnovo_resume' => '1' ];
$draft = [ 'product' => 26, 'quantity' => 3, 'billing_email' => 'fixture@example.test' ];
$state = Artnovo\AccountUI\account_auth_state();
check( 'purchase resume keeps its canonical destination and state', home_url( '/my-account/?artnovo_resume=1' ) === Artnovo\AccountUI\account_login_destination( $state ) && 'fixture@example.test' === $state['email'] && 3 === $draft['quantity'] );
$bridge_ready = false;
$state = Artnovo\AccountUI\account_auth_state();
check( 'missing Bridge falls back to a usable account without a purchase dependency', wc_get_page_permalink( 'myaccount' ) === Artnovo\AccountUI\account_login_destination( $state ) );
$bridge_ready = true;
// Pinova's REST request has no Woo session. Preserve only the exact harmless GET marker.
$cold_session = true;
$resume = home_url( '/my-account/?artnovo_resume=1' );
check( 'cold native REST retains only the canonical purchase resume flag', null === WC()->session && $resume === apply_filters( 'pinova/login_back_url', $resume ) );
check( 'cold REST does not weaken the general destination validator', Artnovo\AccountUI\account_url() === Artnovo\AccountUI\account_auth_destination( $resume ) );
foreach ( [ 'https://external.test/my-account/?artnovo_resume=1', $resume . '&delete=1', home_url( '/my-account/?artnovo_resume[]=1' ), home_url( '/my-account/?artnovo_resume=01' ) ] as $destination ) {
	check( 'cold REST resume exception rejects foreign or noncanonical marker variants', Artnovo\AccountUI\account_url() === apply_filters( 'pinova/login_back_url', $destination ) );
}
$cold_session = false;
// The next normal frontend request loads Woo before the existing account route runs.
$account_page = true; $wp->request = 'my-account'; $logged_in = true; $_GET = [ 'artnovo_resume' => '1' ];
try { foreach ( $hooks['template_redirect'][4] ?? [] as $callback ) { $callback(); } check( 'fresh account GET resumes its valid saved selection', false ); }
catch ( LoginRedirect $redirect ) { check( 'fresh account GET resumes its valid saved selection', 'https://example.test/?artnovo_plan=26#artnovo-order' === $redirect->getMessage() && 3 === $draft['quantity'] ); }
$draft = [];
try { foreach ( $hooks['template_redirect'][4] ?? [] as $callback ) { $callback(); } check( 'fresh account GET without a valid selection falls back safely', false ); }
catch ( LoginRedirect $redirect ) { check( 'fresh account GET without a valid selection falls back safely', Artnovo\AccountUI\account_url() === $redirect->getMessage() ); }
$account_page = false; $wp->request = 'login'; $logged_in = false;
$_GET = [ 'back_url' => Artnovo\AccountUI\account_url() ];
$removed = [];
do_action( 'template_redirect' );
check( 'canonical back_url does not redirect again', count( $removed ) >= 1 );
$account_page = true; $wp->request = 'my-account';
check( 'guest account shares the native minimal form', Artnovo\AccountUI\native_login_page() );
$_GET = [ 'artnovo_email_login' => '1', 'back_url' => 'https://example.test/checkout/order-pay/31/?key=wc_order_fixture' ];
check( 'explicit email login preserves the existing engine instead of the native form', Artnovo\AccountUI\account_email_login_page() && ! Artnovo\AccountUI\native_login_page() );
check( 'direct email login is also excluded from indexing', [ 'noindex' => true, 'nofollow' => true ] === apply_filters( 'wp_robots', [] ) );
$removed = [];
foreach ( $hooks['template_redirect'][3] ?? [] as $callback ) { $callback(); }
check( 'explicit email route retains its query instead of a native canonical redirect', count( $removed ) === 1 && '1' === $_GET['artnovo_email_login'] );
ob_start(); Artnovo\AccountUI\render_account_login(); $email_login = ob_get_clean();
check( 'email signup is available in one minimal column without a duplicate native button', str_contains( $email_login, 'artnovo-email-login' ) && str_contains( $email_login, 'name="artnovo_email"' ) && str_contains( $email_login, 'name="artnovo_account_action" value="send"' ) && ! str_contains( $email_login, '<aside' ) && ! str_contains( $email_login, 'pinovaLoginForm()' ) );
check( 'email form preserves the safe financial destination and explicit route on POST', str_contains( $email_login, 'action="https://example.test/my-account/?artnovo_email_login=1"' ) && str_contains( $email_login, 'value="https://example.test/checkout/order-pay/31/?key=wc_order_fixture"' ) );
try { Artnovo\AccountUI\account_auth_reply( [ 'message' => 'fixture retry' ], false ); check( 'email retry keeps its explicit route', false ); }
catch ( LoginRedirect $redirect ) { check( 'email retry keeps its explicit route', home_url( '/my-account/?artnovo_email_login=1#artnovo-account-login' ) === $redirect->getMessage() ); }
$_GET = [];
$endpoint = 'woo-wallet';
check( 'third-party account endpoints retain their native template', ! Artnovo\AccountUI\native_login_page() && ! Artnovo\AccountUI\custom_account_page() );
$endpoint = 'lost-password';
check( 'guest recovery endpoint uses the custom account template and Pinova', Artnovo\AccountUI\native_recovery_page() && Artnovo\AccountUI\native_login_page() );
$invalid_recovery_back = 'https://external.test/untrusted';
$_GET = [ 'back_url' => $invalid_recovery_back ];
try { foreach ( $hooks['template_redirect'][3] ?? [] as $callback ) { $callback(); } check( 'recovery safely keeps its own endpoint on invalid back_url', false ); }
catch ( LoginRedirect $redirect ) { check( 'recovery safely keeps its own endpoint on invalid back_url', add_query_arg( 'back_url', Artnovo\AccountUI\account_url(), wc_get_account_endpoint_url( 'lost-password' ) ) === $redirect->getMessage() ); }
$_GET = [ 'back_url' => Artnovo\AccountUI\account_url() ];
$removed = [];
foreach ( $hooks['template_redirect'][3] ?? [] as $callback ) { $callback(); }
check( 'direct recovery is not redirected back to generic sign in', count( $removed ) === 1 );
foreach ( [ 'key', 'id', 'login', 'reset-link-sent', 'show-reset-form', 'reset-token' ] as $key ) {
	$_GET = [ $key => 'fixture' ];
	check( 'existing Woo password flow keeps its routing for ' . $key, ! Artnovo\AccountUI\native_recovery_page() && ! Artnovo\AccountUI\custom_account_page() );
}
$_GET = [ 'artnovo_email_login' => '1' ];
check( 'email sign-in does not replace the explicit recovery screen', ! Artnovo\AccountUI\account_email_login_page() && Artnovo\AccountUI\native_recovery_page() );
$_GET = [];
$_SERVER['REQUEST_METHOD'] = 'POST';
check( 'native reset form submissions retain their routing', ! Artnovo\AccountUI\native_recovery_page() );
$_SERVER['REQUEST_METHOD'] = 'GET';
$logged_in = true;
check( 'signed-in customers retain the native lost-password route', ! Artnovo\AccountUI\native_recovery_page() && ! Artnovo\AccountUI\custom_account_page() );
$endpoint = ''; $logged_in = true;
check( 'signed-in accounts never load the standalone login skin', ! Artnovo\AccountUI\native_login_page() );
ob_start(); Artnovo\AccountUI\render_account_login( true ); $complete = ob_get_clean();
check( 'profile email completion retains its proof fields and action', str_contains( $complete, 'name="artnovo_complete_email"' ) && str_contains( $complete, 'name="artnovo_otp_code"' ) && ! str_contains( $complete, 'artnovo-standalone-login' ) );
if ( $wordpress ) {
	// Use the installed parser and native partial without bootstrapping WordPress or its database.
	require $wordpress . '/wp-includes/utf8.php';
	require $wordpress . '/wp-includes/class-wp-token-map.php';
	require $wordpress . '/wp-includes/html-api/html5-named-character-references.php';
	foreach ( [ 'attribute-token', 'decoder', 'span', 'text-replacement', 'tag-processor' ] as $class ) {
		require $wordpress . '/wp-includes/html-api/class-wp-html-' . $class . '.php';
	}
	function wp_kses_uri_attributes(): array { return [ 'href', 'src' ]; }
	$logged_in = false;
	ob_start(); Artnovo\AccountUI\render_standalone_login(); $native = ob_get_clean();
	$tags = new WP_HTML_Tag_Processor( $native );
	check( 'native input keeps its engine binding and required semantics', $tags->next_tag( [ 'tag_name' => 'INPUT' ] ) && 'forms.authenticate.inputs.identifier.value' === $tags->get_attribute( 'pinova-model' ) && true === $tags->get_attribute( 'required' ) && 'ltr' === $tags->get_attribute( 'dir' ) && 'artnovo-native-identifier' === $tags->get_attribute( 'id' ) );
	check( 'native identifier has the requested associated label and required star', str_contains( $native, 'for="artnovo-native-identifier"' ) && str_contains( $native, 'شماره موبایل، ایمیل یا نام کاربری را وارد کنید' ) && str_contains( $native, 'aria-hidden="true">*</span>' ) );
	check( 'native engine assets and remaining proof flows remain in the partial', str_contains( $native, 'assets/js/pages/login-form.min.js' ) && str_contains( $native, 'assets/js/global.min.js' ) && str_contains( $native, 'forms.loginByPassword.inputs.password.value' ) && str_contains( $native, 'forms.loginByOtp.inputs.code.value' ) && str_contains( $native, 'forms.signIn.inputs.code.value' ) );
	check( 'minimal native wrapper has one title without the old Core email form', 1 === substr_count( $native, 'ورود به حساب پانتکس' ) && ! str_contains( $native, 'artnovo_account_action' ) );
	check( 'native form exposes the preserved Core email signup as a secondary route', str_contains( $native, 'ورود و ثبت‌نام با کد ایمیلی' ) && str_contains( $native, 'artnovo_email_login=1' ) && str_contains( $native, 'back_url=' ) );
	check( 'sign-in links to recovery without changing its native submit', str_contains( $native, '/my-account/lost-password/' ) && str_contains( $native, 'pinova-on:keyup.enter="submit()"' ) );
	$tags = new WP_HTML_Tag_Processor( $native ); $policy_links = [];
	while ( $tags->next_tag( [ 'tag_name' => 'A' ] ) ) { $policy_links[] = $tags->get_attribute( 'href' ); }
	check( 'native registration policy links use the official terms and privacy pages', in_array( wc_get_page_permalink( 'terms' ), $policy_links, true ) && in_array( get_privacy_policy_url(), $policy_links, true ) && str_contains( $native, 'حریم خصوصی' ) );
	$policy_url = '';
	$empty_policy = Artnovo\AccountUI\native_login_markup( '<div id="signIn"><a href="#">شرایط</a><a href="#">قوانین</a></div>' );
	check( 'missing policy URL never becomes an empty or placeholder destination', ! str_contains( $empty_policy, 'href=""' ) && ! str_contains( $empty_policy, 'href="#"' ) );
	$endpoint = 'lost-password';
	ob_start(); Artnovo\AccountUI\render_standalone_login(); $recovery = ob_get_clean();
	$tags = new WP_HTML_Tag_Processor( $recovery );
	check( 'recovery accepts a mobile or email through the existing Pinova model and recovery action',
		$tags->next_tag( 'INPUT' ) && 'forms.authenticate.inputs.identifier.value' === $tags->get_attribute( 'pinova-model' )
			&& str_contains( $tags->get_attribute( 'pinova-on:keyup.enter' ), "authenticate({forget: '1'}, 'forgotPassword')" )
			&& str_contains( $recovery, 'شمارهٔ موبایل یا ایمیل' ) && str_contains( $recovery, 'دریافت کد تأیید' ) );
	check( 'recovery keeps Pinova code, resend, and password-reset bindings',
		str_contains( $recovery, 'forms.forgotPassword.inputs.code.value' ) && str_contains( $recovery, 'forms.changePassword.inputs.password_1.value' )
			&& str_contains( $recovery, 'force_otp' ) && str_contains( $recovery, 'بازگشت به ورود' ) );
}
echo "{$assertions} account login assertions, {$failures} failures\n";
exit( $failures ? 1 : 0 );
}
