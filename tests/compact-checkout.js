'use strict';

// Execute the production closure's summary function against an isolated native DOM fixture.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../modules/home/artnovo-home-wizard.js'), 'utf8');
const start = source.indexOf('\tfunction syncNativeSummary() {');
const end = source.indexOf('\n\tfunction refreshOrderReview(', start);
assert.ok(start >= 0 && end > start, 'تابع واقعی همگام‌سازی در کد تولید وجود دارد');
const numbersStart = source.indexOf('\tfunction persianDigits(value) {');
const numbersEnd = source.indexOf('\n\tfunction updateAmounts(plan) {', numbersStart);
assert.ok(numbersStart >= 0 && numbersEnd > numbersStart);
const numberFunctions = source.slice(numbersStart, numbersEnd);
const production = new vm.Script(numberFunctions + source.slice(start, end) + '\nsyncNativeSummary();');
const amountsStart = source.indexOf('\tfunction updateAmounts(plan) {');
const amountsEnd = source.indexOf('\n\tfunction prepareCheckout(', amountsStart);
assert.ok(amountsStart >= 0 && amountsEnd > amountsStart);
const amountsProduction = new vm.Script(numberFunctions + source.slice(start, end) + source.slice(amountsStart, amountsEnd) + '\nupdateAmounts(plan);');

class Element {
	constructor(tagName, attributes = {}, ...children) {
		this.tagName = tagName;
		this.nodeType = 1;
		this.attributes = { ...attributes };
		Object.defineProperty(this.attributes, Symbol.iterator, { value: function* () {
			for (const [name, value] of Object.entries(this)) yield { name, value };
		} });
		this.dataset = {};
		for (const [name, value] of Object.entries(attributes)) this.setAttribute(name, value);
		this.childNodes = [];
		this.listeners = new Map();
		this.append(...children);
	}
	setAttribute(name, value) {
		this.attributes[name] = String(value);
		if (name.startsWith('data-')) this.dataset[name.slice(5).replace(/-([a-z])/g, (_, letter) => letter.toUpperCase())] = String(value);
	}
	removeAttribute(name) { delete this.attributes[name]; }
	getAttribute(name) { return this.attributes[name] ?? null; }
	hasAttribute(name) { return Object.hasOwn(this.attributes, name); }
	get tabIndex() { return Number(this.attributes.tabindex ?? (this.tagName === 'a' ? 0 : -1)); }
	set tabIndex(value) { this.setAttribute('tabindex', value); }
	get textContent() { return this.childNodes.map((node) => typeof node === 'string' ? node : node.textContent).join(''); }
	set textContent(value) { this.replaceChildren(String(value)); }
	append(...children) {
		for (const child of children) {
			const node = typeof child === 'string' ? new Text(child) : child;
			if (node.parentElement) node.parentElement.childNodes.splice(node.parentElement.childNodes.indexOf(node), 1);
			node.parentElement = this;
			this.childNodes.push(node);
		}
	}
	replaceChildren(...children) {
		for (const node of this.childNodes) node.parentElement = null;
		this.childNodes = [];
		this.append(...children);
	}
	contains(node) { return node === this || this.childNodes.some((child) => child instanceof Element && child.contains(node)); }
	remove() {
		if (!this.parentElement) return;
		this.parentElement.childNodes.splice(this.parentElement.childNodes.indexOf(this), 1);
		this.parentElement = null;
	}
	replaceWith(node) {
		const parent = this.parentElement;
		const index = parent.childNodes.indexOf(this);
		node.remove();
		parent.childNodes.splice(index, 1, node);
		node.parentElement = parent;
		this.parentElement = null;
	}
	cloneNode(deep) { return new Element(this.tagName, this.attributes, ...(deep ? this.childNodes.map((node) => node.cloneNode(true)) : [])); }
	querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; }
	querySelectorAll(selector) {
		const matches = (node, simple) => {
			const rejected = [...simple.matchAll(/:not\(\.([\w-]+)\)/g)].map((match) => match[1]);
			const classes = (node.attributes.class ?? '').split(/\s+/);
			if (rejected.some((name) => classes.includes(name))) return false;
			const positive = simple.replace(/:not\([^)]*\)/g, '');
			const tag = positive.match(/^[a-z]+/)?.[0];
			return (!tag || node.tagName === tag) && [...positive.matchAll(/\.([\w-]+)/g)].every((match) => classes.includes(match[1]));
		};
		const selected = (node, parts) => {
			if (!matches(node, parts.at(-1))) return false;
			if (parts.length === 1) return true;
			for (let parent = node.parentElement; parent; parent = parent.parentElement) {
				if (selected(parent, parts.slice(0, -1))) return true;
			}
			return false;
		};
		const selectors = selector.split(',').map((part) => part.trim().split(/\s+/));
		const found = [];
		const visit = (node) => {
			for (const child of node.childNodes) {
				if (!(child instanceof Element)) continue;
				if (selectors.some((parts) => selected(child, parts))) found.push(child);
				visit(child);
			}
		};
		visit(this);
		return found;
	}
	addEventListener(type, listener) { this.listeners.set(type, [...this.listeners.get(type) ?? [], listener]); }
	click(modifiers = {}) {
		const event = { button: 0, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...modifiers };
		for (const listener of this.listeners.get('click') ?? []) listener(event);
		return event;
	}
}

class Text {
	constructor(value) { this.textContent = value; this.nodeType = 3; }
	cloneNode() { return new Text(this.textContent); }
}

const el = (tag, attributes, ...children) => new Element(tag, attributes, ...children);
const money = (value) => el('strong', {}, el('span', { class: 'woocommerce-Price-amount' }, el('bdi', {}, value, ' ', el('span', { class: 'woocommerce-Price-currencySymbol' }, 'تومان'))));
const row = (className, title, ...value) => el('tr', { class: className }, el('th', {}, title), el('td', {}, ...value));
const coupon = () => el('a', { class: 'woocommerce-remove-coupon', 'data-coupon': 'spring-20', href: '?remove_coupon=spring-20' }, 'حذف');
const table = (amount, adjustments = []) => el('table', { class: 'woocommerce-checkout-review-order-table' },
	el('tbody', {}, el('tr', { class: 'cart_item' }, el('td', {}, 'اشتراک', el('strong', { class: 'product-quantity' }, '× ۲')))),
	el('tfoot', {}, row('cart-subtotal', 'جمع جزء', money('۱٬۰۰۰٬۰۰۰')), ...adjustments, row('order-total', 'مبلغ قابل پرداخت', money(amount))));
