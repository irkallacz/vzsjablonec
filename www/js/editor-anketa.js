const tinyMDE = new TinyMDE.Editor({ textarea: document.querySelector('.editor') });
const commandBar = new TinyMDE.CommandBar({
    element: document.querySelector('.editor-toolbar'),
    editor: tinyMDE,
    commands: [
        commands.h2, '|',
        commands.bold, commands.italic, '|',
        commands.insertLink, commands.insertImage, '|',
        commands.ul, commands.ol, '|',
        commands.code, commands.blockquote, commands.hr, '|',
        createPreview('/ankety/texy-preview?class=1', '.editor-dialog'),
        commands.syntax
    ]
});

document.querySelector('.editor-dialog-close-button').addEventListener('click', editorPreviewDialogClose);