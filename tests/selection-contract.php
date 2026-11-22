<?php
// Actual Core selection/cart callbacks against two request snapshots and a shared synthetic session.
// No WordPress installation, database, provider, real order, payment or email.
namespace Artnovo\Core {
	function bridge_available(): bool { return true; }
	function is_service_order( \WC_Order $order ): bool { return $order->service; }
}
namespace Artnovo\HomeOrderUI\Wizard { function is_page(): bool { return $GLOBALS['wizard_page'] ?? false; } }
namespace Artnovo\AccountUI {
	function account_native_login_url( string $return ): string { return 'https://example.test/login?back_url=' . rawurlencode( $return ); }
	function received_bank_payment( \WC_Order $order ): array { return []; }
}
namespace WPVPNBridge\Commerce {
	class Orders {
		public static function max_purchase_quantity( $product, ?string $method = null ): int { return 10; }
		public static function product_terms( $product ): array { return []; }
		public static function fresh_order( int $id ): \WC_Order|false { return \wc_get_order( $id ); }
	}
}
namespace WPVPNBridge { class Settings { public function ready(): bool { return true; } } }
namespace WPVPNBridge\Payment {
	class WalletPayment {
		public const GATEWAY = 'wpvpn_wallet';
		public static function configured(): bool { return false; }
		public static function enabled(): bool { return false; }
		public static function submitted( mixed $key ): bool { return 'submitted' === $key; }
	}
}
namespace Pinova\Channels { class Email { public static function send_code( string $email, int $code ): bool { return true; } } }
namespace Pinova\Services { class UserService { public static function login( int $id ): void {} } }
namespace Pinova\Integrations\Woocommerce { class Checkout { public function validate_checkout_phone(): void {} } }
namespace {
if ( PHP_VERSION_ID < 80500 || PHP_VERSION_ID >= 80600 ) { exit( 1 ); }
define( 'ABSPATH', __DIR__ . '/unused-wordpress/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WC_SESSION_CACHE_GROUP', 'synthetic-sessions' );
$hooks = $persisted = $notices = [];
$current_user = 7;
$failures = $assertions = 0;
$reject_add = $throw_add = $lock_busy = false;
$db_owner = 'selection-request';
$locks = $lock_events = [];
$private_headers = 0;
$cookie_events = [];
class WP_Error {
	public array $errors = [];
	public function __construct( public string $code = '', public string $message = '', public mixed $data = null ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
	public function add( string $code, string $message ): void { $this->errors[$code] = $message; }
}
function add_filter( string $name, callable $callback, int $priority = 10, int $accepted = 1 ): void { $GLOBALS['hooks'][$name][$priority][] = [ $callback, $accepted ]; }
function add_action( ...$args ): void { add_filter( ...$args ); }
function apply_filters( string $hook, mixed $value, ...$args ): mixed {
	$priorities = $GLOBALS['hooks'][$hook] ?? []; ksort( $priorities );
	foreach ( $priorities as $callbacks ) { foreach ( $callbacks as [$callback, $accepted] ) { $value = $callback( ...array_slice( [$value, ...$args], 0, $accepted ) ); } }
	return $value;
}
function do_action( string $hook, ...$args ): void {
	$priorities = $GLOBALS['hooks'][$hook] ?? []; ksort( $priorities );
	foreach ( array_keys( $priorities ) as $priority ) { foreach ( $GLOBALS['hooks'][$hook][$priority] ?? [] as [$callback, $accepted] ) { $callback( ...array_slice( $args, 0, $accepted ) ); } }
}
function remove_action(string $name,callable $callback,int $priority=10):void{foreach($GLOBALS['hooks'][$name][$priority]??[] as $i=>$hook){if($hook[0]===$callback)unset($GLOBALS['hooks'][$name][$priority][$i]);}}
function wp_verify_nonce( string $nonce, string $action ): bool { return ('valid-checkout' === $nonce && 'woocommerce-process_checkout' === $action)||('valid-review'===$nonce&&'update-order-review'===$action)||('valid-wizard'===$nonce&&'artnovo_wizard_checkout'===$action); }
function wp_create_nonce( string $action ): string { return 'fresh-' . $action; }
class SyntheticResponse extends RuntimeException { public function __construct(public array $payload){parent::__construct('Synthetic response');} }
class WP_REST_Request {
	public function __construct( private ?string $intent = null ) {}
	public function get_method(): string { return 'GET'; }
	public function get_route(): string { return '/WC/Store/V1/Checkout/'; }
	public function get_header( string $name ): ?string { return 'X-Artnovo-Purchase-Intent' === $name ? $this->intent : null; }
}
function wp_send_json(array $payload):never{throw new SyntheticResponse($payload);}
function wp_send_json_error(array $data,int $status=200):never{throw new SyntheticResponse(['success'=>false,'data'=>$data,'status'=>$status]);}
function wp_send_json_success(array $data,int $status=200):never{throw new SyntheticResponse(['success'=>true,'data'=>$data,'status'=>$status]);}
function wp_die(string $message,string $title='',array $args=[]):never{throw new SyntheticResponse(['die'=>$message,'status'=>$args['response']??500]);}
function wp_safe_redirect(string $url,int $status=302):void{throw new SyntheticResponse(['redirect'=>$url,'status'=>$status]);}
function wc_nocache_headers():void{++$GLOBALS['private_headers'];}
function wp_parse_url(string $url):array{return parse_url($url);}
function get_http_origin():string{return $GLOBALS['origin']??'https://example.test';}
function is_preview():bool{return false;}
function is_account_page():bool{return false;}
function is_page(int $id):bool{return false;}
function wc_get_page_id(string $page):int{return 1;}
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function has_term( $terms, $taxonomy, int $id ): bool { return in_array( $id, [25, 26], true ); }
function is_user_logged_in(): bool { return $GLOBALS['current_user'] > 0; }
function get_current_user_id(): int { return $GLOBALS['current_user']; }
function wp_get_current_user(): object { return (object) [ 'user_email' => $GLOBALS['account_email'] ?? '' ]; }
function is_wc_endpoint_url( ...$args ): bool { return false; }
function wp_unslash( string $value ): string { return $value; }
function sanitize_text_field( string $value ): string { return $value; }
function sanitize_email( string $value ): string { return $value; }
function wp_salt( string $scheme ): string { return 'synthetic-selection-salt'; }
function maybe_unserialize( mixed $value ): mixed { return $value; }
function wc_coupons_enabled(): bool { return false; }
function wc_tax_enabled(): bool { return $GLOBALS['taxes_enabled'] ?? false; }
function is_email( string $value ): bool { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function post_password_required( int $id ): bool { return in_array($id,$GLOBALS['password_products']??[],true); }
function home_url( string $path ): string { return 'https://example.test' . $path; }
function wc_get_page_permalink( string $name ): string { return 'https://example.test/my-account/'; }
function add_query_arg( array|string $key, mixed $value, ?string $url = null ): string { return (is_array($key)?$value:$url) . '?' . http_build_query(is_array($key)?$key:[$key=>$value]); }
function get_option( string $key, mixed $default = false ): mixed {
	return match($key) {
		'woocommerce_bacs_settings' => ['enabled'=>'yes'],
		'woocommerce_bacs_accounts' => [['account_number'=>'synthetic-account']],
		'artnovo_checkout_email_otp' => $GLOBALS['email_otp_option'] ?? 'no',
		default => $default,
	};
}
function wc_add_notice( string $notice, string $type ): void { $GLOBALS['notices'][] = $notice; }
function wp_cache_delete( string $key, string $group ): void {}
class WC_Cache_Helper { public static function get_cache_prefix( string $group ): string { return 'synthetic:'; } }
class WC_Product {
	public function __construct( private int $id ) {}
	public function is_type( string $type ): bool { return 'simple' === $type; }
	public function get_id(): int { return $this->id; }
	public function get_status(): string { return $GLOBALS['product_status'][$this->id]??'publish'; }
	public function get_meta( string $key, bool $single = true ): string { return 'yes'; }
	public function is_virtual(): bool { return true; }
	public function is_in_stock(): bool { return true; }
	public function is_purchasable(): bool { return true; }
	public function get_max_purchase_quantity(): int { return -1; }
}
function wc_get_product( int $id ): WC_Product|false { return in_array($id,[25,26,99],true)?new WC_Product($id):false; }
class WC_Order {
	public array $meta = [];
	public function __construct( public int $id, public string $method = 'artnovo_gpante', public string $status = 'pending', public int $owner = 7, public bool $service = true ) {}
	public function get_id(): int { return $this->id; }
	public function get_customer_id(): int { return $this->owner; }
	public function get_payment_method( string $context = '' ): string { return $this->method; }
	public function get_created_via(): string { return 'store-api'; }
	public function get_transaction_id( string $context = '' ): string { return ''; }
	public function is_paid(): bool { return false; }
	public function get_meta( string $key, bool $single = true ): mixed { return $this->meta[$key] ?? ''; }
	public function update_meta_data( string $key, mixed $value ): void { $this->meta[$key] = $value; }
	public function has_status( string $status ): bool { return $this->status === $status; }
}
$orders = [];
function wc_get_order( int $id ): WC_Order|false { return $GLOBALS['orders'][$id] ?? false; }
function wc_get_orders( array $query ): array { return array_filter( $GLOBALS['orders'], static fn( WC_Order $order ) => $order->get_customer_id() === $query['customer_id'] ); }
function wc_get_order_statuses(): array { return [ 'wc-pending' => 'pending' ]; }
class WC_Session_Handler {
	public bool $cookie = false;
	public bool $browser_cookie = false;
	public function __construct( public array $data = [] ) {
		$GLOBALS['hooks']['shutdown'][20]=[[[$this,'save_data'],0]];
		$GLOBALS['hooks']['template_redirect'][999]=[[[$this,'destroy_session_if_empty'],0]];
	}
	public function set_customer_session_cookie(bool $set):void{$this->cookie=$set;$GLOBALS['cookie_events'][]='set';}
	public function destroy_session_if_empty():void{if($this->browser_cookie&&$this->cookie&&!$this->data&&!is_user_logged_in()){$this->cookie=false;$GLOBALS['cookie_events'][]='destroy';}}
	public function get_customer_id(): string { return $GLOBALS['synthetic_session_owner'] ?? 'synthetic-customer'; }
	public function get( string $key, mixed $default = null ): mixed { return $this->data[$key]??$default; }
	public function set( string $key, mixed $value ): void { if(null===$value){unset($this->data[$key]);}else{$this->data[$key]=$value;} }
	public function __unset( string $key ): void { unset($this->data[$key]); }
	public function save_data(): void { $GLOBALS['persisted']=$this->data; }
	public function get_session_data(): array { return $GLOBALS['persisted']; }
}
class WC_Cart {
	public array $items = [];
	public function get_cart(): array { return $this->items; }
	public function get_applied_coupons(): array { return []; }
	public function set_applied_coupons( array $coupons ): void {}
	public function set_cart_contents( array $items ): void { $this->items=$items; WC()->session->set('cart',$items); }
	public function calculate_totals(): void {}
	public function get_total( string $context ): float { return 100.0; }
	public function set_quantity( string $key, int $quantity ): bool { $this->items[$key]['quantity']=$quantity;WC()->session->set('cart',$this->items);return true; }
	public function add_to_cart( int $id, int $quantity ): string|false {
		if($GLOBALS['reject_add'])return false;
		$key='product-'.$id;$this->set_cart_contents([$key=>['product_id'=>$id,'quantity'=>$quantity,'data'=>wc_get_product($id)]]);
		if($GLOBALS['throw_add'])throw new RuntimeException('Synthetic late add failure');
		return $key;
	}
}
class WC_Cart_Session {
	public function __construct(private WC_Cart $cart){}
	public function get_cart_from_session(): void { $this->cart->items=WC()->session->get('cart',[]); }
}
function WC(): object { return $GLOBALS['woo']; }
function wc_load_cart(): void {}
$woo=(object)['session'=>new WC_Session_Handler(),'cart'=>new WC_Cart()];
$woo->payment_gateways= new class {
	public function payment_gateways(): array { return ['bacs'=>(object)['id'=>'bacs','enabled'=>'yes']]; }
	public function get_available_payment_gateways(): array { return apply_filters('woocommerce_available_payment_gateways',$this->payment_gateways()); }
};
// WC() normally returns a class with payment_gateways(); the fixture keeps that public API.
class SyntheticWoo {
	public WC_Session_Handler $session;
	public WC_Cart $cart;
	public function __construct(object $woo){$this->session=$woo->session;$this->cart=$woo->cart;$this->gateways=$woo->payment_gateways;}
	private object $gateways;
	public function initialize_session(): void {}
	public function payment_gateways(): object { return $this->gateways; }
}
$woo=new SyntheticWoo($woo);
$wpdb=new class {
	public function prepare(string $query,string $key):string{return str_replace('%s','"'.$key.'"',$query);}
	public function get_var(string $query):string{
		preg_match('/SELECT (GET_LOCK|RELEASE_LOCK)\("([^"]+)"/',$query,$match);
		[, $operation, $key]=$match;$owner=$GLOBALS['db_owner'];$lock=$GLOBALS['locks'][$key]??null;
		$GLOBALS['lock_events'][]=[$operation,$key,$owner];
		if('GET_LOCK'===$operation){
			if($GLOBALS['lock_busy']||($lock&&$lock['owner']!==$owner))return '0';
			$GLOBALS['locks'][$key]=['owner'=>$owner,'depth'=>($lock['depth']??0)+1];return '1';
		}
		if(!$lock||$lock['owner']!==$owner)return '0';
		if(1===$lock['depth'])unset($GLOBALS['locks'][$key]);else --$GLOBALS['locks'][$key]['depth'];return '1';
	}
};
function check(string $name,bool $passed):void{++$GLOBALS['assertions'];if(!$passed){++$GLOBALS['failures'];fwrite(STDERR,'FAIL '.$name."\n");}}
require dirname(__DIR__).'/modules/checkout/artnovo-plan-checkout.php';
function choose(int $id,int $quantity,string $revision,bool $ajax=false,string $purchase_action=''):array|WP_Error {
	$_POST=['artnovo_product'=>(string)$id,'artnovo_quantity'=>(string)$quantity,'artnovo_selection_revision'=>$revision];
	if($ajax)$_POST['artnovo_wizard_cart']=$id.':'.(Artnovo\PlanCheckout\wizard_draft()['quantity']??1);
	if ( '' !== $purchase_action ) $_POST['artnovo_purchase_action'] = $purchase_action;
	return Artnovo\PlanCheckout\wizard_update_selection($ajax,'');
}
$initial=Artnovo\PlanCheckout\wizard_selection_revision();
$a=choose(25,1,$initial);
check('initial selection prepares exactly one invisible cart item', !isset($a['error'])&&'same'===Artnovo\PlanCheckout\cart_selection(25));
$token_a=Artnovo\PlanCheckout\wizard_selection_revision();
$snapshot_a=WC()->session->data;
WC()->session->set('order_awaiting_payment',123);WC()->session->set('store_api_draft_order',456);WC()->session->save_data();
$b=choose(26,2,$token_a);
$token_b=Artnovo\PlanCheckout\wizard_selection_revision();
check('new plan B silently replaces A with the requested quantity', !isset($b['error'])&&'same'===Artnovo\PlanCheckout\cart_selection(26,2)&&1===count(WC()->cart->items));
check('selection changes rotate revision and only clear draft pointers', $token_a!==$token_b&&null===WC()->session->get('order_awaiting_payment')&&null===WC()->session->get('store_api_draft_order'));
check('A to B has no cart warning or notice',[]===$notices);
$snapshot_b=WC()->session->data;
WC()->session=new WC_Session_Handler($snapshot_a);WC()->cart->items=$snapshot_a['cart'];
$stale=choose(25,1,$token_a);
check('old tab snapshot is reloaded under the selection lock', 'same'===Artnovo\PlanCheckout\cart_selection(26,2));
check('Back or replay returns to current B without reactivating A', true===($stale['selection_changed']??false)&&str_contains($stale['redirect'],'artnovo_plan=26'));
check('stale request persists current selection rather than its old snapshot',26===$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]['product']);
$again=choose(25,1,Artnovo\PlanCheckout\wizard_selection_revision());
check('fresh explicit card choice may choose A again', !isset($again['error'])&&'same'===Artnovo\PlanCheckout\cart_selection(25));
$revision=Artnovo\PlanCheckout\wizard_selection_revision();
$quantity=choose(25,3,$revision,true);
check('current quantity update keeps one selected plan', !isset($quantity['error'])&&'same'===Artnovo\PlanCheckout\cart_selection(25,3));
$stale_quantity=choose(25,1,$revision,true);
check('old quantity replay cannot undo the new quantity',true===($stale_quantity['selection_changed']??false)&&'same'===Artnovo\PlanCheckout\cart_selection(25,3));
$reject_add=true;$revision=Artnovo\PlanCheckout\wizard_selection_revision();$rejected=choose(26,1,$revision);$reject_add=false;
check('failed native add restores the previous selection and revision',isset($rejected['error'])&&'same'===Artnovo\PlanCheckout\cart_selection(25,3)&&$revision===Artnovo\PlanCheckout\wizard_selection_revision());
$throw_add=true;$failed=choose(26,1,$revision);$throw_add=false;
check('late native exception restores the previous selection',isset($failed['error'])&&'same'===Artnovo\PlanCheckout\cart_selection(25,3));
WC()->cart->items['product-25']['_wpvpn_wallet_key']='submitted';WC()->session->set('cart',WC()->cart->items);WC()->session->save_data();
$submitted=choose(26,1,$revision);
check('submitted wallet attempt cannot be discarded by selection replacement',isset($submitted['error'])&&'same'===Artnovo\PlanCheckout\cart_selection(25,3)&&'submitted'===WC()->cart->items['product-25']['_wpvpn_wallet_key']);
unset(WC()->cart->items['product-25']['_wpvpn_wallet_key']);WC()->session->set('cart',WC()->cart->items);WC()->session->save_data();
$current_user=0;$_POST=['artnovo_product'=>'25','artnovo_quantity'=>'3','artnovo_selection_revision'=>$revision,'artnovo_login_method'=>'pinova'];
$native=Artnovo\PlanCheckout\wizard_update_selection(false,'');
check('native login saves selection before leaving without submitting an order',str_starts_with($native['redirect']??'','https://example.test/login?')&&25===$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]['product']&&3===$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]['quantity']);
$_POST['artnovo_login_method']='email';$email=Artnovo\PlanCheckout\wizard_update_selection(false,'');
check('no-JS email fallback saves the same selection and quantity',str_contains($email['redirect']??'','artnovo_resume=1')&&25===$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]['product']&&3===$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]['quantity']);
$current_user=7;
WC()->cart->items=[];
check('early native cart loading cannot merge a saved cart into a service selection',false===apply_filters('woocommerce_persistent_cart_enabled',true));
WC()->session->set('cart',[['product_id'=>99,'quantity'=>1]]);
check('stale Core draft does not disable the native wallet top-up persistent cart',true===apply_filters('woocommerce_persistent_cart_enabled',true));
$lock_busy=true;$before=$persisted;$busy=choose(26,1,$revision);$lock_busy=false;
check('another in-flight selection fails without changing persisted state',is_wp_error($busy)&&$persisted===$before);
check('selection notices describe only the three rejected changes',[$rejected['error'],$failed['error'],$submitted['error']]===$notices);