const fixture = (review = null) => ({
	selectionPending: false, reviewCurrent: true,
	nativeCheckout: el('form', {}, ...(review ? [review] : [])),
	order: el('section'), totals: [el('div', {}, 'مبلغ قبلی')], adjustments: el('div', {}, 'جزئیات قبلی'),
	document: { createElement: (tag) => el(tag) }
});
const sync = (bindings) => production.runInNewContext(bindings, { timeout: 1000 });
const updateAmounts = (bindings) => {
	bindings.order.dataset.artnovoCurrency = 'تومان';
	return amountsProduction.runInNewContext({
		...bindings, plan: { id: '26', unitAmount: 100000 }, quantity: 2,
		price: el('span'), quantityLabel: el('span'), decrease: el('button'), increase: el('button'),
		productInput: null, quantityInput: null, form: null,
		checkoutPlans: new Set(['26']), quantityLimits: { 26: 10 }, priceDecimals: 0,
		priceFormat: new Intl.NumberFormat('fa-IR'), quantityFormat: new Intl.NumberFormat('fa-IR'),
		quantityPending: false, authPending: false
	}, { timeout: 1000 });
};

for (const absent of [null, el('table', { class: 'woocommerce-checkout-review-order-table' })]) {
	const missing = fixture(absent);
	assert.equal(sync(missing), false);
	assert.equal(missing.totals[0].textContent, 'مبلغ قبلی');
	assert.equal(missing.adjustments.textContent, 'جزئیات قبلی');
	assert.equal(missing.order.dataset.artnovoSummaryReady, undefined);
}
const noCheckout = fixture();
noCheckout.nativeCheckout = null;
assert.equal(sync(noCheckout), false, 'بدون checkout بومی، محاسبات یا نمایش قبلی تغییر نمی‌کنند');
updateAmounts(noCheckout);
assert.equal(noCheckout.totals[0].textContent, '۲۰۰٬۰۰۰\u00a0تومان', 'در نبود جدول native، تابع واقعی نمایش مبلغ انتخاب را به‌روز می‌کند');

const originalCoupon = coupon();
let originalRemovals = 0;
originalCoupon.addEventListener('click', () => { originalRemovals += 1; });
const original = table('۹۲۳٬۰۰۰', [
	row('cart-discount', 'تخفیف بهار', money('−۲۰۰٬۰۰۰'), ' ', originalCoupon),
	row('tax-rate', 'مالیات', money('۷۲٬۰۰۰')),
	row('fee', 'هزینهٔ سرویس', money('۵۱٬۰۰۰')),
	el('tr', { class: 'extension-heading' }, el('th', {}, 'ردیف بدون مبلغ'))
]);
const state = fixture(original);
const nativeAmount = original.querySelector('.order-total td').childNodes[0];
assert.equal(sync(state), true);
assert.equal(state.totals[0].textContent, '۹۲۳٬۰۰۰ تومان', 'مبلغ نهایی عین خروجی ووکامرس است، نه جمع جزء یا قیمت ضرب تعداد');
updateAmounts(state);
assert.equal(state.totals[0].textContent, '۹۲۳٬۰۰۰ تومان', 'به‌روزرسانی انتخاب، مبلغ معتبر native را با قیمت تخمینی جایگزین نمی‌کند');
assert.notEqual(state.totals[0].childNodes[0], nativeAmount, 'خلاصه، نسخهٔ نمایشی را کپی می‌کند و گره native را جابه‌جا نمی‌کند');
assert.equal(original.querySelector('.order-total td').childNodes[0], nativeAmount);
assert.equal(original.querySelector('.product-quantity').textContent, '× ۲', 'جدول native برای کنترل تعداد و fragment باقی است');
assert.equal(state.adjustments.childNodes.length, 3);
assert.deepEqual(state.adjustments.childNodes.map((node) => node.textContent), ['تخفیف بهار−۲۰۰٬۰۰۰ تومان حذف', 'مالیات۷۲٬۰۰۰ تومان', 'هزینهٔ سرویس۵۱٬۰۰۰ تومان']);
assert.equal(original.attributes['aria-hidden'], 'true');
assert.equal(state.order.dataset.artnovoSummaryReady, '1');
assert.equal(originalCoupon.tabIndex, -1);
const visibleCoupon = state.adjustments.querySelector('.woocommerce-remove-coupon');
assert.equal(visibleCoupon.tabIndex, 0, 'پیوند حذف نمایشی همچنان با صفحه‌کلید قابل دسترس است');
assert.equal(visibleCoupon.click().defaultPrevented, true);
assert.equal(originalRemovals, 1, 'حذف کوپن به handler بومی واگذار می‌شود');
for (const modifier of ['ctrlKey', 'metaKey', 'shiftKey', 'altKey']) {
	assert.equal(visibleCoupon.click({ [modifier]: true }).defaultPrevented, false);
}
assert.equal(originalRemovals, 1, 'کلیک‌های دارای کلید کمکی به زور به حذف تبدیل نمی‌شوند');

const replacementCoupon = coupon();
let replacementRemovals = 0;
replacementCoupon.addEventListener('click', () => { replacementRemovals += 1; });
const replacement = table('۸۰۰٬۰۰۰', [row('cart-discount', 'تخفیف بهار', money('−۲۰۰٬۰۰۰'), replacementCoupon)]);
state.nativeCheckout.replaceChildren(replacement);
visibleCoupon.click();
assert.equal(replacementRemovals, 1, 'پس از تعویض fragment، حذف به پیوند جدید native می‌رسد');
assert.equal(originalRemovals, 1, 'مرجع قدیمی fragment دوباره استفاده نمی‌شود');
assert.equal(sync(state), true);
assert.equal(state.totals[0].textContent, '۸۰۰٬۰۰۰ تومان');
assert.equal(state.adjustments.childNodes.length, 1, 'همگام‌سازی مجدد جزئیات قدیمی را انباشته نمی‌کند');
assert.equal(state.adjustments.querySelector('.woocommerce-remove-coupon').tabIndex, 0);

