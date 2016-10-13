(function ($cms) {
    'use strict';

    $cms.templates.cnsSavedWarning = function cnsSavedWarning(options) {
        var id = $cms.filter.id(options.title);

        document.getElementById('saved_use__' + id).onsubmit = function () {
            var win = get_main_cms_window();

            var explanation = win.document.getElementById('explanation');
            explanation.value = options.explanation;

            var message = win.document.getElementById('message');
            win.insert_textbox(message, options.message, null, false, options.messageHtml);

            if (window.faux_close !== undefined) {
                window.faux_close();
            } else {
                window.close();
            }

            return false;
        };

        document.getElementById('saved_delete__' + id).getElementsByTagName('input')[1].onclick = function () {
            var form = this.form;

            window.fauxmodal_confirm(options.question, function (answer) {
                if (answer) {
                    form.submit();
                }
            });

            return false;
        };
    };

}(window.$cms));