// Drive actual Core checkout callbacks before a synthetic order/charge boundary.
$native_creates = $native_charges = 0;
function checkout_guard(): void { foreach($GLOBALS['hooks']['woocommerce_checkout_process'][1] as [$callback])$callback(); }
function finish_request(): void {
	do_action('shutdown');
	$GLOBALS['hooks']['shutdown']=[20=>[[[WC()->session,'save_data'],0]]];
}
function checkout_attempt(): WP_Error {
	checkout_guard();$errors=new WP_Error();
	foreach($GLOBALS['hooks']['woocommerce_after_checkout_validation'][10] as [$callback])$callback(['payment_method'=>'bacs'],$errors);
	if(!$errors->errors&&!$GLOBALS['notices']){++$GLOBALS['native_creates'];++$GLOBALS['native_charges'];}
	return $errors;
}
$persisted=$snapshot_b;WC()->session=new WC_Session_Handler($snapshot_a);WC()->cart->items=$snapshot_a['cart'];
$events=count($lock_events);$_POST=['payment_method'=>'wallet'];checkout_guard();
check('native Wallet checkout neither locks nor refreshes a stale Core session',count($lock_events)===$events&&WC()->session->data===$snapshot_a);
$_POST=['artnovo_wizard_cart'=>'25:1','artnovo_selection_revision'=>$token_a,'woocommerce-process-checkout-nonce'=>'invalid'];checkout_guard();
check('unverified checkout cannot acquire the selection lock',count($lock_events)===$events);
$_POST['woocommerce-process-checkout-nonce']='valid-checkout';$errors=checkout_attempt();
check('stale checkout reloads B and stops before synthetic order creation or charge',isset($errors->errors['artnovo_selection_changed'])&&0===$native_creates&&0===$native_charges&&'same'===Artnovo\PlanCheckout\cart_selection(26,2));
check('checkout retains its selection lock until native session persistence',1===count($locks));
$db_owner='another-tab';$blocked=choose(25,1,$token_b);$db_owner='selection-request';
check('concurrent prepare cannot replace an in-flight checkout selection',is_wp_error($blocked)&&26===$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]['product']);
finish_request();
check('shutdown saves the current selection and releases the checkout lock',[]===$locks&&26===$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]['product']);
$notices=[];$_POST=['artnovo_wizard_cart'=>'26:2','artnovo_selection_revision'=>$token_b,'woocommerce-process-checkout-nonce'=>'valid-checkout'];
$errors=checkout_attempt();checkout_guard();
check('current checkout can reach the synthetic order boundary',[]===$errors->errors&&1===$native_creates&&1===$native_charges);
finish_request();
check('nested checkout guards release every acquired lock at shutdown',[]===$locks);
$new=choose(25,1,$token_b);$new_revision=Artnovo\PlanCheckout\wizard_selection_revision();
WC()->session=new WC_Session_Handler($snapshot_b);WC()->cart->items=$snapshot_b['cart'];
$notices=[];$_POST=['artnovo_wizard_cart'=>'26:2','artnovo_selection_revision'=>$token_b,'woocommerce-process-checkout-nonce'=>'valid-checkout'];
$errors=checkout_attempt();finish_request();
check('old payment replay after a fresh selection cannot create or charge again',isset($errors->errors['artnovo_selection_changed'])&&1===$native_creates&&1===$native_charges&&$new_revision===Artnovo\PlanCheckout\wizard_selection_revision());
try{Artnovo\PlanCheckout\wizard_otp_locked('synthetic-checkout-exception',static function(){throw new RuntimeException('synthetic');},true);}catch(RuntimeException){}
finish_request();
check('a checkout exception still schedules lock cleanup',[]===$locks);
$persisted=$snapshot_b;WC()->session=new WC_Session_Handler($snapshot_a);WC()->cart->items=$snapshot_a['cart'];
$preserve=function_exists('Artnovo\\PlanCheckout\\wizard_preserve_login_selection')?Artnovo\PlanCheckout\wizard_preserve_login_selection():false;
check('Core login refreshes the latest selected plan before any account-session save',true===$preserve&&26===Artnovo\PlanCheckout\wizard_draft()['product']);
check('Core login keeps the guest selection lock across session migration',1===count($locks));
finish_request();
$persisted=$snapshot_b;WC()->session=new WC_Session_Handler($snapshot_a);WC()->cart->items=$snapshot_a['cart'];
do_action('pinova/user_logged_in',7);
check('native Pinova login refreshes B before its priority-20 migration',26===Artnovo\PlanCheckout\wizard_draft()['product']&&1===count($locks));
finish_request();
WC()->cart->set_cart_contents([['product_id'=>99,'quantity'=>1,'data'=>wc_get_product(99)]]);WC()->session->save_data();
$events=count($lock_events);do_action('pinova/user_logged_in',7);
check('ordinary native login does not acquire a Core selection lock',count($lock_events)===$events);
$notices=[];$_POST=['artnovo_product'=>'99','artnovo_quantity'=>'1','artnovo_selection_revision'=>Artnovo\PlanCheckout\wizard_selection_revision()];
$invalid=Artnovo\PlanCheckout\wizard_update_selection(false,'');
check('non-AJAX selection errors add their notice before the locked save',isset($invalid['error'])&&[$invalid['error']]===$notices);
$persisted=$snapshot_b;WC()->session=new WC_Session_Handler($snapshot_a);WC()->cart->items=$snapshot_a['cart'];$notices=[];
$_POST=['artnovo_wizard_cart'=>'25:1','artnovo_selection_revision'=>$token_a,'woocommerce-process-checkout-nonce'=>'valid-checkout'];
$lock_busy=true;$creates=$native_creates;$charges=$native_charges;checkout_attempt();$lock_busy=false;finish_request();
check('denied busy checkout cannot save A over B or reach a financial boundary',26===$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]['product']&&$creates===$native_creates&&$charges===$native_charges);
$persisted=$snapshot_b;WC()->session=new WC_Session_Handler($snapshot_a);WC()->cart->items=$snapshot_a['cart'];
$_POST=['security'=>'valid-review'];$review_response=[];
try{do_action('woocommerce_checkout_update_order_review',http_build_query(['artnovo_wizard_cart'=>'25:1','artnovo_selection_revision'=>$token_a]));}catch(SyntheticResponse $response){$review_response=$response->payload;}
finish_request();
check('stale native review reloads the current selection without saving A',true===($review_response['reload']??false)&&26===$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]['product']);
$persisted=$snapshot_b;WC()->session=new WC_Session_Handler($snapshot_a);WC()->cart->items=$snapshot_a['cart'];$lock_busy=true;$review_response=[];
try{do_action('woocommerce_checkout_update_order_review',http_build_query(['artnovo_wizard_cart'=>'25:1','artnovo_selection_revision'=>$token_a]));}catch(SyntheticResponse $response){$review_response=$response->payload;}
$lock_busy=false;finish_request();
check('busy native review stops without persisting its stale snapshot',true===($review_response['reload']??false)&&26===$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]['product']);
WC()->session=new WC_Session_Handler($persisted);WC()->cart->items=$persisted['cart'];$review_response=[];
try{do_action('woocommerce_checkout_update_order_review',http_build_query(['artnovo_wizard_cart'=>'26:2','artnovo_selection_revision'=>$token_b]));}catch(SyntheticResponse $response){$review_response=$response->payload;}
check('current Core review continues with its selection locked',[]===$review_response&&1===count($locks));
WC()->session->set('chosen_payment_method','bacs');finish_request();
check('native review can persist its current choice before lock release','bacs'===$persisted['chosen_payment_method']&&26===$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]['product']&&[]===$locks);
$review_response=[];
try{do_action('woocommerce_checkout_update_order_review','billing_email=ordinary%40example.test');}catch(SyntheticResponse $response){$review_response=$response->payload;}
check('markerless service review checks current selection under lock and then continues natively',[]===$review_response&&1===count($locks));
finish_request();
WC()->cart->items=[['product_id'=>99,'quantity'=>1,'data'=>wc_get_product(99)]];
$events=count($lock_events);do_action('woocommerce_checkout_update_order_review','billing_email=ordinary%40example.test');
check('unrelated native review never acquires a Core selection lock',count($lock_events)===$events);
WC()->session=new WC_Session_Handler($persisted);WC()->cart->items=$persisted['cart'];
$wizard_page=true;$_SERVER['REQUEST_METHOD']='GET';$_GET=['artnovo_plan'=>'25','artnovo_checkout'=>'1'];$return=[];
try{foreach($hooks['template_redirect'][9] as [$callback])$callback();}catch(SyntheticResponse $response){$return=$response->payload;}
check('old checkout GET follows B before rendering an auto-prepare form',str_contains($return['redirect']??'','artnovo_plan=26'));
unset($_GET['artnovo_checkout']);$return=[];
try{foreach($hooks['template_redirect'][9] as [$callback])$callback();}catch(SyntheticResponse $response){$return=$response->payload;}
check('fresh explicit card GET remains available for a different plan',[]===$return);

