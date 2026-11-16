(() => {
	'use strict';
	if (!document.body || typeof MutationObserver !== 'function' || document.getElementById('artnovo-toasts')) return;
	const selector = [
		'.woocommerce-message', '.woocommerce-error', '.coupon-error-notice',
		'.woocommerce-notices-wrapper .woocommerce-info', '.woocommerce-NoticeGroup .woocommerce-info',
		'.wc-block-components-notice-banner.is-error', '.wc-block-components-notice-banner.is-success',
		'.woocommerce-notices-wrapper .wc-block-components-notice-banner.is-info',
		'[data-artnovo-auth-message]', '[data-artnovo-quantity-status]', '[pinova-text$="errorMsg"]', '.wpvpn-subscription-link [role="status"]',
		'[data-artnovo-copy-status]', '.notyf__toast'
	].join(',');
	const persistent = '.artnovo-received-status,.artnovo-account-subscriptions,.woocommerce-form-login-toggle,.woocommerce-form-coupon-toggle,.cart-empty,[data-artnovo-notice-permanent]';
	const states = new WeakMap();
	const dirty = new WeakSet();
	const active = new Set();
	const notyfMessages = new Set();
	const announcerStates = new WeakMap();
	const region = document.createElement('section');
	region.id = 'artnovo-toasts';
	region.className = 'artnovo-toasts';
	region.setAttribute('aria-label', 'اعلان‌ها');
	const announcer = document.createElement('div');
	announcer.className = 'artnovo-toast-announcer';
	announcer.setAttribute('role', 'status');
	announcer.setAttribute('aria-live', 'polite');
	announcer.setAttribute('aria-atomic', 'true');
	document.body.append(region, announcer);

	function attributes(node) {
		return { live: node.getAttribute('aria-live'), hidden: node.getAttribute('aria-hidden') };
	}
	function restore(node, saved) {
		for (const [name, value] of [['aria-live', saved.live], ['aria-hidden', saved.hidden]]) {
			if (value === null) node.removeAttribute(name); else node.setAttribute(name, value);
		}
	}
	function release(source, state) {
		if (!state) return;
		state.dismiss?.();
		source.removeAttribute('data-artnovo-toast-source');
		restore(source, state.original);
		state.signature = '';
	}
	function collect(root) {
		if (root.nodeType !== 1 || root.closest('#artnovo-toasts')) return [];
		return [...(root.matches(selector) ? [root] : []), ...root.querySelectorAll(selector)];
	}
	function safeContent(source) {
		const container = document.createElement('div');
		function copy(parent, node) {
			if (node.nodeType === 3) { parent.append(document.createTextNode(node.textContent)); return; }
			if (node.nodeType !== 1 || /^(SCRIPT|STYLE|IFRAME|OBJECT|TEMPLATE|NOSCRIPT|SVG|MATH)$/.test(node.tagName)) return;
			let next = parent;
			if (node.tagName === 'A') {
				try {
					const href = node.getAttribute('href');
					if (!href?.trim()) throw new Error('Missing link destination');
					const url = new URL(href, location.href);
					if (['http:', 'https:'].includes(url.protocol) && !url.username && !url.password) {
						next = document.createElement('a');
						next.href = url.href;
						// Preserve native field links and notice actions without copying their handlers.
						next.addEventListener('click', (event) => { event.preventDefault(); node.click(); });
						parent.append(next);
					}
				} catch { /* Unsafe destinations remain plain text. */ }
			} else if (['UL', 'OL', 'LI', 'P', 'STRONG', 'EM', 'BR', 'BDI'].includes(node.tagName)) {
				next = document.createElement(node.tagName.toLowerCase());
				if (node.tagName === 'BDI' && ['ltr', 'rtl', 'auto'].includes(node.getAttribute('dir'))) next.dir = node.getAttribute('dir');
				parent.append(next);
			}
			for (const child of node.childNodes) copy(next, child);
		}
		const body = source.querySelector('.notyf__message,.wc-block-components-notice-banner__content') || source;
		for (const child of body.childNodes) copy(container, child);
		return container;
	}
	function kind(source) {
		const flag = source.getAttribute('data-error');
		return source.matches('.woocommerce-error,.coupon-error-notice,.is-error,.notyf__toast--error,[pinova-text$="errorMsg"],[data-artnovo-quantity-status]') || flag !== null && !['0', 'false'].includes(flag)
			? 'error' : source.matches('.woocommerce-message,.is-success,.notyf__toast--success') ? 'success' : 'info';
	}
	function show(source, state, content, announce) {
		state.dismiss?.();
		const toast = document.createElement('div');
		toast.className = 'artnovo-toast';
		toast.dataset.kind = kind(source);
		content.className = 'artnovo-toast-body';
		const close = document.createElement('button');
		close.type = 'button';
		close.className = 'artnovo-toast-close';
		close.setAttribute('aria-label', 'بستن اعلان');
		close.textContent = '×';
		const progress = document.createElement('div');
		progress.className = 'artnovo-toast-progress';
		progress.setAttribute('aria-hidden', 'true');
		toast.append(content, close, progress);
		const previousFocus = document.activeElement;
		const pauses = new Set();
		let remaining = 5000;
		let started;
		let timer;
		let dismissed = false;
		const entry = { dismiss, pause };
		function dismiss() {
			if (dismissed) return;
			dismissed = true;
			window.clearTimeout(timer);
			if (toast.contains(document.activeElement) && previousFocus?.isConnected) previousFocus.focus({ preventScroll: true });
			toast.remove();
			active.delete(entry);
		}
		function resume() {
			if (dismissed || pauses.size) return;
			toast.dataset.paused = '0';
			started = performance.now();
			timer = window.setTimeout(dismiss, remaining);
		}
		function pause(reason, value) {
			const wasPaused = pauses.size > 0;
			if (value) pauses.add(reason); else pauses.delete(reason);
			if (!wasPaused && pauses.size) {
				window.clearTimeout(timer);
				remaining = Math.max(0, remaining - (performance.now() - started));
				toast.dataset.paused = '1';
			} else if (wasPaused && !pauses.size) resume();
		}
		close.addEventListener('click', dismiss);
		toast.addEventListener('mouseenter', () => pause('hover', true));
		toast.addEventListener('mouseleave', () => pause('hover', false));
		toast.addEventListener('focusin', () => pause('focus', true));
		toast.addEventListener('focusout', (event) => { if (!toast.contains(event.relatedTarget)) pause('focus', false); });
		state.dismiss = dismiss;
		region.append(toast);
		active.add(entry);
		resume();
		pause('document', document.hidden);
		if (announce) {
			announcer.textContent = '';
			window.setTimeout(() => { announcer.textContent = content.textContent; }, 0);
		}
	}
	function process(source, announce) {
		let state = states.get(source);
		const form = source.closest('form');
		const copy = source.matches('.wpvpn-subscription-link [role="status"],[data-artnovo-copy-status]');
		if (source.closest(persistent) && !copy || source.querySelector('form,input,select,textarea')
			|| source.querySelector('button') && !source.matches('.notyf__toast,.wc-block-components-notice-banner')
			|| source.closest('[hidden]') || source.matches('[data-artnovo-auth-message]') && (form?.getAttribute('aria-busy') === 'true' || form?.dataset.artnovoAuthPending === '1')) {
			release(source, state);
			return;
		}
		const content = safeContent(source);
		const text = content.textContent.trim();
		if (!text) { release(source, state); dirty.delete(source); return; }
		const signature = text + kind(source) + [...content.querySelectorAll('a')].map((link) => link.href).join('|');
		if (state?.signature === signature && !dirty.has(source)) return;
		if (!state) { state = { original: attributes(source) }; states.set(source, state); }
		state.signature = signature;
		dirty.delete(source);
		source.dataset.artnovoToastSource = '1';
		source.setAttribute('aria-live', 'off');
		source.setAttribute('aria-hidden', 'true');
		if (source.matches('.notyf__toast')) {
			notyfMessages.add(text);
			if (notyfMessages.size > 20) notyfMessages.delete(notyfMessages.values().next().value);
		}
		show(source, state, content, announce);
	}
	function muteNotyf() {
		for (const native of document.querySelectorAll('.notyf-announcer')) {
			if (notyfMessages.has(native.textContent.trim())) {
				if (!announcerStates.has(native)) announcerStates.set(native, attributes(native));
				native.setAttribute('aria-live', 'off');
				native.setAttribute('aria-hidden', 'true');
			} else if (announcerStates.has(native)) restore(native, announcerStates.get(native));
		}
	}
	function scan(root, announce = true) {
		for (const source of collect(root)) process(source, announce);
		muteNotyf();
	}
	function patchScroll() {
		const $ = window.jQuery;
		const original = $?.scroll_to_notices;
		if (typeof original !== 'function' || original.artnovoNotices) return;
		function wrapped(target) {
			const nodes = target?.toArray?.() || [];
			const handled = nodes.length > 0 && nodes.every((node) => {
				if (!node.matches?.('.woocommerce-error,.woocommerce-message,.woocommerce-info,.woocommerce-notices-wrapper,.woocommerce-NoticeGroup,.wc-block-components-notice-banner')) return false;
				scan(node);
				const sources = collect(node);
				return sources.length > 0 && sources.every((source) => source.dataset.artnovoToastSource === '1');
			});
			if (!handled) return original.apply(this, arguments);
		}
		wrapped.artnovoNotices = true;
		$.scroll_to_notices = wrapped;
	}
	const observer = new MutationObserver((records) => {
		const candidates = new Set();
		for (const record of records) {
			const target = record.target.nodeType === 1 ? record.target : record.target.parentElement;
			if (!target || target.closest('#artnovo-toasts') || target === announcer) continue;
			const source = target.closest(selector);
			if (source) {
				candidates.add(source);
				if (record.type !== 'attributes') dirty.add(source);
			}
			for (const node of collect(target)) candidates.add(node);
			for (const added of record.addedNodes || []) for (const node of collect(added)) { candidates.add(node); dirty.add(node); }
		}
		for (const source of candidates) process(source, true);
		muteNotyf();
		patchScroll();
	});
	observer.observe(document.body, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['hidden', 'class', 'data-error', 'aria-busy', 'data-artnovo-auth-pending', 'data-artnovo-notice-permanent'] });
	scan(document.body);
	patchScroll();
	document.addEventListener('visibilitychange', () => { for (const entry of active) entry.pause('document', document.hidden); });
	if (window.jQuery) {
		window.jQuery(patchScroll);
		window.jQuery(document.body).on('init_checkout updated_checkout checkout_error added_to_cart wc_fragments_refreshed', () => { patchScroll(); scan(document.body); });
	}
})();
