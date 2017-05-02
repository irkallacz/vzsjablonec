// nastavení
$.texyla.setDefaults({
	emoticonPath: "%texyla_base%/emoticons/%var%.gif",
	emoticons: {
		':D'        :  '01',
		':p'        :  '02',
		'8)'        :  '03',
		';)'        :  '04',
		':)'        :  '05',
		':?'        :  '06',
		':|'        :  '07',
		':roll:'    :  '08',
		':cry:'     :  '09',
		':bored:'   :  '10',
		':dead:'    :  '11',
		':shock:'   :  '12',
		':evil:'    :  '13',
		':sick:'    :  '14',
		':oops:'    :  '15',
		':love:'    :  '16',
		':('        :  '17',
		':twisted:' :  '18',
		':lol:'     :  '19',
		':?:'       :  '20',
		':!:'       :  '21',
		'(y)'       :  '22'
	}
});

$.texyla.initPlugin(function () {
	this.options.emoticonPath = this.expand(this.options.emoticonPath);
});

$.texyla.addWindow("emoticon", {
	createContent: function () {
		var _this = this;

		var emoticons = $('<div></div>');
		var emoticonsEl = $('<div class="emoticons"></div>').appendTo(emoticons);

		// projít smajly
		for (var i in this.options.emoticons) {
			function emClk(emoticon) {
				return function () {
					_this.texy.replace(emoticon);

					if (emoticons.find("input.close-after-insert").get(0).checked) {
						emoticons.dialog("close");
					}
				}
			};

			$(
				"<img src='" + this.options.emoticonPath.replace("%var%", this.options.emoticons[i]) +
				"' title='" + i + "' alt='" + i + "' class='ui-state-default'>"
			)
				.hover(function () {
					$(this).addClass("ui-state-hover");
				}, function () {
					$(this).removeClass("ui-state-hover");
				})
				.click(emClk(i))
				.appendTo(emoticonsEl);
		}

		emoticons.append("<br><label><input type='checkbox' checked class='close-after-insert'> " + this.lng.windowCloseAfterInsert + "</label>");

		return emoticons;
	},

	dimensions: [192, 170]
});