// These fixtures verify hook order and state boundaries, not real HTTP headers.
$current_user=0;$wizard_page=true;$_POST=[];$_GET=['artnovo_plan'=>'25'];
foreach(['GET','HEAD'] as $method){
	$_SERVER['REQUEST_METHOD']=$method;$persisted=[];WC()->session=new WC_Session_Handler();WC()->cart->items=[];
	WC()->session->browser_cookie=true;WC()->session->cookie=true;$cookie_events=[];$private_headers=0;
	do_action('template_redirect');
	check("$method purchase page establishes its guest cookie before output and keeps an empty native session",['set']===$cookie_events&&WC()->session->cookie&&$private_headers>0);
}
$wizard_page=false;$_SERVER['REQUEST_METHOD']='GET';WC()->session=new WC_Session_Handler();WC()->session->browser_cookie=true;WC()->session->cookie=true;$cookie_events=[];
do_action('template_redirect');
check('ordinary pages retain native empty-session cleanup',['destroy']===$cookie_events&&!WC()->session->cookie);
$wizard_page=true;$_SERVER['REQUEST_METHOD']='POST';WC()->session=new WC_Session_Handler();WC()->session->browser_cookie=true;WC()->session->cookie=true;$cookie_events=[];
do_action('template_redirect');
check('purchase POST without its form is not initialized as a guest GET',['destroy']===$cookie_events&&!WC()->session->cookie);

