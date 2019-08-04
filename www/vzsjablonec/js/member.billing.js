function recount(name, oldPrice, newPrice) {
	var el = document.getElementById('billing-'+name);
	const value = parseFloat(el.value);
	el.value = value + newPrice - oldPrice;

	const incomes = parseFloat(document.getElementById('billing-incomes').value);
	const expenses = parseFloat(document.getElementById('billing-expenses').value);
	document.getElementById('billing-final').value = incomes - expenses;
}

document.querySelectorAll('input.remove').forEach(function (el) {
	el.addEventListener('click', function () {
		const row       = el.parentElement.parentElement;
		const category  = row.parentElement.parentElement.id;
		const old       = parseFloat(row.querySelector('input.final').value);

		recount(category, old, 0);
		row.remove();
	});
});

document.querySelectorAll('input.price, input.count').forEach(function (el) {
	el.addEventListener('change', function () {
		const row       = el.parentElement.parentElement;
		const category  = row.parentElement.parentElement.id;
		const final     = parseFloat(row.querySelector('input.price').value) * parseInt(row.querySelector('input.count').value);
		var   field     = row.querySelector('input.final');
		const old       = parseFloat(field.value);
		field.value = final;

		recount(category, old, final);
	})
});
