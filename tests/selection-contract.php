<?php
// Actual Core selection/cart callbacks against two request snapshots and a shared synthetic session.
// No WordPress installation, database, provider, real order, payment or email.
namespace Artnovo\Core { function bridge_available(): bool { return true; } }
namespace Artnovo\HomeOrderUI\Wizard { function is_page(): bool { return $GLOBALS['wizard_page'] ?? false; } }
namespace Artnovo\AccountUI {
	function account_native_login_url( string $return ): string { return 'https://example.test/login?back_url=' . rawurlencode( $return ); }
}
namespace WPVPNBridge\Commerce {
	class Orders { public static function max_purchase_quantity( $product, string $method ): int { return 10; } }
}
namespace WPVPNBridge\Payment {
	class WalletPayment {
		public const GATEWAY = 'wpvpn_wallet';
		public static function configured(): bool { return false; }
		public static function enabled(): bool { return false; }
		public static function submitted( mixed $key ): bool { return 'submitted' === $key; }
	}
}
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
	foreach ( $priorities as $callbacks ) { foreach ( $callbacks as [$callback, $accepted] ) { $callback( ...array_slice( $args, 0, $accepted ) ); } }
}
function remove_action(string $name,callable $callback,int $priority=10):void{foreach($GLOBALS['hooks'][$name][$priority]??[] as $i=>$hook){if($hook[0]===$callback)unset($GLOBALS['hooks'][$name][$priority][$i]);}}
function wp_verify_nonce( string $nonce, string $action ): bool { return ('valid-checkout' === $nonce && 'woocommerce-process_checkout' === $action)||('valid-review'===$nonce&&'update-order-review'===$action); }
class SyntheticResponse extends RuntimeException { public function __construct(public array $payload){parent::__construct('Synthetic response');} }
function wp_send_json(array $payload):never{throw new SyntheticResponse($payload);}
function wp_safe_redirect(string $url,int $status=302):void{throw new SyntheticResponse(['redirect'=>$url]);}
function wc_nocache_headers():void{}
function is_preview():bool{return false;}
function is_account_page():bool{return false;}
function is_page(int $id):bool{return false;}
function wc_get_page_id(string $page):int{return 1;}
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function has_term( $terms, $taxonomy, int $id ): bool { return in_array( $id, [25, 26], true ); }
function is_user_logged_in(): bool { return $GLOBALS['current_user'] > 0; }
function get_current_user_id(): int { return $GLOBALS['current_user']; }
function is_wc_endpoint_url( ...$args ): bool { return false; }
function wp_unslash( string $value ): string { return $value; }
function sanitize_text_field( string $value ): string { return $value; }
function sanitize_email( string $value ): string { return $value; }
function wp_salt( string $scheme ): string { return 'synthetic-selection-salt'; }
function maybe_unserialize( mixed $value ): mixed { return $value; }
function wc_coupons_enabled(): bool { return false; }
function wc_tax_enabled(): bool { return false; }
function is_email( string $value ): bool { return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function post_password_required( int $id ): bool { return false; }
function home_url( string $path ): string { return 'https://example.test' . $path; }
function wc_get_page_permalink( string $name ): string { return 'https://example.test/my-account/'; }
function add_query_arg( array|string $key, mixed $value, ?string $url = null ): string { return (is_array($key)?$value:$url) . '?' . http_build_query(is_array($key)?$key:[$key=>$value]); }
function get_option( string $key, mixed $default = false ): mixed {
	return match($key) {
		'woocommerce_bacs_settings' => ['enabled'=>'yes'],
		'woocommerce_bacs_accounts' => [['account_number'=>'synthetic-account']],
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
	public function is_virtual(): bool { return true; }
	public function is_in_stock(): bool { return true; }
	public function is_purchasable(): bool { return true; }
	public function get_max_purchase_quantity(): int { return -1; }
}
function wc_get_product( int $id ): WC_Product|false { return in_array($id,[25,26,99],true)?new WC_Product($id):false; }
class WC_Session_Handler {
	public function __construct( public array $data = [] ) { $GLOBALS['hooks']['shutdown'][20]=[[[$this,'save_data'],0]]; }
	public function get_customer_id(): string { return 'synthetic-customer'; }
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
$woo=(object)['session'=>new WC_Session_Handler(),'cart'=>new WC_Cart()];
$woo->payment_gateways= new class { public function get_available_payment_gateways(): array { return apply_filters('woocommerce_available_payment_gateways',['bacs'=>(object)['id'=>'bacs']]); } };
// WC() normally returns a class with payment_gateways(); the fixture keeps that public API.
class SyntheticWoo {
	public WC_Session_Handler $session;
	public WC_Cart $cart;
	public function __construct(object $woo){$this->session=$woo->session;$this->cart=$woo->cart;$this->gateways=$woo->payment_gateways;}
	private object $gateways;
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
function choose(int $id,int $quantity,string $revision,bool $ajax=false):array|WP_Error {
	$_POST=['artnovo_product'=>(string)$id,'artnovo_quantity'=>(string)$quantity,'artnovo_selection_revision'=>$revision];
	if($ajax)$_POST['artnovo_wizard_cart']=$id.':'.(Artnovo\PlanCheckout\wizard_draft()['quantity']??1);
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
$events=count($lock_events);do_action('woocommerce_checkout_update_order_review','billing_email=ordinary%40example.test');
check('ordinary review never acquires a Core selection lock',count($lock_events)===$events);
WC()->session=new WC_Session_Handler($persisted);WC()->cart->items=$persisted['cart'];
$wizard_page=true;$_SERVER['REQUEST_METHOD']='GET';$_GET=['artnovo_plan'=>'25','artnovo_checkout'=>'1'];$return=[];
try{foreach($hooks['template_redirect'][9] as [$callback])$callback();}catch(SyntheticResponse $response){$return=$response->payload;}
check('old checkout GET follows B before rendering an auto-prepare form',str_contains($return['redirect']??'','artnovo_plan=26'));
unset($_GET['artnovo_checkout']);$return=[];
try{foreach($hooks['template_redirect'][9] as [$callback])$callback();}catch(SyntheticResponse $response){$return=$response->payload;}
check('fresh explicit card GET remains available for a different plan',[]===$return);
echo "{$assertions} selection assertions, {$failures} failures\n";
exit($failures?1:0);
}
