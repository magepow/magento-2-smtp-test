define(['jquery', 'Magento_Ui/js/modal/alert'], function ($, alert) {
    'use strict';

    return function (config) {
        var button = $('#' + config.buttonId),
            resultBox = $('#' + config.resultId);

        // scope: read current store/website params from URL
        function getScopeParams() {
            var params = {};
            var matchStore = window.location.href.match(/[?&]store=([^&]+)/);
            var matchWebsite = window.location.href.match(/[?&]website=([^&]+)/);
            if (matchStore) {
                params.store = decodeURIComponent(matchStore[1]);
            }
            if (matchWebsite) {
                params.website = decodeURIComponent(matchWebsite[1]);
            }
            return params;
        }

        function scopeFieldId(scope, section, group, field) {
            // Magento config field ids: {section}_{group}_{field} (+ scope suffix not needed, same id per scope tab)
            // Works for default/website/store because only one visible form is rendered.
            return '#' + section + '_' + group + '_' + field;
        }

        button.on('click', function () {
            var testEmail = $(scopeFieldId(null, 'system', 'smtp', 'test_email')).val();
            if (!testEmail) {
                alert({content: $.mage.__('Please enter a test email address.')});
                return;
            }
            resultBox.html('<p>Testing...</p>');
            button.prop('disabled', true);

            var data = $.extend({
                form_key: window.FORM_KEY,
                test_email: testEmail
            }, getScopeParams());

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: data,
                showLoader: true
            }).done(function (resp) {
                button.prop('disabled', false);
                if (resp.success) {
                    resultBox.html('<div class="message message-success success"><div>' + resp.message + '</div></div>');
                } else {
                    var html = '<div class="message message-error error"><div>' + (resp.message || $.mage.__('Unable to send test email.')) + '</div>';
                    if (resp.detail) {
                        html += '<div style="margin-top:8px;white-space:pre-wrap;">' + $('<div/>').text(resp.detail).html() + '</div>';
                    }
                    html += '</div>';
                    resultBox.html(html);
                    if (resp.detail) {
                        alert({title: resp.message, content: $('<div/>').text(resp.detail).html()});
                    }
                }
            }).fail(function (jqXHR) {
                button.prop('disabled', false);
                var msg = $.mage.__('Request failed.');
                try {
                    var r = JSON.parse(jqXHR.responseText);
                    if (r.message) { msg = r.message; }
                    if (r.detail) { msg += '\n' + r.detail; }
                } catch (e) {}
                resultBox.html('<div class="message message-error error"><div>' + $('<div/>').text(msg).html() + '</div></div>');
            });
        });
    };
});
