(() => {
	'use strict';
	if (typeof fetch !== 'function' || typeof FormData !== 'function' || typeof AbortController !== 'function') return;
	const digits = (value) => value.replace(/[۰-۹٠-٩]/g, (digit) => String(digit.charCodeAt(0) % 16));
	const number = new Intl.NumberFormat('fa-IR');
	const mobileHint = 'شمارهٔ موبایل ایران باید ۱۱ رقم و با ۰۹ شروع شود؛ مانند 09123456789. بدون صفر یا با پیش‌شمارهٔ ۹۸ هم می‌توانید وارد کنید.';
	document.querySelectorAll('.artnovo-compact-auth').forEach((form) => {
		if (form.dataset.artnovoAuthInitialized) return;
		let config;
		try { config = JSON.parse(form.dataset.artnovoAuthConfig); } catch { return; }
		const identifier = form.elements.namedItem('artnovo_auth_identifier');
		const proof = form.elements.namedItem('artnovo_auth_proof');
		const fields = form.querySelector('[data-artnovo-auth-proof]');
		const label = form.querySelector('[data-artnovo-auth-proof-label]');
		const message = form.querySelector('[data-artnovo-auth-message]');
		const edit = form.querySelector('[data-artnovo-auth-edit]');
		const resend = form.querySelector('[data-artnovo-auth-resend]');
		const submit = form.querySelector('button[type="submit"]');
		if (!identifier || !proof || !fields || !label || !message || !edit || !resend || !submit) return;
		form.dataset.artnovoAuthInitialized = '1';
		let pending = false;
		let leaving = false;
		let method = '';
		let engine = '';
		let challengeJwt = '';
		let expires = 0;
		let timer;
		let preparedIdentity = '';
		const available = () => form.dataset.artnovoAuthAvailable !== '0';
		const identity = () => ['artnovo_product', 'artnovo_quantity'].map((name) => form.elements.namedItem(name)?.value).join(':');
		const show = (text, error = false) => {
			const template = document.createElement('template');
			template.innerHTML = String(text || '');
			template.content.querySelectorAll('script, style').forEach((node) => node.remove());
			const plain = template.content.textContent.trim();
			message.textContent = plain;
			const phones = [...plain.matchAll(/(?<![0-9])(?:\+98|0098|98|0)?9[0-9]{9}(?![0-9])/g)];
			if (phones.length) {
				const parts = [];
				let end = 0;
				for (const match of phones) {
					parts.push(document.createTextNode(plain.slice(end, match.index)));
					const phone = document.createElement('bdi');
					phone.dir = 'ltr';
					phone.textContent = match[0];
					parts.push(phone);
					end = match.index + match[0].length;
				}
				parts.push(document.createTextNode(plain.slice(end)));
				message.replaceChildren(...parts);
			}
			message.dataset.error = error ? '1' : '0';
			identifier.setAttribute('aria-invalid', error && !method ? 'true' : 'false');
			proof.setAttribute('aria-invalid', error && method ? 'true' : 'false');
		};
		const countdown = () => {
			window.clearTimeout(timer);
			const remaining = Math.max(0, Math.ceil(expires - Date.now() / 1000));
			resend.disabled = pending || remaining > 0;
			if (method === 'otp' && remaining > 0) timer = window.setTimeout(countdown, 1000);
			resend.textContent = remaining > 0 ? `ارسال دوباره تا ${number.format(remaining)} ثانیهٔ دیگر` : 'ارسال دوبارهٔ کد';
		};
		const busy = (value) => {
			pending = value;
			form.dataset.artnovoAuthPending = value ? '1' : '0';
			form.setAttribute('aria-busy', value ? 'true' : 'false');
			identifier.readOnly = value || Boolean(method);
			proof.readOnly = value;
			submit.disabled = value || !available();
			edit.disabled = value;
			countdown();
			document.dispatchEvent(new CustomEvent('artnovo:auth-busy', { detail: { pending: value } }));
		};
		const safeUrl = (value, expected) => {
			try {
				const url = new URL(value, window.location.href);
				return url.origin === window.location.origin && !url.username && !url.password
					&& url.pathname === new URL(expected, window.location.href).pathname ? url : null;
			} catch { return null; }
		};
		const refresh = (value = config.refreshUrl) => {
			const url = safeUrl(value, form.action) || safeUrl(config.refreshUrl, form.action);
			if (!url) throw new Error('برای ادامه، صفحهٔ خرید را دوباره باز کنید.');
			url.searchParams.set('artnovo_refresh', String(Date.now()));
			leaving = true;
			show('در حال تازه‌کردن فرم خرید…');
			window.location.replace(url.href);
		};
		const complete = () => {
			const url = safeUrl(config.resumeUrl, config.resumeUrl);
			const product = form.elements.namedItem('artnovo_product')?.value;
			if (!url || !/^[1-9][0-9]*$/.test(product || '')) throw new Error('ورود انجام شد؛ صفحهٔ خرید را دوباره باز کنید.');
			// Fetch the authenticated session once; the server resolves any newer selection.
			url.searchParams.set('artnovo_plan', product);
			url.searchParams.set('artnovo_checkout', '1');
			url.hash = 'artnovo-order';
			challengeJwt = '';
			proof.value = '';
			leaving = true;
			show('وارد شدید؛ در حال آماده‌کردن پرداخت…');
			window.location.replace(url.href);
		};
		const updateRevision = (revision) => {
			if (typeof revision !== 'string') return;
			const scope = form.closest('#artnovo-order') || form;
			scope.querySelectorAll('[name="artnovo_selection_revision"]').forEach((input) => { input.value = revision; });
		};
		const parse = async (response) => {
			let result;
			try { result = await response.json(); } catch { throw new Error('پاسخ ورود دریافت نشد. کمی بعد دوباره تلاش کنید.'); }
			if (result.code === 'rest_cookie_invalid_nonce') { refresh(); return null; }
			const data = result.data || {};
			if (data.refresh_required || data.selection_changed) { refresh(data.redirect); return null; }
			if (result.success !== true) throw new Error(data.message || result.message || 'ورود انجام نشد. اطلاعات را بررسی و دوباره تلاش کنید.');
			updateRevision(data.selection_revision);
			return result;
		};
		const request = async (url, options) => {
			const controller = new AbortController();
			let timedOut = false;
			const deadline = window.setTimeout(() => { timedOut = true; controller.abort(); }, 25000);
			try {
				return await parse(await fetch(url, { ...options, signal: controller.signal }));
			} catch (error) {
				if (!timedOut) throw error;
				// The server may have completed the POST; recover from its state instead of replaying it.
				challengeJwt = '';
				proof.value = '';
				refresh();
				return null;
			} finally { window.clearTimeout(deadline); }
		};
		const core = async (action = '', code = '') => {
			const payload = new FormData(form);
			payload.delete('artnovo_auth_identifier');
			payload.delete('artnovo_auth_proof');
			payload.set('artnovo_wizard_ajax', '1');
			payload.set('artnovo_login_method', action ? 'email' : 'pinova');
			if (action) {
				payload.set('artnovo_otp_action', action);
				payload.set('artnovo_email', identifier.value.trim());
				if (code) payload.set('artnovo_otp_code', code);
			}
			return request(form.action, { method: 'POST', credentials: 'same-origin', body: payload });
		};
		const native = async (url, payload) => {
			const endpoint = safeUrl(url, url);
			if (!endpoint) throw new Error('مسیر ورود در دسترس نیست. دوباره تلاش کنید.');
			return request(endpoint.href, {
				method: 'POST', credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'X-WP-Nonce': config.restNonce },
				body: new URLSearchParams(payload)
			});
		};
		const prepare = async () => {
			if (preparedIdentity === identity()) return true;
			const response = await core();
			if (!response) return false;
			if (!/^[a-f0-9]{64}$/.test(response.data?.selection_revision) || typeof response.data?.redirect !== 'string') {
				throw new Error('ادامهٔ خرید آماده نشد. صفحه را تازه کنید و دوباره تلاش کنید.');
			}
			preparedIdentity = identity();
			return true;
		};
		const challenge = (nextMethod, nextEngine, until = 0, jwt = '') => {
			method = nextMethod;
			engine = nextEngine;
			expires = until;
			challengeJwt = jwt;
			identifier.readOnly = true;
			fields.hidden = false;
			proof.disabled = false;
			proof.required = true;
			proof.value = '';
			proof.type = method === 'password' ? 'password' : 'text';
			proof.autocomplete = method === 'password' ? 'current-password' : 'one-time-code';
			proof.inputMode = method === 'password' ? 'text' : 'numeric';
			proof.removeAttribute('maxlength');
			proof.removeAttribute('pattern');
			if (method === 'otp') {
				const length = Number(engine === 'email' ? config.emailCodeLength : config.nativeCodeLength);
				proof.maxLength = length;
				// Normalize local digits on input before native browser validation runs.
				proof.pattern = `[0-9]{${length}}`;
			}
			label.textContent = method === 'password' ? 'رمز عبور' : 'کد تأیید';
			edit.textContent = identifier.value.includes('@') ? 'ویرایش ایمیل'
				: method === 'otp' ? 'ویرایش شماره موبایل'
					: /^[+0-9\s()-]+$/.test(identifier.value) ? 'ویرایش موبایل یا نام کاربری' : 'ویرایش نام کاربری';
			resend.hidden = method !== 'otp';
			submit.textContent = 'ورود و ادامهٔ پرداخت';
			countdown();
			proof.focus();
		};
		const begin = async () => {
			identifier.value = digits(identifier.value.trim());
			if (identifier.value.includes('@') && config.emailOtp) {
				const response = await core('send');
				if (!response) return;
				challenge('otp', 'email', Number(response.data.expires));
				show(response.data.message);
				return;
			}
			if (!await prepare()) return;
			const payload = { identifier: identifier.value };
			let response;
			try {
				response = await native(config.authenticateUrl, payload);
			} catch (error) {
				// Short numeric usernames remain native candidates; explain phone-shaped input only after native rejection.
				const phoneShaped = /^(?:\+|0098|98|0?9)[0-9]+$/.test(identifier.value.replace(/[\s()-]/g, ''));
				if (phoneShaped && ['برای دریافت کد تایید، از تلفن همراه یا ایمیل استفاده کنید.', 'ایمیل یا تلفن همراه معتبر نمی‌باشد.', 'تلفن همراه معتبر نمی‌باشد.'].includes(error.message)) {
					throw new Error(mobileHint);
				}
				throw error;
			}
			if (!response) return;
			const data = response.data;
			if (data.login_method !== 'password' && (data.login_method !== 'otp' || !data.jwt)) {
				throw new Error('روش ورود آماده نشد. شناسه را بررسی و دوباره تلاش کنید.');
			}
			challenge(data.login_method, 'native', Date.now() / 1000 + Number(data.ttl || 0), data.jwt || '');
			show(response.message || (method === 'password' ? 'رمز عبور حساب خود را وارد کنید.' : 'کد ارسال‌شده را وارد کنید.'));
		};
		const verify = async () => {
			const value = method === 'otp' ? digits(proof.value.trim()) : proof.value;
			proof.value = '';
			let response;
			if (engine === 'email') {
				response = await core('verify', value);
			} else {
				if (!await prepare()) return;
				response = await native(method === 'password' ? config.passwordUrl : config.otpUrl,
					method === 'password' ? { identifier: identifier.value, password: value } : { jwt: challengeJwt, code: value });
			}
			if (response) complete();
		};
		const run = async (action) => {
			if (pending || leaving || !available()) return;
			busy(true);
			show('در حال بررسی…');
			try { await action(); }
			catch (error) {
				show(error instanceof TypeError ? 'ارتباط برقرار نشد. اتصال اینترنت را بررسی و دوباره تلاش کنید.' : error.message, true);
				(method ? proof : identifier).focus();
			} finally { if (!leaving) busy(false); }
		};
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			if (!form.reportValidity()) return;
			run(method ? verify : begin);
		});
		proof.addEventListener('input', () => { if (method === 'otp') proof.value = digits(proof.value); });
		edit.addEventListener('click', () => {
			if (pending) return;
			method = engine = challengeJwt = preparedIdentity = '';
			expires = 0;
			fields.hidden = true;
			proof.value = '';
			proof.required = false;
			proof.disabled = true;
			identifier.readOnly = false;
			submit.textContent = 'ادامهٔ خرید';
			countdown();
			show('');
			identifier.focus();
		});
		resend.addEventListener('click', () => {
			if (Date.now() / 1000 >= expires) run(begin);
		});
		window.addEventListener('pagehide', () => { challengeJwt = ''; proof.value = ''; window.clearTimeout(timer); });
		window.addEventListener('pageshow', (event) => { if (event.persisted) refresh(); });
		const active = Number(form.dataset.artnovoAuthExpires);
		if (config.emailOtp && identifier.value.includes('@') && active > Date.now() / 1000) {
			challenge('otp', 'email', active);
			show('کد قبلی هنوز معتبر است. ایمیل خود را بررسی کنید.');
		}
	});
})();