function wizard_post(array $post):array{
	$_SERVER['REQUEST_METHOD']='POST';$_POST=$post;
	try{foreach($GLOBALS['hooks']['template_redirect'][7] as [$callback])$callback();}catch(SyntheticResponse $response){return $response->payload;}
	return [];
}
$persisted=$snapshot_b;WC()->session=new WC_Session_Handler($snapshot_a);WC()->cart->items=$snapshot_a['cart'];$before_events=count($lock_events);
$stale_post=['artnovo_wizard_nonce'=>'expired','artnovo_product'=>'25','artnovo_quantity'=>'1','artnovo_selection_revision'=>$token_a,'artnovo_login_method'=>'pinova'];
$response=wizard_post($stale_post);
check('expired normal POST redirects to a fresh GET of current B',303===($response['status']??0)&&str_contains($response['redirect']??'','artnovo_plan=26')&&!str_contains($response['redirect']??'','artnovo_checkout'));
check('invalid nonce cannot mutate the loaded cart, authenticate, acquire a selection lock or reach an order boundary',WC()->session->data===$snapshot_a&&WC()->cart->items===$snapshot_a['cart']&&0===$current_user&&$before_events===count($lock_events)&&1===$native_creates&&1===$native_charges);
finish_request();
check('an expired POST cannot persist its old A snapshot over B',$persisted===$snapshot_b);
$persisted=$snapshot_b;WC()->session=new WC_Session_Handler($snapshot_a);WC()->cart->items=$snapshot_a['cart'];
$stale_post['artnovo_wizard_ajax']='1';$response=wizard_post($stale_post);$repeat=wizard_post($stale_post);
check('expired AJAX POST returns an error with a safe fresh-GET recovery destination',false===($response['success']??null)&&403===($response['status']??0)&&true===($response['data']['refresh_required']??false)&&str_contains($response['data']['redirect']??'','artnovo_plan=26')&&!empty($response['data']['message']));
check('replaying an expired AJAX POST never advances selection or payment',$response===$repeat&&$persisted===$snapshot_b&&1===$native_creates&&1===$native_charges);
finish_request();
foreach([
	'public fallback'=>[[],26,[],'artnovo_plan=26'],
	'expired draft fallback'=>[[Artnovo\PlanCheckout\WIZARD_DRAFT=>array_replace($snapshot_b[Artnovo\PlanCheckout\WIZARD_DRAFT],['expires'=>time()-1])],25,[],'artnovo_plan=25'],
	'private product'=>[[],25,[25],'#artnovo-categories'],
	'ordinary product'=>[[],99,[],'#artnovo-categories'],
	'nonexistent product'=>[[],888,[],'#artnovo-categories'],
] as $name=>[$stored,$product,$protected,$destination]){
	$persisted=$stored;WC()->session=new WC_Session_Handler($stored);WC()->cart->items=[];$password_products=$protected;
	$response=wizard_post(['artnovo_wizard_nonce'=>'expired','artnovo_product'=>(string)$product]);
	check("recovery $name is a read-only safe GET",303===($response['status']??0)&&str_contains($response['redirect']??'',$destination)&&$stored===$persisted&&WC()->session->data===$stored&&[]===WC()->cart->items);
}
$password_products=[];$product_status[25]='draft';$persisted=[];WC()->session=new WC_Session_Handler();
$response=wizard_post(['artnovo_wizard_nonce'=>'expired','artnovo_product'=>'25']);
check('recovery does not expose an unpublished submitted product',str_contains($response['redirect']??'','#artnovo-categories'));
$product_status=[];

