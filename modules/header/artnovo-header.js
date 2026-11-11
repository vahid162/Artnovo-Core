(() => {
	function setupHeader() {
		const menus = document.querySelectorAll('[data-elementor-type="header"].elementor-location-header .elementor-widget-nav-menu');
		for (const menu of menus) {
			const toggle = menu.querySelector('.elementor-menu-toggle');
			const menuIsOpen = () => toggle?.getAttribute('aria-expanded') === 'true' || toggle?.classList.contains('elementor-active');
			const closeMenu = () => {
				if (menuIsOpen()) toggle.click();
			};
			if (toggle) {
				if (!toggle.querySelector('.artnovo-header-menu-label')) {
					const label = document.createElement('span');
					label.className = 'artnovo-header-menu-label';
					label.textContent = 'منو';
					label.setAttribute('aria-hidden', 'true');
					toggle.append(label);
				}
				toggle.setAttribute('aria-label', 'منوی اصلی');
				menu.addEventListener('keydown', (event) => {
					if (event.key !== 'Escape' || !menuIsOpen()) return;
					event.preventDefault();
					event.stopPropagation();
					closeMenu();
					toggle.focus();
				});
			}
			menu.addEventListener('click', (event) => {
				if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
				const link = event.target.closest?.('a[href]');
				if (!link || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;
				const url = new URL(link.href);
				if (url.origin !== location.origin || url.pathname !== '/' || url.hash !== '#artnovo-categories') return;
				const wizard = document.querySelector('[data-artnovo-wizard]');
				if (!wizard) return;
				// Keep the active stage and its URL instead of navigating to the first stage.
				event.preventDefault();
				event.stopPropagation();
				closeMenu();
				requestAnimationFrame(() => {
					const stage = wizard.querySelector('[data-artnovo-panel]:not([hidden])');
					if (!stage) return;
					const heading = stage.dataset.artnovoPanel === '2'
						? stage.querySelector('[data-artnovo-group]:not([hidden]) h2')
						: stage.querySelector('h2');
					const focusTarget = heading ?? stage;
					if (!focusTarget.hasAttribute('tabindex')) focusTarget.tabIndex = -1;
					focusTarget.focus({ preventScroll: true });
					wizard.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
				});
			}, true);
		}
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupHeader, { once: true });
	else setupHeader();
})();