state.nativeCheckout.replaceChildren(table('۱٬۰۰۰٬۰۰۰'));
assert.equal(sync(state), true);
assert.equal(state.totals[0].textContent, '۱٬۰۰۰٬۰۰۰ تومان');
assert.equal(state.adjustments.childNodes.length, 0, 'حذف کوپن، مالیات و هزینه از fragment در خلاصه هم منعکس می‌شود');

for (const [raw, thousand, decimal, shown] of [
	['1,234,567.50', ',', '.', '۱٬۲۳۴٬۵۶۷٫۵۰'],
	['١٬٢٣٤٫٥٠', '٬', '٫', '۱٬۲۳۴٫۵۰'],
	['1.234,50', '.', ',', '۱٬۲۳۴٫۵۰'],
	['1 234 567,50', ' ', ',', '۱٬۲۳۴٬۵۶۷٫۵۰'],
	['0.0050', ',', '.', '۰٫۰۰۵۰']
]) {
	const localized = fixture(table(raw, [row('fee', 'هزینه', money(raw))]));
	localized.order.dataset.artnovoPriceThousandSeparator = thousand;
	localized.order.dataset.artnovoPriceDecimalSeparator = decimal;
	assert.equal(sync(localized), true);
	assert.equal(localized.totals[0].textContent, shown + ' تومان', 'مبلغ native بدون بازحساب، با ارقام و جداکنندهٔ نمایشی فارسی می‌شود');
	assert.equal(localized.adjustments.childNodes[0].textContent, 'هزینه' + shown + ' تومان');
	assert.equal(localized.nativeCheckout.querySelector('.order-total td').textContent, raw + ' تومان', 'گره و مقدار منبع native برای همگام‌سازی بعدی دست‌نخورده می‌مانند');
}

const technicalAmount = el('span', { class: 'woocommerce-Price-amount', 'data-amount': '1234.50' },
	el('bdi', {}, '1,234.50 ', el('input', { value: '09370123456' }),
		el('code', {}, 'token-123'), el('span', { 'data-artnovo-technical': '' }, 'vpn-123'),
		el('span', { class: 'woocommerce-Price-currencySymbol' }, 'CURRENCY20')));
const technical = fixture(el('table', { class: 'woocommerce-checkout-review-order-table' },
	el('tfoot', {}, row('order-total', 'مبلغ', technicalAmount))));
sync(technical);
assert.equal(technical.totals[0].textContent, '۱٬۲۳۴٫۵۰ token-123vpn-123CURRENCY20');
assert.equal(technical.totals[0].querySelector('.woocommerce-Price-amount').getAttribute('data-amount'), '1234.50');
assert.equal(technical.totals[0].querySelector('input').getAttribute('value'), '09370123456', 'اطلاعات ورودی و مقدار قابل‌کپی به‌دلیل نمایش مبلغ تغییر نمی‌کنند');

const humanTitle = el('h3', { 'data-product': '25' }, 'پلن ', el('strong', {}, '70'), ' گیگ ٢ ماهه',
	el('input', { value: 'username20' }), el('code', {}, 'ss://token20'));
new vm.Script(numberFunctions + '\nlocalizeText(humanTitle, persianDigits);').runInNewContext({ humanTitle, order: el('section') });
assert.equal(humanTitle.textContent, 'پلن ۷۰ گیگ ۲ ماههss://token20');
assert.equal(humanTitle.getAttribute('data-product'), '25');
assert.equal(humanTitle.querySelector('input').getAttribute('value'), 'username20');
console.log('آزمون همگام‌سازی مبلغ، تخفیف، مالیات، هزینه، fragment و حذف کوپن موفق بود.');