// Only explicit native sign-in preparation may use the guest AJAX selection seam.
$persisted=[];WC()->session=new WC_Session_Handler();WC()->cart->items=[];
$prepare=['artnovo_wizard_nonce'=>'valid-wizard','artnovo_wizard_ajax'=>'1','artnovo_login_method'=>'pinova','artnovo_product'=>'26','artnovo_quantity'=>'2','artnovo_selection_revision'=>Artnovo\PlanCheckout\wizard_selection_revision()];
$response=wizard_post($prepare);$native_draft=$persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]??[];
check('native guest AJAX preparation persists only a validated selection and its current revision',true===($response['success']??false)&&str_starts_with($response['data']['redirect']??'','https://example.test/login?')&&26===($native_draft['product']??0)&&2===($native_draft['quantity']??0)&&($response['data']['selection_revision']??'')===($native_draft['revision']??null)&&[]===WC()->cart->items&&0===$current_user);
$before=$persisted;$response=wizard_post($prepare);
check('replaying an older guest preparation cannot replace its newer draft',true===($response['data']['selection_changed']??false)&&$before===$persisted&&[]===WC()->cart->items);
$prepare['artnovo_selection_revision']=Artnovo\PlanCheckout\wizard_selection_revision();$response=wizard_post($prepare);
check('current native guest preparation can resume without rotating the same selection',true===($response['success']??false)&&($response['data']['selection_revision']??null)===$native_draft['revision']&&[]===WC()->cart->items);
foreach(['artnovo_product'=>'99','artnovo_quantity'=>'11','artnovo_payment'=>'unknown','artnovo_login_method'=>'unknown'] as $field=>$value){
	$before=$persisted;$invalid_prepare=array_replace($prepare,[$field=>$value]);$response=wizard_post($invalid_prepare);
	check("native guest preparation still validates $field",false===($response['success']??null)&&409===($response['status']??0)&&$before===$persisted&&[]===WC()->cart->items&&0===$current_user);
}
$persisted=[];WC()->session=new WC_Session_Handler();$prepare['artnovo_selection_revision']=Artnovo\PlanCheckout\wizard_selection_revision();unset($prepare['artnovo_login_method']);$response=wizard_post($prepare);
check('ordinary guest AJAX quantity requests cannot prepare a selection',true===($response['data']['selection_changed']??false)&&!isset($persisted[Artnovo\PlanCheckout\WIZARD_DRAFT])&&[]===WC()->cart->items);
$prepare['artnovo_login_method']='pinova';unset($prepare['artnovo_wizard_ajax']);$_POST=$prepare;
$without_flag=Artnovo\PlanCheckout\wizard_update_selection(true,'');
check('guest native AJAX exception requires its explicit AJAX marker',true===($without_flag['selection_changed']??false)&&!isset($persisted[Artnovo\PlanCheckout\WIZARD_DRAFT]));
$prepare['artnovo_wizard_ajax']='1';$origin='https://foreign.example';$before=$persisted;$before_events=count($lock_events);$response=wizard_post($prepare);$origin='https://example.test';
check('native guest AJAX preparation rejects a foreign origin before selection writes',false===($response['success']??null)&&403===($response['status']??0)&&$persisted===$before&&$before_events===count($lock_events));

