/* Execute the production notice adapter with an isolated DOM, clock and jQuery; no network. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../modules/notices/notices.js'), 'utf8');
let checks = 0;
const check = (value, message) => { assert(value, message); checks++; };
function fixture(setup = () => {}) {
	let now = 0, nextTimer = 0, observer;
	const timers = new Map();
	const document = { hidden: false, listeners: {}, addEventListener(name, fn) { this.listeners[name] = fn; } };
	class Text {
		constructor(text) { this.nodeType = 3; this.textContent = text; this.parentElement = null; }
	}
	class Element {
		constructor(tag) {
			this.nodeType = 1; this.tagName = tag.toUpperCase(); this.childNodes = []; this.attributes = new Map(); this.listeners = {};
			this.dataset = new Proxy({}, { get: (_, key) => this.getAttribute('data-' + key.replace(/[A-Z]/g, c => '-' + c.toLowerCase())) ?? undefined,
				set: (_, key, value) => { this.setAttribute('data-' + key.replace(/[A-Z]/g, c => '-' + c.toLowerCase()), value); return true; } });
		}
		get className() { return this.getAttribute('class') || ''; }
		set className(value) { this.setAttribute('class', value); }
		get id() { return this.getAttribute('id'); } set id(value) { this.setAttribute('id', value); }
		get href() { return this.getAttribute('href'); } set href(value) { this.setAttribute('href', value); }
		get textContent() { return this.childNodes.map(node => node.textContent).join(''); }
		set textContent(value) { for (const old of this.childNodes) old.parentElement = null; this.childNodes = []; if (String(value)) this.append(new Text(String(value))); }
		set innerHTML(_) { throw new Error('Unsafe HTML insertion'); }
		setAttribute(name, value) { this.attributes.set(name, String(value)); }
		getAttribute(name) { return this.attributes.has(name) ? this.attributes.get(name) : null; }
		removeAttribute(name) { this.attributes.delete(name); }
		append(...nodes) { for (const node of nodes) { if (node.parentElement) node.remove(); node.parentElement = this; this.childNodes.push(node); } }
		remove() { if (this.parentElement) this.parentElement.childNodes = this.parentElement.childNodes.filter(node => node !== this); this.parentElement = null; }
		contains(node) { return node === this || this.childNodes.some(child => child.nodeType === 1 && child.contains(node)); }
		get isConnected() { return document.body.contains(this); }
		matches(selector) {
			const tokenMatches = (node, token) => {
				const tag = token.match(/^[a-z][\w-]*/i)?.[0];
				if (tag && node.tagName !== tag.toUpperCase()) return false;
				const outside = token.replace(/\[[^\]]+\]/g, '');
				if ([...outside.matchAll(/\.([\w-]+)/g)].some(([, name]) => !node.className.split(/\s+/).includes(name))) return false;
				const id = outside.match(/#([\w-]+)/)?.[1]; if (id && node.id !== id) return false;
				for (const [, name, operator, value] of token.matchAll(/\[([\w-]+)(\$?=)?(?:"([^"]*)")?\]/g)) {
					const actual = node.getAttribute(name); if (actual === null || operator === '=' && actual !== value || operator === '$=' && !actual.endsWith(value)) return false;
				}
				return true;
			};
			return selector.split(',').some(part => {
				const tokens = part.trim().split(/\s+/); let node = this;
				if (!tokenMatches(node, tokens.pop())) return false;
				while (tokens.length) { const token = tokens.pop(); node = node.parentElement; while (node && !tokenMatches(node, token)) node = node.parentElement; if (!node) return false; }
				return true;
			});
		}
		closest(selector) { let node = this; while (node) { if (node.matches(selector)) return node; node = node.parentElement; } return null; }
		querySelectorAll(selector) { const result = []; for (const node of this.childNodes) if (node.nodeType === 1) { if (node.matches(selector)) result.push(node); result.push(...node.querySelectorAll(selector)); } return result; }
		querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
		addEventListener(name, fn) { (this.listeners[name] ||= []).push(fn); }
		fire(name, extra = {}) { const event = { preventDefault() { this.defaultPrevented = true; }, ...extra }; for (const fn of this.listeners[name] || []) fn(event); return event; }
		focus() { document.activeElement = this; } click() { this.fire('click'); }
	}
	document.body = new Element('body'); document.activeElement = document.body;
	document.createElement = tag => new Element(tag); document.createTextNode = text => new Text(text);
	document.querySelectorAll = selector => document.body.querySelectorAll(selector);
	document.getElementById = id => document.querySelectorAll('#' + id)[0] || null;
	const element = (tag, attributes = {}, text = '', parent = document.body) => { const node = new Element(tag); for (const [key, value] of Object.entries(attributes)) node.setAttribute(key, value); node.textContent = text; parent.append(node); return node; };
	const before = setup({ document, element });
	let scrolls = 0;
	const jquery = value => typeof value === 'function' ? value() : { on() {}, toArray: () => [value] };
	jquery.scroll_to_notices = () => ++scrolls;
	const window = { jQuery: jquery, setTimeout(fn, delay) { const id = ++nextTimer; timers.set(id, { fn, due: now + delay }); return id; }, clearTimeout(id) { timers.delete(id); } };
	const context = { document, window, location: { href: 'https://example.test/checkout/' }, URL, performance: { now: () => now }, MutationObserver: class { constructor(fn) { observer = fn; } observe() {} } };
	vm.runInNewContext(source, context);
	const emit = (target, type = 'childList') => observer([{ target, type, addedNodes: [] }]);
	const update = (target, text) => { target.textContent = text; emit(target); };
	const advance = ms => { const end = now + ms; for (;;) { const entry = [...timers].filter(([, timer]) => timer.due <= end).sort((a, b) => a[1].due - b[1].due)[0]; if (!entry) break; now = entry[1].due; timers.delete(entry[0]); entry[1].fn(); } now = end; };
	return { document, element, before, update, emit, advance, context, jquery, scrolls: () => scrolls,
		toasts: () => document.querySelectorAll('.artnovo-toast'), announcer: () => document.querySelectorAll('.artnovo-toast-announcer')[0] };
}
const initial = fixture(({ element }) => element('ul', { class: 'woocommerce-error', role: 'alert', id: 'original-error' }, 'خطای اولیه'));
initial.advance(0);
check(initial.toasts().length === 1 && initial.announcer().textContent === 'خطای اولیه', 'server-rendered error is announced after takeover');
check(initial.before.parentElement === initial.document.body && initial.before.getAttribute('role') === 'alert' && initial.before.id === 'original-error', 'original notice, role and ID remain');
check(initial.before.dataset.artnovoToastSource === '1' && initial.before.getAttribute('aria-live') === 'off', 'only transferred source is hidden and muted');
initial.advance(7999); check(initial.toasts().length === 1, 'toast lasts eight seconds');
initial.advance(1); check(initial.toasts().length === 0, 'toast expires at eight seconds');
initial.update(initial.before, 'خطای اولیه'); check(initial.toasts().length === 1, 'identical repeated errors still notify');
initial.emit(initial.before, 'attributes'); check(initial.toasts().length === 1, 'attribute refresh does not duplicate a notice');
const timer = fixture(({ element }) => element('div', { class: 'woocommerce-message' }, 'انجام شد'));
timer.advance(1000); const toast = timer.toasts()[0]; toast.fire('mouseenter'); timer.advance(6000);
check(timer.toasts().length === 1 && toast.dataset.paused === '1', 'hover pauses countdown');
toast.fire('focusin'); toast.fire('mouseleave'); timer.advance(6000); check(timer.toasts().length === 1, 'focus continues pause after hover leaves');
toast.fire('focusout', { relatedTarget: timer.document.body }); timer.advance(6999); check(timer.toasts().length === 1, 'remaining time resumes accurately');
timer.document.hidden = true; timer.document.listeners.visibilitychange(); timer.advance(6000); check(timer.toasts().length === 1, 'hidden document pauses countdown');
timer.document.hidden = false; timer.document.listeners.visibilitychange(); timer.advance(1); check(timer.toasts().length === 0, 'countdown resumes when document returns');
const auth = fixture(({ element }) => { const form = element('form', { 'aria-busy': 'true' }); return { form, status: element('p', { 'data-artnovo-auth-message': '', role: 'status' }, 'در حال ورود…', form) }; });
check(!auth.before.status.dataset.artnovoToastSource && auth.toasts().length === 0, 'busy and confirmed-login progress remain inline');
auth.update(auth.before.status, 'کد ارسال شد'); auth.before.form.setAttribute('aria-busy', 'false'); auth.emit(auth.before.form, 'attributes');
check(auth.toasts().length === 1, 'completed Core request notifies');
auth.before.form.setAttribute('aria-busy', 'true'); auth.emit(auth.before.form, 'attributes');
check(!auth.before.status.dataset.artnovoToastSource && auth.before.status.getAttribute('aria-hidden') === null, 'new busy state restores native inline accessibility');
const native = fixture(({ element }) => {
	const accounts = element('div', { class: 'artnovo-account-subscriptions' }); const controls = element('div', { class: 'wpvpn-subscription-link' }, '', accounts);
	const status = element('span', { role: 'status' }, '', controls); return { status, controls, input: element('input', { type: 'url' }, '', controls) };
});
native.update(native.before.status, 'لینک انتخاب شد. دستی کپی کنید.');
check(native.toasts().length === 1 && native.before.status.parentElement === native.before.controls && native.before.input.parentElement === native.before.controls, 'Bridge manual-copy source inside real account wrapper remains intact');
native.advance(8000); native.update(native.before.status, 'لینک انتخاب شد. دستی کپی کنید.'); check(native.toasts().length === 1, 'repeated Bridge copy fallback still notifies');
const permanent = fixture(({ element }) => {
	element('p', { class: 'woocommerce-info' }, 'هنوز خریدی ندارید'); const toggle = element('div', { class: 'woocommerce-form-login-toggle' }); element('div', { class: 'woocommerce-info' }, 'وارد شوید', toggle);
	return element('div', { class: 'artnovo-received-status', role: 'status' }, 'در انتظار تأیید پرداخت');
});
check(permanent.toasts().length === 0 && permanent.before.getAttribute('aria-hidden') === null, 'empty/login and business states stay permanent');
const notyf = fixture(({ element }) => { const toast = element('div', { class: 'notyf__toast notyf__toast--error' }); element('div', { class: 'notyf__message' }, 'خطای ورود', toast); return { toast, live: element('div', { class: 'notyf-announcer', 'aria-live': 'polite' }, 'خطای ورود') }; });
check(notyf.toasts().length === 1 && notyf.before.toast.parentElement === notyf.document.body && notyf.before.live.getAttribute('aria-live') === 'off', 'Notyf DOM is retained and matching native announcement is muted');
notyf.update(notyf.before.live, 'پیام منتقل‌نشده'); check(notyf.before.live.getAttribute('aria-live') === 'polite', 'unhandled Notyf announcement retains native accessibility');
const safe = fixture(({ element }) => {
	const notice = element('div', { class: 'woocommerce-message' }); element('script', {}, 'window.pwned=true', notice); element('img', { onerror: 'window.pwned=true' }, '', notice);
	element('a', { href: 'javascript:alert(1)' }, 'پیوند نامعتبر', notice); const valid = element('a', { href: '#billing_phone' }, 'اصلاح شماره', notice); valid.addEventListener('click', () => { valid.nativeClicked = true; }); return valid;
});
const content = safe.toasts()[0].querySelector('.artnovo-toast-body'); const links = content.querySelectorAll('a');
check(!content.querySelector('script,img') && links.length === 1 && !content.textContent.includes('window.pwned'), 'unsafe HTML, handlers and URL schemes never enter toast');
links[0].click(); check(safe.before.nativeClicked, 'safe action links preserve native click handling');
const scroll = fixture(({ element }) => element('div', { class: 'woocommerce-message' }, 'اعلان'));
scroll.jquery.scroll_to_notices(scroll.jquery(scroll.before)); check(scroll.scrolls() === 0, 'Woo scroll skips transferred source');
scroll.jquery.scroll_to_notices(scroll.jquery(scroll.element('div', {}, 'قسمت دیگر'))); check(scroll.scrolls() === 1, 'Woo scroll keeps unrelated targets native');
const dynamic = scroll.element('ul', { class: 'woocommerce-error' }, 'خطای پرداخت');
scroll.jquery.scroll_to_notices(scroll.jquery(dynamic)); check(scroll.scrolls() === 1 && dynamic.dataset.artnovoToastSource === '1', 'synchronous checkout scroll transfers a new error before native focus/scroll');
const block = fixture(({ element }) => { const node = element('div', { class: 'wc-block-components-notice-banner is-error' }); element('div', { class: 'wc-block-components-notice-banner__content' }, 'خطای بلوکی', node); element('button', {}, 'بستن اصلی', node); return node; });
check(block.toasts().length === 1 && !block.toasts()[0].textContent.includes('بستن اصلی'), 'block notice supports native dismiss button without cloning it');
const inline = fixture(({ element }) => {
	const form = element('form');
	const field = element('input', { id: 'pinova-identifier', 'aria-invalid': 'true', 'aria-describedby': 'pinova-identifier-error' }, '', form);
	const error = element('div', { id: 'pinova-identifier-error', 'pinova-text': 'forms.authenticate.inputs.identifier.errorMsg', role: 'alert' }, 'شماره درست نیست', form);
	return { form, field, error };
});
check(inline.toasts().length === 0 && !inline.before.error.dataset.artnovoToastSource, 'Pinova field validation stays inline instead of becoming a temporary notice');
inline.advance(8001);
check(inline.before.error.parentElement === inline.before.form && inline.before.error.textContent === 'شماره درست نیست'
	&& inline.before.error.getAttribute('aria-hidden') === null && !inline.before.error.dataset.artnovoToastSource, 'Pinova field error stays visible and accessible after eight seconds');
