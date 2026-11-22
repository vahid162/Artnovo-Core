(() => {
	const page = document.querySelector('.artnovo-wallet-page');
	if (!page) return;

	const input = page.querySelector('#woo_wallet_balance_to_add');
	const topupButton = input?.form?.querySelector('[data-artnovo-topup-cta]');
	if (topupButton) {
		const format = new Intl.NumberFormat('fa-IR');
		const updateTopupButton = () => {
			topupButton.textContent = input.value && input.validity.valid
				? `پرداخت ${format.format(Number(input.value))} ${page.dataset.currencyLabel}`
				: 'پرداخت شارژ';
		};
		input.addEventListener('input', updateTopupButton);
		input.form.addEventListener('submit', (event) => {
			if (topupButton.disabled) {
				event.preventDefault();
				return;
			}
			topupButton.disabled = true;
			topupButton.textContent = 'در حال اتصال به درگاه…';
		});
		window.addEventListener('pageshow', () => {
			topupButton.disabled = false;
			updateTopupButton();
		});
		updateTopupButton();
	}

	if (input && page.dataset.currency === 'IRT') {
		const minimum = Number(input.min) || 0;
		const maximum = input.max ? Number(input.max) : Infinity;
		const presets = [100000, 200000, 500000].filter(value => value >= minimum && value <= maximum);
		if (presets.length) {
			const group = document.createElement('div');
			group.className = 'artnovo-wallet-presets';
			group.setAttribute('role', 'group');
			group.setAttribute('aria-label', 'مبلغ‌های پیشنهادی به تومان');
			const format = new Intl.NumberFormat('fa-IR');
			for (const amount of presets) {
				const button = document.createElement('button');
				button.type = 'button';
				button.textContent = format.format(amount);
				button.dataset.amount = amount;
				button.setAttribute('aria-pressed', 'false');
				button.addEventListener('click', () => {
					input.value = String(amount);
					input.dispatchEvent(new Event('input', { bubbles: true }));
				});
				group.append(button);
			}
			input.after(group);
			input.addEventListener('input', () => {
				for (const button of group.children) {
					button.setAttribute('aria-pressed', String(Number(input.value) === Number(button.dataset.amount)));
				}
			});
		}
	}

	const form = page.querySelector('#woo_wallet_transfer_form');
	const submit = form?.querySelector('input[name="woo_wallet_transfer_fund"][type="submit"]');
	const amount = form?.querySelector('#woo_wallet_transfer_amount');
	const recipient = form?.querySelector('#woo_wallet_transfer_user_id');
	const dialog = document.createElement('dialog');
	if (!submit || !amount || !recipient || typeof form.requestSubmit !== 'function' || typeof dialog.showModal !== 'function') return;
	dialog.className = 'artnovo-wallet-transfer-dialog';
	dialog.setAttribute('aria-labelledby', 'artnovo-wallet-review-label');
	dialog.innerHTML = '<h2 id="artnovo-wallet-review-label">بررسی انتقال</h2><p data-amount-summary></p><p data-recipient-summary></p><p>کارمزد، در صورت وجود، طبق تنظیمات کیف پول اعمال می‌شود.</p><div><button type="button" data-confirm>تأیید انتقال</button><button type="button" data-cancel autofocus>بازگشت و ویرایش</button></div>';
	page.append(dialog);
	submit.value = 'بررسی انتقال';
	let confirmed = false;
	form.addEventListener('submit', event => {
		if (confirmed) {
			confirmed = false;
			return;
		}
		event.preventDefault();
		event.stopImmediatePropagation();
		const value = new Intl.NumberFormat('fa-IR', { maximumFractionDigits: 2 }).format(Number(amount.value));
		const selected = recipient.selectedOptions[0];
		dialog.querySelector('[data-amount-summary]').textContent = 'مبلغ درخواستی: ' + value + ' ' + page.dataset.currencyLabel;
		dialog.querySelector('[data-recipient-summary]').textContent = 'گیرنده: ' + (selected?.textContent.trim() || 'گیرندهٔ انتخاب‌شده');
		if (!dialog.open) dialog.showModal();
	}, true);
	dialog.querySelector('[data-cancel]').addEventListener('click', () => dialog.close());
	dialog.querySelector('[data-confirm]').addEventListener('click', () => {
		dialog.close();
		confirmed = true;
		form.requestSubmit(submit);
		confirmed = false;
	});
})();
