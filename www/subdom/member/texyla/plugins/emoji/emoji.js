// nastavení
$.texyla.setDefaults({
	emoji: [
		'😀',
		'😋',
		'😎',
		'😉',
		'😃',
		'😕',
		'😐',
		'🙄',
		'😪',
		'😴',
		'💀',
		'😲',
		'😠',
		'🤢',
		'🤨',
		'❤️',
		'☹',
		'😈',
		'🤣',
		'🤬',
		'🤪',
		'😵',
		'🤑',
		'🤐',
		'💋',
		'👍'
	]
});

$.texyla.addWindow('emoji', {
	createContent: function () {
		var _this = this;

		var dialogWindow = $('<div></div>');
		var emojiElement = $('<div class="emoji"></div>').appendTo(dialogWindow);

		function place(emoji) {
			return function () {
				_this.texy.replace(emoji);
				dialogWindow.dialog('close');
			}
		}

		for (var i in this.options.emoji) {
			var emoji = this.options.emoji[i];
			$('<span>'+emoji+'</span>')
				.hover(function () {
					$(this).addClass('ui-state-hover');
				}, function () {
					$(this).removeClass('ui-state-hover');
				})
				.click(place(emoji))
				.appendTo(emojiElement);
		}

		return dialogWindow;
	},
	title: 'Vložit emoji',
	dimensions: [350, 145]
});