// Explicit asynchronous selection has the same origin/revision/lock fences as native preparation.
$persisted=[];WC()->session=new WC_Session_Handler();WC()->cart->items=[];$current_user=0;
$instant=['artnovo_wizard_nonce'=>'valid-wizard','artnovo_wizard_ajax'=>'1','artnovo_wizard_select'=>'1','artnovo_product'=>'25','artnovo_quantity'=>'2','artnovo_selection_revision'=>Artnovo\PlanCheckout\wizard_selection_revision()];
$response=wizard_post($instant);
check('explicit guest selection returns canonical identity and fresh authentication nonces without preparing payment',true===($response['success']??false)&&25===($response['data']['product']??0)&&'25:2'===($response['data']['cart_identity']??'')&&''===($response['data']['checkout_html']??null)&&''===($response['data']['checkout_assets']??null)&&'fresh-wp_rest'===($response['data']['rest_nonce']??'')&&'fresh-artnovo_wizard_checkout'===($response['data']['wizard_nonce']??'')&&[]===WC()->cart->items&&0===$current_user);
finish_request();$before=$persisted;$response=wizard_post($instant);
check('old explicit selection cannot replace a newer revision',true===($response['data']['selection_changed']??false)&&$persisted===$before);
finish_request();
$current_user=7;WC()->session=new WC_Session_Handler($snapshot_b);WC()->cart->items=$snapshot_b['cart'];$persisted=$snapshot_b;
$instant['artnovo_selection_revision']=Artnovo\PlanCheckout\wizard_selection_revision();$_POST=$instant;
$selected=Artnovo\PlanCheckout\wizard_update_selection(true,'');
check('an explicit signed-in asynchronous card selection silently replaces another plan',25===($selected['product']??0)&&'same'===Artnovo\PlanCheckout\cart_selection(25,2)&&1===count(WC()->cart->items));
finish_request();$before=$persisted;$instant['artnovo_selection_revision']=Artnovo\PlanCheckout\wizard_selection_revision();
$origin='https://foreign.example';$response=wizard_post($instant);$origin='https://example.test';
check('signed-in asynchronous selection rejects foreign origin and creates no order or charge',false===($response['success']??null)&&403===($response['status']??0)&&$before===$persisted&&1===$native_creates&&1===$native_charges);
finish_request();

// Rejected requests may already have loaded A while another tab persisted B.
// Shutdown must not write that old snapshot, even though rejection happens before the lock.
foreach ( [
	'foreign signed-in selection' => [ 7, 'https://foreign.example', [ 'artnovo_wizard_select' => '1' ] ],
	'foreign guest native preparation' => [ 0, 'https://foreign.example', [ 'artnovo_login_method' => 'pinova' ] ],
	'foreign guest OTP request' => [ 0, 'https://foreign.example', [ 'artnovo_otp_action' => 'send' ] ],
	'signed-in OTP request' => [ 7, 'https://example.test', [ 'artnovo_otp_action' => 'send' ] ],
] as $case => [ $current_user, $origin, $extra ] ) {
	$persisted = $snapshot_b;
	WC()->session = new WC_Session_Handler( $snapshot_a );
	WC()->cart->items = $snapshot_a['cart'];
	$before_events = count( $lock_events );
	$response = wizard_post( $extra + [
		'artnovo_wizard_nonce' => 'valid-wizard', 'artnovo_wizard_ajax' => '1',
		'artnovo_product' => '25', 'artnovo_quantity' => '1', 'artnovo_selection_revision' => $token_a,
	] );
	check( "$case is rejected without mutating the loaded session, cart or payment boundary",
		false === ( $response['success'] ?? null ) && 403 === ( $response['status'] ?? 0 )
		&& $snapshot_a === WC()->session->data && $snapshot_a['cart'] === WC()->cart->items
		&& $before_events === count( $lock_events ) && 1 === $native_creates && 1 === $native_charges );
	finish_request();
	check( "$case shutdown preserves the newer selection B", $snapshot_b === $persisted );
}
$origin = 'https://example.test';