// Run the actual history recovery and its initialization/pageshow wiring before the real prepare helper.
const recoveryStart = source.indexOf('\tfunction recoverPurchaseHistory(');
const recoveryEnd = source.indexOf('\n\tfunction selectionBadge()', recoveryStart);
assert.ok(recoveryStart >= 0 && recoveryEnd > recoveryStart, 'بازیابی واقعی تاریخچه پیش از آماده‌سازی خرید وجود دارد');
const historyFixture = ({ type = 'navigate', prepare = true, href = 'https://example.test/?artnovo_plan=25#artnovo-order', action = 'https://example.test/' } = {}) => {
	const replaced = [];
	const events = new Map();
	const bankConnection = [];
	const location = { href, origin: 'https://example.test', replace: (url) => replaced.push(url) };
	const context = vm.createContext({
		selectionForm: prepare ? { action } : null, historyRecovering: false,
		location, URL, Date: { now: () => 1730000000000 },
		showBankConnection: (active) => bankConnection.push(active),
		window: { performance: { getEntriesByType: () => [{ type }] }, addEventListener: (name, listener) => events.set(name, listener) }
	});
	// Production's early return exits its enclosing IIFE, so execute the captured block inside the same shape.
	new vm.Script('(() => {' + source.slice(recoveryStart, recoveryEnd) + '\nglobalThis.readyForPrepare = true; })();').runInContext(context, { timeout: 1000 });
	return { context, replaced, events, location, bankConnection };
};
for (const type of ['navigate', 'reload']) {
	const normal = historyFixture({ type });
	assert.equal(normal.context.readyForPrepare, true, 'انتخاب عادی و reload مسیر مستقیم خود را حفظ می‌کنند');
	assert.deepEqual(normal.replaced, []);
}
const back = historyFixture({ type: 'back_forward' });
assert.equal(back.context.readyForPrepare, undefined, 'بازگشت تاریخچه پیش از auto-prepare قدیمی متوقف می‌شود');
assert.equal(back.replaced.length, 1);
const guarded = new URL(back.replaced[0]);
assert.equal(guarded.searchParams.get('artnovo_plan'), '25');
assert.equal(guarded.searchParams.get('artnovo_checkout'), '1', 'سرور انتخاب فعلی را در GET محافظت‌شده تعیین می‌کند');
assert.equal(guarded.searchParams.get('artnovo_refresh'), '1730000000000');
assert.equal(guarded.hash, '#artnovo-order');
const cached = historyFixture();
cached.events.get('pageshow')({ persisted: false });
assert.deepEqual(cached.replaced, [], 'pageshow عادی رفت‌وبرگشت اضافه نمی‌سازد');
cached.events.get('pageshow')({ persisted: true });
cached.events.get('pageshow')({ persisted: true });
assert.deepEqual(cached.bankConnection, [false, false], 'بازگشت به فرم، وضعیت اتصال قبلی را پاک می‌کند');
assert.equal(cached.replaced.length, 1, 'بازگشت BFCache فقط یک GET تازه می‌گیرد و فرم قدیمی را POST نمی‌کند');
assert.equal(new URL(cached.replaced[0]).searchParams.get('artnovo_checkout'), '1');
for (const options of [
	{ prepare: false },
	{ href: 'https://example.test/#artnovo-categories' },
	{ href: 'https://example.test/?artnovo_plan=0' },
	{ href: 'https://example.test/?artnovo_plan=25x' },
	{ href: 'https://external.test/?artnovo_plan=25' },
	{ href: 'http://example.test/?artnovo_plan=25' },
	{ href: 'javascript:alert(1)' },
	{ action: 'https://external.test/' },
	{ action: 'javascript:alert(1)' },
	{ action: 'https://example.test/other-route/' },
	{ action: 'https://user:password@example.test/' }
]) {
	const ignored = historyFixture({ type: 'back_forward', ...options });
	assert.deepEqual(ignored.replaced, [], 'مهمان، نبود پلن و نشانی نامعتبر نباید به بازیابی خرید تبدیل شوند');
	assert.equal(ignored.context.readyForPrepare, true);
	ignored.events.get('pageshow')({ persisted: true });
	assert.deepEqual(ignored.replaced, [], 'BFCache نیز همان مرزهای نشانی و وجود فرم واردشده را حفظ می‌کند');
}
const noNavigationApi = historyFixture();
noNavigationApi.context.window.performance = undefined;
assert.doesNotThrow(() => noNavigationApi.events.get('pageshow')({ persisted: true }));
assert.equal(noNavigationApi.replaced.length, 1, 'BFCache برای بازیابی به Navigation Timing وابسته نیست');
assert.ok(source.indexOf('if (recoverPurchaseHistory()) return;') < source.indexOf("if (state.step === '3' && !review"));
console.log('آزمون بازیابی GET تاریخچه و BFCache، انتخاب عادی، reload و مرزهای نشانی موفق بود.');

// Exercise the production async queue, not a second implementation of its transitions.
const functionBlock = (from, to) => {
	const fromIndex = source.indexOf(from);
	const toIndex = source.indexOf(to, fromIndex);
	assert.ok(fromIndex >= 0 && toIndex > fromIndex);
	return source.slice(fromIndex, toIndex);
};
const queueCode = functionBlock('\tfunction prepareCheckout(', '\n\tfunction installNativeCheckout(')
	+ functionBlock('\tfunction updateSelectionRevision(', '\n\tfunction showQuantityStatus(')
	+ functionBlock('\tfunction go(', '\n\troot.addEventListener(\'click\'');
const flush = () => new Promise((resolve) => setImmediate(resolve));
const revision = (letter) => letter.repeat(64);
class Payload {
	constructor(form) { this.fields = new Map([...form.fields].map(([name, field]) => [name, field.value])); }
	set(name, value) { this.fields.set(name, String(value)); }
	get(name) { return this.fields.get(name); }
	delete(name) { this.fields.delete(name); }
}
const queueFixture = ({ signedIn = false } = {}) => {
	const events = [];
	const requests = [];
	const navigation = [];
	const sequence = [];
	const fields = new Map([
		['artnovo_product', { value: '25' }], ['artnovo_quantity', { value: '1' }],
		['artnovo_selection_revision', { value: revision('a') }], ['artnovo_wizard_nonce', { value: 'nonce-a' }],
		['artnovo_auth_identifier', { value: 'username123' }], ['artnovo_auth_proof', { value: 'private-proof123' }]
	]);
	const select = {
		action: 'https://example.test/', dataset: {}, fields,
		elements: { namedItem: (name) => fields.get(name) },
		querySelectorAll: (selector) => selector.includes('artnovo_wizard_nonce') ? [fields.get('artnovo_wizard_nonce')] : [],
		dispatchEvent: (event) => events.push({ type: event.type, detail: event.detail, available: select.dataset.artnovoAuthAvailable })
	};
	const nativeHost = { hidden: false, inert: false, contains: (field) => field === nativeRevision };
	const nativeRevision = { value: revision('a') };
	const bindings = {
		form: signedIn ? null : select, prepare: signedIn ? select : null, selectionForm: select,
		nativeHost, nativeCheckout: null, nativeRefreshAllowed: false, nativeAssetsFailed: false,
		preparationPanel: { hidden: true, dataset: {} },
		preparationStatus: { dataset: { artnovoPrepareMessage: 'در حال دریافت روش‌های پرداخت…' } }, retryPrepare: {},
		state: { step: '2', category: '1', plan: null }, quantity: 1,
		selectionPending: false, selectionRecovery: false, selectionSerial: 0, preparing: false,
		queuedSelection: null, requestedIdentity: '', confirmedIdentity: '', historyRecovering: false,
		reviewCurrent: false, review: false, quantityPending: false, authPending: false, asyncSupported: true,
		checkoutPlans: new Set(['25', '26', '27']), nativeRequests: new Set(),
		cancelNativeReview: () => {},
		order: {
			dataset: {}, querySelectorAll: (selector) => selector.includes('artnovo_selection_revision') ? [fields.get('artnovo_selection_revision'), nativeRevision] : []
		},
		adjustments: { replaceChildren() {} },
		findPlan: (id) => ({ id }), normalize: (value) => value,
		writeLocation: (next) => sequence.push('location:' + next.plan),
		render: (next) => { bindings.state = next; bindings.quantity = 1; sequence.push('render:' + next.plan); },
		updateAmounts: (plan) => {
			select.dataset.artnovoAuthAvailable = !bindings.selectionPending && !bindings.selectionRecovery && bindings.checkoutPlans.has(plan.id) ? '1' : '0';
			fields.get('artnovo_product').value = plan.id;
			fields.get('artnovo_quantity').value = String(bindings.quantity);
		},
		showQuantityStatus: (message, pending) => { bindings.status = { message, pending }; },
		installNativeCheckout: () => { bindings.installed = true; },
		loadCheckoutAssets: async () => { bindings.assetsLoaded = true; },
		refreshOrderReview: async () => true,
		location: { href: 'https://example.test/', origin: 'https://example.test', replace: (url) => navigation.push(url) },
		URL, FormData: Payload, AbortController, setTimeout, clearTimeout,
		CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
		fetch: (url, options) => {
			sequence.push('fetch:' + options.body.get('artnovo_product'));
			return new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
		}
	};
	const context = vm.createContext(bindings);
	new vm.Script(queueCode).runInContext(context);
	const choose = (id) => context.go({ step: '3', category: '1', plan: id });
	const succeed = (index, overrides = {}) => {
		const request = requests[index];
		const product = Number(request.options.body.get('artnovo_product'));
		const count = Number(request.options.body.get('artnovo_quantity'));
		request.resolve({ json: async () => ({ success: true, data: {
			product, quantity: count, cart_identity: `${product}:${count}`, selection_revision: revision(String.fromCharCode(98 + index)),
			wizard_nonce: 'nonce-' + index, rest_nonce: 'rest-' + index,
			redirect: `https://example.test/?artnovo_plan=${product}&artnovo_checkout=1`,
			checkout_html: '', checkout_assets: '', ...overrides
		} }) });
	};
	return { context, events, fields, nativeRevision, nativeHost, requests, navigation, sequence, choose, succeed };
};

