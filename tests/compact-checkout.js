'use strict';

// Execute the production closure's summary function against an isolated native DOM fixture.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../modules/home/artnovo-home-wizard.js'), 'utf8');
const start = source.indexOf('\tfunction syncNativeSummary() {');
const end = source.indexOf('\n\tfunction refreshOrderReview() {', start);
assert.ok(start >= 0 && end > start, 'تابع واقعی همگام‌سازی در کد تولید وجود دارد');
const production = new vm.Script(source.slice(start, end) + '\nsyncNativeSummary();');
const amountsStart = source.indexOf('\tfunction updateAmounts(plan) {');
const amountsEnd = source.indexOf('\n\tfunction prepareCheckout(', amountsStart);
assert.ok(amountsStart >= 0 && amountsEnd > amountsStart);
const amountsProduction = new vm.Script(source.slice(start, end) + source.slice(amountsStart, amountsEnd) + '\nupdateAmounts(plan);');

class Element {
	constructor(tagName, attributes = {}, ...children) {
		this.tagName = tagName;
		this.attributes = { ...attributes };
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
	get tabIndex() { return Number(this.attributes.tabindex ?? (this.tagName === 'a' ? 0 : -1)); }
	set tabIndex(value) { this.setAttribute('tabindex', value); }
	get textContent() { return this.childNodes.map((node) => typeof node === 'string' ? node : node.textContent).join(''); }
	set textContent(value) { this.replaceChildren(String(value)); }
	append(...children) {
		for (const child of children) {
			const node = typeof child === 'string' ? new Text(child) : child;
			node.parentElement = this;
			this.childNodes.push(node);
		}
	}
	replaceChildren(...children) {
		for (const node of this.childNodes) node.parentElement = null;
		this.childNodes = [];
		this.append(...children);
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
	constructor(value) { this.textContent = value; }
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
console.log('آزمون همگام‌سازی مبلغ، تخفیف، مالیات، هزینه، fragment و حذف کوپن موفق بود.');
