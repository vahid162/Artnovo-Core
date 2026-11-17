/* Exercise production bulk-copy behavior with authorized ready-link fixtures; no network/browser. */
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
	console.log(`PASS ${checks} received bulk-copy and manual-fallback assertions`);
})().catch(error => { console.error(error); process.exitCode = 1; });