async function testAsyncSelection() {
	const flow = queueFixture();
	flow.choose('25');
	assert.deepEqual(flow.sequence.slice(0, 3), ['location:25', 'render:25', 'fetch:25'], 'مرحلهٔ سوم پیش از پایان یا حتی شروع انتظار شبکه نمایش داده می‌شود');
	assert.equal(flow.context.selectionPending, true);
	assert.equal(flow.context.preparationPanel.hidden, true, 'مهمان نشانگر دریافت روش‌های پرداخت را نمی‌بیند');
	assert.equal(flow.context.preparationPanel.dataset.artnovoPreparing, '0');
	assert.match(flow.context.status.message, /اولیه است/);
	assert.equal(flow.context.status.pending, true, 'مبلغ تخمینی به‌عنوان مبلغ قطعی پرداخت معرفی نمی‌شود');
	assert.equal(flow.nativeHost.inert, true);
	assert.equal(flow.events[0].detail.pending, true);
	assert.equal(flow.events[0].available, '0');
	assert.equal(flow.fields.get('artnovo_auth_identifier').value, 'username123', 'آماده‌سازی، شناسهٔ قابل‌ویرایش مهمان را تغییر نمی‌دهد');
	assert.equal(flow.requests[0].options.method, 'POST');
	assert.equal(flow.requests[0].options.credentials, 'same-origin');
	assert.equal(flow.requests[0].options.body.get('artnovo_wizard_select'), '1');
	assert.equal(flow.requests[0].options.body.get('artnovo_auth_identifier'), undefined, 'شناسه و رمز ورود جزو درخواست انتخاب نیستند');
	assert.equal(flow.requests[0].options.body.get('artnovo_auth_proof'), undefined);
	assert.equal(new URL(flow.requests[0].url).searchParams.get('artnovo_checkout'), '1');
	flow.context.prepareCheckout('25', 1);
	assert.equal(flow.requests.length, 1, 'انتخاب تکراری حین آماده‌سازی دوباره POST نمی‌شود');
	flow.choose('26');
	flow.choose('27');
	assert.equal(flow.requests.length, 1, 'انتخاب‌های سریع به‌صورت هم‌زمان با revision یکسان ارسال نمی‌شوند');
	flow.succeed(0);
	await flush();
	assert.equal(flow.requests.length, 2);
	assert.equal(flow.requests[1].options.body.get('artnovo_product'), '27', 'آخرین انتخاب، مورد میانی را بی‌اخطار جایگزین می‌کند');
	assert.equal(flow.requests[1].options.body.get('artnovo_selection_revision'), revision('b'), 'صف از revision تأییدشدهٔ سرور استفاده می‌کند');
	assert.equal(flow.nativeRevision.value, revision('a'), 'پاسخ قدیمی revision تازه را به فرم پرداخت قدیمی نمی‌دهد');
	assert.equal(flow.events.some((event) => event.type === 'artnovo:selection-ready'), false, 'پاسخ A هیچ‌وقت ورود یا پرداخت انتخاب C را باز نمی‌کند');
	flow.succeed(1);
	await flush();
	assert.equal(flow.context.confirmedIdentity, '27:1');
	assert.equal(flow.context.selectionPending, false);
	const ready = flow.events.findIndex((event) => event.type === 'artnovo:selection-ready');
	assert.ok(ready >= 0);
	assert.equal(flow.events[ready].available, '1');
	assert.equal(flow.events[ready].detail.product, 27);
	assert.equal(flow.events[ready + 1].type, 'artnovo:selection-busy');
	assert.equal(flow.events[ready + 1].detail.pending, false, 'ready پیش از busyfalse آماده‌بودن واقعی را به auth می‌رساند');
	assert.deepEqual(flow.navigation, [], 'خرید عادی AJAX نیاز به درخواست سند تازه ندارد');
	flow.context.prepareCheckout('27', 1);
	assert.equal(flow.requests.length, 2, 'خرید تأییدشدهٔ تکراری دوباره آماده نمی‌شود');
	assert.equal(flow.context.prepareCheckout('999', 1), false, 'محصول بدون روش پرداخت مجاز آماده نمی‌شود');

	const lost = queueFixture();
	lost.choose('25');
	lost.requests[0].reject(new TypeError('network failure'));
	await flush();
	assert.equal(lost.context.selectionRecovery, true);
	assert.equal(lost.context.reviewCurrent, false);
	assert.equal(lost.nativeHost.inert, true);
	assert.equal(lost.events.at(-1).available, '0', 'خطای مبهم اجازهٔ ورود/پرداخت را باز نمی‌کند');
	lost.context.prepareCheckout('25', 1);
	assert.equal(lost.requests.length, 1, 'تلاش دوباره پس از خطای مبهم POST را کورکورانه تکرار نمی‌کند');
	assert.equal(lost.navigation.length, 1);
	assert.equal(new URL(lost.navigation[0]).searchParams.get('artnovo_checkout'), '1');
	assert.ok(new URL(lost.navigation[0]).searchParams.has('artnovo_refresh'));

	for (const data of [
		{ refresh_required: true, redirect: 'https://example.test/?artnovo_plan=27&artnovo_checkout=1' },
		{ selection_changed: true, redirect: 'https://example.test/?artnovo_plan=27&artnovo_checkout=1' }
	]) {
		const changed = queueFixture();
		changed.choose('25');
		changed.requests[0].resolve({ json: async () => ({ success: !data.refresh_required, data }) });
		await flush();
		assert.equal(changed.navigation.length, 1, 'nonce منقضی یا انتخاب تب دیگر فقط از وضعیت سرور GET می‌شود');
		assert.equal(changed.requests.length, 1);
		assert.equal(changed.events.some((event) => event.type === 'artnovo:selection-ready'), false);
	}
	for (const invalid of [
		{ product: 26 }, { quantity: 2 }, { cart_identity: '26:1' }, { selection_revision: 'short' },
		{ wizard_nonce: '' }, { rest_nonce: '' }, { checkout_html: null },
		{ redirect: 'https://external.test/?artnovo_plan=25' },
		{ redirect: 'https://example.test/other/?artnovo_plan=25' },
		{ redirect: 'https://user:password@example.test/?artnovo_plan=25' }
	]) {
		const denied = queueFixture();
		denied.choose('25');
		denied.succeed(0, invalid);
		await flush();
		assert.equal(denied.context.reviewCurrent, false);
		assert.equal(denied.context.selectionRecovery, true, 'پاسخ ناسازگار نه مبلغ و نه اقدام نهایی را معتبر نمی‌کند');
		assert.equal(denied.events.some((event) => event.type === 'artnovo:selection-ready'), false);
		assert.deepEqual(denied.navigation, [], 'redirect نامعتبر دنبال نمی‌شود');
	}
	const restored = queueFixture();
	restored.choose('25');
	restored.context.historyRecovering = true;
	restored.succeed(0);
	await flush();
	assert.equal(restored.events.some((event) => event.type === 'artnovo:selection-ready'), false, 'پاسخ دیرهنگام بعد از بازیابی تاریخچه کنار گذاشته می‌شود');
	const abandoned = queueFixture();
	abandoned.choose('25');
	abandoned.context.state = { step: '2', category: '2', plan: null };
	abandoned.context.abandonSelection();
	abandoned.succeed(0);
	await flush();
	assert.equal(abandoned.events.some((event) => event.type === 'artnovo:selection-ready'), false, 'نتیجهٔ انتخاب رهاشده در دستهٔ دیگر دوباره فرم یا پلن قبلی را باز نمی‌کند');
	assert.equal(abandoned.context.selectionPending, false);
	assert.equal(abandoned.context.selectionRecovery, false, 'ترک انتخاب خطای مبهم شبکه نیست و نیاز به بازیابی اجباری ندارد');
	abandoned.choose('26');
	abandoned.succeed(1);
	await flush();
	assert.equal(abandoned.context.confirmedIdentity, '26:1');
	const previous = queueFixture({ signedIn: true });
	previous.context.confirmedIdentity = '25:1';
	previous.context.review = previous.context.reviewCurrent = true;
	previous.choose('26');
	assert.equal(previous.context.preparationPanel.dataset.artnovoPreparing, '1');
	previous.context.state = { step: '2', category: '2', plan: null };
	previous.context.abandonSelection();
	assert.equal(previous.context.preparationPanel.dataset.artnovoPreparing, '0', 'ترک انتخاب نشانگر را متوقف می‌کند');
	previous.succeed(0);
	await flush();
	previous.choose('25');
	assert.equal(previous.requests.length, 2, 'بازانتخاب پلن آمادهٔ قبلی پس از رهاکردن انتخاب جدید باید دوباره با نشست واقعی تأیید شود');
	assert.equal(previous.nativeHost.inert, true, 'در زمان بازتأیید، پرداخت قبلی همچنان بسته می‌ماند');
	assert.equal(previous.context.preparationPanel.hidden, false);
	assert.equal(previous.context.preparationStatus.textContent, 'در حال دریافت روش‌های پرداخت…');
	assert.equal(previous.context.retryPrepare.hidden, true);
	previous.succeed(1);
	await flush();
	assert.equal(previous.context.confirmedIdentity, '25:1');
	assert.equal(previous.context.reviewCurrent, true);
	assert.equal(previous.nativeHost.inert, false, 'انتخاب صریح قبلی پس از تأیید تازه دوباره قابل پرداخت می‌شود');
	assert.equal(previous.context.preparationPanel.dataset.artnovoPreparing, '0');
	assert.equal(previous.context.preparationPanel.hidden, true);
	const signed = queueFixture({ signedIn: true });
	signed.context.refreshOrderReview = async () => false;
	signed.choose('25');
	signed.succeed(0, { checkout_html: '<form></form>' });
	await flush();
	assert.equal(signed.context.installed, true);
	assert.equal(signed.context.assetsLoaded, true);
	assert.equal(signed.context.reviewCurrent, false, 'بدون رویداد تأیید مبلغ بومی، فرم درج‌شده هم اجازهٔ پرداخت ندارد');
	assert.equal(signed.context.preparationPanel.dataset.artnovoPreparing, '0');
	assert.match(signed.context.preparationStatus.textContent, /مبلغ نهایی تأیید نشد/);
	assert.equal(signed.context.retryPrepare.hidden, false);
	const swapping = queueFixture({ signedIn: true });
	let assetsDone;
	let assetLoads = 0;
	let refreshes = 0;
	swapping.context.loadCheckoutAssets = () => {
		assetLoads += 1;
		return assetLoads === 1 ? new Promise((resolve) => { assetsDone = resolve; }) : Promise.resolve();
	};
	swapping.context.refreshOrderReview = async () => { refreshes += 1; return true; };
	swapping.choose('25');
	swapping.succeed(0);
	await flush();
	swapping.choose('26');
	assetsDone();
	await flush();
	assert.equal(swapping.requests.length, 2);
	assert.equal(swapping.context.reviewCurrent, false);
	assert.equal(swapping.nativeHost.inert, true);
	assert.equal(swapping.context.preparationPanel.dataset.artnovoPreparing, '1', 'تعویض پلن حلقه را تا تأیید انتخاب تازه نگه می‌دارد');
	assert.equal(refreshes, 0, 'تکمیل دیرهنگام asset انتخاب A موجب تأیید پرداخت A نمی‌شود');
	swapping.succeed(1);
	await flush();
	assert.equal(swapping.context.confirmedIdentity, '26:1');
	assert.equal(refreshes, 1);
	assert.equal(swapping.nativeHost.inert, false);
	const initialize = new vm.Script(functionBlock("\tif (state.step === '3' &&", '\n\tbindNativeEvents();'));
	for (const signedIn of [false, true]) {
		const fallback = queueFixture({ signedIn });
		const posted = [];
		fallback.context.asyncSupported = false;
		fallback.context.state = { step: '3', category: '1', plan: '25' };
		fallback.context.order.dataset.artnovoAutoPrepare = signedIn ? '1' : '0';
		for (const name of ['artnovo_wizard_ajax', 'artnovo_wizard_select', 'artnovo_otp_action']) fallback.fields.set(name, { value: 'stale-marker' });
		fallback.context.selectionForm.submit = () => posted.push(Object.fromEntries([...fallback.fields].filter(([, field]) => !field.disabled).map(([name, field]) => [name, field.value])));
		initialize.runInContext(fallback.context);
		initialize.runInContext(fallback.context);
		assert.equal(posted.length, 1, 'مرورگر بدون API ناهمگام برای انتخاب معتبر فقط یک POST بومی می‌فرستد');
		assert.deepEqual(fallback.navigation, [], 'نبود API باعث حلقهٔ بازیابی GET نمی‌شود');
		assert.equal(fallback.requests.length, 0);
		assert.equal(posted[0].artnovo_product, '25');
		assert.equal(posted[0].artnovo_quantity, '1');
		assert.equal(posted[0].artnovo_wizard_nonce, 'nonce-a');
		assert.equal(posted[0].artnovo_selection_revision, revision('a'));
		for (const name of ['artnovo_wizard_ajax', 'artnovo_wizard_select', 'artnovo_otp_action']) assert.equal(Object.hasOwn(posted[0], name), false, 'POST بومی علامت AJAX یا درخواست OTP کهنه ندارد');
	}
	for (const asyncSupported of [false, true]) {
		const failedPage = queueFixture({ signedIn: true });
		failedPage.context.asyncSupported = asyncSupported;
		failedPage.context.state = { step: '3', category: '1', plan: '25' };
		failedPage.context.order.dataset.artnovoAutoPrepare = '0';
		let attempts = 0;
		failedPage.context.selectionForm.submit = () => { attempts += 1; };
		initialize.runInContext(failedPage.context);
		assert.equal(attempts, 0, 'صفحهٔ شکست POST بومی auto-prepare خاموش را رعایت و POST را دوباره اجرا نمی‌کند');
		assert.equal(failedPage.requests.length, 0);
		assert.deepEqual(failedPage.navigation, []);
		failedPage.context.prepareCheckout('25', 1);
		assert.equal(attempts + failedPage.requests.length, 1, 'تلاش دوباره فقط با اقدام صریح کاربر امکان دارد');
		if (asyncSupported) { failedPage.succeed(0); await flush(); }
	}
	console.log('آزمون نمایش فوری، صف انتخاب، پاسخ دیرهنگام، تکرار، خطای شبکه، nonce/تب دیگر، فرم نامعتبر و بسته‌ماندن پرداخت موفق بود.');
}