check(inline.before.field.getAttribute('aria-describedby') === inline.before.error.id && inline.before.field.getAttribute('aria-invalid') === 'true', 'native field/error relationship and invalid state remain intact');
inline.update(inline.before.error, 'شماره هنوز کامل نیست');
check(inline.before.error.getAttribute('pinova-text') === 'forms.authenticate.inputs.identifier.errorMsg'
	&& inline.before.error.getAttribute('role') === 'alert' && inline.toasts().length === 0, 'native Pinova binding and announcement semantics survive repeated validation');
inline.advance(8001);
check(inline.before.error.textContent === 'شماره هنوز کامل نیست' && inline.before.error.getAttribute('aria-hidden') === null, 'updated field validation has no toast expiration');
const close = block.toasts()[0].querySelector('button'); check(close.type === 'button' && close.getAttribute('aria-label') === 'بستن اعلان', 'dismiss has an explicit non-submit type and Persian label');
close.click(); check(block.toasts().length === 0, 'dismiss closes the toast immediately');
const css = fs.readFileSync(require('node:path').join(__dirname, '../modules/notices/notices.css'), 'utf8');
check(css.includes('prefers-reduced-motion: reduce') && css.includes('animation: none') && css.includes('min-height: 44px'), 'reduced-motion and minimum touch size styles exist');
check(css.includes('artnovo-toast-countdown 8s') && css.includes('background: #214354') && css.includes('border-radius: 23px 23px 23px 10px'), 'countdown CSS shares the eight-second lifetime and the contrasting mobile bubble');
console.log(`PASS ${checks} notice lifecycle, ownership, security and accessibility assertions`);
