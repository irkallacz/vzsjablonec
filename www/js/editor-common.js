const commands = {
	h2: {name: 'h2', title: 'Největší nadpis', innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-header"></use></svg>'},
	bold: {name: 'bold', title: 'Tučně', innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-bold"></use></svg>'},
	italic: {name: 'italic', title: 'Kurzíva', innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-italic"></use></svg>'},
	insertLink: {name: 'insertLink', title: 'Odkaz',  innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-chain"></use></svg>',
		action: editor => {
			let dest = window.prompt('Adresa odkazu');
			if (dest) editor.wrapSelection('"', `":${dest}`);
		}
	},
	insertImage: {name: 'insertImage', title: 'Obrázek',  innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-image"></use></svg>',
		action: editor => {
			let dest = window.prompt('Adresa obrázku');
			if (dest) editor.wrapSelection(`[* ${dest} .(`, ') *]');
		}
	},
	ul: {name: 'ul', title: 'Seznam', innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-list-ul"></use></svg>' },
	ol: {name: 'ol', title: 'Číslovaný seznam', innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-list-ol"></use></svg>'},
	code: {name: 'code', title: 'Kód', innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-code"></use></svg>' },
	blockquote: {name: 'blockquote', title: 'Citace', innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-quote-left"></use></svg>'},
	hr: {
		name: 'hr', title: 'Čára', innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-minus"></use></svg>',
		action: editor => editor.paste('\n-------------------\n'),
	},
	syntax: {
		name: 'syntax', title: 'Nápověda Texy', innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-question-circle"></use></svg>',
		action: editor => window.open('https://texy.info/cs/syntax', '_blank')
	}
};

function createPreview(previewUrl, selector) {
	return {name: 'preview', title: 'Náhled', innerHTML: '<svg height="18" width="18"><use xlink:href="/img/symbols.svg#icon-eye"></use></svg>',
		action: editor => {
			const content = editor.getContent();
			if (content) {
				const response = fetch(previewUrl, {
						method: 'POST',
						body: new URLSearchParams({ texy: content }),
						headers: {'X-Requested-With': 'XMLHttpRequest'}
					}
				).then(response => response.text()
				).then(response => {
					const dialog = document.querySelector(selector);
					dialog.querySelector('.editor-preview').innerHTML = response
					dialog.showModal();
				});
			}
		}
	}
}

function editorPreviewDialogClose(event) {
	event.preventDefault();
	//document.querySelector('.editor-dialog').close();
	this.parentNode.parentNode.close();
}