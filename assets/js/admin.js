jQuery(document).ready(function($) {
    'use strict';

    var $btn = $('#tka-scan-btn');
    var $loader = $('#tka-scan-loader');
    var $results = $('#tka-scan-results');

    if (!$btn.length) return;

    $btn.on('click', function() {
        $btn.prop('disabled', true);
        $loader.show();
        $results.html('<p>' + tkaConsentAdmin.labels.scanning + '</p>');

        $.post(tkaConsentAdmin.ajaxUrl, {
            action: 'tka_scan_scripts',
            nonce: tkaConsentAdmin.nonce
        })
        .done(function(response) {
            $loader.hide();
            $btn.prop('disabled', false);

            if (!response.success) {
                var errorMsg = tkaConsentAdmin.labels.scanFailed.replace('%s', response.data.message || tkaConsentAdmin.labels.unknownError);
                $results.html('<div class="notice notice-error inline"><p>' + errorMsg + '</p></div>');
                return;
            }

            var scripts = response.data.scripts;
            if (!scripts.length) {
                $results.html('<p>' + tkaConsentAdmin.labels.noScripts + '</p>');
                return;
            }

            renderResults(scripts);
        })
        .fail(function(xhr, status, error) {
            $loader.hide();
            $btn.prop('disabled', false);
            var errorMsg = tkaConsentAdmin.labels.scanFailed.replace('%s', error);
            $results.html('<div class="notice notice-error inline"><p>' + errorMsg + '</p></div>');
        });
    });

    function getSignature(script) {
        if (script.type === 'external') {
            try {
                var url = new URL(script.src);
                return url.hostname.replace('www.', '');
            } catch (e) {
                return script.name;
            }
        } else {
            var content = script.full_content;
            if (content.indexOf('gtag') !== -1 || content.indexOf('dataLayer') !== -1) {
                return 'gtag';
            }
            if (content.indexOf('fbq') !== -1) {
                return 'fbq';
            }
            return '';
        }
    }

    function renderResults(scripts) {
        var labels = tkaConsentAdmin.labels;
        var html = '<table class="wp-list-table widefat fixed striped posts" style="margin-top: 15px;">';
        html += '<thead><tr>';
        html += '<th style="width: 100px;">' + labels.type + '</th>';
        html += '<th>' + labels.script + '</th>';
        html += '<th style="width: 150px;">' + labels.suggestedCategory + '</th>';
        html += '<th style="width: 120px;">' + labels.status + '</th>';
        html += '<th style="width: 180px;">' + labels.actions + '</th>';
        html += '</tr></thead>';
        html += '<tbody>';

        scripts.forEach(function(script, index) {
            var typeBadge = script.type === 'external' ? labels.external : labels.inline;
            var displayContent = '';
            if (script.type === 'external') {
                displayContent = '<code>' + script.src + '</code>';
            } else {
                displayContent = '<strong>' + labels.inlineScript + '</strong><pre style="margin:5px 0; font-size:11px; background:#f6f7f7; padding:5px; border:1px solid #dcdcde; overflow:auto;">' + script.preview + '</pre>';
            }

            var categoryLabel = script.suggested_category.charAt(0).toUpperCase() + script.suggested_category.slice(1);
            var statusLabel = script.is_blocked 
                ? '<span style="color: #2271b1; font-weight: bold;">' + labels.blocked + '</span>'
                : '<span style="color: #64cbd4;">' + labels.allowed + '</span>';

            var signature = getSignature(script);

            var actionsHtml = '';
            if (script.is_blocked) {
                actionsHtml = '<span class="description">' + labels.alreadyBlocked + '</span>';
            } else if (!signature) {
                actionsHtml = '<span class="description">' + labels.noSignature + '</span>';
            } else {
                var categories = ['statistics', 'marketing', 'preferences'];
                categories.forEach(function(cat) {
                    var btnLabel = labels.blockAs.replace('%s', cat.charAt(0).toUpperCase() + cat.slice(1));
                    actionsHtml += '<button type="button" class="button button-small tka-block-action" data-category="' + cat + '" data-signature="' + signature + '" data-index="' + index + '" style="margin-bottom: 3px; display: block; width: 100%; text-align: left;">' + btnLabel + '</button>';
                });
            }

            html += '<tr id="tka-script-row-' + index + '">';
            html += '<td><span class="badge" style="background:#ddd; padding:2px 6px; border-radius:3px; font-size:11px;">' + typeBadge + '</span></td>';
            html += '<td>' + displayContent + '</td>';
            html += '<td>' + categoryLabel + '</td>';
            html += '<td class="tka-script-status">' + statusLabel + '</td>';
            html += '<td class="tka-script-actions">' + actionsHtml + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $results.html(html);

        $('.tka-block-action').on('click', function() {
            var $btnAction = $(this);
            var category = $btnAction.data('category');
            var signature = $btnAction.data('signature');
            var rowIndex = $btnAction.data('index');

            var $textarea = $('textarea[name="tka_cookie_consent_options[blocked_' + category + ']"]');
            if ($textarea.length) {
                var currentVal = $textarea.val().trim();
                var signaturesArray = currentVal ? currentVal.split(',').map(function(s) { return s.trim(); }) : [];
                
                if (signaturesArray.indexOf(signature) === -1) {
                    signaturesArray.push(signature);
                    $textarea.val(signaturesArray.join(', '));
                    
                    $textarea.css('background-color', '#fff8e5');
                    setTimeout(function() {
                        $textarea.css('background-color', '');
                    }, 1000);
                }

                var $row = $('#tka-script-row-' + rowIndex);
                $row.find('.tka-script-status').html('<span style="color: #2271b1; font-weight: bold;">' + labels.blocked + '</span>');
                $row.find('.tka-script-actions').html('<span class="description">' + labels.alreadyBlocked + '</span>');
            }
        });
    }
});
