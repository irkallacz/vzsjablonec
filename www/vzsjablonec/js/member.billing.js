function recount() {
	var income = 0;
	var expense = 0;

	document.querySelectorAll('#billing table.edit tr.row').forEach(function (row) {
		const category  = row.parentElement.parentElement.id;
		const final     = parseFloat(row.querySelector('input.price').value) * parseInt(row.querySelector('input.count').value);
		row.querySelector('input.final').value = final;
		if (category === 'incomes') 	income += final;
		if (category === 'expenses') 	expense += final;
	});

	document.getElementById('billing-incomes').value 	= income;
	document.getElementById('billing-expenses').value 	= expense;
	document.getElementById('billing-final').value 		= income - expense;
}

document.querySelectorAll('input.price, input.count').forEach(function (el) {
	el.addEventListener('change', recount);
});

recount();