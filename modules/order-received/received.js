(() => {
	const root = document.querySelector('.artnovo-received');
	if (!root) return;

	initServiceLinks();
	initBankCopy();
	initReadyCopy();

	function initServiceLinks() {
		for (const link of root.querySelectorAll('.wpvpn-service a[rel~="noreferrer"]')) {
			if (link.closest('.wpvpn-subscription-link')) continue;
			let destination;
			try {
				destination = new URL(link.href);
			} catch {
				continue;
			}
			if (destination.protocol !== 'https:') continue;
			const controls = document.createElement('div');
			controls.className = 'wpvpn-subscription-link';
			controls.dataset.artnovoServiceTools = '';
			controls.dataset.copied = 'لینک اتصال کپی شد.';
			controls.dataset.selected = 'لینک انتخاب شد. آن را با دستگاه خود کپی کنید.';
			const existing = link.parentElement.tagName === 'P' ? link.parentElement : link;
			existing.insertAdjacentElement('afterend', controls);
			const details = document.createElement('details');
			const summary = document.createElement('summary');
			summary.textContent = 'مشاهدهٔ لینک اتصال';
			const reference = existing.previousElementSibling?.previousElementSibling;
			details.append(summary, existing);
			if (reference?.tagName === 'P' && reference.querySelector('code')) details.append(reference);
			const field = document.createElement('p');
			const label = document.createElement('label');
			label.textContent = 'لینک خصوصی اشتراک';
			const input = document.createElement('input');
			input.type = 'url';
			input.dir = 'ltr';
			input.readOnly = true;
			input.value = destination.href;
			label.append(input);
			field.append(label);
			const actions = document.createElement('p');
			const copy = document.createElement('button');
			copy.type = 'button';
			copy.className = root.dataset.artnovoReceivedMulti === '1' ? 'button artnovo-account-button-secondary' : 'button';
			if (root.dataset.artnovoReceivedMulti !== '1') copy.dataset.artnovoReceivedPrimary = '';
			copy.hidden = true;
			copy.textContent = 'کپی لینک اتصال';
			const status = document.createElement('span');
			status.setAttribute('role', 'status');
			actions.append(copy, status);
			details.append(field);
			controls.append(actions, details);
			new MutationObserver(() => {
				if (status.textContent !== controls.dataset.selected) return;
				details.open = true;
				input.focus({ preventScroll: true });
				input.select();
			}).observe(status, { childList: true, characterData: true, subtree: true });
			const arrange = () => controls.append(details);
			if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', arrange, { once: true });
			else setTimeout(arrange, 0);
		}
	}

	function initReadyCopy() {
		const button = root.querySelector('[data-artnovo-copy-ready]');
		const fallback = root.querySelector('[data-artnovo-ready-fallback]');
		const field = fallback?.querySelector('textarea');
		if (!button || !field?.value.trim()) return;
		const status = button.parentElement.querySelector('[data-artnovo-copy-status]');
		button.hidden = false;
		button.addEventListener('click', async () => {
			let message;
			try {
				await navigator.clipboard.writeText(field.value);
				message = 'لینک اکانت‌های آماده کپی شد.';
			} catch {
				fallback.hidden = false;
				field.focus({ preventScroll: true });
				field.select();
				message = 'لینک‌های آماده انتخاب شدند؛ آن‌ها را با دستگاه خود کپی کنید.';
			}
			if (status) { status.hidden = false; status.textContent = message; }
		});
	}

	function initBankCopy() {
		let number = 0;
		for (const value of root.querySelectorAll('.wc-bacs-bank-details li:is(.account_number, .iban, .sort_code, .bic) strong')) {
			if (!value.textContent.trim() || value.parentElement.querySelector('[data-artnovo-copy]')) continue;
			if (!value.id) {
				let id;
				do {
					id = 'artnovo-native-bank-value-' + (++number);
				} while (document.getElementById(id));
				value.id = id;
			}
			const button = document.createElement('button');
			button.type = 'button';
			button.hidden = true;
			button.dataset.artnovoCopy = value.id;
			button.textContent = 'کپی';
			const status = document.createElement('span');
			status.dataset.artnovoCopyStatus = '';
			status.hidden = true;
			status.setAttribute('role', 'status');
			value.parentElement.append(button, status);
		}
	}

	for (const button of root.querySelectorAll('[data-artnovo-copy]')) {
		const target = document.getElementById(button.dataset.artnovoCopy);
		if (!target || !root.contains(target)) continue;
		const status = button.parentElement.querySelector('[data-artnovo-copy-status]');
		button.hidden = false;
		button.addEventListener('click', async () => {
			const value = 'value' in target ? target.value : target.textContent.trim();
			try {
				await navigator.clipboard.writeText(value);
				if (status) {
					status.hidden = false;
					status.textContent = 'کپی شد.';
				}
			} catch {
				if ('select' in target) {
					target.focus();
					target.select();
				} else {
					const range = document.createRange();
					range.selectNodeContents(target);
					const selection = window.getSelection();
					selection.removeAllRanges();
					selection.addRange(range);
				}
				if (status) {
					status.hidden = false;
					status.textContent = 'متن انتخاب شد. آن را با دستگاه خود کپی کنید.';
				}
			}
		});
	}

	for (const form of root.querySelectorAll('[data-artnovo-receipt-form]')) {
		const submit = form.querySelector('button[type="submit"], input[type="submit"]');
		const status = form.querySelector('[data-artnovo-upload-status]');
		const file = form.querySelector('input[type="file"]');
		const filename = form.querySelector('[data-artnovo-receipt-filename]');
		file?.addEventListener('change', () => {
			if (filename) filename.textContent = file.files?.[0]?.name || 'انتخاب تصویر رسید';
		});
		form.addEventListener('submit', (event) => {
			if (form.dataset.artnovoSubmitting === 'true') {
				event.preventDefault();
				return;
			}
			form.dataset.artnovoSubmitting = 'true';
			form.setAttribute('aria-busy', 'true');
			if (submit) submit.disabled = true;
			if (status) {
				status.hidden = false;
				status.textContent = 'در حال ارسال رسید…';
			}
		});
		window.addEventListener('pageshow', () => {
			delete form.dataset.artnovoSubmitting;
			form.removeAttribute('aria-busy');
			if (submit) submit.disabled = false;
			if (status) status.hidden = true;
		});
	}
})();
