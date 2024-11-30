document.querySelector('form').addEventListener('input', event => {
	if (!window.onbeforeunload) {
		window.addEventListener('beforeunload', event =>{
			event.preventDefault();
		})
	}
});
