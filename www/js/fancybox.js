$(document).ready(function() {
	$().fancybox({
		selector: '[data-fancybox="images"]',
		loop    : true,
		hash	: false,
		afterShow: function(fancybox, element) {
			var filename = element.src.substring(element.src.lastIndexOf('/')+1);
			history.replaceState(null, null, window.location.href.split("#")[0]+'#'+filename);
		},
		afterClose: function() {
			history.replaceState(null, null, window.location.pathname);
		}
	});
});
