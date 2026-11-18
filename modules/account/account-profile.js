(() => {
	'use strict';

	const form = document.querySelector('.artnovo-profile-form');
	if (!form) return;

	const navigation = form.querySelector('.artnovo-profile-navigation');
	const sections = [...form.querySelectorAll('.artnovo-profile-section')];
	const controls = [...navigation.querySelectorAll('[aria-controls]')];
	const desktop = window.matchMedia('(min-width: 720px)');

	function updateNavigation() {
		if (desktop.matches && !sections.some(section => section.open)) sections[0].open = true;
		for (const [index, section] of sections.entries()) {
			controls[index].setAttribute('aria-expanded', String(section.open));
		}
	}

	for (const section of sections) section.addEventListener('toggle', updateNavigation);
	for (const [index, control] of controls.entries()) {
		control.addEventListener('click', () => {
			for (const [sectionIndex, section] of sections.entries()) section.open = sectionIndex === index;
			updateNavigation();
		});
	}
	desktop.addEventListener('change', updateNavigation);

	form.addEventListener('invalid', event => {
		const section = event.target.closest('.artnovo-profile-section');
		if (section) section.open = true;
	}, true);

	const errors = form.closest('.artnovo-account-native')?.querySelectorAll('.artnovo-profile-notices .woocommerce-error [data-id]') ?? [];
	for (const [index, error] of [...errors].entries()) {
		const field = form.elements.namedItem(error.dataset.id);
		if (!(field instanceof HTMLInputElement)) continue;
		error.id = 'artnovo-profile-field-error-' + index;
		const description = field.getAttribute('aria-describedby');
		field.setAttribute('aria-describedby', description ? description + ' ' + error.id : error.id);
		field.setAttribute('aria-invalid', 'true');
	}

	form.classList.add('is-enhanced');
	form.noValidate = false;
	navigation.hidden = false;
	updateNavigation();
})();
