(() => {
	const order = document.getElementById('artnovo-order');
	const root = order?.closest('[data-elementor-id]');
	if (!root) return;

	const empty = order.querySelector('[data-artnovo-empty]');
	const selected = order.querySelector('[data-artnovo-selected]');
	const title = order.querySelector('[data-artnovo-title]');
	const price = order.querySelector('[data-artnovo-price]');
	const tabs = Array.from(root.querySelectorAll('[role="tab"]'));
	const steps = Array.from(root.querySelectorAll('[data-artnovo-step]'));
	const plans = new Map();
	for (const link of root.querySelectorAll('article a[href]')) {
		const url = new URL(link.href);
		const id = url.searchParams.get('artnovo_plan');
		if (url.origin !== location.origin || url.hash !== '#artnovo-order' || !/^\d+$/.test(id ?? '')) continue;
		const card = link.closest('article');
		const name = card.querySelector('.elementor-widget-theme-post-excerpt')?.textContent.trim();
		const amount = card.querySelector('.woocommerce-Price-amount');
		const currency = amount?.querySelector('.woocommerce-Price-currencySymbol')?.textContent.trim();
		if (!name || !amount || !currency) continue;
		plans.set(id, { link, name, price: `${amount.textContent.replace(currency, '').trim()} ${currency}`, panel: card.closest('[role="tabpanel"]') });
	}

	const tabFor = (panel) => tabs.find((tab) => tab.getAttribute('aria-controls') === panel?.id);
	const activeTab = () => tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') ?? tabs[0];
	const panelFor = (tab) => tab && document.getElementById(tab.getAttribute('aria-controls'));
	const markStep = (index) => steps.forEach((step, offset) => {
		if (offset === index) step.setAttribute('aria-current', 'step');
		else step.removeAttribute('aria-current');
	});
	function focusAndScroll(element) {
		if (!element) return;
		if (!element.hasAttribute('tabindex')) element.tabIndex = -1;
		element.focus({ preventScroll: true });
		element.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
	}
	function showPlan(id) {
		const plan = plans.get(id);
		order.dataset.artnovoPlanId = plan ? id : '';
		empty.hidden = Boolean(plan);
		selected.hidden = !plan;
		title.textContent = plan?.name ?? '';
		price.textContent = plan?.price ?? '';
		for (const entry of plans.values()) {
			if (entry === plan) entry.link.setAttribute('aria-current', 'true');
			else entry.link.removeAttribute('aria-current');
		}
	}
	function updateUrl(id, hash) {
		const url = new URL(location.href);
		if (id) url.searchParams.set('artnovo_plan', id);
		else url.searchParams.delete('artnovo_plan');
		url.hash = hash;
		if (url.href !== location.href) history.pushState(null, '', url);
	}
	function restoreSelection() {
		const id = new URLSearchParams(location.search).get('artnovo_plan');
		const plan = plans.get(id);
		if (plan) {
			const tab = tabFor(plan.panel);
			if (tab && tab !== activeTab()) tab.click();
		}
		if (!id || plan || !order.dataset.artnovoPlanId) showPlan(id);
		markStep(order.dataset.artnovoPlanId ? 2 : 0);
	}

	root.addEventListener('click', (event) => {
		if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
		const tab = event.target.closest('[role="tab"]');
		if (tabs.includes(tab) && event.isTrusted) {
			requestAnimationFrame(() => {
				const panel = panelFor(tab);
				const plan = plans.get(order.dataset.artnovoPlanId);
				if (plan && plan.panel !== panel) {
					showPlan(null);
					updateUrl(null, '#artnovo-plans');
				}
				markStep(1);
				focusAndScroll(panel?.querySelector('h2'));
			});
			return;
		}

		const method = event.target.closest('[data-artnovo-method]');
		if (method && order.contains(method)) {
			order.querySelectorAll('[data-artnovo-method]').forEach((item) => item.setAttribute('aria-pressed', String(item === method)));
			return;
		}

		const link = event.target.closest('a[href]');
		if (!link) return;
		const href = link.getAttribute('href');
		if (href === '#artnovo-plans' || href === '#artnovo-categories') {
			event.preventDefault();
			const current = activeTab();
			markStep(href === '#artnovo-plans' ? 1 : 0);
			focusAndScroll(href === '#artnovo-plans' ? panelFor(current)?.querySelector('h2') : current);
			return;
		}
		const id = new URL(link.href).searchParams.get('artnovo_plan');
		if (plans.get(id)?.link !== link) return;
		event.preventDefault();
		showPlan(id);
		updateUrl(id, '#artnovo-order');
		markStep(2);
		focusAndScroll(order);
	});

	order.querySelector('form')?.addEventListener('submit', (event) => event.preventDefault());
	window.addEventListener('popstate', restoreSelection);
	window.addEventListener('load', restoreSelection, { once: true });
})();
