(() => {
	const disclosure = document.querySelector('.artnovo-account-shell [data-artnovo-account-more]');
	const summary = disclosure?.querySelector('summary');
	if (!summary) return;

	disclosure.addEventListener('keydown', event => {
		if (event.key !== 'Escape' || !disclosure.open) return;
		event.preventDefault();
		disclosure.open = false;
		summary.focus();
	});

	document.addEventListener('click', event => {
		if (disclosure.open && !disclosure.contains(event.target)) disclosure.open = false;
	});
})();