function testNativeForms() {
	const nativeHost = el('div');
	const marker = (name, value) => { const input = el('input', { name }); input.value = value; return input; };
	const checkout = (identity, rev, ...nodes) => {
		const result = el('form', { class: 'checkout', 'data-render': identity },
			marker('artnovo_wizard_cart', identity), marker('artnovo_selection_revision', rev), ...nodes);
		result.elements = { namedItem: (name) => result.childNodes.find((node) => node.getAttribute?.('name') === name) };
		return result;
	};
	const original = checkout('25:1', revision('a'), el('div', {}, 'old fields'));
	const couponForm = el('form', { class: 'checkout_coupon' }, el('input', { value: 'old-coupon' }));
	let nativeHandler = 0;
	original.addEventListener('click', () => nativeHandler++);
	couponForm.addEventListener('click', () => nativeHandler++);
	nativeHost.append(original, couponForm);
	const inline = el('script', {}, 'must-not-execute');
	const fresh = checkout('26:2', revision('b'), el('div', {}, 'new fields'), inline);
	const fragment = el('fragment', {}, el('div', {}, fresh, el('form', { class: 'checkout_coupon' }, el('input', { value: 'new-coupon' }))));
	const context = vm.createContext({
		nativeHost, nativeCheckout: original, cartIdentity: null,
		document: { createElement: () => ({ content: fragment, set innerHTML(value) {} }) }
	});
	new vm.Script(functionBlock('\tfunction installNativeCheckout(', '\n\tasync function loadCheckoutAssets(')).runInContext(context);
	context.installNativeCheckout('<native-fixture>', { cart_identity: '26:2', selection_revision: revision('b') });
	assert.equal(context.nativeCheckout, original, 'outer فرم پرداخت برای handler بومی WooCommerce همان شیء می‌ماند');
	assert.equal(nativeHost.querySelector('form.checkout_coupon'), couponForm, 'outer فرم کوپن نیز همان شیء باقی می‌ماند');
	assert.equal(original.getAttribute('data-render'), '26:2');
	assert.match(original.textContent, /new fields/);
	assert.equal(original.textContent.includes('old fields'), false);
	assert.equal(original.querySelectorAll('script').length, 0, 'اسکریپت فرم HTML به‌جای صف asset اجرا نمی‌شود');
	assert.equal(context.cartIdentity.value, '26:2');
	original.click(); couponForm.click();
	assert.equal(nativeHandler, 2, 'listenerهای بومی پس از جایگزینی باقی‌اند');
	assert.throws(() => context.installNativeCheckout('<wrong-marker>', { cart_identity: '27:1', selection_revision: revision('b') }), /هماهنگ نیست/);
	console.log('آزمون حفظ فرم‌های native و handler کوپن، marker و حذف اسکریپت HTML موفق بود.');
}