// Contact-free checkout belongs only to the authenticated, unchanged Core purchase.
$current_user=7;$wizard_page=true;$_GET=['artnovo_plan'=>'26','artnovo_checkout'=>'1'];$_POST=['payment_method'=>'bacs'];
$persisted=$snapshot_b;WC()->session=new WC_Session_Handler($snapshot_b);WC()->cart->items=$snapshot_b['cart'];
$native_fields=['billing'=>['billing_email'=>['type'=>'email','required'=>true],'billing_city'=>['required'=>true]],'shipping'=>['shipping_city'=>['required'=>true]],'account'=>['account_username'=>['required'=>true]],'order'=>['order_comments'=>[]]];
check('contact-free fixture is an authenticated current Core purchase',Artnovo\PlanCheckout\wizard_email_checkout());
check('Core purchase displays no email or address inputs',['billing'=>[],'shipping'=>[],'account'=>[],'order'=>[]]===apply_filters('woocommerce_checkout_fields',$native_fields));
$posted=['billing_email'=>'attacker@example.test','payment_method'=>'bacs','terms'=>1];
$account_email='';$phone_only=apply_filters('woocommerce_checkout_posted_data',$posted);
check('a phone-only account may order without a billing email or a forged replacement',''===($phone_only['billing_email']??null)&&'bacs'===$phone_only['payment_method']&&1===$phone_only['terms']);
$account_email='owner@example.test';$with_email=apply_filters('woocommerce_checkout_posted_data',$posted);
check('an existing account email is retained for native order mail without asking for it again','owner@example.test'===($with_email['billing_email']??null));
$account_email='not-an-email';$invalid_email=apply_filters('woocommerce_checkout_posted_data',$posted);
check('an invalid stored account email does not become an order contact',''===($invalid_email['billing_email']??null));
check('contact-free checkout still cannot update or merge the account',false===apply_filters('woocommerce_checkout_update_customer_data',true));
function phone_validator_state( array $phone_hooks, bool $expect_pinova ): bool {
	$phone = [ new \Pinova\Integrations\Woocommerce\Checkout(), 'validate_checkout_phone' ];
	$other = static function (): void {};
	$locations = [ 'woocommerce_checkout_process' => 0, 'woocommerce_after_checkout_validation' => 2 ];
	foreach ( $locations as $name => $accepted ) {
		$callbacks = in_array( $name, $phone_hooks, true ) ? [ $phone, $other ] : [ $other ];
		$GLOBALS['hooks'][ $name ][10] = array_map( static fn( $cb ) => [ $cb, $accepted ], $callbacks );
		$GLOBALS['wp_filter'][ $name ] = (object) [ 'callbacks' => [ 10 => array_map( static fn( $cb ) => [ 'function' => $cb ], $callbacks ) ] ];
	}
	foreach ( $GLOBALS['hooks']['woocommerce_checkout_process'][9] as [ $callback ] ) {
		$callback();
	}
	foreach ( $locations as $name => $accepted ) {
		$remaining = array_column( $GLOBALS['hooks'][ $name ][10], 0 );
		if ( $remaining !== ( $expect_pinova && in_array( $name, $phone_hooks, true ) ? [ $phone, $other ] : [ $other ] ) ) {
			return false;
		}
	}
	return true;
}
$phone_variants = [ 'legacy' => [ 'woocommerce_checkout_process' ], 'current' => [ 'woocommerce_after_checkout_validation' ],
	'both' => [ 'woocommerce_checkout_process', 'woocommerce_after_checkout_validation' ] ];
foreach(['no','yes'] as $enabled){
	$email_otp_option=$enabled;
	foreach ( $phone_variants as $variant => $phone_hooks ) {
		check( "Core email proof option $enabled removes only the $variant Pinova phone validation for the signed-in wizard", phone_validator_state( $phone_hooks, false ) );
	}
}
foreach(['ordinary'=>[7,false,false,[]],'invalid nonce'=>[7,false,false,['artnovo_wizard_cart'=>'26:2','woocommerce-process-checkout-nonce'=>'wrong']],'guest'=>[0,true,false,[]],'taxed'=>[7,true,true,[]]] as $case=>[$current_user,$wizard_page,$taxes_enabled,$_POST]){
	check("$case checkout retains native contact fields",$native_fields===apply_filters('woocommerce_checkout_fields',$native_fields));
	check("$case checkout retains native posted contact data",$posted===apply_filters('woocommerce_checkout_posted_data',$posted));
	check( "$case checkout retains native phone validation on both hooks", phone_validator_state( $phone_variants['both'], true ) );
}
$taxes_enabled=false;$current_user=7;$wizard_page=false;$_GET=[];$_POST=['artnovo_wizard_cart'=>'26:2','woocommerce-process-checkout-nonce'=>'valid-checkout'];$account_email='owner@example.test';
check('native checkout AJAX with the current marker and nonce gets the same contact policy','owner@example.test'===(apply_filters('woocommerce_checkout_posted_data',$posted)['billing_email']??null));
check( 'authenticated native checkout AJAX removes both Pinova phone validation hooks', phone_validator_state( $phone_variants['both'], false ) );
WC()->cart->items=[['product_id'=>99,'quantity'=>1,'data'=>wc_get_product(99)]];
check('an ordinary product or wallet top-up never inherits contact-free checkout',$native_fields===apply_filters('woocommerce_checkout_fields',$native_fields)&&$posted===apply_filters('woocommerce_checkout_posted_data',$posted));

// The same catalog choice starts another intent only when it is a new authorized action.
$persisted = []; WC()->session = new WC_Session_Handler(); WC()->cart->items = [];
choose( 25, 1, Artnovo\PlanCheckout\wizard_selection_revision() );
$old = $orders[801] = new WC_Order( 801 );
$old->meta['original-order-item'] = 120;
WC()->session->set( 'order_awaiting_payment', 801 );
WC()->session->set( 'store_api_draft_order', 801 );
WC()->session->save_data();
$catalog_revision = Artnovo\PlanCheckout\wizard_selection_revision();
$action = str_repeat( 'a', 32 );
$fresh = choose( 25, 1, $catalog_revision, false, $action );
$fresh_draft = Artnovo\PlanCheckout\wizard_draft();
$intent = Artnovo\PlanCheckout\wizard_purchase_intent( $fresh_draft );
check( 'ordinary same-plan catalog action detaches only the old online order and retains its saved item', ! isset( $fresh['error'] )
	&& '' !== $intent && 801 === $fresh_draft['fresh_from'] && 120 === $old->meta['original-order-item']
	&& null === WC()->session->get( 'order_awaiting_payment' ) && null === WC()->session->get( 'store_api_draft_order' ) );
$replayed = choose( 25, 1, $catalog_revision, false, $action );
check( 'same action and stale initial revision returns the confirmed context', true === ( $replayed['selection_changed'] ?? false ) && $fresh_draft === Artnovo\PlanCheckout\wizard_draft() );
choose( 25, 2, Artnovo\PlanCheckout\wizard_selection_revision() );
$edited = Artnovo\PlanCheckout\wizard_draft();
check( 'quantity editing rotates revision without dropping the intent or earlier-order context', $edited['revision'] !== $fresh_draft['revision']
	&& $intent === $edited['purchase_intent'] && 801 === $edited['fresh_from'] && 2 === $edited['quantity'] );
check( 'same-cart requests must carry the current intent and revision', ! Artnovo\PlanCheckout\wizard_matches_purchase_request( $edited, '', $edited['revision'] )
	&& ! Artnovo\PlanCheckout\wizard_matches_purchase_request( $edited, $intent, $fresh_draft['revision'] )
	&& Artnovo\PlanCheckout\wizard_matches_purchase_request( $edited, $intent, $edited['revision'] ) );
$synthetic_session_owner = '7';
$stale_store = apply_filters( 'rest_dispatch_request', null, new WP_REST_Request( str_repeat( 'f', 64 ) ) );
check( 'mixed-case Store API route with trailing slash rejects a stale intent before dispatch', $stale_store instanceof WP_Error
	&& 'artnovo_selection_changed' === $stale_store->get_error_code() );
