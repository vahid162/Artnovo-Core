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
			constructor(value = '') { this.value = value; this.dataset = {}; this.listeners = {}; this.attributes = {}; this.disabled = false; this.hidden = false; }
			addEventListener(name, callback) { this.listeners[name] = callback; }
			setAttribute(name, value) { this.attributes[name] = value; }
			removeAttribute(name) { delete this.attributes[name]; }
			focus() { this.focused = true; }
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
		const document = { querySelectorAll: () => [form], dispatchEvent: (event) => busy.push(event.detail.pending) };
		const requests = [], responses = [], redirects = [], busy = [], windowEvents = {};
		const window = {
			location: { href: 'https://example.test/?artnovo_plan=26#artnovo-order', origin: 'https://example.test', replace: (url) => redirects.push(url) },
			setTimeout: () => 1, clearTimeout: () => {}, addEventListener: (name, callback) => { windowEvents[name] = callback; }
		};
		class FormData {
			constructor() { this.values = new Map(Object.entries(controls).filter(([, field]) => !field.disabled).map(([name, field]) => [name, field.value])); }
			set(name, value) { this.values.set(name, value); }
			delete(name) { this.values.delete(name); }
			entries() { return this.values.entries(); }
		}
		const fetch = async (url, request) => {
			requests.push({ url, body: Object.fromEntries(request.body.entries()), headers: request.headers });
			if (!responses.length) throw new Error('Unexpected request');
			const response = responses.shift();
			return response instanceof Promise ? response : { json: async () => response };
		};
		class CustomEvent { constructor(name, detail) { this.name = name; Object.assign(this, detail); } }
		new Function('document', 'window', 'fetch', 'FormData', 'CustomEvent', source)(document, window, fetch, FormData, CustomEvent);
		const submit = async () => { form.listeners.submit({ preventDefault() {} }); await settle(); };
		return { form, controls, nodes, requests, responses, redirects, busy, submit, windowEvents };
	}

	const email = fixture({ identifier: 'new@example.test' });
	email.responses.push({ success: true, data: { expires: Math.floor(Date.now() / 1000) + 120, selection_revision: newRevision, message: 'sent' } });
	await email.submit();
	check(email.requests.length === 1 && email.requests[0].url === 'https://example.test/', 'email uses the existing Core endpoint only');
	check(email.requests[0].body.artnovo_login_method === 'email' && email.requests[0].body.artnovo_otp_action === 'send' && email.requests[0].body.artnovo_email === 'new@example.test', 'email signup is sent to the existing Core OTP engine');
	check(email.controls.artnovo_auth_proof.maxLength === 6 && email.controls.artnovo_auth_identifier.readOnly, 'Core email code owns its proof length and locks identifier');
	email.controls.artnovo_auth_proof.value = '۱۲۳۴۵۶';
	email.responses.push({ success: true, data: { redirect: 'https://example.test/?artnovo_plan=26' } });
	await email.submit();
	check(email.requests[1].body.artnovo_otp_code === '123456' && email.requests[1].body.artnovo_selection_revision === newRevision, 'email proof normalizes digits and posts the latest selection revision');
	check(email.redirects[0] === 'https://example.test/my-account/?artnovo_resume=1', 'authenticated continuation fetches fresh nonces through canonical resume');

	const mobile = fixture({ identifier: '۰۹۱۲۰۰۰۰۰۰۰' });
	mobile.responses.push(prepared, { success: true, data: { login_method: 'otp', jwt: 'test-native-challenge', ttl: 120 } });
	await mobile.submit();
	check(mobile.requests.length === 2 && mobile.requests[0].body.artnovo_login_method === 'pinova' && mobile.requests[0].body.artnovo_wizard_nonce === 'existing-wizard-nonce', 'native authentication first preserves the nonce-protected selection');
	check(mobile.requests[1].body.identifier === '09120000000' && mobile.requests[1].body.force_otp === '1' && mobile.requests[1].headers['X-WP-Nonce'] === 'native-rest-nonce', 'mobile uses native Pinova forced OTP with a REST nonce');
	check(!mobile.redirects.length && mobile.controls.artnovo_auth_proof.maxLength === 4, 'prepare does not redirect to the full native login page');
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
	username.controls.artnovo_auth_proof.value = 'synthetic-secret';
	username.responses.push({ success: false, message: 'provider rejection' });
	await username.submit();
	check(username.requests[2].body.identifier === 'returning_user' && username.requests[2].body.password === 'synthetic-secret' && !('jwt' in username.requests[2].body), 'native password verification uses identifier and password, without JWT');
	check(username.nodes.message.textContent === 'provider rejection' && !username.redirects.length && username.controls.artnovo_auth_proof.value === '' && !username.nodes.submit.disabled, 'native failure is inline, clears proof and allows a retry');
	username.nodes.edit.listeners.click();
	check(username.controls.artnovo_auth_proof.disabled && !username.controls.artnovo_auth_identifier.readOnly, 'editing discards proof state without changing the selected plan');

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
	return assertions;
}

if (typeof module !== 'undefined' && require.main === module) {
	const fs = require('node:fs');
	const assert = require('node:assert/strict');
	const source = fs.readFileSync(require('node:path').join(__dirname, '../modules/auth/compact-purchase.js'), 'utf8');
	testCompactAuth(source, assert).then((count) => console.log(`${count} compact authentication state assertions passed`)).catch((error) => { console.error(error); process.exitCode = 1; });
}
