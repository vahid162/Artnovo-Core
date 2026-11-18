/* Exercise received-page copy and receipt interactions without network or uploads. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.resolve(__dirname, '../../../modules/order-received/received.js'), 'utf8');
let checks = 0;
const check = (value, label) => { assert(value, label); checks++; };
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

(async () => {
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
	console.log(`PASS ${checks} received copy, manual fallback and receipt assertions`);
})().catch(error => { console.error(error); process.exitCode = 1; });
