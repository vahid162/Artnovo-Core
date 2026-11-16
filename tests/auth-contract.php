<?php
// Actual Core authentication helpers/request callback with process-local accounts/codes; no WordPress or network.
namespace Artnovo\Core {
	function bridge_available(): bool { return $GLOBALS['bridge_ready']; }
}
namespace Artnovo\AccountUI {
	function account_url( string $view = 'subscriptions' ): string {
		return 'subscriptions' === $view ? 'https://example.test/my-account/' : 'https://example.test/my-account/?artnovo_view=' . $view;
	}
}
namespace Artnovo\PlanCheckout {
	function checkout_post_value( string $name ): string { return is_string( $_POST[$name] ?? null ) ? trim( $_POST[$name] ) : ''; }
	function wizard_draft(): array { return ( $GLOBALS['draft']['expires'] ?? 0 ) >= time() ? $GLOBALS['draft'] : []; }
	function wizard_url( int $id ): string { return 'https://example.test/?artnovo_plan=' . $id . '#artnovo-order'; }
}
namespace Pinova {
	class Pinova {
		public static function users_can_register(): bool { return true; }
		public static function get_login_url( string $return ): string { return 'https://example.test/login?back_url=' . rawurlencode( $return ); }
	}
}
namespace Pinova\Channels {
	class Email { public static function send_code( string $email, int $code ): bool { $GLOBALS['sent_code'] = $code; return true; } }
}
namespace Pinova\Helpers {
	class JWT { public const DEFAULT_TTL = 180; }
	class IP { public static function get(): string { return '192.0.2.1'; } }
	class Number { public static function en( string $value ): string { return $value; } }
}
namespace Pinova\Services {
	class UserService { public static function login( int $id, string $method ): void { $GLOBALS['logins'][] = $id; } }
	class FirewallService { public static function is_blocked( string $value ): bool { return false; } }
}
namespace {
if ( PHP_VERSION_ID < 80500 || PHP_VERSION_ID >= 80600 ) { exit( 1 ); }
define( 'ABSPATH', __DIR__ . '/unused-wordpress/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
$hooks = $transients = $accounts = $logins = $updates = [];
$draft = [];
$bridge_ready = true;
$current_user = 7;
$created = 0;
$failures = 0;
$assertions = 0;
class WP_Error {
	public function __construct( public string $code = '', public string $message = '', public mixed $data = null ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
	public function get_error_data(): mixed { return $this->data; }
}
class SyntheticAuthResponse extends RuntimeException {
	public function __construct( public bool $success, public array $data, public int $status = 200 ) { parent::__construct( 'Synthetic response termination' ); }
}
class WP_User {
	public array $roles = [ 'customer' ];
	public array $caps = [ 'customer' => true ];
	public function __construct( public int $ID, public string $user_email ) {}
}
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted = 1 ): void { $GLOBALS['hooks'][$hook][$priority][] = $callback; }
function add_action( ...$args ): void { add_filter( ...$args ); }
function apply_filters( string $hook, mixed $value ): mixed {
	foreach ( $GLOBALS['hooks'][$hook] ?? [] as $callbacks ) { foreach ( $callbacks as $callback ) { $value = $callback( $value ); } }
	return $value;
}
function get_option( string $key ): string { return 'yes'; }
function get_transient( string $key ): mixed { return $GLOBALS['transients'][$key] ?? false; }
function set_transient( string $key, mixed $value, int $ttl ): bool { $GLOBALS['transients'][$key] = $value; return true; }
function delete_transient( string $key ): bool { if ( ! isset( $GLOBALS['transients'][$key] ) ) { return false; } unset( $GLOBALS['transients'][$key] ); return true; }
function wp_salt( string $scope ): string { return 'synthetic-local-auth-salt'; }
function is_email( string $email ): bool { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function get_user_by( string $field, mixed $value ): WP_User|false {
	foreach ( $GLOBALS['accounts'] as $user ) { if ( 'id' === $field ? $user->ID === (int) $value : 0 === strcasecmp( $user->user_email, $value ) ) { return clone $user; } }
	return false;
}
function get_current_user_id(): int { return $GLOBALS['current_user']; }
function is_user_logged_in(): bool { return get_current_user_id() > 0; }
function is_account_page(): bool { return true; }
function wc_nocache_headers(): void {}
function wp_verify_nonce( string $nonce, string $action ): bool { return 'valid-account' === $nonce && 'artnovo_account_email_otp' === $action; }
function get_http_origin(): string { return 'https://example.test'; }
function wp_send_json_success( array $data ): never { throw new SyntheticAuthResponse( true, $data ); }
function wp_send_json_error( array $data, int $status ): never { throw new SyntheticAuthResponse( false, $data, $status ); }
function wp_safe_redirect( string $url, int $status ): never { throw new SyntheticAuthResponse( true, [ 'redirect' => $url ], $status ); }
function is_super_admin( int $id ): bool { return false; }
function clean_user_cache( int $id ): void {}
function wp_update_user( array $values ): int|WP_Error {
	$GLOBALS['updates'][] = $values;
	$GLOBALS['accounts'][$values['ID']]->user_email = $values['user_email'];
	return $values['ID'];
}
function wc_create_new_customer( string $email, string $name, string $password ): int {
	++$GLOBALS['created']; $id = 100 + $GLOBALS['created']; $GLOBALS['accounts'][$id] = new WP_User( $id, $email ); return $id;
}
function wp_generate_password( int $length ): string { return str_repeat( 'x', $length ); }
function wc_get_page_permalink( string $page ): string { return 'https://example.test/' . ( 'myaccount' === $page ? 'my-account' : $page ) . '/'; }
function home_url( string $path ): string { return 'https://example.test' . $path; }
function wp_parse_url( string $url, int $component = -1 ): mixed { return parse_url( $url, $component ); }
function wp_validate_redirect( string $url, string $fallback ): string { return str_starts_with( $url, 'https://example.test/' ) ? $url : $fallback; }
function esc_url_raw( string $url ): string { return $url; }
function trailingslashit( string $value ): string { return rtrim( $value, '/' ) . '/'; }
function add_query_arg( string|array $key, mixed $value, ?string $url = null ): string {
	return ( is_array( $key ) ? $value : $url ) . '?' . http_build_query( is_array( $key ) ? $key : [ $key => $value ] );
}
function WC(): object {
	static $woo;
	return $woo ??= (object) [ 'session' => new class {
		private array $data = [];
		public function has_session(): bool { return true; }
		public function get_customer_id(): int { return 7; }
		public function get( string $key, mixed $default = null ): mixed { return $this->data[$key] ?? $default; }
		public function set( string $key, mixed $value ): void { $this->data[$key] = $value; }
		public function __unset( string $key ): void { unset( $this->data[$key] ); }
		public function save_data(): void {}
		public function set_customer_session_cookie( bool $set ): void {}
	}, 'query' => new class { public function get_query_vars(): array { return [ 'orders' => 'orders', 'order-pay' => 'order-pay', 'order-received' => 'order-received' ]; } } ];
}
$wpdb = new class {
	public function prepare( string $query, string $key ): string { return $query; }
	public function get_var( string $query ): string { return '1'; }
};
function check( string $name, bool $passed ): void {
	++$GLOBALS['assertions']; if ( ! $passed ) { ++$GLOBALS['failures']; fwrite( STDERR, 'FAIL ' . $name . "\n" ); }
}
require dirname( __DIR__ ) . '/modules/auth/artnovo-checkout-email.php';
require dirname( __DIR__ ) . '/modules/auth/account-auth.php';
function challenge( string $email ): string {
	$GLOBALS['transients'] = [];
	$key = Artnovo\AccountUI\account_auth_key( true );
	$result = Artnovo\PlanCheckout\wizard_otp_send( $key, $email, '192.0.2.1' );
	check( 'synthetic code can be prepared', is_array( $result ) );
	$_POST['artnovo_otp_code'] = (string) $GLOBALS['sent_code'];
	return $key;
}
$accounts[7] = new WP_User( 7, '' );
$accounts[8] = new WP_User( 8, 'existing@example.test' );
$key = challenge( 'verified@example.test' );
$result = Artnovo\PlanCheckout\wizard_otp_complete_email( $key, 'verified@example.test', '192.0.2.1', 7 );
check( 'verified email is attached only to the signed-in mobile account', $result instanceof WP_User && 7 === $result->ID && 'verified@example.test' === $accounts[7]->user_email );
check( 'completion never creates or signs into another account', 0 === $created && [] === $logins && [ [ 'ID' => 7, 'user_email' => 'verified@example.test' ] ] === $updates );
check( 'completion code cannot be replayed', is_wp_error( Artnovo\PlanCheckout\wizard_otp_complete_email( $key, 'verified@example.test', '192.0.2.1', 7 ) ) );
$accounts[7]->user_email = '';
$updates = [];
$key = challenge( 'existing@example.test' );
check( 'another account email cannot merge or replace the current account', is_wp_error( Artnovo\PlanCheckout\wizard_otp_complete_email( $key, 'existing@example.test', '192.0.2.1', 7 ) ) && '' === $accounts[7]->user_email && [] === $updates );
$key = challenge( 'verified@example.test' );
$_POST['artnovo_otp_code'] = '000000';
check( 'wrong email code does not update the account', is_wp_error( Artnovo\PlanCheckout\wizard_otp_complete_email( $key, 'verified@example.test', '192.0.2.1', 7 ) ) && [] === $updates );
$_POST['artnovo_otp_code'] = (string) $sent_code;
check( 'another IP cannot use the email proof', is_wp_error( Artnovo\PlanCheckout\wizard_otp_complete_email( $key, 'verified@example.test', '192.0.2.2', 7 ) ) && [] === $updates );
$current_user = 8;
check( 'email proof cannot be attached to another signed-in account', is_wp_error( Artnovo\PlanCheckout\wizard_otp_complete_email( $key, 'verified@example.test', '192.0.2.1', 7 ) ) && [] === $updates );
$current_user = 7;
$accounts[7]->user_email = 'already@example.test';
$key = challenge( 'verified@example.test' );
check( 'completion does not replace an existing account email', is_wp_error( Artnovo\PlanCheckout\wizard_otp_complete_email( $key, 'verified@example.test', '192.0.2.1', 7 ) ) && 'already@example.test' === $accounts[7]->user_email && [] === $updates );
$accounts[7]->user_email = '';
$accounts[7]->roles = [ 'administrator' ]; $accounts[7]->caps = [ 'administrator' => true ];
$key = challenge( 'verified@example.test' );
check( 'customer email completion rejects privileged accounts', is_wp_error( Artnovo\PlanCheckout\wizard_otp_complete_email( $key, 'verified@example.test', '192.0.2.1', 7 ) ) && [] === $updates );
$accounts[7]->roles = [ 'customer' ]; $accounts[7]->caps = [ 'customer' => true ];
$key = challenge( 'new@example.test' );
$new = Artnovo\PlanCheckout\wizard_otp_verify( $key, 'new@example.test', '192.0.2.1' );
check( 'email signup remains available independently of native Pinova mobile signup', $new instanceof WP_User && 'new@example.test' === $new->user_email && 1 === $created );
$key = challenge( 'existing@example.test' );
$existing = Artnovo\PlanCheckout\wizard_otp_verify( $key, 'existing@example.test', '192.0.2.1' );
check( 'existing email account retains its identity', $existing instanceof WP_User && 8 === $existing->ID && 1 === $created );
$resume = 'https://example.test/my-account/?artnovo_resume=1';
$draft = [ 'product' => 26, 'quantity' => 3, 'expires' => time() + 180 ];
check( 'native Pinova receives only the canonical purchase return', 'https://example.test/login?back_url=' . rawurlencode( $resume ) === Artnovo\AccountUI\account_native_login_url( $resume ) );
check( 'return resumes the selected plan', 'https://example.test/?artnovo_plan=26#artnovo-order' === Artnovo\AccountUI\account_wizard_return() );
check( 'return does not change quantity or selection', 26 === $draft['product'] && 3 === $draft['quantity'] );
check( 'foreign native return is rejected', 'https://example.test/login?back_url=' . rawurlencode( Artnovo\AccountUI\account_url() ) === Artnovo\AccountUI\account_native_login_url( 'https://other.test/?artnovo_resume=1' ) );
check( 'Pinova administrator return stays owned by Pinova', 'https://example.test/wp-admin/' === apply_filters( 'pinova/login_back_url', 'https://example.test/wp-admin/' ) );
$draft['expires'] = time() - 1;
check( 'expired selection cannot resume through native login', Artnovo\AccountUI\account_url() === Artnovo\AccountUI\account_auth_destination( $resume ) );
$draft['expires'] = time() + 180;
$bridge_ready = false;
check( 'missing Bridge does not create an unavailable purchase destination', '' === Artnovo\AccountUI\account_wizard_return() && Artnovo\AccountUI\account_url() === Artnovo\AccountUI\account_auth_destination( $resume ) );
check( 'email proof and signup remain available without Bridge or Wallet', Artnovo\PlanCheckout\wizard_email_otp_enabled() );

// Execute the registered HTTP callback, so request-mode dispatch and login side effects are covered.
function account_request( string $action, string $email, bool $complete, string $code = '' ): SyntheticAuthResponse {
	$_POST = [
		'artnovo_account_action' => $action, 'artnovo_account_ajax' => '1',
		'artnovo_account_nonce' => 'valid-account', 'artnovo_email' => $email,
		'artnovo_otp_code' => $code, 'artnovo_redirect' => 'https://example.test/my-account/?artnovo_view=profile',
	];
	if ( $complete ) { $_POST['artnovo_complete_email'] = '1'; }
	try {
		foreach ( $GLOBALS['hooks']['template_redirect'][5] ?? [] as $callback ) { $callback(); }
	} catch ( SyntheticAuthResponse $response ) { return $response; }
	throw new RuntimeException( 'The actual account request callback did not produce a response.' );
}
$bridge_ready = true;
$current_user = 7; $accounts[7]->user_email = '';
$accounts = [ 7 => $accounts[7], 8 => $accounts[8] ];
$transients = $logins = $updates = []; $created = 0;
$sent = account_request( 'send', 'callback@example.test', true );
check( 'completion request sends a challenge without changing identity', $sent->success && ( $sent->data['expires'] ?? 0 ) > time() && '' === $accounts[7]->user_email && [] === $updates && [] === $logins && 0 === $created );
$completed = account_request( 'verify', 'callback@example.test', true, (string) $sent_code );
check( 'actual completion callback updates only the authenticated account', $completed->success && 'callback@example.test' === $accounts[7]->user_email && [ [ 'ID' => 7, 'user_email' => 'callback@example.test' ] ] === $updates );
check( 'completion callback returns safely without creating or logging into another account', 'https://example.test/my-account/?artnovo_view=profile' === ( $completed->data['redirect'] ?? '' ) && [] === $logins && 0 === $created && 7 === $current_user );
$replayed = account_request( 'verify', 'callback@example.test', true, (string) $sent_code );
check( 'actual completion callback rejects replay without another write or login', ! $replayed->success && 400 === $replayed->status && 1 === count( $updates ) && [] === $logins && 0 === $created );
$accounts[7]->user_email = ''; $transients = $updates = [];
$sent = account_request( 'send', 'existing@example.test', true );
$conflict = account_request( 'verify', 'existing@example.test', true, (string) $sent_code );
check( 'completion request cannot merge the current account into another email owner', $sent->success && ! $conflict->success && 403 === $conflict->status && '' === $accounts[7]->user_email && 'existing@example.test' === $accounts[8]->user_email && [] === $updates && [] === $logins && 0 === $created );
$current_user = 0; $transients = [];
$sent = account_request( 'send', 'guest-callback@example.test', false );
$guest = account_request( 'verify', 'guest-callback@example.test', false, (string) $sent_code );
$guest_id = $logins[0] ?? 0;
check( 'actual guest request still creates and logs into its email customer', $sent->success && $guest->success && 1 === $created && 1 === count( $logins ) && 'guest-callback@example.test' === ( $accounts[$guest_id]->user_email ?? '' ) && [] === $updates );
$transients = []; $logins = [];
$sent = account_request( 'send', 'existing@example.test', false );
$existing_login = account_request( 'verify', 'existing@example.test', false, (string) $sent_code );
check( 'actual existing-email request preserves its customer identity', $sent->success && $existing_login->success && [8] === $logins && 1 === $created && [] === $updates );
echo "{$assertions} authentication assertions, {$failures} failures\n";
exit( $failures ? 1 : 0 );
}