const assetFixture = (nodes, existing = ['wc-checkout-js'], failId = '') => {
	const present = new Map(existing.map((id) => [id, { id, tagName: 'SCRIPT' }]));
	const appended = [];
	const executed = [];
	const hooks = [];
	const makeAsset = (tagName, attrs = {}, text = '') => {
		const attributes = new Map(Object.entries(attrs));
		const node = { tagName: tagName.toUpperCase(), textContent: text, attributes: [],
			setAttribute(name, value) {
				attributes.set(name, String(value));
				this.attributes = [...attributes].map(([name, value]) => ({ name, value }));
			},
			getAttribute: (name) => attributes.get(name) ?? null,
			get id() { return attributes.get('id') || ''; }, get rel() { return attributes.get('rel') || ''; }
		};
		for (const [name, value] of attributes) node.setAttribute(name, value);
		return node;
	};
	const originals = nodes.map(([tag, attrs, text]) => makeAsset(tag, attrs, text));
	const context = vm.createContext({
		nativeAssetsFailed: false, location: { href: 'https://example.test/', origin: 'https://example.test' }, URL, setTimeout, clearTimeout,
		window: { jQuery() {} }, bindNativeEvents: () => hooks.push('bind'),
		document: {
			querySelectorAll: () => [...present.values()].filter((node) => node.tagName === 'SCRIPT'),
			getElementById: (id) => present.get(id),
			createElement: (tag) => tag === 'template'
				? { content: { querySelectorAll: () => originals }, set innerHTML(value) {} }
				: makeAsset(tag),
			head: { append: (node) => {
				appended.push(node); present.set(node.id, node);
				if (node.tagName === 'SCRIPT' && !node.getAttribute('src')) executed.push(node.textContent);
				queueMicrotask(() => node.id === failId ? node.onerror?.() : node.onload?.());
			} }
		}
	});
	new vm.Script(functionBlock('\tasync function loadCheckoutAssets(', '\n\tfunction bindNativeEvents(')).runInContext(context);
	return { context, appended, executed, hooks, present };
};