$plan_cart = WC()->cart->get_cart();
WC()->cart->set_cart_contents( [ 'ordinary-99' => [ 'product_id' => 99, 'quantity' => 1, 'data' => wc_get_product( 99 ) ] ] );
WC()->session->save_data();
check( 'native unrelated Blocks remains available even in a session that retains an online intent',
	null === apply_filters( 'rest_dispatch_request', null, new WP_REST_Request() ) );
WC()->cart->set_cart_contents( $plan_cart );
WC()->session->save_data();
unset( $synthetic_session_owner );

$orders[802] = new WC_Order( 802, 'bacs' );
WC()->session->set( 'order_awaiting_payment', 802 );
WC()->session->save_data();
check( 'an unrelated BACS pointer is guarded before classic WooCommerce can change its items', is_wp_error( Artnovo\PlanCheckout\wizard_fresh_purchase_checkout( true ) )
	&& 802 === WC()->session->get( 'order_awaiting_payment' ) && [] === $orders[802]->meta );
WC()->session->set( 'store_api_draft_order', 802 );
WC()->session->save_data();
check( 'managed Store API read or edit cannot rebuild an unrelated BACS draft reference',
	is_wp_error( Artnovo\PlanCheckout\wizard_fresh_purchase_checkout( protect_store_pointer: true ) ) && 802 === WC()->session->get( 'store_api_draft_order' ) );
WC()->session->__unset( 'store_api_draft_order' );
Artnovo\PlanCheckout\wizard_bind_fresh_purchase_order( $orders[802] );
check( 'selecting native BACS after catalog detachment follows the same BACS order without an online claim or marker',
	802 === Artnovo\PlanCheckout\wizard_draft()['fresh_order'] && empty( Artnovo\PlanCheckout\wizard_draft()['purchase_claimed'] )
	&& true === Artnovo\PlanCheckout\wizard_fresh_purchase_checkout() && [] === $orders[802]->meta );
WC()->session->set( Artnovo\PlanCheckout\WIZARD_DRAFT, $edited );
WC()->session->__unset( 'order_awaiting_payment' );
WC()->session->save_data();
$new_order = new WC_Order( 0 );
Artnovo\PlanCheckout\wizard_claim_online_purchase( $new_order );
check( 'missing order after durable claim fails closed', is_wp_error( Artnovo\PlanCheckout\wizard_fresh_purchase_checkout( true ) ) );
$new_order->id = 803;
$orders[803] = $new_order;
check( 'interruption after order save and before session binding recovers the marked order', $new_order === Artnovo\PlanCheckout\wizard_fresh_purchase_checkout( true )
	&& $intent === $new_order->get_meta( Artnovo\PlanCheckout\PURCHASE_META ) );
$new_order->status = 'checkout-draft';
check( 'verified owned Store API draft can resume without creating another order', true === Artnovo\PlanCheckout\wizard_fresh_purchase_checkout( true, true )
	&& 803 === WC()->session->get( 'store_api_draft_order' ) );
check( 'classic cannot recreate an unfinished Store API draft', is_wp_error( Artnovo\PlanCheckout\wizard_fresh_purchase_checkout( true ) ) );

$current_user = 0;
$wizard_page = true;
$persisted = [];
WC()->session = new WC_Session_Handler();
WC()->cart->items = [];
$online_action = str_repeat( 'b', 32 );
$guest = wizard_post( [ 'artnovo_wizard_nonce' => 'valid-wizard', 'artnovo_wizard_ajax' => '1', 'artnovo_wizard_select' => '1',
	'artnovo_login_method' => 'pinova', 'artnovo_product' => '25', 'artnovo_quantity' => '1',
	'artnovo_selection_revision' => Artnovo\PlanCheckout\wizard_selection_revision(), 'artnovo_purchase_action' => $online_action ] );
$guest_draft = Artnovo\PlanCheckout\wizard_draft();
check( 'guest catalog action marks a resumable intent without preparing a paying cart', true === ( $guest['success'] ?? false )
	&& true === ( $guest_draft['purchase_pending_login'] ?? null ) && '' !== Artnovo\PlanCheckout\wizard_purchase_intent( $guest_draft ) && [] === WC()->cart->items );
finish_request();
$current_user = 7;
WC()->session->set( 'order_awaiting_payment', 801 );
WC()->session->set( 'store_api_draft_order', 801 );
WC()->session->save_data();
$resumed = choose( 25, 1, $guest_draft['revision'] );
$resumed_draft = Artnovo\PlanCheckout\wizard_draft();
check( 'Pinova selection recovery detaches the older online pointer with the existing guest intent and action', ! isset( $resumed['error'] )
	&& $guest_draft['purchase_intent'] === ( $resumed_draft['purchase_intent'] ?? null ) && 801 === ( $resumed_draft['fresh_from'] ?? null )
	&& $online_action === ( $resumed_draft['purchase_action'] ?? null ) && empty( $resumed_draft['purchase_pending_login'] )
	&& null === WC()->session->get( 'order_awaiting_payment' ) && null === WC()->session->get( 'store_api_draft_order' ) );

$mixed_cart = WC()->cart->get_cart() + [
	'legacy-wallet' => [ 'product_id' => 99, 'quantity' => 1, 'data' => wc_get_product( 99 ), 'recharge_amount' => 100000 ],
];
WC()->cart->set_cart_contents( $mixed_cart );
WC()->session->set( Artnovo\PlanCheckout\WIZARD_DRAFT, $resumed_draft + [ 'fresh_order' => 802 ] );
WC()->session->set( 'order_awaiting_payment', 802 );
WC()->session->save_data();
$orders[802]->meta['original-order-item'] = 41;
$revision = Artnovo\PlanCheckout\wizard_selection_revision();
$action = str_repeat( 'c', 32 );
$reject_add = true;
$rejected = choose( 26, 2, $revision, false, $action );
$reject_add = false;
check( 'failed fresh selection restores the mixed cart and leaves the saved order linked',
	isset( $rejected['error'] ) && $mixed_cart === WC()->cart->get_cart()
	&& 802 === WC()->session->get( 'order_awaiting_payment' ) && $revision === Artnovo\PlanCheckout\wizard_selection_revision() );

$notices = [];
$fresh = choose( 26, 2, $revision, false, $action );
$new_draft = Artnovo\PlanCheckout\wizard_draft();
check( 'fresh catalog selection silently replaces the legacy wallet and every previous row',
	! isset( $fresh['error'] ) && [] === $notices && 'same' === Artnovo\PlanCheckout\cart_selection( 26, 2 ) );
check( 'fresh catalog selection leaves the previous BACS order intact and permits online checkout',
	41 === $orders[802]->meta['original-order-item'] && null === WC()->session->get( 'order_awaiting_payment' )
	&& empty( $new_draft['fresh_order'] ) && $resumed_draft['purchase_intent'] !== Artnovo\PlanCheckout\wizard_purchase_intent( $new_draft )
	&& true === Artnovo\PlanCheckout\wizard_fresh_purchase_checkout( true ) );
echo "{$assertions} selection assertions, {$failures} failures\n";
exit($failures?1:0);
}
