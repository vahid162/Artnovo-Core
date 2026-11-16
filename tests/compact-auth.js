/* Execute the production adapter with a small DOM and captured HTTP; no browser, credentials or network. */
async function testCompactAuth(source, assert) {
	let assertions = 0;
	const check = (condition, message) => { assertions++; assert(condition, message); };
	const revision = 'a'.repeat(64);
	const newRevision = 'b'.repeat(64);
	const prepared = { success: true, data: { redirect: 'https://example.test/login/', selection_revision: newRevision } };
	const settle = async () => { for (let index = 0; index < 80; index++) await Promise.resolve(); };
	function fixture(options = {}) {
		class Control {
			constructor(value = '') { this.value = value; this.dataset = {}; this.listeners = {}; this.attributes = {}; this.disabled = false; this.hidden = false; this.childNodes = []; this.text = ''; }
			addEventListener(name, callback) { this.listeners[name] = callback; }
			setAttribute(name, value) { this.attributes[name] = value; }
			removeAttribute(name) { delete this.attributes[name]; }
			focus() { this.focused = true; }
			get textContent() { return this.childNodes.length ? this.childNodes.map((node) => node.textContent).join('') : this.text; }
			set textContent(value) { this.childNodes = []; this.text = String(value); }
			replaceChildren(...nodes) { this.childNodes = nodes; this.text = ''; }
			set innerHTML(value) { throw new Error('HTML must never be inserted into the live DOM'); }
		}
		const names = ['artnovo_auth_identifier', 'artnovo_auth_proof', 'artnovo_product', 'artnovo_quantity', 'artnovo_selection_revision', 'artnovo_wizard_nonce', 'artnovo_login_method'];
		const controls = Object.fromEntries(names.map((name) => [name, new Control()]));
		Object.assign(controls.artnovo_product, { value: '26' });
		Object.assign(controls.artnovo_quantity, { value: '1' });
		Object.assign(controls.artnovo_selection_revision, { value: revision });
		Object.assign(controls.artnovo_wizard_nonce, { value: 'existing-wizard-nonce' });
		Object.assign(controls.artnovo_login_method, { value: 'pinova' });
		Object.assign(controls.artnovo_auth_identifier, { value: options.identifier || '' });
		Object.assign(controls.artnovo_auth_proof, { disabled: true });
		const nodes = Object.fromEntries(['proof', 'proof-label', 'message', 'edit', 'resend', 'submit'].map((name) => [name, new Control()]));
		const config = {
			authenticateUrl: 'https://example.test/wp-json/pinova/user/authenticate',
			otpUrl: 'https://example.test/wp-json/pinova/user/login/otp',
			passwordUrl: 'https://example.test/wp-json/pinova/user/login/password',
			restNonce: 'native-rest-nonce', resumeUrl: 'https://example.test/my-account/?artnovo_resume=1',
			refreshUrl: 'https://example.test/?artnovo_plan=26&artnovo_checkout=1#artnovo-order',
			emailOtp: options.emailOtp !== false, emailCodeLength: 6, nativeCodeLength: 4
		};
		const form = new Control();
		form.action = 'https://example.test/';
		form.dataset = { artnovoAuthConfig: JSON.stringify(config), artnovoAuthAvailable: '1', artnovoAuthExpires: String(options.expires || 0) };
		form.elements = { namedItem: (name) => controls[name] };
		form.reportValidity = () => true;
		form.querySelector = (selector) => selector === 'button[type="submit"]' ? nodes.submit : nodes[selector.slice('[data-artnovo-auth-'.length, -1)];
		form.querySelectorAll = () => [controls.artnovo_selection_revision];
		form.closest = () => form;
		const requests = [], responses = [], redirects = [], busy = [], windowEvents = {}, templates = [], timers = new Map();
		const document = {
			querySelectorAll: () => [form], dispatchEvent: (event) => busy.push(event.detail.pending),
			createTextNode: (value) => ({ textContent: String(value), nodeType: 3 }),
			createElement: (tag) => {
				if (tag === 'bdi') return Object.assign(new Control(), { tagName: 'BDI' });
				if (tag !== 'template') throw new Error('Only inert templates may parse provider messages');
				let markup = '';
				return {
					set innerHTML(value) { markup = value; templates.push(value); },
					content: {
						querySelectorAll: () => [...markup.matchAll(/<(script|style)\b[^>]*>[\s\S]*?<\/\1>/gi)].map(([match]) => ({ remove: () => { markup = markup.replace(match, ''); } })),
						get textContent() { return markup.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, '\u00a0').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>'); }
					}
				};
			}
		};
		const window = {
			location: { href: 'https://example.test/?artnovo_plan=26#artnovo-order', origin: 'https://example.test', replace: (url) => redirects.push(url) },
			setTimeout: (callback, delay) => { const id = ++window.nextTimer; timers.set(id, { callback, delay }); return id; },
			clearTimeout: (id) => timers.delete(id), nextTimer: 0,
			addEventListener: (name, callback) => { windowEvents[name] = callback; }
		};
		class FormData {
			constructor() { this.values = new Map(Object.entries(controls).filter(([, field]) => !field.disabled).map(([name, field]) => [name, field.value])); }
			set(name, value) { this.values.set(name, value); }
			delete(name) { this.values.delete(name); }
			entries() { return this.values.entries(); }
		}
		const fetch = async (url, request) => {
			requests.push({ url, body: Object.fromEntries(request.body.entries()), headers: request.headers, signal: request.signal });
			if (!responses.length) throw new Error('Unexpected request');
			const response = responses.shift();
			let abort;
			try {
				return await Promise.race([
					response instanceof Promise ? response : { json: async () => response },
					new Promise((resolve, reject) => {
						abort = () => { const error = new Error('Request aborted'); error.name = 'AbortError'; reject(error); };
						request.signal?.addEventListener('abort', abort, { once: true });
					})
				]);
			} finally { request.signal?.removeEventListener('abort', abort); }
		};
		class CustomEvent { constructor(name, detail) { this.name = name; Object.assign(this, detail); } }
		new Function('document', 'window', 'fetch', 'FormData', 'CustomEvent', source)(document, window, fetch, FormData, CustomEvent);
		const submit = async () => { form.listeners.submit({ preventDefault() {} }); await settle(); };
		const expire = async () => {
			for (const [id, item] of timers) if (item.delay === 25000) { timers.delete(id); item.callback(); }
			await settle();
		};
		return { form, controls, nodes, requests, responses, redirects, busy, submit, windowEvents, expire, templates };
	}

	const email = fixture({ identifier: 'new@example.test' });
	email.responses.push({ success: true, data: { expires: Math.floor(Date.now() / 1000) + 120, selection_revision: newRevision, message: 'sent' } });
	await email.submit();
	check(email.requests.length === 1 && email.requests[0].url === 'https://example.test/', 'email uses the existing Core endpoint only');
	check(email.requests[0].body.artnovo_login_method === 'email' && email.requests[0].body.artnovo_otp_action === 'send' && email.requests[0].body.artnovo_email === 'new@example.test', 'email signup is sent to the existing Core OTP engine');
	check(email.controls.artnovo_auth_proof.maxLength === 6 && email.controls.artnovo_auth_identifier.readOnly, 'Core email code owns its proof length and locks identifier');
	check(email.nodes.edit.textContent === 'ویرایش ایمیل', 'email proof names the specific editable information');
	email.controls.artnovo_auth_proof.value = '۱۲۳۴۵۶';
	email.responses.push({ success: true, data: { redirect: 'https://example.test/?artnovo_plan=26' } });
	await email.submit();
	check(email.requests[1].body.artnovo_otp_code === '123456' && email.requests[1].body.artnovo_selection_revision === newRevision, 'email proof normalizes digits and posts the latest selection revision');
	check(email.redirects[0] === 'https://example.test/my-account/?artnovo_resume=1', 'authenticated continuation fetches fresh nonces through canonical resume');

	const mobile = fixture({ identifier: '۰۹۱۲۰۰۰۰۰۰۰' });
	mobile.responses.push(prepared, { success: true, data: { login_method: 'otp', jwt: 'test-native-challenge', ttl: 120 } });
	await mobile.submit();
	check(mobile.requests.length === 2 && mobile.requests[0].body.artnovo_login_method === 'pinova' && mobile.requests[0].body.artnovo_wizard_nonce === 'existing-wizard-nonce', 'native authentication first preserves the nonce-protected selection');
	check(mobile.requests[1].body.identifier === '09120000000' && !('force_otp' in mobile.requests[1].body) && mobile.requests[1].headers['X-WP-Nonce'] === 'native-rest-nonce', 'mobile normalizes local digits without overriding native identifier classification or its REST nonce');
	check(!mobile.redirects.length && mobile.controls.artnovo_auth_proof.maxLength === 4, 'prepare does not redirect to the full native login page');
	check(mobile.nodes.edit.textContent === 'ویرایش شماره موبایل', 'mobile proof names the phone editing action');
	mobile.controls.artnovo_quantity.value = '2';
	mobile.controls.artnovo_auth_proof.value = '۱۲۳۴';
	mobile.responses.push(prepared, { success: true, data: {} });
	await mobile.submit();
	check(mobile.requests[2].body.artnovo_quantity === '2' && mobile.requests[3].body.jwt === 'test-native-challenge' && mobile.requests[3].body.code === '1234', 'a changed quantity is prepared before native proof while JWT stays in the verification body');
	check(mobile.busy.join(',') === 'true,false,true', 'selection stays locked through successful native verification and redirect');

	const username = fixture({ identifier: 'returning_user' });
	username.responses.push(prepared, { success: true, data: { login_method: 'password', ttl: 120 } });
	await username.submit();
	check(username.requests[1].body.force_otp === undefined && username.controls.artnovo_auth_proof.type === 'password' && username.nodes.resend.hidden, 'username honors native password authentication');
	check(username.nodes.edit.textContent === 'ویرایش نام کاربری', 'username proof names the username editing action');
	username.controls.artnovo_auth_proof.value = 'synthetic-secret';
	username.responses.push({ success: false, message: 'provider rejection' });
	await username.submit();
	check(username.requests[2].body.identifier === 'returning_user' && username.requests[2].body.password === 'synthetic-secret' && !('jwt' in username.requests[2].body), 'native password verification uses identifier and password, without JWT');
	check(username.nodes.message.textContent === 'provider rejection' && !username.redirects.length && username.controls.artnovo_auth_proof.value === '' && !username.nodes.submit.disabled, 'native failure is inline, clears proof and allows a retry');
	username.nodes.edit.listeners.click();
	check(username.controls.artnovo_auth_proof.disabled && !username.controls.artnovo_auth_identifier.readOnly, 'editing discards proof state without changing the selected plan');

	const numericUsername = fixture({ identifier: '1234' });
	numericUsername.responses.push(prepared, { success: true, data: { login_method: 'password' } });
	await numericUsername.submit();
	check(numericUsername.requests[1].body.identifier === '1234' && !('force_otp' in numericUsername.requests[1].body) && numericUsername.controls.artnovo_auth_proof.type === 'password', 'a numeric username remains eligible for native password login');
	for (const [value, expected] of [['09120000000', '09120000000'], ['9120000000', '9120000000'], ['+989120000000', '+989120000000'], ['989120000000', '989120000000'], ['00989120000000', '00989120000000'], ['۰۹۱۲۰۰۰۰۰۰۰', '09120000000'], ['٠٩١٢٠٠٠٠٠٠٠', '09120000000'], ['+98 (912) 000-0000', '+98 (912) 000-0000']]) {
		const formatted = fixture({ identifier: value });
		formatted.responses.push(prepared, { success: true, data: { login_method: 'otp', jwt: 'test-native-challenge', ttl: 120 } });
		await formatted.submit();
		check(formatted.requests[1]?.body.identifier === expected, `Iranian mobile format ${value} reaches the native classifier without speculative coercion`);
	}
	const shortInternational = fixture({ identifier: '+98912000000' });
	shortInternational.responses.push(prepared, { success: false, message: 'تلفن همراه معتبر نمی‌باشد.' });
	await shortInternational.submit();
	check(shortInternational.requests[1]?.body.identifier === '+98912000000' && shortInternational.nodes.message.textContent.includes('۱۱ رقم') && shortInternational.controls.artnovo_auth_identifier.attributes['aria-invalid'] === 'true' && !shortInternational.nodes.submit.disabled, 'a native rejection of an explicit Iranian prefix gives a correctable format error');
	for (const value of ['0912000000', '912000000']) {
		const malformed = fixture({ identifier: value });
		malformed.responses.push(prepared, { success: false, message: 'برای دریافت کد تایید، از تلفن همراه یا ایمیل استفاده کنید.' });
		await malformed.submit();
		check(malformed.requests.length === 2 && malformed.nodes.message.textContent.includes('۱۱ رقم') && malformed.nodes.message.textContent.includes('09123456789') && malformed.form.dataset.artnovoAuthPending === '0', 'native rejection of phone-shaped input explains the Iranian length and format');
		const numericAccount = fixture({ identifier: value });
		numericAccount.responses.push(prepared, { success: true, data: { login_method: 'password' } });
		await numericAccount.submit();
		check(numericAccount.requests[1].body.identifier === value && numericAccount.controls.artnovo_auth_proof.type === 'password' && numericAccount.nodes.edit.textContent === 'ویرایش موبایل یا نام کاربری', 'an existing phone-shaped numeric username still follows native password proof');
	}
	for (const value of ['9999999999', '09999999999']) {
		const numericAccount = fixture({ identifier: value });
		numericAccount.responses.push(prepared, { success: true, data: { login_method: 'password' } });
		await numericAccount.submit();
		check(numericAccount.requests[1]?.body.identifier === value && !('force_otp' in numericAccount.requests[1].body) && numericAccount.controls.artnovo_auth_proof.type === 'password' && numericAccount.nodes.edit.textContent === 'ویرایش موبایل یا نام کاربری', 'a phone-length numeric username remains unchanged and native password authentication owns the challenge');
		numericAccount.controls.artnovo_auth_proof.value = 'synthetic-secret';
		numericAccount.responses.push({ success: true, data: {} });
		await numericAccount.submit();
		check(numericAccount.requests[2]?.body.identifier === value && numericAccount.redirects.length === 1, 'phone-length numeric username password verification keeps its original account identifier');
	}
	const blockedPhone = fixture({ identifier: '0912000000' });
	blockedPhone.responses.push(prepared, { success: false, message: 'ایمیل یا تلفن همراه مسدود شده است.' });
	await blockedPhone.submit();
	check(blockedPhone.nodes.message.textContent === 'ایمیل یا تلفن همراه مسدود شده است.', 'format guidance does not conceal a native account block');

	const providerMarkup = fixture({ identifier: '09120000000' });
	providerMarkup.responses.push(prepared, { success: true, message: 'کد به <span dir="ltr">+989120000000</span> ارسال شد.<img src="https://malicious.test/pixel" onerror="bad()"><script>bad()</script><style>bad{}</style>', data: { login_method: 'otp', jwt: 'test-native-challenge', ttl: 120 } });
	await providerMarkup.submit();
	check(providerMarkup.nodes.message.textContent === 'کد به +989120000000 ارسال شد.' && providerMarkup.templates.some((html) => html.includes('<span')), 'provider markup is parsed in an inert template and only its message text reaches the live status');
	const isolatedPhone = providerMarkup.nodes.message.childNodes.find((node) => node.tagName === 'BDI');
	check(isolatedPhone?.dir === 'ltr' && isolatedPhone.textContent === '+989120000000', 'the phone and international prefix are isolated in their logical left-to-right order');
	check(!providerMarkup.nodes.message.textContent.includes('bad') && !providerMarkup.nodes.message.textContent.includes('<'), 'scripts, style contents and active markup never enter the displayed provider message');

	const stale = fixture({ identifier: 'returning_user' });
	stale.responses.push({ success: false, data: { refresh_required: true, redirect: 'https://malicious.test/steal' } });
	await stale.submit();
	check(stale.requests.length === 1 && stale.redirects.length === 1 && new URL(stale.redirects[0]).origin === 'https://example.test' && new URL(stale.redirects[0]).searchParams.has('artnovo_refresh'), 'stale nonce uses a safe fresh GET and never reaches an auth provider');

	const invalidPrepare = fixture({ identifier: 'returning_user' });
	invalidPrepare.responses.push({ success: true, data: { message: 'not a preparation' } });
	await invalidPrepare.submit();
	check(invalidPrepare.requests.length === 1 && !invalidPrepare.redirects.length && invalidPrepare.nodes.message.dataset.error === '1', 'an incomplete preparation cannot initiate native authentication');

	const restored = fixture({ identifier: 'returning@example.test', expires: Date.now() / 1000 + 90 });
	check(restored.requests.length === 0 && !restored.controls.artnovo_auth_proof.disabled && !restored.nodes.resend.hidden, 'an existing Core email challenge resumes without sending another email');
	restored.windowEvents.pageshow({ persisted: true });
	check(restored.redirects.length === 1 && new URL(restored.redirects[0]).searchParams.has('artnovo_refresh'), 'browser Back from cache refreshes nonce and authentication state');

	const pending = fixture({ identifier: 'returning_user' });
	let release;
	pending.responses.push(new Promise((resolve) => { release = resolve; }));
	await pending.submit();
	await pending.submit();
	pending.nodes.edit.listeners.click();
	check(pending.requests.length === 1 && pending.form.dataset.artnovoAuthPending === '1' && pending.nodes.submit.disabled && pending.controls.artnovo_auth_identifier.readOnly, 'duplicate submits and edits are blocked until preparation finishes');
	pending.responses.push({ success: false, message: 'provider unavailable' });
	release({ json: async () => prepared });
	await settle();
	check(pending.form.dataset.artnovoAuthPending === '0' && pending.nodes.message.textContent === 'provider unavailable', 'a provider failure releases the interface while retaining the selected purchase');

	const coreTimeout = fixture({ identifier: 'new@example.test' });
	coreTimeout.responses.push(new Promise(() => {}));
	await coreTimeout.submit();
	await coreTimeout.expire();
	check(coreTimeout.requests.length === 1 && coreTimeout.requests[0].signal?.aborted && coreTimeout.redirects.length === 1 && new URL(coreTimeout.redirects[0]).searchParams.has('artnovo_refresh'), 'Core timeout aborts the pending POST and recovers with one safe GET, without replay');
	await coreTimeout.submit();
	check(coreTimeout.requests.length === 1, 'a timed-out Core request is never resubmitted while the fresh GET starts');

	const nativeTimeout = fixture({ identifier: 'returning_user' });
	nativeTimeout.responses.push(prepared, new Promise(() => {}));
	await nativeTimeout.submit();
	await nativeTimeout.expire();
	check(nativeTimeout.requests.length === 2 && nativeTimeout.requests[1].signal?.aborted && nativeTimeout.redirects.length === 1, 'native authentication shares the same abort deadline and fresh GET recovery');

	const brokenNetwork = fixture({ identifier: 'returning_user' });
	brokenNetwork.responses.push(Promise.reject(new TypeError('Network unavailable')));
	await brokenNetwork.submit();
	check(brokenNetwork.form.dataset.artnovoAuthPending === '0' && !brokenNetwork.nodes.submit.disabled && !brokenNetwork.redirects.length, 'a rejected network request releases pending state and leaves an explicit retry available');
	return assertions;
}

if (typeof module !== 'undefined' && require.main === module) {
	const fs = require('node:fs');
	const assert = require('node:assert/strict');
	const source = fs.readFileSync(require('node:path').join(__dirname, '../modules/auth/compact-purchase.js'), 'utf8');
	testCompactAuth(source, assert).then((count) => console.log(`${count} compact authentication state assertions passed`)).catch((error) => { console.error(error); process.exitCode = 1; });
}