async function testNativeAssets() {
	const assets = assetFixture([
		['script', { id: 'wc-checkout-js-extra' }, 'old-params'],
		['script', { id: 'wc-checkout-js', src: 'https://example.test/wc-checkout.js' }],
		['script', { id: 'gateway-js-extra' }, 'new-params'],
		['script', { id: 'gateway-js-before' }, 'new-before'],
		['script', { id: 'gateway-js', src: 'https://gateway.example/native.js', nonce: 'native-nonce', integrity: 'sha256-native', crossorigin: 'anonymous', type: 'text/javascript' }],
		['script', { id: 'gateway-js-after' }, 'new-after'],
		['script', {}, 'no-id-full-page-script'],
		['link', { id: 'native-css', rel: 'stylesheet', href: 'https://example.test/native.css' }],
		['link', { id: 'not-style', rel: 'preconnect', href: 'https://example.test/' }]
	]);
	await assets.context.loadCheckoutAssets('<registered-native-assets>');
	assert.deepEqual(assets.executed, ['new-params', 'new-before', 'new-after'], 'extra/before/after افزونهٔ جدید به‌ترتیب اجرا و تنظیمات اسکریپت موجود دوباره اجرا نمی‌شوند');
	assert.deepEqual(assets.appended.map((node) => node.id), ['gateway-js-extra', 'gateway-js-before', 'gateway-js', 'gateway-js-after', 'native-css']);
	const gateway = assets.present.get('gateway-js');
	for (const [name, value] of [['nonce', 'native-nonce'], ['integrity', 'sha256-native'], ['crossorigin', 'anonymous'], ['type', 'text/javascript']]) assert.equal(gateway.getAttribute(name), value);
	await assets.context.loadCheckoutAssets('<registered-native-assets>');
	assert.equal(assets.appended.length, 5, 'بازگشت به مرحلهٔ پرداخت asset یا handler قبلی را دوباره اجرا نمی‌کند');
	const first = assetFixture([['script', { id: 'wc-checkout-js', src: '/wc-checkout.js' }]], []);
	await first.context.loadCheckoutAssets('<first-native-checkout>', () => first.hooks.push('before-checkout'));
	assert.equal(first.hooks[0], 'before-checkout', 'listener مبلغ پیش از اجرای init native ثبت می‌شود');
	assert.equal(first.appended.length, 1);
	for (const source of ['javascript:alert(1)', 'data:text/javascript,alert(1)', 'https://user:password@external.test/native.js', 'http://external.test/native.js']) {
		const denied = assetFixture([['script', { id: 'unsafe-js', src: source }]]);
		await assert.rejects(denied.context.loadCheckoutAssets('<unsafe-native-asset>'), /معتبر نیست/);
		assert.equal(denied.appended.length, 0);
		assert.equal(denied.context.nativeAssetsFailed, true);
	}
	const failed = assetFixture([['script', { id: 'failed-js', src: '/failed.js' }]], ['wc-checkout-js'], 'failed-js');
	await assert.rejects(failed.context.loadCheckoutAssets('<failed-native-asset>'), /بارگذاری نشد/);
	await assert.rejects(failed.context.loadCheckoutAssets('<do-not-replay>'), /کامل نشد/);
	assert.equal(failed.appended.length, 1, 'بارگذاری ناقص sticky است و اسکریپت نامطمئن را دوباره اجرا نمی‌کند');
	console.log('آزمون ترتیب و یکتایی asset بومی، صف before/extra/after، ویژگی‌های امنیتی و شکست بسته موفق بود.');
}

(async () => {
	await testAsyncSelection();
	testNativeForms();
	await testNativeAssets();
})().catch((error) => { console.error(error); process.exitCode = 1; });
