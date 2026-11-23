(() => {
	const root = document.querySelector('.artnovo-account');
	if (!root) return;

	const form = root.querySelector('.artnovo-account-auth');
	if (form) initEmailLogin(form);

	initSubscriptionTools();
	initSubscriptionQr();

	function localUrl(value, base = location.href) {
		if (typeof value !== 'string' || !value) return null;
		try {
			const url = new URL(value, base);
			return url.protocol === location.protocol && url.origin === location.origin ? url.href : null;
		} catch {
			return null;
		}
	}

	function displayDigits(value, price = false) {
		if (price) {
			for (const [separator, replacement] of [
				[root.dataset.artnovoPriceThousandSeparator ?? ',', '٬'],
				[root.dataset.artnovoPriceDecimalSeparator ?? '.', '٫']
			]) {
				if (!separator) continue;
				const escaped = separator.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
				value = value.replace(new RegExp('(?<=[0-9٠-٩۰-۹])' + escaped + '(?=[0-9٠-٩۰-۹])', 'gu'), replacement);
			}
		}
		return value.replace(/[0-9٠-٩]/g, digit => '۰۱۲۳۴۵۶۷۸۹'[/[0-9]/.test(digit) ? Number(digit) : digit.charCodeAt(0) - 0x660]);
	}

	function initSubscriptionTools() {
		const list = root.querySelector('.artnovo-account-subscriptions');
		if (!list) return;
		const cards = [...list.querySelectorAll(':scope > .wpvpn-subscription[data-artnovo-card]')];
		const number = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 });
		const groups = [
			{ key: 'ready', label: 'اکانت فعال', states: ['ready'], rows: [] },
			{ key: 'pending', label: 'در حال پیگیری', states: ['transfer', 'receipt', 'pending', 'wallet_insufficient', 'wallet_pending', 'wallet_review', 'issuing', 'review'], rows: [] },
			{ key: 'ended', label: 'پایان‌یافته', states: ['expired', 'closed'], rows: [] }
		];
		let first = null;
		let enhanced = 0;
		for (const card of cards) {
			let data;
			try {
				data = JSON.parse(card.dataset.artnovoCard);
			} catch {
				continue;
			}
			const presentation = data?.presentation;
			const controls = card.querySelector(':scope > .wpvpn-subscription-link');
			const input = controls?.querySelector('input[readonly][type="url"]');
			const copy = controls?.querySelector('button');
			const ready = Boolean(input?.value && copy);
			const state = ready ? 'ready' : presentation?.state;
			const group = groups.find(item => item.states.includes(state));
			const viewUrl = localUrl(data?.purchase?.view_url);
			const purchaseLink = [...card.querySelectorAll('a[href]')].find(link => localUrl(link.getAttribute('href')) === viewUrl);
			const primaryUrl = ready ? null : localUrl(presentation?.action_url);
			const label = ready ? 'آمادهٔ اتصال' : presentation?.label;
			const title = card.querySelector(':scope > h3');
			const status = card.querySelector(':scope > p');

			if (!group || !viewUrl || !purchaseLink || !title || !status || status.querySelector('a') ||
				typeof label !== 'string' || !label.trim() ||
				(!ready && (state === 'ready' || controls || !primaryUrl || typeof presentation?.action_label !== 'string' ||
					!presentation.action_label.trim()))) continue;

			card.classList.add('artnovo-account-row');
			card.dataset.artnovoState = state;
			status.classList.add('artnovo-account-row-status');
			status.textContent = label;

			const rowControls = controls || document.createElement('div');
			rowControls.classList.add('artnovo-account-row-controls');
			if (ready) {
				copy.classList.add('artnovo-account-button', 'artnovo-account-row-action');
				compactConnectionTools(controls);
			} else {
				const action = document.createElement('a');
				action.className = 'artnovo-account-button artnovo-account-row-action';
				action.href = primaryUrl;
				action.textContent = presentation.action_label;
				rowControls.append(action);
				card.append(rowControls);
			}
			renderSubscriptionRow(card, title, status, rowControls, purchaseLink, data, primaryUrl, ready, number);
			first ??= card;
			group.rows.push(card);
			enhanced++;
		}

		if (!first) return;
		// The Bridge still owns the query and pagination. Group only rows in its current response.
		for (const group of groups.filter(item => item.rows.length)) {
			const section = document.createElement('section');
			section.className = 'artnovo-account-group';
			section.dataset.artnovoGroup = group.key;
			const heading = document.createElement('h2');
			heading.id = 'artnovo-account-group-' + group.key;
			heading.textContent = group.key === 'ready' && group.rows.length > 1 ? 'اکانت‌های فعال' : group.label;
			const count = document.createElement('span');
			count.className = 'artnovo-account-group-count';
			const countText = number.format(group.rows.length);
			count.textContent = countText;
			count.setAttribute('aria-label', countText + ' اکانت');
			heading.append(' ', count);
			section.setAttribute('aria-labelledby', heading.id);
			section.append(heading);
			first.before(section);
			group.container = section;
		}
		for (const group of groups) group.container?.append(...group.rows);

		const nativeHeading = list.querySelector(':scope > h2');
		if (enhanced === list.querySelectorAll('.wpvpn-subscription').length &&
			nativeHeading?.textContent.trim() === 'اکانت‌های من') {
			nativeHeading.remove();
		}
	}

	function renderSubscriptionRow(card, title, status, controls, purchaseLink, data, primaryUrl, ready, number) {
		const information = document.createElement('div');
		information.className = 'artnovo-account-row-info';
		const heading = document.createElement('div');
		heading.className = 'artnovo-account-row-heading';
		heading.append(title, status);
		const meta = document.createElement('div');
		meta.className = 'artnovo-account-row-meta';
		const purchase = document.createElement('details');
		purchase.className = 'artnovo-account-purchase';
		const summary = document.createElement('summary');
		summary.textContent = 'جزئیات خرید';
		purchase.append(summary);
		let showsExpiry = false;
		for (const paragraph of card.querySelectorAll(':scope > p')) {
			if (paragraph.querySelector('a')) continue;
			const text = paragraph.textContent.trim();
			const quota = ready && text.match(/^حجم خریداری‌شده\s+([0-9٠-٩۰-۹.,٫٬]+)\s+گیگابایت$/u);
			if (quota && !card.classList.contains('artnovo-account-row-has-quota')) {
				card.append(subscriptionQuota(displayDigits(quota[1].replace('.', '٫'))));
				card.classList.add('artnovo-account-row-has-quota');
				paragraph.remove();
			} else if (ready) {
				showsExpiry ||= text.startsWith('پایان اعتبار ');
				meta.append(paragraph);
			} else {
				purchase.append(paragraph);
			}
		}

		const purchaseData = data.purchase;
		if (ready && Number.isInteger(data.days_left) && data.days_left > 0) {
			const time = document.createElement('p');
			time.textContent = number.format(data.days_left) + ' روز اعتبار باقی‌مانده';
			(showsExpiry ? purchase : meta).prepend(time);
		}
		if (typeof purchaseData.number === 'string' && purchaseData.number) {
			const reference = document.createElement('p');
			reference.textContent = 'خرید ' + displayDigits(purchaseData.number);
			meta.append(reference);
		}
		if (card.dataset.artnovoState === 'transfer' && typeof purchaseData.amount === 'string' && purchaseData.amount) {
			const amount = document.createElement('p');
			amount.textContent = displayDigits(purchaseData.amount, true);
			meta.append(amount);
		}
		const usage = data.usage;
		if (ready && Number.isFinite(usage?.used_bytes) && usage.used_bytes >= 0 &&
			Number.isFinite(usage.remaining_bytes) && usage.remaining_bytes >= 0) {
			for (const [label, bytes] of [['حجم مصرف‌شده', usage.used_bytes], ['حجم باقی‌مانده', usage.remaining_bytes]]) {
				const value = document.createElement('p');
				value.textContent = label + ' ' + number.format(bytes / 1073741824) + ' گیگابایت';
				meta.append(value);
			}
		}
		information.append(heading, meta);
		card.prepend(information);

		const values = document.createElement('dl');
		for (const [key, label] of [['number', 'شماره پیگیری'], ['date', 'تاریخ خرید'], ['amount', 'مبلغ خرید']]) {
			if (typeof purchaseData[key] !== 'string' || !purchaseData[key]) continue;
			const term = document.createElement('dt');
			term.textContent = label;
			const value = document.createElement('dd');
			value.textContent = displayDigits(purchaseData[key], key === 'amount');
			values.append(term, value);
		}
		if (values.childElementCount) purchase.append(values);

		const previousParent = purchaseLink.parentElement;
		purchaseLink.textContent = 'مشاهدهٔ سفارش و رسید';
		purchase.append(purchaseLink);
		if (previousParent.tagName === 'P' && !previousParent.childElementCount && !previousParent.textContent.trim()) {
			previousParent.remove();
		}
		const actions = document.createElement('div');
		actions.className = 'artnovo-account-actions';
		for (const [url, label] of [
			[data.buy_url, data.buy_label || 'خرید دوبارهٔ این پلن'],
			[data.renew_url, 'تمدید همین اکانت'],
			[data.help_url, 'راهنمای اتصال'],
			[data.support_url, 'پشتیبانی']
		]) {
			const destination = localUrl(url);
			if (!destination || destination === primaryUrl || typeof label !== 'string') continue;
			const link = document.createElement('a');
			link.href = destination;
			link.textContent = label;
			actions.append(link);
		}
		if (actions.childElementCount) purchase.append(actions);
		(controls.querySelector(':scope > .artnovo-account-link-details') || controls).append(purchase);
	}

	function subscriptionQuota(quantity) {
		const quota = document.createElement('div');
		quota.className = 'artnovo-account-row-quota';
		const ring = document.createElement('div');
		ring.className = 'artnovo-account-row-quota-ring';
		const value = document.createElement('strong');
		value.textContent = quantity;
		const unit = document.createElement('span');
		unit.textContent = 'گیگابایت';
		ring.append(value, unit);
		const label = document.createElement('span');
		label.textContent = 'حجم پلن';
		quota.append(ring, label);
		return quota;
	}

	function compactConnectionTools(controls) {
		const input = controls.querySelector('input[type="url"]');
		if (!input) return;
		const details = document.createElement('details');
		details.className = 'artnovo-account-link-details';
		const summary = document.createElement('summary');
		summary.textContent = 'جزئیات اتصال';
		details.append(summary);
		for (const paragraph of Array.from(controls.children)) {
			if (paragraph.contains(input) || paragraph.querySelector('a')) details.append(paragraph);
		}
		controls.append(details);
		const status = controls.querySelector('[role="status"]');
		if (status) {
			status.setAttribute('aria-live', 'polite');
			// Bridge owns copying; expose its manual-copy fallback after failure.
			new MutationObserver(() => {
				if (status.textContent === controls.dataset.selected) {
					details.open = true;
					input.focus({ preventScroll: true });
					input.select();
				}
			}).observe(status, { childList: true, subtree: true, characterData: true });
		}
		return details;
	}

	function initEmailLogin(loginForm) {
		const email = loginForm.elements.namedItem('artnovo_email');
		const code = loginForm.elements.namedItem('artnovo_otp_code');
		const action = loginForm.elements.namedItem('artnovo_account_action');
		const fields = loginForm.querySelector('[data-artnovo-code-fields]');
		const submit = loginForm.querySelector('[data-artnovo-auth-submit]');
		const edit = loginForm.querySelector('[data-artnovo-auth-edit]');
		const resend = loginForm.querySelector('[data-artnovo-auth-resend]');
		const message = loginForm.querySelector('[data-artnovo-auth-message]');
		if (!email || !code || !action || !fields || !submit || !edit || !resend || !message) return;

		const endpoint = new URL(loginForm.action, location.href);
		const accountPath = endpoint.pathname.replace(/\/?$/, '/');
		const approvedReturn = localUrl(loginForm.elements.namedItem('artnovo_redirect')?.value, endpoint);
		const completingEmail = loginForm.elements.namedItem('artnovo_complete_email')?.value === '1';
		const secondsFormat = new Intl.NumberFormat('fa-IR');
		const unavailable = submit.disabled;
		let expires = Number(loginForm.dataset.artnovoExpires) || 0;
		let pending = false;
		let expired = expires > 0 && expires <= Date.now() / 1000;
		let timer;

		function showMessage(text, error = false) {
			message.hidden = !text;
			message.toggleAttribute('data-error', error);
			message.textContent = text;
		}

		function updateControls() {
			const active = expires > 0;
			fields.hidden = !active;
			email.readOnly = active || pending;
			code.disabled = !active || expired;
			code.required = active && !expired;
			code.readOnly = pending;
			submit.disabled = unavailable || pending || expired;
			submit.textContent = active ? (completingEmail ? 'تأیید و ثبت ایمیل' : 'تأیید و ورود به پنل') : 'ارسال کد تأیید';
			edit.disabled = pending;
			resend.disabled = unavailable || pending || active && !expired;
			loginForm.setAttribute('aria-busy', String(pending));
		}

		function updateCountdown() {
			clearTimeout(timer);
			const seconds = Math.max(0, Math.ceil(expires - Date.now() / 1000));
			resend.textContent = seconds
				? 'ارسال دوباره پس از ' + secondsFormat.format(seconds) + ' ثانیه'
				: 'ارسال دوبارهٔ کد';
			if (seconds) {
				timer = setTimeout(updateCountdown, 1000);
				return;
			}
			if (expires && !expired && !pending) {
				const focusWasOnCode = document.activeElement === code || document.activeElement === submit;
				expired = true;
				updateControls();
				showMessage('مهلت کد تمام شد. برای ادامه دوباره کد بگیرید.', true);
				if (focusWasOnCode) resend.focus({ preventScroll: true });
			}
		}

		async function requestCode(nextAction) {
			if (unavailable || pending || !email.reportValidity()) return;
			if (nextAction === 'verify' && !code.reportValidity()) return;
			const payload = new FormData(loginForm);
			payload.set('artnovo_account_action', nextAction);
			payload.set('artnovo_account_ajax', '1');
			if (nextAction === 'send') payload.delete('artnovo_otp_code');
			pending = true;
			updateControls();
			updateCountdown();
			showMessage(nextAction === 'send' ? 'در حال ارسال کد…' : 'در حال بررسی کد…');
			let redirecting = false;
			let focusCode = false;
			try {
				const response = await fetch(endpoint, {
					method: 'POST',
					body: payload,
					credentials: 'same-origin',
					headers: { Accept: 'application/json' }
				});
				const result = await response.json();
				if (!response.ok || !result.success) {
					showMessage(result.data?.message || 'درخواست انجام نشد. دوباره تلاش کنید.', true);
					focusCode = nextAction === 'verify';
					return;
				}
				if (nextAction === 'verify') {
					const destination = new URL(result.data.redirect, endpoint);
					if (!result.data.redirect || destination.origin !== location.origin ||
						(!destination.pathname.startsWith(accountPath) && destination.href !== approvedReturn)) {
						throw new Error('Unexpected login destination');
					}
					showMessage('ایمیل تأیید شد. در حال باز کردن پنل…');
					location.replace(destination.href);
					redirecting = true;
					return;
				}
				const expiration = Number(result.data.expires);
				if (!Number.isFinite(expiration) || expiration <= Date.now() / 1000) {
					throw new Error('Missing code expiration');
				}
				expires = expiration;
				expired = false;
				code.value = '';
				showMessage(result.data.message || 'کد تأیید به ایمیل شما ارسال شد.');
				focusCode = true;
			} catch {
				showMessage('ارتباط با سایت برقرار نشد. دوباره تلاش کنید.', true);
			} finally {
				if (!redirecting) {
					pending = false;
					updateControls();
					updateCountdown();
					if (focusCode && !code.disabled) code.focus({ preventScroll: true });
				}
			}
		}

		loginForm.addEventListener('submit', (event) => {
			event.preventDefault();
			if (!expired) void requestCode(expires ? 'verify' : 'send');
		});
		edit.addEventListener('click', () => {
			expires = 0;
			expired = false;
			code.value = '';
			updateControls();
			updateCountdown();
			showMessage('');
			email.focus({ preventScroll: true });
		});
		resend.addEventListener('click', () => {
			if (expired) void requestCode('send');
		});
		updateControls();
		updateCountdown();
	}

	function initSubscriptionQr() {
		const library = window.jQuery;
		if (!library?.fn.qrcode) return;
		let qrNumber = 0;
		for (const controls of root.querySelectorAll('.wpvpn-subscription-link')) {
			const input = controls.querySelector('input[type="url"]');
			if (!input?.value || controls.querySelector('[data-artnovo-qr]')) continue;
			let destination;
			try {
				destination = new URL(input.value);
			} catch {
				continue;
			}
			if (!['https:', 'http:'].includes(destination.protocol)) continue;
			const toggle = document.createElement('button');
			toggle.type = 'button';
			toggle.dataset.artnovoQr = '';
			toggle.textContent = 'نمایش QR';
			toggle.setAttribute('aria-expanded', 'false');
			const target = document.createElement('div');
			target.dataset.artnovoQrTarget = '';
			target.id = 'artnovo-subscription-qr-' + (++qrNumber);
			target.hidden = true;
			target.setAttribute('role', 'img');
			target.setAttribute('aria-label', 'کد QR لینک اتصال این اشتراک');
			toggle.setAttribute('aria-controls', target.id);
			const status = document.createElement('p');
			status.className = 'artnovo-account-muted';
			status.hidden = true;
			status.setAttribute('role', 'status');
			const details = controls.querySelector('.artnovo-account-link-details');
			const purchase = details?.querySelector('.artnovo-account-purchase');
			if (purchase) purchase.before(toggle, target, status);
			else (details || controls).append(toggle, target, status);
			toggle.addEventListener('click', () => {
				if (!target.childElementCount) {
					try {
						library(target).qrcode({ text: destination.href, width: 256, height: 256 });
					} catch {
						target.replaceChildren();
						status.hidden = false;
						status.textContent = 'نمایش QR انجام نشد. از دکمهٔ کپی لینک استفاده کنید.';
						return;
					}
				}
				target.hidden = !target.hidden;
				toggle.setAttribute('aria-expanded', String(!target.hidden));
				toggle.textContent = target.hidden ? 'نمایش QR' : 'بستن QR';
			});
		}
	}
})();
