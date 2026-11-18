/* Exercise received-page copy and receipt interactions without network or uploads. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.resolve(__dirname, '../../../modules/order-received/received.js'), 'utf8');
let checks = 0;
const check = (value, label) => { assert(value, label); checks++; };

function paymentTableFixture(hasControl) {
	const elements = [];
	const table = {
		querySelector: () => hasControl ? { required: true } : null,
		before(details) { this.insertedBefore = details; }
	};
	const root = { dataset: { artnovoNativeOrderPay: '' }, querySelector: () => table };
	vm.runInNewContext(source, {
		document: {
			querySelector: () => root,
			createElement(tag) {
				const element = { tag, append(...children) { this.children = children; } };
				elements.push(element);
				return element;
			}
		}
	});
	return { table, elements };
}

function fixture({ failure = false, value = 'https://example.invalid/ready-1\nhttps://example.invalid/ready-3' } = {}) {
	const copied = [];
	const status = { hidden: true, textContent: '' };
	const field = { value, focus() { this.focused = true; }, select() { this.selected = true; } };
	const fallback = { hidden: true, querySelector: selector => selector === 'textarea' ? field : null };
	const button = { hidden: true, parentElement: { querySelector: () => status }, addEventListener(name, callback) { if (name === 'click') this.click = callback; } };
	const root = {
		dataset: { state: 'partial', artnovoReceivedMulti: '1' },
		querySelector: selector => ({ '[data-artnovo-copy-ready]': button, '[data-artnovo-ready-fallback]': fallback })[selector] || null,
		querySelectorAll: () => []
	};
	vm.runInNewContext(source, {
		document: { querySelector: selector => selector === '.artnovo-received' ? root : null },
		navigator: { clipboard: { async writeText(text) { if (failure) throw new Error('Clipboard unavailable'); copied.push(text); } } }
	});
	return { root, button, fallback, field, status, copied };
}

function receiptFixture() {
	const revoked = [];
	const listeners = {};
	const input = {
		accept: 'image/jpeg,image/png', files: [], attributes: {},
		addEventListener(name, callback) { this[name] = callback; },
		setAttribute(name, value) { this.attributes[name] = value; },
		removeAttribute(name) { delete this.attributes[name]; }
	};
	const submit = { disabled: false };
	const image = {
		hidden: true,
		addEventListener(name, callback) { this[name] = callback; },
		removeAttribute(name) { delete this[name]; }
	};
	const error = { hidden: true };
	const filename = { textContent: 'انتخاب تصویر رسید' };
	const prompt = { hidden: true, textContent: 'تغییر تصویر' };
	const hint = { textContent: 'راهنمای تصویر' };
	const status = { hidden: true };
	const controls = {
		'input[type="file"]': input,
		'button[type="submit"], input[type="submit"]': submit,
		'[data-artnovo-receipt-image]': image,
		'[data-artnovo-receipt-error]': error,
		'[data-artnovo-receipt-filename]': filename,
		'[data-artnovo-receipt-prompt]': prompt,
		'[data-artnovo-receipt-hint]': hint,
		'[data-artnovo-upload-status]': status
	};
	const form = {
		dataset: {},
		elements: { namedItem: () => ({ value: '2097152' }) },
		hasAttribute: name => name === 'data-artnovo-receipt-preview',
		querySelector: selector => controls[selector] || null,
		setAttribute() {}, removeAttribute() {},
		addEventListener(name, callback) { this[name] = callback; }
	};
	const root = {
		querySelector: () => null,
		querySelectorAll: selector => selector === '[data-artnovo-receipt-form]' ? [form] : []
	};
	vm.runInNewContext(source, {
		document: { querySelector: () => root },
		window: { addEventListener(name, callback) { listeners[name] = callback; } },
		URL: {
			createObjectURL: file => `blob:${file.name}`,
			revokeObjectURL: url => revoked.push(url)
		}
	});
	const select = files => { input.files = files; input.change(); };
	const attemptSubmit = () => {
		let allowed = true;
		form.submit({ preventDefault() { allowed = false; } });
		return allowed;
	};
	return { select, attemptSubmit, submit, image, filename, prompt, hint, error, status, input, revoked, listeners };
}

function amountFixture(failure) {
	const copied = [];
	const fallback = { hidden: true };
	const status = { hidden: true };
	const target = {
		value: '540000.25',
		closest: () => fallback,
		focus() { this.focused = true; },
		select() { this.selected = true; }
	};
	const button = {
		hidden: true,
		dataset: { artnovoCopy: 'amount' },
		parentElement: { querySelector: () => status },
		addEventListener(name, callback) { this[name] = callback; }
	};
	const root = {
		querySelector: () => null,
		querySelectorAll: selector => selector === '[data-artnovo-copy]' ? [button] : [],
		contains: value => value === target
	};
	vm.runInNewContext(source, {
		document: { querySelector: () => root, getElementById: id => id === 'amount' ? target : null },
		navigator: { clipboard: { async writeText(value) { if (failure) throw new Error(); copied.push(value); } } }
	});
	return { button, copied, fallback, status, target };
}

function paymentFixture(responses) {
	let timer;
	const requests = [];
	const navigations = [];
	const expired = { hidden: true };
	let now = 0;
	const root = {
		dataset: {
			state: 'partial',
			artnovoPaymentStatusUrl: '/?wc-ajax=artnovo_received_payment_status',
			artnovoPaymentTrackUrl: '/checkout/order-received/1/?artnovo_status=1',
			artnovoPaymentStatusOrder: '1',
			artnovoPaymentStatusKey: 'fixture-order-key',
			artnovoPaymentStatusNonce: 'fixture-nonce',
			artnovoPaymentReadyCount: '1',
		},
		querySelector: selector => selector === '[data-artnovo-payment-poll-expired]' ? expired : null,
		querySelectorAll: () => [],
	};
	const document = {
		hidden: false,
		querySelector: () => root,
		addEventListener(name, callback) { if (name === 'visibilitychange') this.visibilityChange = callback; },
	};
	vm.runInNewContext(source, {
		document,
		window: { location: { replace: url => navigations.push(url) } },
		performance: { now: () => now },
		URLSearchParams,
		AbortSignal,
		setTimeout: (callback, delay) => timer = { callback, delay },
		clearTimeout: () => { timer = null; },
		async fetch(url, options) {
			requests.push({ url, options });
			const response = responses.shift();
			if (response instanceof Error) throw response;
			return response;
		},
	});
	return {
		requests, navigations, expired, tracking: root.dataset.artnovoPaymentTrackUrl,
		get timer() { return timer; },
		visibility(hidden) { document.hidden = hidden; document.visibilityChange(); },
		async next(after) {
			const { callback, delay } = timer;
			timer = null;
			now += after ?? delay;
			await callback();
		},
	};
}

(async () => {
	const paymentTable = paymentTableFixture(false);
	const [details, summary] = paymentTable.elements;
	check(details === paymentTable.table.insertedBefore && details.tag === 'details' && summary.tag === 'summary'
		&& details.children[0] === summary && details.children[1] === paymentTable.table, 'payment disclosure reuses the native table inside the form');
	const extendedPayment = paymentTableFixture(true);
	check(extendedPayment.elements.length === 0 && !extendedPayment.table.insertedBefore, 'tables with additional payment controls stay open');

	const ready = fixture();
	check(!ready.button.hidden, 'ready-link batch enables its primary copy action');
	await ready.button.click();
	check(ready.copied.length === 1 && ready.copied[0] === ready.field.value, 'one clipboard operation contains exactly the ready links supplied by the server');
	check(ready.fallback.hidden && !ready.field.selected, 'successful copy leaves manual fallback closed');
	check(!ready.status.hidden && ready.status.textContent === 'لینک اکانت‌های آماده کپی شد.', 'successful copy reports the real result');
	check(ready.root.dataset.state === 'partial', 'copying does not mark unfinished units ready or change payment state');
	const fault = fixture({ failure: true });
	await fault.button.click();
	check(fault.copied.length === 0 && !fault.fallback.hidden && fault.field.focused && fault.field.selected, 'clipboard failure reveals and selects the original ready-link textarea');
	check(fault.status.textContent.includes('انتخاب شدند') && !fault.status.textContent.includes('کپی شد'), 'manual fallback does not claim a successful clipboard write');
	check(fault.root.dataset.state === 'partial', 'clipboard failure cannot advance the order state');
	const empty = fixture({ value: '' });
	check(empty.button.hidden && !empty.button.click && empty.copied.length === 0, 'no copy action is enabled without authorized ready links');

	const amount = amountFixture(false);
	await amount.button.click();
	check(!amount.button.hidden && amount.copied[0] === '540000.25' && amount.fallback.hidden, 'amount copy uses the exact server value without grouping or rounding');
	const amountFault = amountFixture(true);
	await amountFault.button.click();
	check(!amountFault.fallback.hidden && amountFault.target.focused && amountFault.target.selected && !amountFault.copied.length,
		'clipboard failure shows and selects the original exact value for manual copy');

	const receipt = receiptFixture();
	check(receipt.submit.disabled && !receipt.attemptSubmit() && receipt.image.hidden, 'the enhanced form requires a selected image');
	const png = { type: 'image/png', size: 20, name: 'receipt.png' };
	receipt.select([png]);
	check(!receipt.submit.disabled && !receipt.image.hidden && receipt.image.src === 'blob:receipt.png'
		&& receipt.filename.textContent === png.name && !receipt.prompt.hidden, 'valid selection previews locally and enables the existing submit');
	receipt.image.error();
	check(receipt.image.hidden && receipt.hint.textContent.includes('در دسترس نیست') && !receipt.submit.disabled,
		'preview decoding failure explains the missing preview while leaving image validation to the server');
	receipt.select([{ ...png, type: 'text/plain' }]);
	check(receipt.submit.disabled && !receipt.error.hidden && receipt.input.attributes['aria-invalid'] === 'true'
		&& receipt.image.hidden && receipt.revoked[0] === 'blob:receipt.png' && receipt.prompt.hidden
		&& receipt.filename.textContent === 'انتخاب تصویر رسید' && receipt.hint.textContent === 'راهنمای تصویر',
		'replacing the image with an invalid type removes its preview and filename and prevents submit');
	receipt.select([{ ...png, size: 2097153 }]);
	check(receipt.submit.disabled && receipt.error.textContent.includes('حجم'), 'the preview respects the native form byte cap');
	receipt.select([png]);
	check(receipt.error.hidden && !receipt.input.attributes['aria-invalid'] && receipt.attemptSubmit() && receipt.submit.disabled
		&& !receipt.status.hidden && !receipt.attemptSubmit(), 'valid input can submit natively once and double submission is blocked');
	receipt.listeners.pagehide();
	receipt.listeners.pageshow();
	check(!receipt.submit.disabled && receipt.status.hidden && !receipt.image.hidden && receipt.revoked.length === 2,
		'browser return restores the selected image and leaves the form retryable');
	receipt.select([]);
	check(receipt.submit.disabled && receipt.image.hidden && receipt.prompt.hidden && receipt.error.hidden, 'clearing selection returns to the initial state');
	const ok = (state, ready_count) => ({ ok: true, status: 200, json: async () => ({ success: true, data: { state, ready_count } }) });
	const payment = paymentFixture([ok('partial', 1), new Error('temporary failure'), ok('partial', 2)]);
	await payment.next();
	const { url, options } = payment.requests[0];
	check(url.endsWith('wc-ajax=artnovo_received_payment_status') && options.method === 'POST'
		&& options.credentials === 'same-origin' && options.mode === 'same-origin' && options.cache === 'no-store'
		&& options.body.get('order') === '1' && options.body.get('key') === 'fixture-order-key'
		&& options.body.get('_ajax_nonce') === 'fixture-nonce' && !url.includes('fixture-'),
		'status reads use the private same-origin projection and send proof of access in the body');
	check(!payment.navigations.length && payment.timer.delay === 5000, 'unchanged status backs off without refreshing the page');
	payment.visibility(true);
	check(!payment.timer && payment.requests.length === 1, 'hiding the tab cancels the next read');
	payment.visibility(false);
	await payment.next();
	check(!payment.navigations.length && payment.timer.delay === 7000, 'temporary failures preserve current state and back off');
	await payment.next();
	check(payment.navigations[0] === payment.tracking && !payment.timer, 'another ready unit refreshes only the protected tracking view');

	const unauthorized = paymentFixture([{ ok: false, status: 403 }]);
	await unauthorized.next();
	check(!unauthorized.navigations.length && !unauthorized.timer, 'access denial stops status reads without exposing private state');
	const expired = paymentFixture([]);
	await expired.next(16 * 60 * 1000);
	check(!expired.requests.length && !expired.navigations.length && !expired.timer && !expired.expired.hidden,
		'automatic status monitoring is bounded and shows the manual fallback afterward');
	console.log(`PASS ${checks} received payment, copy and receipt assertions`);
})().catch(error => { console.error(error); process.exitCode = 1; });
