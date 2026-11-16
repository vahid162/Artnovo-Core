<?php
// Render the production guest-auth form with process-local WordPress helpers; no network or database.
namespace Artnovo\HomeOrderUI\Wizard { const PAGE_ID = 68; }
namespace Artnovo\PlanCheckout {
	const WIZARD_DRAFT = 'artnovo_wizard_checkout';
	const EMAIL_OTP_DIGITS = 6;
	function wizard_draft(): array { return $GLOBALS['draft']; }
	function wizard_email_otp_enabled(): bool { return $GLOBALS['email_enabled']; }
	function wizard_email_otp_expiry( string $email ): int { return $GLOBALS['expires']; }
	function wizard_url( int $product, bool $checkout = false ): string { return 'https://example.test/?artnovo_plan=' . $product . ( $checkout ? '&artnovo_checkout=1' : '' ) . '#artnovo-order'; }
}
namespace Pinova\Services { class OTPService { public static function code_length(): int { return 4; } } }
namespace {
if ( PHP_VERSION_ID < 80500 || PHP_VERSION_ID >= 80600 ) { exit( 1 ); }
define( 'ABSPATH', __DIR__ . '/unused-wordpress/' );
$draft = [];
$email_enabled = true;
$expires = 0;
$assertions = $failures = 0;
function esc_attr( mixed $value ): string { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( mixed $value ): string { return esc_attr( $value ); }
function esc_url( string $url ): string { return esc_attr( $url ); }
function wp_json_encode( mixed $value ): string { return json_encode( $value, JSON_UNESCAPED_UNICODE ); }
function wp_nonce_field( string $action, string $name, bool $referer ): void { echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce-for-' . esc_attr( $action ) . '">'; }
function wp_create_nonce( string $action ): string { return 'nonce-<&"-' . $action; }
function wp_unique_id( string $prefix ): string { static $n = 0; return $prefix . ++$n; }
function get_permalink( int $id ): string { return 'https://example.test/'; }
function rest_url( string $path = '' ): string { return 'https://example.test/wp-json/' . $path; }
function wc_get_page_permalink( string $page ): string { return 'https://example.test/my-account/'; }
function add_query_arg( string $name, string $value, string $url ): string { return $url . '?' . http_build_query( [ $name => $value ] ); }
function is_email( string $email ): bool { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function disabled( bool $disabled ): void { if ( $disabled ) { echo 'disabled'; } }
function check( string $label, bool $passed ): void { ++$GLOBALS['assertions']; if ( ! $passed ) { ++$GLOBALS['failures']; fwrite( STDERR, 'FAIL ' . $label . "\n" ); } }
function render( bool $available = true ): string { ob_start(); Artnovo\HomeOrderUI\Wizard\render_compact_auth( 26, 2, str_repeat( 'a', 64 ), $available ); return ob_get_clean(); }
$path = dirname( __DIR__ ) . '/modules/auth/compact-purchase.php';
if ( ! is_file( $path ) ) { check( 'production compact authentication form is available', false ); exit( 1 ); }
require $path;
$html = render();
check( 'one native submit action serves the whole guest sign-in form', 1 === preg_match_all( '/<button[^>]+type="submit"/', $html ) );
check( 'selection and the existing nonce are posted together', str_contains( $html, 'nonce-for-artnovo_wizard_checkout' ) && str_contains( $html, 'name="artnovo_product" value="26"' ) && str_contains( $html, 'name="artnovo_quantity" value="2"' ) && str_contains( $html, str_repeat( 'a', 64 ) ) );
check( 'normal form submission retains native Pinova fallback', str_contains( $html, 'name="artnovo_login_method" value="pinova"' ) && str_contains( $html, '<noscript>' ) );
check( 'the form does not opt into the old parallel OTP interceptor', ! str_contains( $html, 'data-artnovo-email-otp' ) );
preg_match( '/data-artnovo-auth-config="([^"]+)"/', $html, $match );
$config = json_decode( html_entity_decode( $match[1] ?? '', ENT_QUOTES, 'UTF-8' ), true );
check( 'escaped configuration remains valid and carries only native destinations', is_array( $config ) && 'https://example.test/wp-json/pinova/user/authenticate' === ( $config['authenticateUrl'] ?? null ) && 'https://example.test/wp-json/pinova/user/login/password' === ( $config['passwordUrl'] ?? null ) && 'nonce-<&"-wp_rest' === ( $config['restNonce'] ?? null ) );
check( 'post-login return refreshes authenticated state through the canonical account route', 'https://example.test/my-account/?artnovo_resume=1' === ( $config['resumeUrl'] ?? null ) );
check( 'native code length and Core email capability remain separately owned', 4 === ( $config['nativeCodeLength'] ?? null ) && 6 === ( $config['emailCodeLength'] ?? null ) && true === ( $config['emailOtp'] ?? null ) );
check( 'initial proof input is disabled until an authentication challenge exists', 1 === preg_match( '/<input[^>]+name="artnovo_auth_proof"[^>]*disabled/', $html ) );
$draft = [ 'billing_email' => '"/><script>bad()</script>' ];
$html = render();
check( 'a malformed session identifier cannot inject markup into the form', ! str_contains( $html, '<script>bad()' ) );
$draft = [ 'billing_email' => 'returning@example.test' ]; $expires = time() + 120;
$html = render();
check( 'a valid active Core email challenge can resume without resending', str_contains( $html, 'value="returning@example.test"' ) && str_contains( $html, 'data-artnovo-auth-expires="' . $expires . '"' ) );
$html = render( false );
check( 'unavailable purchases keep the primary action disabled', 1 === preg_match( '/<button[^>]+type="submit"[^>]*disabled/', $html ) );
$email_enabled = false; $html = render();
preg_match( '/data-artnovo-auth-config="([^"]+)"/', $html, $match );
$config = json_decode( html_entity_decode( $match[1] ?? '', ENT_QUOTES, 'UTF-8' ), true );
check( 'disabled Core email OTP leaves native authentication configured', false === ( $config['emailOtp'] ?? null ) && isset( $config['otpUrl'] ) );
echo "{$assertions} compact authentication assertions, {$failures} failures\n";
exit( $failures ? 1 : 0 );
}
