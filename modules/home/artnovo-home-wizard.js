(() => {
	const root = document.querySelector('[data-artnovo-wizard]');
	if (!root) return;

	const byData = (attribute) => new Map(Array.from(
		root.querySelectorAll(`[${attribute}]`), (element) => [element.getAttribute(attribute), element]
	));
	const panels = byData('data-artnovo-panel');
	const groups = byData('data-artnovo-group');
	const categories = byData('data-artnovo-category');
	const steps = byData('data-artnovo-go');
	const progress = root.querySelector('[data-artnovo-progress]');
	const footer = document.querySelector('.elementor-location-footer[data-elementor-type="footer"]');
	const mobile = matchMedia('(max-width: 767px)');
	const scrollRoom = '--artnovo-progress-scroll-room';
	const order = root.querySelector('#artnovo-order');
	const empty = order?.querySelector('[data-artnovo-empty]');
	const selected = order?.querySelector('[data-artnovo-selected]');
	const title = order?.querySelector('[data-artnovo-title]');
	const price = order?.querySelector('[data-artnovo-price]');
	if (panels.size !== 3 || !progress || !empty || !selected || !title || !price) return;

	const quantityLabel = order.querySelector('[data-artnovo-quantity]');
	const decrease = order.querySelector('[data-artnovo-quantity-step="-1"]');
	const increase = order.querySelector('[data-artnovo-quantity-step="1"]');
	const totals = order.querySelectorAll('[data-artnovo-total]');
	const form = order.querySelector('.artnovo-order-form');
	const prepare = order.querySelector('[data-artnovo-prepare]');
	const selectionForm = prepare ?? form;
	const nativeHost = order.querySelector('[data-artnovo-native-checkout]');
	const preparationPanel = order.querySelector('[data-artnovo-preparation]');
	const preparationStatus = order.querySelector('[data-artnovo-prepare-status]');
	const retryPrepare = order.querySelector('[data-artnovo-retry-prepare]');
	let nativeCheckout = order.querySelector('.artnovo-order-native form.checkout');
	let cartIdentity = nativeCheckout?.elements.namedItem('artnovo_wizard_cart');
	const quantityStatus = order.querySelector('[data-artnovo-quantity-status]');
	const productInput = form?.elements.namedItem('artnovo_product');
	const quantityInput = form?.elements.namedItem('artnovo_quantity');
	const adjustments = order.querySelector('[data-artnovo-adjustments]');
	const draftPlan = order.dataset.artnovoDraftPlan;
	let resumeDraft = Boolean(draftPlan);
	let review = order.dataset.artnovoCheckoutReview === '1';
	const checkoutPlans = new Set(JSON.parse(order.dataset.artnovoCheckoutPlans ?? '[]'));
	const quantityLimits = JSON.parse(order.dataset.artnovoQuantityLimits ?? '{}');
	const unitAmounts = JSON.parse(order.dataset.artnovoUnitAmounts ?? '{}');
	const priceDecimals = Number(order.dataset.artnovoPriceDecimals ?? 0);
	const priceFormat = new Intl.NumberFormat('fa-IR', {
		minimumFractionDigits: priceDecimals,
		maximumFractionDigits: priceDecimals
	});
	const quantityFormat = new Intl.NumberFormat('fa-IR');
	let quantity = 1;
	let preparing = false;
	let quantityPending = false;
	let reviewCurrent = review;
	let authPending = form?.dataset.artnovoAuthPending === '1';
	let historyRecovering = false;
	let selectionPending = false;
	let selectionSerial = 0;
	let queuedSelection = null;
	let requestedIdentity = '';
	let requestedPurchaseAction = '';
	let confirmedIdentity = review ? draftPlan + ':' + order.dataset.artnovoDraftQuantity : '';
	let selectionRecovery = false;
	let nativeAssetsFailed = false;
	let nativeEventsBound = false;
	let nativeRefreshAllowed = false;
	let cancelNativeReview = () => {};
	const nativeRequests = new Set();
	const asyncSupported = typeof fetch === 'function' && typeof FormData === 'function' && typeof AbortController === 'function';

	function recoverPurchaseHistory(persisted = false) {
		if (!selectionForm || !persisted && window.performance?.getEntriesByType?.('navigation')?.[0]?.type !== 'back_forward') return false;
		if (historyRecovering) return true;
		let url;
		try {
			url = new URL(location.href);
			const action = new URL(selectionForm.action, location.href);
			if (url.origin !== location.origin || action.origin !== location.origin || url.pathname !== action.pathname
				|| url.username || url.password || action.username || action.password
				|| !/^[1-9][0-9]*$/.test(url.searchParams.get('artnovo_plan') || '')) return false;
		} catch { return false; }
		// History can restore an old plan with a fresh revision; let the server recover before any prepare POST.
		url.searchParams.set('artnovo_checkout', '1');
		url.searchParams.set('artnovo_refresh', String(Date.now()));
		url.hash = 'artnovo-order';
		historyRecovering = true;
		location.replace(url.href);
		return true;
	}
	if (recoverPurchaseHistory()) return;
	window.addEventListener('pageshow', (event) => {
		if (event.persisted) {
			showBankConnection(false);
			recoverPurchaseHistory(true);
		}
	});

	function selectionBadge() {
		const badge = document.createElement('span');
		badge.className = 'artnovo-selected-badge';
		badge.textContent = '✓ انتخاب‌شده';
		badge.hidden = true;
		return badge;
	}

	const durationBadges = new Map();
	for (const [category, button] of categories) {
		if (button.disabled) continue;
		const badge = selectionBadge();
		badge.setAttribute('aria-hidden', 'true');
		const label = button.querySelector('.artnovo-duration-name') ?? button;
		label.append(badge);
		durationBadges.set(category, badge);
	}

	const plans = [];
	const planDetails = new Set();
	for (const [category, group] of groups) {
		for (const detail of group.querySelectorAll('article details.e-accordion-item-base')) {
			detail.setAttribute('name', 'artnovo-plan-details');
			planDetails.add(detail);
		}
		for (const link of group.querySelectorAll('article a[href]')) {
			const url = new URL(link.href);
			const id = url.searchParams.get('artnovo_plan');
			if (url.origin !== location.origin || url.hash !== '#artnovo-order' || !/^\d+$/.test(id ?? '')) continue;
			const card = link.closest('article');
			const nameNode = card.querySelector('[data-artnovo-plan-title], .elementor-widget-theme-post-excerpt');
			const name = persianDigits(nameNode?.textContent.trim() ?? '');
			const amount = card.querySelector('ins .woocommerce-Price-amount') ?? card.querySelector('.woocommerce-Price-amount');
			const currency = amount?.querySelector('.woocommerce-Price-currencySymbol')?.textContent.trim();
			if (name && amount && currency) {
				if (nameNode) localizeText(nameNode, persianDigits);
				localizeMoney(card);
				const label = document.createElement('span');
				label.append(...link.childNodes);
				const badge = selectionBadge();
				link.append(label, badge);
				plans.push({
					id, category, card, link, name, label, badge,
					unitAmount: unitAmounts[id],
					price: `${amount.textContent.replace(currency, '').trim()} ${currency}`
				});
			}
		}
	}

	const categoryAvailable = (category) => groups.has(category) && categories.has(category) && !categories.get(category).disabled;
	const findPlan = (id, category) => plans.find((plan) => plan.id === id && (!category || plan.category === category) && categoryAvailable(plan.category));
	let state;

	function normalize(input) {
		const plan = findPlan(input.plan, input.category);
		const category = plan?.category ?? (categoryAvailable(input.category) ? input.category : null);
		const step = input.step === '3' && plan ? '3' : input.step !== '1' && category ? '2' : '1';
		return { step, category, plan: plan?.id ?? null };
	}

	function readLocation(saved) {
		const query = new URLSearchParams(location.search);
		const plan = query.get('artnovo_plan');
		const category = query.get('artnovo_category');
		if (saved && (saved.step === '3' && plan === saved.plan || saved.step === '2' && !plan && category === saved.category || saved.step === '1' && !plan && !category)) {
			return normalize(saved);
		}
		return normalize({ step: plan ? '3' : category ? '2' : '1', category, plan });
	}

	function writeLocation(next) {
		const url = new URL(location.href);
		url.searchParams.delete('artnovo_category');
		url.searchParams.delete('artnovo_plan');
		if (next.step === '3') url.searchParams.set('artnovo_plan', next.plan);
		if (next.step === '2') url.searchParams.set('artnovo_category', next.category);
		if (next.step === '1') {
			if (url.hash === '#artnovo-order' || url.hash === '#artnovo-plans') url.hash = '';
		} else {
			url.hash = next.step === '3' ? '#artnovo-order' : '#artnovo-plans';
		}
		if (review && next.step === '3') url.searchParams.set('artnovo_checkout', '1');
		else url.searchParams.delete('artnovo_checkout');
		const entry = { ...history.state, artnovoWizard: next };
		if (url.href === location.href) history.replaceState(entry, '', url);
		else history.pushState(entry, '', url);
	}

	function focusStepHeading(selection) {
		const scope = selection.step === '2' ? groups.get(selection.category) : panels.get(selection.step);
		const heading = scope.querySelector('h2') ?? scope;
		if (!heading.hasAttribute('tabindex')) heading.tabIndex = -1;
		heading.focus({ preventScroll: true });
		return heading;
	}

	function preserveProgressPosition() {
		const stickyTop = Number.parseFloat(getComputedStyle(progress).top) || 0;
		const wasPinned = mobile.matches && progress.getBoundingClientRect().top <= stickyTop + 1;
		const footerFillsViewport = footer?.parentElement === document.body &&
			getComputedStyle(document.body).getPropertyValue('--artnovo-viewport-adjustment') !== '';
		root.toggleAttribute('data-artnovo-keep-sticky', wasPinned && !footerFillsViewport);
		if (!wasPinned || !footerFillsViewport) {
			document.body.style.removeProperty(scrollRoom);
			return;
		}
		// Measure the normal-flow position to retain only enough scroll for the pinned bar.
		progress.style.position = 'static';
		const pinOffset = window.scrollY + progress.getBoundingClientRect().top - stickyTop;
		progress.style.removeProperty('position');
		document.body.style.setProperty(scrollRoom, `${Math.max(0, Math.ceil(pinOffset))}px`);
	}

	function persianDigits(value) {
		return String(value).replace(/[0-9٠-٩]/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit.charCodeAt(0) % 16]);
	}

	function moneyText(value) {
		const thousand = order.dataset.artnovoPriceThousandSeparator ?? ',';
		const decimal = order.dataset.artnovoPriceDecimalSeparator ?? '.';
		if (thousand !== decimal) {
			const separators = [thousand, decimal].filter(Boolean).sort((a, b) => b.length - a.length)
				.map((separator) => separator.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&'));
			if (separators.length) {
				const pattern = new RegExp('([0-9۰-۹٠-٩])(' + separators.join('|') + ')(?=[0-9۰-۹٠-٩])', 'g');
				value = String(value).replace(pattern, (_, digit, separator) => digit + (separator === thousand ? '٬' : '٫'));
			}
		}
		return persianDigits(value);
	}

	function localizeText(scope, format) {
		if (!scope) return;
		if (scope.nodeType === 3) {
			scope.textContent = format(scope.textContent);
			return;
		}
		if (/^(INPUT|TEXTAREA|SELECT|SCRIPT|STYLE|CODE|PRE)$/i.test(scope.tagName ?? '')
			|| /(?:^|\s)woocommerce-Price-currencySymbol(?:\s|$)/.test(scope.getAttribute?.('class') ?? '')
			|| scope.hasAttribute?.('data-artnovo-technical')) return;
		for (const child of scope.childNodes) localizeText(child, format);
	}

	function localizeMoney(scope) {
		for (const amount of scope.querySelectorAll('.woocommerce-Price-amount')) localizeText(amount, moneyText);
	}

	function updateAmounts(plan) {
		const canPrice = Number.isSafeInteger(plan?.unitAmount);
		const formatAmount = (amount) => `${priceFormat.format(amount / 10 ** priceDecimals)}\u00a0${order.dataset.artnovoCurrency}`;
		price.textContent = canPrice ? formatAmount(plan.unitAmount) : plan?.price ?? '';
		if (productInput) productInput.value = plan?.id ?? '';
		if (quantityInput) quantityInput.value = String(quantity);
		if (form) form.dataset.artnovoAuthAvailable = !selectionPending && !selectionRecovery && checkoutPlans.has(plan?.id) ? '1' : '0';
		if (!quantityLabel || !decrease || !increase) return;
		const limit = quantityLimits[plan?.id] ?? 1;
		quantityLabel.textContent = quantityFormat.format(quantity);
		decrease.disabled = quantityPending || authPending || !canPrice || quantity === 1;
		increase.disabled = quantityPending || authPending || !canPrice || quantity >= limit || !Number.isSafeInteger((quantity + 1) * Math.max(plan.unitAmount, 1));
		const total = canPrice ? formatAmount(quantity * plan.unitAmount) : price.textContent;
		if (!syncNativeSummary()) {
			for (const output of totals) output.textContent = total;
		}
	}

	function prepareCheckout(planId, count, purchaseAction = '') {
		if (!selectionForm || !checkoutPlans.has(planId)) return false;
		if (!asyncSupported) {
			if (preparing || historyRecovering) return true;
			preparing = true;
			selectionForm.elements.namedItem('artnovo_product').value = planId;
			selectionForm.elements.namedItem('artnovo_quantity').value = String(count);
			if (purchaseAction) {
				const action = document.createElement('input');
				action.type = 'hidden';
				action.name = 'artnovo_purchase_action';
				action.value = purchaseAction;
				selectionForm.append(action);
			}
			for (const name of ['artnovo_wizard_ajax', 'artnovo_wizard_select', 'artnovo_otp_action']) {
				const field = selectionForm.elements.namedItem(name);
				if (field) field.disabled = true;
			}
			selectionForm.submit();
			return true;
		}
		if (selectionRecovery || nativeAssetsFailed) {
			recoverSelection();
			return true;
		}
		const identity = planId + ':' + count;
		if (identity === requestedIdentity && selectionPending && (!purchaseAction || requestedPurchaseAction)
			|| !purchaseAction && identity === confirmedIdentity && !selectionPending && reviewCurrent) return true;
		queuedSelection = { product: planId, quantity: count, purchaseAction, serial: ++selectionSerial };
		requestedIdentity = identity;
		requestedPurchaseAction = purchaseAction;
		selectionPending = true;
		reviewCurrent = false;
		setSelectionState(true);
		void prepareSelection();
		return true;
	}

	function selectionEndpoint(planId) {
		const endpoint = new URL(selectionForm.action, location.href);
		if (endpoint.origin !== location.origin || endpoint.username || endpoint.password) throw new Error('مسیر خرید معتبر نیست.');
		endpoint.searchParams.set('artnovo_plan', planId);
		endpoint.searchParams.set('artnovo_checkout', '1');
		endpoint.hash = '';
		return endpoint;
	}

	function recoverSelection() {
		try {
			const endpoint = selectionEndpoint(state.plan);
			endpoint.searchParams.set('artnovo_refresh', String(Date.now()));
			endpoint.hash = 'artnovo-order';
			historyRecovering = true;
			location.replace(endpoint.href);
		} catch { showQuantityStatus('برای ادامه، صفحهٔ خرید را دوباره باز کنید.'); }
	}

	function setSelectionState(pending, message = '', ready = null) {
		if (nativeHost) {
			nativeHost.hidden = pending || !reviewCurrent;
			nativeHost.inert = pending || !reviewCurrent;
		}
		if (preparationPanel) {
			preparationPanel.hidden = Boolean(form) || !pending && reviewCurrent;
			preparationPanel.dataset.artnovoPreparing = pending && !form ? '1' : '0';
		}
		if (preparationStatus) preparationStatus.textContent = message || (pending ? preparationStatus.dataset.artnovoPrepareMessage : '');
		if (retryPrepare) retryPrepare.hidden = pending;
		if (pending) {
			nativeRefreshAllowed = false;
			cancelNativeReview();
			for (const request of nativeRequests) request.abort();
			nativeRequests.clear();
			delete order.dataset.artnovoSummaryReady;
			delete order.dataset.artnovoNativeReview;
			adjustments?.replaceChildren();
		}
		updateAmounts(findPlan(state.plan, state.category));
		if (ready) form?.dispatchEvent(new CustomEvent('artnovo:selection-ready', { bubbles: true, detail: ready }));
		form?.dispatchEvent(new CustomEvent('artnovo:selection-busy', {
			bubbles: true, detail: { pending: pending || selectionRecovery, product: state.plan, quantity }
		}));
		showQuantityStatus(message || (pending ? 'مبلغ نمایش‌داده‌شده اولیه است؛ مبلغ نهایی پس از بررسی خرید مشخص می‌شود.' : ''), pending || Boolean(message));
	}

	function abandonSelection() {
		// Leaving for another category is not permission to restore an obsolete selection later.
		selectionSerial += 1;
		queuedSelection = null;
		requestedIdentity = '';
		requestedPurchaseAction = '';
		selectionPending = reviewCurrent = nativeRefreshAllowed = false;
		cancelNativeReview();
		for (const request of nativeRequests) request.abort();
		nativeRequests.clear();
		setSelectionState(false);
	}

	async function prepareSelection() {
		if (preparing || historyRecovering) return;
		preparing = true;
		try {
			while (queuedSelection && !historyRecovering) {
				const intent = queuedSelection;
				queuedSelection = null;
				const endpoint = selectionEndpoint(intent.product);
				const payload = new FormData(selectionForm);
				for (const field of ['artnovo_auth_identifier', 'artnovo_auth_proof', 'artnovo_otp_action', 'artnovo_wizard_cart']) payload.delete(field);
				payload.set('artnovo_product', intent.product);
				payload.set('artnovo_quantity', String(intent.quantity));
				payload.set('artnovo_wizard_ajax', '1');
				payload.set('artnovo_wizard_select', '1');
				if (intent.purchaseAction) payload.set('artnovo_purchase_action', intent.purchaseAction);
				const controller = new AbortController();
				const timer = setTimeout(() => controller.abort(), 25000);
				let result;
				try {
					const response = await fetch(endpoint.href, { method: 'POST', body: payload, credentials: 'same-origin', signal: controller.signal });
					result = await response.json();
				} finally { clearTimeout(timer); }
				if (historyRecovering) return;
				if (followCurrentSelection(result, endpoint)) { historyRecovering = true; return; }
				if (result.success !== true) throw new Error(result.data?.message || 'انتخاب شما تأیید نشد. برای ادامه، صفحهٔ خرید را تازه کنید.');
				const data = result.data;
				const target = new URL(data?.redirect, endpoint);
				if (String(data?.product) !== intent.product || data.quantity !== intent.quantity
					|| data.cart_identity !== intent.product + ':' + intent.quantity
					|| !/^[a-f0-9]{64}$/.test(data.selection_revision || '')
					|| typeof data.wizard_nonce !== 'string' || !data.wizard_nonce
					|| typeof data.rest_nonce !== 'string' || !data.rest_nonce
					|| target.origin !== endpoint.origin || target.pathname !== endpoint.pathname || target.username || target.password
					|| target.searchParams.get('artnovo_plan') !== intent.product
					|| typeof data.checkout_html !== 'string' || typeof data.checkout_assets !== 'string') throw new Error('پاسخ خرید معتبر نیست. برای ادامه، صفحه را تازه کنید.');
				// A completed older selection only advances the queue's credentials; it never unlocks an old form.
				updateSelectionRevision(data.selection_revision, false);
				for (const field of selectionForm.querySelectorAll('[name="artnovo_wizard_nonce"]')) field.value = data.wizard_nonce;
				if (intent.serial !== selectionSerial) continue;
				if (prepare) {
					installNativeCheckout(data.checkout_html, data);
					nativeRefreshAllowed = true;
					let initialReview = null;
					await loadCheckoutAssets(data.checkout_assets, () => { initialReview = refreshOrderReview(true); });
					if (intent.serial !== selectionSerial || historyRecovering) continue;
					const current = await (initialReview ?? refreshOrderReview());
					nativeRefreshAllowed = false;
					if (intent.serial !== selectionSerial || historyRecovering) continue;
					if (!current) throw new Error('مبلغ نهایی تأیید نشد. برای ادامه، صفحهٔ خرید را تازه کنید.');
					review = true;
				}
				selectionPending = false;
				reviewCurrent = true;
				confirmedIdentity = data.cart_identity;
				order.dataset.artnovoCheckoutReview = prepare ? '1' : '0';
				setSelectionState(false, '', data);
			}
		} catch (error) {
			// A POST or part of an asset queue may already have succeeded. Do not blindly replay it.
			selectionRecovery = true;
			selectionPending = false;
			reviewCurrent = false;
			queuedSelection = null;
			setSelectionState(false, error instanceof TypeError ? 'ارتباط تأیید نشد. برای ادامهٔ همین خرید، صفحه را تازه کنید.' : error.message);
		} finally {
			preparing = false;
			nativeRefreshAllowed = false;
		}
	}

	function installNativeCheckout(html, data) {
		if (!nativeHost) throw new Error('فرم پرداخت در دسترس نیست. صفحه را تازه کنید.');
		const template = document.createElement('template');
		template.innerHTML = html;
		template.content.querySelectorAll('script').forEach((node) => node.remove());
		const checkouts = template.content.querySelectorAll('form.checkout');
		const fresh = checkouts[0];
		if (checkouts.length !== 1 || fresh.elements.namedItem('artnovo_wizard_cart')?.value !== data.cart_identity
			|| fresh.elements.namedItem('artnovo_selection_revision')?.value !== data.selection_revision) throw new Error('فرم پرداخت با انتخاب شما هماهنگ نیست. صفحه را تازه کنید.');
		// WooCommerce caches these outer forms and binds their handlers once.
		for (const selector of ['form.checkout', 'form.checkout_coupon']) {
			const next = template.content.querySelector(selector);
			const current = nativeHost.querySelector(selector);
			if (!current) continue;
			if (!next) throw new Error('فرم پرداخت کامل دریافت نشد. صفحه را تازه کنید.');
			for (const attribute of Array.from(current.attributes)) current.removeAttribute(attribute.name);
			for (const attribute of next.attributes) current.setAttribute(attribute.name, attribute.value);
			current.replaceChildren(...next.childNodes);
			next.replaceWith(current);
		}
		nativeHost.replaceChildren(...template.content.childNodes);
		nativeCheckout = nativeHost.querySelector('form.checkout');
		cartIdentity = nativeCheckout.elements.namedItem('artnovo_wizard_cart');
	}

	async function loadCheckoutAssets(html, beforeCheckout = () => {}) {
		if (nativeAssetsFailed) throw new Error('بارگذاری پرداخت کامل نشد. صفحه را تازه کنید.');
		const template = document.createElement('template');
		template.innerHTML = html;
		const loaded = new Set(Array.from(document.querySelectorAll('script[id]'), (node) => node.id));
		try {
			for (const original of template.content.querySelectorAll('script[id], style[id], link[id]')) {
				const base = original.id.replace(/-js-(before|after|extra|translations)$/, '-js');
				if (!original.id || document.getElementById(original.id) || base !== original.id && loaded.has(base)) continue;
				if (original.tagName === 'LINK' && original.rel !== 'stylesheet') continue;
				if (original.id === 'wc-checkout-js') beforeCheckout();
				const asset = document.createElement(original.tagName.toLowerCase());
				for (const attribute of original.attributes) asset.setAttribute(attribute.name, attribute.value);
				asset.textContent = original.textContent;
				const source = original.getAttribute(original.tagName === 'SCRIPT' ? 'src' : 'href');
				if (source) {
					const url = new URL(source, location.href);
					if (url.username || url.password || url.protocol !== 'https:' && !(url.protocol === 'http:' && url.origin === location.origin)) throw new Error('نشانی فایل پرداخت معتبر نیست.');
					if (asset.tagName === 'SCRIPT') asset.async = false;
					await new Promise((resolve, reject) => {
						const timer = setTimeout(() => reject(new Error('فایل پرداخت بارگذاری نشد. صفحه را تازه کنید.')), 15000);
						asset.onload = () => { clearTimeout(timer); resolve(); };
						asset.onerror = () => { clearTimeout(timer); reject(new Error('فایل پرداخت بارگذاری نشد. صفحه را تازه کنید.')); };
						document.head.append(asset);
					});
				} else document.head.append(asset);
				bindNativeEvents();
			}
			bindNativeEvents();
			if (!window.jQuery || !document.getElementById('wc-checkout-js')) throw new Error('پرداخت آماده نشد. صفحه را تازه کنید.');
		} catch (error) { nativeAssetsFailed = true; throw error; }
	}

	function bindNativeEvents() {
		if (nativeEventsBound || !window.jQuery) return;
		nativeEventsBound = true;
		const body = window.jQuery(document.body);
		window.jQuery.ajaxPrefilter((options, original, request) => {
			let url;
			try { url = new URL(options.url, location.href); } catch { return; }
			if (url.origin !== location.origin) return;
			const action = url.searchParams.get('wc-ajax');
			if (action === 'checkout' && nativeCheckout?.classList.contains('processing')
				&& nativeCheckout.querySelector('[name="payment_method"]:checked')?.value === 'artnovo_gpante') {
				showBankConnection(true);
			}
			if (action !== 'update_order_review') return;
			if ((!reviewCurrent || selectionPending) && !nativeRefreshAllowed) { request.abort(); return; }
			nativeRequests.add(request);
			request.always(() => nativeRequests.delete(request));
		});
		body.on('updated_checkout payment_method_selected', () => { syncNativeSummary(); });
		body.on('checkout_error', () => showBankConnection(false));
		body.on('applied_coupon_in_checkout removed_coupon_in_checkout', () => {
			queueMicrotask(() => window.jQuery(order.querySelector('form.checkout_coupon')).stop(true, true).show());
		});
	}

	function showBankConnection(active) {
		const status = nativeHost?.querySelector('[data-artnovo-bank-connecting]');
		if (status) status.textContent = active ? 'در حال اتصال به درگاه پرداخت…' : '';
	}

	function updateSelectionRevision(value, includeNative = true) {
		if (typeof value !== 'string' || !/^[a-f0-9]{64}$/.test(value)) return;
		for (const field of order.querySelectorAll('[name="artnovo_selection_revision"]')) {
			if (includeNative || !nativeHost?.contains(field)) field.value = value;
		}
	}

	function followCurrentSelection(result, endpoint) {
		if (!(result.data?.refresh_required || result.success && result.data?.selection_changed) || typeof result.data.redirect !== 'string') return false;
		let target;
		try { target = new URL(result.data.redirect, endpoint); } catch { return false; }
		if (target.origin !== location.origin || target.pathname !== endpoint.pathname || target.username || target.password) return false;
		location.replace(target.href);
		return true;
	}

	function showQuantityStatus(message, pending = false) {
		if (!quantityStatus) return;
		quantityStatus.toggleAttribute('data-artnovo-notice-permanent', pending);
		quantityStatus.dataset.error = message && !pending ? '1' : '0';
		quantityStatus.textContent = message;
		quantityStatus.hidden = !message;
	}

	function syncPaymentButton(amount, walletBlocked) {
		const method = nativeCheckout?.querySelector('[name="payment_method"]:checked');
		if (!method) return;
		const button = nativeCheckout.querySelector('#place_order');
		if (!button) return;
		const gatewayLabel = method.getAttribute('data-order_button_text');
		let label = gatewayLabel || button.dataset.value;
		if (method.value === 'bacs') {
			label = 'ثبت سفارش و نمایش اطلاعات واریز';
		} else if (walletBlocked && amount && method.value === 'artnovo_gpante') {
			label = 'پرداخت کامل ' + moneyText(amount) + ' در سان‌پی';
		} else if (!gatewayLabel) {
			if (method.value === 'wallet') label = 'پرداخت با کیف پول';
			else if (amount) label = 'پرداخت ' + moneyText(amount);
		}
		if (label && button.textContent !== label) button.textContent = label;
	}

	function syncNativeSummary() {
		if (selectionPending || !reviewCurrent) return false;
		const table = nativeCheckout?.querySelector('.woocommerce-checkout-review-order-table');
		const amount = table?.querySelector('.order-total td');
		const walletRow = table?.querySelector('.wallet-pay-partial');
		const walletChoice = walletRow?.querySelector('input[name="partial_pay_through_wallet"]');
		const walletApplied = walletRow && (!walletChoice || walletChoice.checked && !walletChoice.dataset.artnovoWalletPending);
		const walletBlocked = !walletRow && Boolean(nativeCheckout?.querySelector('#artnovo-wallet-checkout-blocker'));
		syncPaymentButton(walletApplied || walletBlocked ? amount?.querySelector('.woocommerce-Price-amount')?.textContent.trim() : '', walletBlocked);
		if (!amount) return false;
		if (walletRow) {
			// Keep TeraWallet's original control and automatic deduction notice in the form.
			const contribution = walletRow.querySelector('.wallet-amount .woocommerce-Price-amount')?.textContent.trim();
			if (walletChoice && !walletChoice.labels?.length && contribution) {
				const label = document.createElement('label');
				walletChoice.before(label);
				label.append(walletChoice, ' استفاده از اعتبار کیف پول تا ' + moneyText(contribution));
			}
			table.removeAttribute('aria-hidden');
			delete order.dataset.artnovoSummaryReady;
			order.dataset.artnovoNativeReview = '1';
			adjustments?.replaceChildren();
			return true;
		}
		delete order.dataset.artnovoNativeReview;
		// WooCommerce remains the source of totals, discounts, taxes and fees.
		table.setAttribute('aria-hidden', 'true');
		order.dataset.artnovoSummaryReady = '1';
		for (const output of totals) {
			output.replaceChildren(...Array.from(amount.childNodes, (node) => node.cloneNode(true)));
			localizeMoney(output);
		}
		if (adjustments) {
			adjustments.replaceChildren();
			for (const row of table.querySelectorAll('tfoot tr:not(.cart-subtotal):not(.order-total)')) {
				const value = row.querySelector('td');
				if (!value) continue;
				const item = document.createElement('div');
				const label = document.createElement('span');
				const detail = document.createElement('span');
				label.textContent = row.querySelector('th')?.textContent ?? '';
				detail.append(...Array.from(value.childNodes, (node) => node.cloneNode(true)));
				localizeMoney(detail);
				for (const link of detail.querySelectorAll('.woocommerce-remove-coupon')) {
					link.removeAttribute('tabindex');
					link.addEventListener('click', (event) => {
						if (event.button || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
						event.preventDefault();
						Array.from(nativeCheckout.querySelectorAll('.woocommerce-remove-coupon'))
							.find((original) => original.dataset.coupon === link.dataset.coupon)?.click();
					});
				}
				item.append(label, detail);
				adjustments.append(item);
			}
		}
		for (const control of table.querySelectorAll('a, button, input, select, textarea')) control.tabIndex = -1;
		return true;
	}

	function refreshOrderReview(initializing = false) {
		if (!window.jQuery || !nativeCheckout) return Promise.resolve(false);
		cancelNativeReview();
		const body = window.jQuery(document.body);
		return new Promise((resolve) => {
			const timer = setTimeout(() => finish(false), 15000);
			function finish(updated) {
				clearTimeout(timer);
				body.off('updated_checkout.artnovoQuantity', onUpdate);
				resolve(updated);
			}
			cancelNativeReview = () => finish(false);
			function onUpdate() {
				if (selectionPending && !nativeRefreshAllowed || cartIdentity?.value !== state.plan + ':' + quantity) return;
				const displayed = nativeCheckout.querySelector('.woocommerce-checkout-review-order-table .product-quantity')?.textContent;
				const digits = displayed?.replace(/[۰-۹٠-٩]/g, (digit) => String(digit.charCodeAt(0) % 16)).match(/\d+/);
				if (Number(digits?.[0]) === quantity) finish(true);
			}
			body.on('updated_checkout.artnovoQuantity', onUpdate);
			// Native checkout initializes itself. Reuse an in-flight initial review instead of duplicating it.
			if (!initializing && !nativeRequests.size) body.trigger('update_checkout');
		});
	}

	async function changeCheckoutQuantity(plan, nextQuantity) {
		if (!prepare || !cartIdentity || !window.jQuery || quantityPending) return;
		if (order.querySelector('form.processing, #order_review .blockOverlay')) {
			showQuantityStatus('کمی صبر کنید تا مبلغ سفارش به‌روزرسانی شود.', true);
			return;
		}
		quantityPending = true;
		updateAmounts(plan);
		showQuantityStatus('در حال به‌روزرسانی مبلغ سفارش…', true);
		const payload = new FormData(prepare);
		payload.set('artnovo_product', plan.id);
		payload.set('artnovo_quantity', String(nextQuantity));
		payload.set('artnovo_wizard_ajax', '1');
		payload.set('artnovo_wizard_cart', cartIdentity.value);
		try {
			const response = await fetch(prepare.action, { method: 'POST', body: payload, credentials: 'same-origin' });
			const result = await response.json();
			if (followCurrentSelection(result, new URL(prepare.action))) return;
			if (!result.success) {
				showQuantityStatus(result.data?.message || 'تعداد سفارش تغییر نکرد. دوباره تلاش کنید.');
				return;
			}
			updateSelectionRevision(result.data.selection_revision);
			quantity = result.data.quantity;
			cartIdentity.value = result.data.cart_identity;
			reviewCurrent = await refreshOrderReview();
			if (reviewCurrent) confirmedIdentity = cartIdentity.value;
			showQuantityStatus(reviewCurrent ? '' : 'مبلغ تازه نمایش داده نشد. تعداد را دوباره تغییر دهید یا صفحه را تازه کنید.');
		} catch {
			reviewCurrent = false;
			showQuantityStatus('تعداد جدید تأیید نشد. دوباره تلاش کنید یا صفحه را تازه کنید.');
		} finally {
			quantityPending = false;
			updateAmounts(plan);
		}
	}

	function render(next, moveFocus = false) {
		const plan = findPlan(next.plan, next.category);
		const currentPanel = panels.get(next.step);
		if (state && state.step !== next.step) preserveProgressPosition();
		for (const [step, panel] of panels) {
			if (step !== next.step) panel.hidden = true;
		}
		for (const [category, group] of groups) group.hidden = category !== next.category;
		order.dataset.artnovoPlanId = plan?.id ?? '';
		empty.hidden = Boolean(plan);
		selected.hidden = !plan;
		title.textContent = plan?.name ?? '';
		if (state?.plan !== next.plan) {
			quantity = resumeDraft && next.plan === draftPlan ? Number(order.dataset.artnovoDraftQuantity) : 1;
			if (next.plan) resumeDraft = false;
		}
		updateAmounts(plan);
		for (const [category, badge] of durationBadges) {
			const isSelected = category === next.category;
			categories.get(category).setAttribute('aria-pressed', String(isSelected));
			badge.hidden = !isSelected;
		}
		for (const entry of plans) {
			const isSelected = entry === plan;
			entry.card.toggleAttribute('data-artnovo-selected-card', isSelected);
			entry.label.hidden = isSelected;
			entry.badge.hidden = !isSelected;
			if (isSelected) entry.link.setAttribute('aria-current', 'true');
			else entry.link.removeAttribute('aria-current');
		}
		updateStepAvailability(next, plan);
		for (const [step, button] of steps) {
			if (step === next.step) button.setAttribute('aria-current', 'step');
			else button.removeAttribute('aria-current');
		}
		progress.dataset.artnovoProgress = next.step;
		progress.style.setProperty('--artnovo-progress-fill', String(Number(next.step) / 3));
		if (state && state.step !== next.step) {
			currentPanel.dataset.artnovoDirection = next.step < state.step ? 'back' : 'forward';
		}
		currentPanel.hidden = false;
		state = next;
		if (selectionPending && !next.plan) abandonSelection();

		if (moveFocus) {
			const heading = focusStepHeading(next);
			const { top, bottom } = heading.getBoundingClientRect();
			const spaceAbove = top - Math.max(0, progress.getBoundingClientRect().bottom);
			const overflowBelow = Math.max(0, bottom - window.innerHeight);
			const scrollDelta = spaceAbove < 0 ? spaceAbove : Math.min(spaceAbove, overflowBelow);
			if (scrollDelta) window.scrollBy({ top: scrollDelta, behavior: 'instant' });
		}
	}

	function updateStepAvailability(next, plan) {
		for (const [step, button] of steps) {
			button.disabled = quantityPending || authPending || step === '2' && !next.category || step === '3' && !plan;
		}
	}

	function go(input, purchaseAction = '') {
		if (quantityPending || authPending || nativeCheckout?.classList.contains('processing')) return;
		const next = normalize(input);
		writeLocation(next);
		render(next, true);
		if (next.step === '3' && (purchaseAction || !reviewCurrent || next.plan + ':' + quantity !== confirmedIdentity)) prepareCheckout(next.plan, quantity, purchaseAction);
	}

	root.addEventListener('click', (event) => {
		if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
		if ((quantityPending || authPending || nativeCheckout?.classList.contains('processing')) && event.target.closest('[data-artnovo-category], [data-artnovo-go], article a[href], .artnovo-order-change')) {
			event.preventDefault();
			return;
		}
		const category = event.target.closest('button[data-artnovo-category]');
		if (category && !category.disabled) {
			event.preventDefault();
			const chosen = category.dataset.artnovoCategory;
			go({ step: '2', category: chosen, plan: chosen === state.category ? state.plan : null });
			return;
		}
		const step = event.target.closest('button[data-artnovo-go]');
		if (step && !step.disabled && step.dataset.artnovoGo !== state.step) {
			go({ ...state, step: step.dataset.artnovoGo });
			return;
		}
		const quantityButton = event.target.closest('button[data-artnovo-quantity-step]');
		if (quantityButton && !quantityButton.disabled && (quantityButton === decrease || quantityButton === increase)) {
			const next = quantity + (quantityButton === increase ? 1 : -1);
			const plan = findPlan(state.plan, state.category);
			if (review && reviewCurrent && !selectionPending && plan) void changeCheckoutQuantity(plan, next);
			else {
				quantity = next;
				updateAmounts(plan);
				if (plan) prepareCheckout(plan.id, quantity);
			}
			return;
		}
		if (event.target.closest('[data-artnovo-retry-prepare]')) {
			prepareCheckout(state.plan, quantity);
			return;
		}
		const link = event.target.closest('a[href]');
		if (!link) return;
		const href = link.getAttribute('href');
		if (href === '#artnovo-plans' || href === '#artnovo-categories') {
			event.preventDefault();
			go({ ...state, step: href === '#artnovo-plans' && state.category ? '2' : '1' });
			return;
		}
		const plan = plans.find((entry) => entry.link === link && categoryAvailable(entry.category));
		if (!plan) return;
		event.preventDefault();
		if (!selectionForm || !window.crypto?.getRandomValues) location.assign(link.href);
		else {
			const action = Array.from(window.crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('');
			go({ step: '3', category: plan.category, plan: plan.id }, action);
		}
	});

	root.addEventListener('toggle', (event) => {
		if (!planDetails.has(event.target) || !event.target.open) return;
		for (const detail of planDetails) {
			if (detail !== event.target && detail.open) detail.open = false;
		}
	}, true);
	root.addEventListener('focusin', (event) => {
		if (!matchMedia('(min-width: 768px)').matches) return;
		const card = event.target.closest('article');
		if (!plans.some((plan) => plan.card === card)) return;
		for (const detail of planDetails) {
			if (detail.open && !card.contains(detail)) detail.open = false;
		}
	});
	root.addEventListener('keydown', (event) => {
		if (event.key !== 'Escape' || event.defaultPrevented) return;
		const detail = event.target.closest('details[open]');
		if (!planDetails.has(detail)) return;
		event.preventDefault();
		detail.open = false;
		detail.querySelector('summary')?.focus({ preventScroll: true });
	});

	footer?.addEventListener('click', (event) => {
		if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
		const link = event.target.closest?.('a[href]');
		if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;
		const url = new URL(link.href);
		if (url.origin !== location.origin || url.pathname !== '/' || url.hash !== '#artnovo-categories') return;
		event.preventDefault();
		event.stopPropagation();
		focusStepHeading(state);
		root.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
	}, true);

	window.addEventListener('scroll', () => {
		if (window.scrollY === 0) document.body.style.removeProperty(scrollRoom);
	}, { passive: true });
	mobile.addEventListener('change', () => document.body.style.removeProperty(scrollRoom));
	window.addEventListener('popstate', (event) => {
		if (quantityPending || authPending) writeLocation(state);
		else {
			const next = readLocation(event.state?.artnovoWizard);
			// History is not a new explicit purchase. Resolve it from current server state without replaying a POST.
			if (next.step === '3' && (selectionPending || next.plan + ':' + quantity !== confirmedIdentity)) recoverPurchaseHistory(true);
			else render(next, true);
		}
	});
	render(readLocation(history.state?.artnovoWizard));
	document.addEventListener('artnovo:auth-busy', (event) => {
		authPending = Boolean(event.detail?.pending);
		updateAmounts(findPlan(state.plan, state.category));
		updateStepAvailability(state, findPlan(state.plan, state.category));
	});
	if (state.step === '3' && !review && (form || order.dataset.artnovoAutoPrepare === '1')) prepareCheckout(state.plan, quantity);
	bindNativeEvents();
	const walletChoiceSelector = '.wallet-pay-partial input[name="partial_pay_through_wallet"]';
	order.addEventListener('click', (event) => {
		if (event.target.matches(walletChoiceSelector) && event.target.dataset.artnovoWalletPending) event.preventDefault();
	}, true);
	order.addEventListener('change', (event) => {
		if (!event.target.matches(walletChoiceSelector)) return;
		// Woo replaces this control when it recalculates from TeraWallet's updated session.
		event.target.dataset.artnovoWalletPending = '1';
		syncNativeSummary();
	}, true);
	order.addEventListener('submit', (event) => {
		const walletUpdating = nativeCheckout?.querySelector(walletChoiceSelector)?.dataset.artnovoWalletPending;
		if (event.target === form && (selectionPending || selectionRecovery)
			|| event.target === nativeCheckout && walletUpdating
			|| (event.target === nativeCheckout || event.target.matches('form.checkout_coupon')) && (selectionPending || quantityPending || !reviewCurrent)) {
			event.preventDefault();
			event.stopImmediatePropagation();
			showQuantityStatus('ابتدا صبر کنید مبلغ سفارش به‌روزرسانی شود یا صفحه را تازه کنید.', quantityPending);
		}
	}, true);

})();
