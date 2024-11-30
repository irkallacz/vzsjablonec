const descriptionTinyMDE = new TinyMDE.Editor({ textarea: document.getElementById('frm-akceForm-description') });
const descriptionCommandBar = new TinyMDE.CommandBar({
	element: document.getElementById('frm-akceForm-description-toolbar'),
	editor: descriptionTinyMDE,
	commands: [
		commands.h2, '|',
		commands.bold, commands.italic, '|',
		commands.insertLink, commands.insertImage, '|',
		commands.ul, commands.ol, '|',
		commands.code, commands.blockquote, commands.hr, '|',
		createPreview('/akce/texy-preview?class=1', '#frm-akceForm-description-dialog'),
		commands.syntax
	]
});

document.querySelector('#frm-akceForm-description-editor-dialog-close-button').addEventListener('click', editorPreviewDialogClose);


if (document.getElementById('frm-akceForm-message')) {
	const messageTinyMDE = new TinyMDE.Editor({ textarea: document.getElementById('frm-akceForm-message') });
	const messageCommandBar = new TinyMDE.CommandBar({
		element: document.getElementById('frm-akceForm-message-toolbar'),
		editor: messageTinyMDE,
		commands: [
			commands.h2, '|',
			commands.bold, commands.italic, '|',
			commands.insertLink, commands.insertImage, '|',
			commands.ul, commands.ol, '|',
			commands.code, commands.blockquote, commands.hr, '|',
			createPreview('/akce/texy-preview?class=1', '#frm-akceForm-message-dialog'),
			commands.syntax
		]
	});

	document.querySelector('#frm-akceForm-message-editor-dialog-close-button').addEventListener('click', editorPreviewDialogClose);
}
