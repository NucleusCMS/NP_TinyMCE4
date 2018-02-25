tinymce.init({
    selector:'textarea#inputbody,textarea#inputmore',
    language:'<%_(lang_code)%>',
    menu: {
        edit:   {title: 'Edit', items: 'undo redo | cut copy paste pastetext | selectall'},
        insert: {title: 'Insert', items: 'link image | template hr'},
        format: {title: 'Format', items: 'bold italic underline strikethrough superscript subscript | formats | removeformat'},
        view:   {title: 'View', items: 'visualaid'},
        table:  {title: 'Table', items: 'inserttable tableprops deletetable | cell row column'},
        tools:  {title: 'Tools', items: 'spellchecker code'}
    },
    plugins: [
    'advlist autolink lists link image charmap print preview anchor textcolor',
    'searchreplace visualblocks code fullscreen',
    'insertdatetime media table contextmenu paste code help'
    ],
    toolbar: 'undo redo |  formatselect | bold forecolor backcolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help'
});
