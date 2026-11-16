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
	const nativeCheckout = order.querySelector('.artnovo-order-native form.checkout');
	const cartIdentity = nativeCheckout?.elements.namedItem('artnovo_wizard_cart');
	const quantityStatus = order.querySelector('[data-artnovo-quantity-status]');
	const productInput = form?.elements.namedItem('artnovo_product');
	const quantityInput = form?.elements.namedItem('artnovo_quantity');
	const adjustments = order.querySelector('[data-artnovo-adjustments]');
	const draftPlan = order.dataset.artnovoDraftPlan;
	let resumeDraft = Boolean(draftPlan);
	const review = order.dataset.artnovoCheckoutReview === '1';
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
	let reviewCurrent = true;
	let authPending = form?.dataset.artnovoAuthPending === '1';

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
			const name = card.querySelector('[data-artnovo-plan-title], .elementor-widget-theme-post-excerpt')?.textContent.trim();
			const amount = card.querySelector('ins .woocommerce-Price-amount') ?? card.querySelector('.woocommerce-Price-amount');
			const currency = amount?.querySelector('.woocommerce-Price-currencySymbol')?.textContent.trim();
			if (name && amount && currency) {
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

	function updateAmounts(plan) {
		const canPrice = Number.isSafeInteger(plan?.unitAmount);
		const formatAmount = (amount) => `${priceFormat.format(amount / 10 ** priceDecimals)}\u00a0${order.dataset.artnovoCurrency}`;
		price.textContent = canPrice ? formatAmount(plan.unitAmount) : plan?.price ?? '';
		if (productInput) productInput.value = plan?.id ?? '';
		if (quantityInput) quantityInput.value = String(quantity);
		if (form) form.dataset.artnovoAuthAvailable = checkoutPlans.has(plan?.id) ? '1' : '0';
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

	function prepareCheckout(planId, count) {
		if (!prepare || !checkoutPlans.has(planId)) return false;
		if (!preparing) {
			preparing = true;
			prepare.elements.namedItem('artnovo_product').value = planId;
			prepare.elements.namedItem('artnovo_quantity').value = String(count);
			const email = nativeCheckout?.elements.namedItem('billing_email');
			if (email) {
				const field = document.createElement('input');
				field.type = 'hidden';
				field.name = 'artnovo_email';
				field.value = email.value;
				prepare.append(field);
			}
			prepare.submit();
		}
		return true;
	}

	function updateSelectionRevision(value) {
		if (typeof value !== 'string' || !/^[a-f0-9]{64}$/.test(value)) return;
		for (const field of order.querySelectorAll('[name="artnovo_selection_revision"]')) field.value = value;
	}

	function followCurrentSelection(result, endpoint) {
		if (!(result.data?.refresh_required || result.success && result.data?.selection_changed) || typeof result.data.redirect !== 'string') return false;
		const target = new URL(result.data.redirect, endpoint);
		if (target.origin !== location.origin || target.pathname !== endpoint.pathname || target.username || target.password) return false;
		location.replace(target.href);
		return true;
	}

	function showQuantityStatus(message) {
		if (!quantityStatus) return;
		quantityStatus.textContent = message;
		quantityStatus.hidden = !message;
	}

	function labelBankTransferButton() {
		if (nativeCheckout?.querySelector('[name="payment_method"]:checked')?.value !== 'bacs') return;
		const button = nativeCheckout.querySelector('#place_order');
		if (button) button.value = button.textContent = 'ثبت سفارش و نمایش اطلاعات واریز';
	}

	function syncNativeSummary() {
		const table = nativeCheckout?.querySelector('.woocommerce-checkout-review-order-table');
		const amount = table?.querySelector('.order-total td');
		if (!amount) return false;
		// WooCommerce remains the source of totals, discounts, taxes and fees.
		table.setAttribute('aria-hidden', 'true');
		order.dataset.artnovoSummaryReady = '1';
		for (const output of totals) output.replaceChildren(...Array.from(amount.childNodes, (node) => node.cloneNode(true)));
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

	function refreshOrderReview() {
		const body = window.jQuery(document.body);
		return new Promise((resolve) => {
			const timer = setTimeout(() => finish(false), 15000);
			function finish(updated) {
				clearTimeout(timer);
				body.off('updated_checkout.artnovoQuantity', onUpdate);
				resolve(updated);
			}
			function onUpdate() {
				const displayed = nativeCheckout.querySelector('.woocommerce-checkout-review-order-table .product-quantity')?.textContent;
				const digits = displayed?.replace(/[۰-۹٠-٩]/g, (digit) => String(digit.charCodeAt(0) % 16)).match(/\d+/);
				if (Number(digits?.[0]) === quantity) finish(true);
			}
			body.on('updated_checkout.artnovoQuantity', onUpdate);
			body.trigger('update_checkout');
		});
	}

	async function changeCheckoutQuantity(plan, nextQuantity) {
		if (!prepare || !cartIdentity || !window.jQuery || quantityPending) return;
		if (order.querySelector('form.processing, #order_review .blockOverlay')) {
			showQuantityStatus('کمی صبر کنید تا مبلغ سفارش به‌روزرسانی شود.');
			return;
		}
		quantityPending = true;
		updateAmounts(plan);
		showQuantityStatus('در حال به‌روزرسانی مبلغ سفارش…');
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
		if (state?.plan !== next.plan && (!review || !state)) {
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

	function go(input) {
		if (quantityPending || authPending) return;
		const next = normalize(input);
		if (next.step === '3' && (!review || next.plan !== draftPlan) && prepareCheckout(next.plan, next.plan === draftPlan ? Number(order.dataset.artnovoDraftQuantity) : 1)) return;
		writeLocation(next);
		render(next, true);
	}

	root.addEventListener('click', (event) => {
		if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
		if ((quantityPending || authPending) && event.target.closest('[data-artnovo-category], [data-artnovo-go], article a[href], .artnovo-order-change')) {
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
			if (review && plan) void changeCheckoutQuantity(plan, next);
			else {
				quantity = next;
				updateAmounts(plan);
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
		// An explicit card choice starts from fresh server selection state, unlike Back/replay.
		location.assign(link.href);
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
		else render(readLocation(event.state?.artnovoWizard), true);
	});
	render(readLocation(history.state?.artnovoWizard));
	document.addEventListener('artnovo:auth-busy', (event) => {
		authPending = Boolean(event.detail?.pending);
		updateAmounts(findPlan(state.plan, state.category));
		updateStepAvailability(state, findPlan(state.plan, state.category));
	});
	if (order.dataset.artnovoAutoPrepare === '1') prepareCheckout(state.plan, quantity);
	if (nativeCheckout && window.jQuery) {
		const body = window.jQuery(document.body);
		const couponForm = window.jQuery(order.querySelector('form.checkout_coupon'));
		body.on('updated_checkout payment_method_selected', () => {
			labelBankTransferButton();
			syncNativeSummary();
		});
		body.on('applied_coupon_in_checkout removed_coupon_in_checkout', () => {
			// Finish WooCommerce's collapse before paint, keeping its notice callback.
			queueMicrotask(() => couponForm.stop(true, true).show());
		});
		labelBankTransferButton();
	}
	order.addEventListener('submit', (event) => {
		if ((event.target === nativeCheckout || event.target.matches('form.checkout_coupon')) && (quantityPending || !reviewCurrent)) {
			event.preventDefault();
			event.stopImmediatePropagation();
			showQuantityStatus('ابتدا صبر کنید مبلغ سفارش به‌روزرسانی شود یا صفحه را تازه کنید.');
		}
	}, true);

})();
