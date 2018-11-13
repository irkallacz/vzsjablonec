$(function () {
	$('.copy').click(function () {
		var el = this;
		var text = el.querySelector('.text').innerHTML;
		navigator.clipboard.writeText(text).then(function() {
			el.classList.add('blue');
		}, function() {
			el.classList.add('red');
		});
	});
});