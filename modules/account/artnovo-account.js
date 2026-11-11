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
			return url.origin === location.origin ? url.href : null;
		} catch {
			return null;
		}
	}

	function initSubscriptionTools() {
		const number = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 });
		for (const card of root.querySelectorAll('.wpvpn-subscription[data-artnovo-card]')) {
			let data;
			try {
				data = JSON.parse(card.dataset.artnovoCard);
			} catch {
				continue;
			}
			if (!data || typeof data !== 'object') continue;
			const metrics = document.createElement('div');
			metrics.className = 'artnovo-account-metrics';

			function addMetric(label, value, note = '') {
				const metric = document.createElement('div');
				metric.className = 'artnovo-account-metric';
				const title = document.createElement('span');
				title.textContent = label;
				const amount = document.createElement('strong');
				amount.textContent = value;
				metric.append(title, amount);
				if (note) {
					const hint = document.createElement('small');
					hint.textContent = note;
					metric.append(hint);
				}
				metrics.append(metric);
			}

			if (Number.isInteger(data.days_left) && data.days_left >= 0) {
				addMetric('زمان باقی‌مانده', data.days_left ? number.format(data.days_left) + ' روز' : 'پایان اعتبار');
			}
			const usage = data.usage;
			const hasUsage = usage && Number.isFinite(usage.used_bytes) && usage.used_bytes >= 0 &&
				Number.isFinite(usage.remaining_bytes) && usage.remaining_bytes >= 0;
			addMetric('حجم مصرف‌شده', hasUsage ? number.format(usage.used_bytes / 1073741824) + ' گیگابایت' : '—', hasUsage ? '' : 'به‌زودی');
			addMetric('حجم باقی‌مانده', hasUsage ? number.format(usage.remaining_bytes / 1073741824) + ' گیگابایت' : '—', hasUsage ? '' : 'به‌زودی');

			const actions = document.createElement('div');
			actions.className = 'artnovo-account-actions';
			for (const [key, label] of [['buy_url', 'خرید دوبارهٔ این پلن'], ['help_url', 'راهنمای اتصال'], ['support_url', 'پشتیبانی']]) {
				const destination = localUrl(data[key]);
				if (!destination) continue;
				const link = document.createElement('a');
				link.href = destination;
				link.textContent = key === 'buy_url' && typeof data.buy_label === 'string' && data.buy_label.trim()
					? data.buy_label
					: label;
				link.className = key === 'buy_url' ? 'artnovo-account-button' : 'artnovo-account-button artnovo-account-button-secondary';
				actions.append(link);
			}
			const renewUrl = localUrl(data.renew_url);
			const renewal = document.createElement(renewUrl ? 'a' : 'button');
			renewal.className = 'artnovo-account-button artnovo-account-button-secondary';
			renewal.textContent = renewUrl ? 'تمدید همین اشتراک' : 'تمدید همین اشتراک · به‌زودی';
			if (renewUrl) {
				renewal.href = renewUrl;
			} else {
				renewal.type = 'button';
				renewal.disabled = true;
			}
			actions.append(renewal);
			card.append(metrics, actions);

			const purchase = data.purchase;
			const viewUrl = localUrl(purchase?.view_url);
			if (viewUrl) {
				const details = document.createElement('details');
				details.className = 'artnovo-account-purchase';
				const summary = document.createElement('summary');
				summary.textContent = 'جزئیات خرید و پرداخت';
				const values = document.createElement('dl');
				for (const [key, label] of [['number', 'شماره پیگیری'], ['date', 'تاریخ خرید'], ['amount', 'مبلغ خرید']]) {
					if (typeof purchase[key] !== 'string' || !purchase[key]) continue;
					const title = document.createElement('dt');
					title.textContent = label;
					const value = document.createElement('dd');
					value.textContent = purchase[key];
					values.append(title, value);
				}
				const original = Array.from(card.querySelectorAll('a[href]')).find((link) =>
					localUrl(link.getAttribute('href')) === viewUrl);
				const previousParent = original?.parentElement;
				const link = original || document.createElement('a');
				if (!original) link.href = viewUrl;
				link.textContent = 'مشاهدهٔ جزئیات و ارسال رسید';
				details.append(summary, values, link);
				if (previousParent?.tagName === 'P' && !previousParent.childElementCount && !previousParent.textContent.trim()) {
					previousParent.remove();
				}
				card.append(details);
			}
		}
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
			submit.textContent = active ? 'تأیید و ورود به پنل' : 'ارسال کد تأیید';
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
			controls.append(toggle, target, status);
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
