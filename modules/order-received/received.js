(() => {
	const root = document.querySelector('.artnovo-received');
	if (!root) return;

	if (root.dataset?.artnovoNativeOrderPay !== undefined) {
		const table = root.querySelector('form#order_review > table.shop_table');
		if (table && !table.querySelector('input:not([type="hidden"]), select, textarea, button, [contenteditable="true"]')) {
			const details = document.createElement('details');
			details.className = 'artnovo-order-pay-breakdown';
			const summary = document.createElement('summary');
			summary.textContent = 'جزئیات مبلغ سفارش';
			table.before(details);
			details.append(summary, table);
		}
		return;
	}

	initServiceLinks();
	initBankCopy();
	initReadyCopy();
	initReceiptForms();
	initPaymentStatus();

	function initPaymentStatus() {
		const {
			artnovoPaymentStatusUrl: endpoint,
			artnovoPaymentTrackUrl: tracking,
			artnovoPaymentStatusOrder: order,
			artnovoPaymentStatusKey: key,
			artnovoPaymentStatusNonce: nonce,
			artnovoPaymentReadyCount: readyCount,
			state,
		} = root.dataset || {};
		if (!endpoint) return;

		const body = new URLSearchParams({ order, key, _ajax_nonce: nonce });
		const deadline = performance.now() + 16 * 60 * 1000;
		let delay = 3000;
		let timer;
		let reading = false;
		let stopped = false;

		function schedule() {
			clearTimeout(timer);
			if (stopped || reading || document.hidden) return;
			const remaining = deadline - performance.now();
			if (remaining <= 0) {
				stopped = true;
				root.querySelector('[data-artnovo-payment-poll-expired]').hidden = false;
				return;
			}
			timer = setTimeout(check, Math.min(delay, remaining));
		}

		async function check() {
			if (document.hidden || performance.now() >= deadline) {
				schedule();
				return;
			}
			reading = true;
			try {
				const response = await fetch(endpoint, {
					method: 'POST',
					mode: 'same-origin',
					credentials: 'same-origin',
					cache: 'no-store',
					redirect: 'error',
					referrerPolicy: 'no-referrer',
					signal: AbortSignal.timeout(10000),
					body,
				});
				if ([401, 403].includes(response.status)) {
					stopped = true;
					return;
				}
				if (response.ok) {
					const result = await response.json();
					const next = result?.data;
					if (!document.hidden && result?.success && typeof next?.state === 'string' && Number.isInteger(next.ready_count)
						&& (next.state !== state || next.ready_count !== Number(readyCount))) {
						stopped = true;
						window.location.replace(tracking);
					}
				}
			} catch {
				// Keep the current status and the manual refresh action available.
			} finally {
				reading = false;
				delay = Math.min(delay + 2000, 30000);
				schedule();
			}
		}
		document.addEventListener('visibilitychange', schedule);
		schedule();
	}

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
			const target = bankCopyTarget(value);
			if (!target.id) {
				let id;
				do {
					id = 'artnovo-native-bank-value-' + (++number);
				} while (document.getElementById(id));
				target.id = id;
			}
			const button = document.createElement('button');
			button.type = 'button';
			button.hidden = true;
			button.dataset.artnovoCopy = target.id;
			button.textContent = target === value ? 'کپی' : 'کپی شماره کارت';
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
					const fallback = target.closest('[data-artnovo-copy-fallback]');
					if (fallback) fallback.hidden = false;
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

	function bankCopyTarget(value) {
		if (!root.hasAttribute('data-artnovo-quick-transfer') || value.childElementCount
			|| !value.parentElement.classList.contains('account_number')) return value;
		const digits = value.textContent.trim()
			.replace(/[۰-۹٠-٩]/g, digit => String(digit.charCodeAt(0) - (digit >= '۰' ? 0x06F0 : 0x0660)))
			.replace(/[\s-]/g, '');
		if (!/^\d{16}$/.test(digits)) return value;

		const fallback = document.createElement('div');
		fallback.dataset.artnovoCopyFallback = '';
		fallback.hidden = true;
		const field = document.createElement('input');
		field.type = 'text';
		field.dir = 'ltr';
		field.readOnly = true;
		field.value = digits;
		field.setAttribute('aria-label', 'شماره کارت برای کپی');
		fallback.append(field);
		value.parentElement.append(fallback);
		value.dir = 'ltr';
		value.textContent = digits.match(/.{4}/g).join(' ').replace(/\d/g, digit => '۰۱۲۳۴۵۶۷۸۹'[digit]);
		return field;
	}

	function initReceiptForms() {
		for (const form of root.querySelectorAll('[data-artnovo-receipt-form]')) {
			const submit = form.querySelector('button[type="submit"], input[type="submit"]');
			const status = form.querySelector('[data-artnovo-upload-status]');
			const input = form.querySelector('input[type="file"]');
			const filename = form.querySelector('[data-artnovo-receipt-filename]');
			const updatePreview = form.hasAttribute('data-artnovo-receipt-preview') && input?.files
				? receiptPreview(form, input, submit, filename) : null;
			if (updatePreview) updatePreview();
			else input?.addEventListener('change', () => {
				if (filename) filename.textContent = input.files?.[0]?.name || 'انتخاب تصویر رسید';
			});
			form.addEventListener('submit', (event) => {
				if (form.dataset.artnovoSubmitting === 'true' || (updatePreview && !updatePreview())) {
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
				if (updatePreview) updatePreview();
				else if (submit) submit.disabled = false;
				if (status) status.hidden = true;
			});
		}
	}

	function receiptPreview(form, input, submit, filename) {
		const image = form.querySelector('[data-artnovo-receipt-image]');
		const error = form.querySelector('[data-artnovo-receipt-error]');
		const prompt = form.querySelector('[data-artnovo-receipt-prompt]');
		const hint = form.querySelector('[data-artnovo-receipt-hint]');
		const initialFilename = filename?.textContent;
		const initialHint = hint?.textContent;
		const maxBytes = Number(form.elements.namedItem('MAX_FILE_SIZE')?.value) || Infinity;
		const types = input.accept.split(',').map(type => type.trim());
		let objectUrl;
		let previous;
		function clearImage() {
			if (!objectUrl) return;
			URL.revokeObjectURL(objectUrl);
			image.removeAttribute('src');
			image.hidden = true;
			objectUrl = undefined;
			previous = undefined;
		}
		function update() {
			const file = input.files[0];
			let message = '';
			if (file && (!file.size || (file.type && !types.includes(file.type)))) {
				message = 'یک تصویر معتبر JPG یا PNG انتخاب کنید.';
			} else if (file && file.size > maxBytes) {
				message = 'حجم تصویر بیشتر از حد مجاز است. تصویر کم‌حجم‌تری انتخاب کنید.';
			}
			const valid = Boolean(file && !message);
			if (submit) submit.disabled = !valid || form.dataset.artnovoSubmitting === 'true';
			if (message) input.setAttribute('aria-invalid', 'true');
			else input.removeAttribute('aria-invalid');
			if (error) {
				error.textContent = message;
				error.hidden = !message;
			}
			if (filename) filename.textContent = valid ? file.name : initialFilename;
			if (prompt) prompt.hidden = !valid;
			if (!valid || file !== previous) {
				clearImage();
				if (hint) hint.textContent = initialHint;
			}
			if (valid && image && !objectUrl && typeof URL.createObjectURL === 'function') {
				try {
					objectUrl = URL.createObjectURL(file);
					image.src = objectUrl;
					image.hidden = false;
					previous = file;
				} catch {
					if (hint) hint.textContent = 'پیش‌نمایش تصویر در دسترس نیست.';
				}
			}
			return valid;
		}
		input.addEventListener('change', update);
		image?.addEventListener('error', () => {
			if (!objectUrl) return;
			image.hidden = true;
			if (hint) hint.textContent = 'پیش‌نمایش تصویر در دسترس نیست.';
		});
		window.addEventListener('pagehide', clearImage);
		return update;
	}
})();
