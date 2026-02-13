(function ($) {
    'use strict';
    var contentState = { page: 1, perPage: 12, total: 0 };
    var markdownEditor = null;

    function ajax(action, data) {
        return $.post(AiFrAdmin.ajaxUrl, $.extend({}, data || {}, {
            action: action,
            nonce: AiFrAdmin.nonce
        }));
    }

    function esc(text) {
        return $('<div/>').text(text || '').html();
    }

    function switchSection(section) {
        $('.ai-fr-nav-item').removeClass('is-active');
        $('.ai-fr-nav-item[data-section="' + section + '"]').addClass('is-active');
        $('.ai-fr-section').removeClass('is-active');
        $('#ai-fr-section-' + section).addClass('is-active');
        $('#ai-fr-submit-wrap').toggle(section !== 'overview');
        if (section === 'content' && markdownEditor && markdownEditor.codemirror) {
            setTimeout(function () {
                markdownEditor.codemirror.refresh();
                markdownEditor.codemirror.focus();
            }, 30);
        }
    }

    function updateTocFromEditor() {
        var content = $('#llms_content').val() || '';
        var lines = content.split('\n');
        var toc = [];
        lines.forEach(function (line) {
            var m = line.match(/^(#{1,6})\s+(.+)$/);
            if (!m) return;
            toc.push({
                level: m[1].length,
                title: m[2]
            });
        });
        var html = '';
        toc.forEach(function (item) {
            html += '<li style="margin-left:' + ((item.level - 1) * 10) + 'px">' + esc(item.title) + '</li>';
        });
        $('#ai-fr-toc').html(html || '<li>Nessun heading trovato.</li>');
    }

    var previewTimer = null;

    function refreshPreview() {
        var content = $('#llms_content').val() || '';
        ajax('ai_fr_get_llms_preview', { content: content }).done(function (res) {
            if (!res || !res.success) return;
            $('#ai-fr-preview-pane').html(res.data.html || '');
            $('#ai-fr-token-count').text(res.data.tokens || 0);
            var validation = (res.data || {}).validation || { count: 0 };
            $('#ai-fr-link-validation').text((validation.count || 0) + ' issue');
        });
    }

    function renderWarnings(stats) {
        var warnings = ((stats || {}).diagnostics || {}).warnings || [];
        var html = '';
        warnings.forEach(function (w) {
            html += '<li>' + esc(w.message) + '</li>';
        });
        $('#ai-fr-overview-warnings').html(html || '<li>Nessun avviso.</li>');
    }

    function refreshOverview() {
        ajax('ai_fr_get_overview_stats', {}).done(function (res) {
            if (!res || !res.success) return;
            var d = res.data || {};
            $('#ai-fr-llms-chars').text((d.llms || {}).chars || 0);
            $('#ai-fr-llms-lines').text((d.llms || {}).lines || 0);
            $('#ai-fr-last-regen').text((d.llms || {}).last_regen_time || 'n/d');
            var sr = (d.diagnostics || {}).sitemap_robots || {};
            $('#ai-fr-sr-info').text('Sitemap: ' + (sr.sitemap_url || 'n/d') + ' | Robots: ' + (sr.robots_url || 'n/d'));
            renderWarnings(d);
        });
    }

    function renderContentRows(items) {
        var html = '';
        (items || []).forEach(function (item) {
            var badge = item.included
                ? '<span class="ai-fr-badge is-ok">Inclusa</span>'
                : '<span class="ai-fr-badge">Esclusa</span>';
            var actionLabel = item.excluded ? 'Includi' : 'Escludi';
            var next = item.excluded ? 0 : 1;
            html += '<tr>';
            html += '<td>' + badge + '</td>';
            html += '<td><a href="' + esc(item.edit_url) + '">' + esc(item.title || '(Senza titolo)') + '</a></td>';
            html += '<td>' + esc(item.post_type) + '</td>';
            html += '<td>' + esc(item.language) + '</td>';
            html += '<td>' + esc(item.status) + '</td>';
            html += '<td>' + esc(String(item.tokens || 0)) + '</td>';
            html += '<td><button type="button" class="button ai-fr-toggle-exclusion" data-post-id="' + item.id + '" data-exclude="' + next + '">' + actionLabel + '</button></td>';
            html += '</tr>';
        });
        $('#ai-fr-content-tbody').html(html || '<tr><td colspan="7">Nessun contenuto.</td></tr>');
    }

    function loadContentItems() {
        ajax('ai_fr_list_content_items', {
            page: contentState.page,
            per_page: contentState.perPage,
            search: $('#ai-fr-content-search').val() || '',
            post_type: $('#ai-fr-content-type').val() || 'all',
            status: $('#ai-fr-content-status').val() || 'any'
        }).done(function (res) {
            if (!res || !res.success) return;
            var data = res.data || {};
            contentState.total = data.total || 0;
            renderContentRows(data.items || []);
            var totalPages = Math.max(1, Math.ceil(contentState.total / contentState.perPage));
            $('#ai-fr-page-info').text('Pagina ' + contentState.page + ' / ' + totalPages);
            $('#ai-fr-prev-page').prop('disabled', contentState.page <= 1);
            $('#ai-fr-next-page').prop('disabled', contentState.page >= totalPages);
        });
    }

    function refreshTimeline() {
        ajax('ai_fr_get_event_timeline', { limit: 30 }).done(function (res) {
            if (!res || !res.success) return;
            var html = '';
            ((res.data || {}).items || []).forEach(function (it) {
                html += '<li><strong>' + esc(it.time || '') + '</strong> - ' + esc(it.type || '') + '</li>';
            });
            $('#ai-fr-timeline-list').html(html || '<li>Nessun evento.</li>');
        });
    }

    function loadSnapshots() {
        ajax('ai_fr_list_llms_snapshots', {}).done(function (res) {
            if (!res || !res.success) return;
            var html = '';
            ((res.data || {}).items || []).forEach(function (item) {
                html += '<li>' +
                    '<label><input type="checkbox" class="ai-fr-snapshot-select" value="' + esc(item.id || '') + '"> </label>' +
                    '<strong>' + esc(item.created_at || '') + '</strong> - ' + esc(item.reason || '') +
                    ' (' + esc(String(item.tokens || 0)) + ' token) ' +
                    (item.note ? '<em>' + esc(item.note) + '</em> ' : '') +
                    '<button type="button" class="button-link ai-fr-restore-snapshot" data-id="' + esc(item.id || '') + '">Ripristina</button></li>';
            });
            $('#ai-fr-snapshot-list').html(html || '<li>Nessuno snapshot.</li>');
        });
    }

    function compareSnapshots() {
        var selected = $('.ai-fr-snapshot-select:checked').map(function () {
            return $(this).val();
        }).get();
        if (selected.length !== 2) {
            $('#ai-fr-diff-summary').text('Seleziona esattamente 2 snapshot.');
            return;
        }

        ajax('ai_fr_compare_llms_snapshots', {
            left_id: selected[0],
            right_id: selected[1]
        }).done(function (res) {
            if (!res || !res.success) return;
            var d = res.data || {};
            var s = d.summary || {};
            $('#ai-fr-diff-summary').text(
                'Linee +' + (s.added_lines || 0) + ' / -' + (s.removed_lines || 0) +
                ' | Delta token: ' + ((s.token_delta > 0 ? '+' : '') + (s.token_delta || 0))
            );
            var rowsHtml = '';
            (d.rows || []).forEach(function (row) {
                var cls = 'ai-fr-diff-row-' + (row.type || 'equal');
                var left = (row.left_num ? row.left_num + ': ' : '') + (row.left || '');
                var right = (row.right_num ? row.right_num + ': ' : '') + (row.right || '');
                rowsHtml += '<tr class="' + cls + '"><td><code>' + esc(left) + '</code></td><td><code>' + esc(right) + '</code></td></tr>';
            });
            $('#ai-fr-diff-rows').html(rowsHtml || '<tr><td colspan="2">Nessuna differenza.</td></tr>');
        });
    }

    function runSimulation() {
        ajax('ai_fr_run_ai_simulation', {
            content: $('#llms_content').val() || ''
        }).done(function (res) {
            if (!res || !res.success) return;
            var d = res.data || {};
            var msg = 'Score: ' + (d.score || 0) + ' | Token: ' + (d.tokens || 0) + ' | Duplicati: ' + (d.duplicates || 0);
            var sug = (d.suggestions || []).join(' ');
            $('#ai-fr-simulation-result').text(msg + '. ' + sug);
        });
    }

    $(function () {
        if (window.wp && wp.codeEditor && window.AiFrCodeEditor && $('#llms_content').length) {
            markdownEditor = wp.codeEditor.initialize($('#llms_content')[0], AiFrCodeEditor.settings || {});
            if (markdownEditor && markdownEditor.codemirror) {
                markdownEditor.codemirror.setOption('lineNumbers', true);
                markdownEditor.codemirror.setOption('viewportMargin', 20);
                markdownEditor.codemirror.on('change', function () {
                    $('#llms_content').val(markdownEditor.codemirror.getValue()).trigger('input');
                });
                setTimeout(function () {
                    markdownEditor.codemirror.refresh();
                }, 30);
            }
        }

        $('.ai-fr-nav-item').on('click', function () {
            switchSection($(this).data('section'));
        });

        $('[data-section-jump]').on('click', function () {
            switchSection($(this).data('section-jump'));
        });

        $('#ai-fr-refresh-overview').on('click', refreshOverview);
        $('#ai-fr-refresh-diagnostics').on('click', function () {
            ajax('ai_fr_run_diagnostics', {}).done(refreshOverview);
        });

        $('#ai-fr-regenerate-overview,#ai-fr-run-now').on('click', function () {
            ajax('ai_fr_regenerate_all', { force: 0, mode: 'batch' }).done(function (res) {
                if (!res || !res.success) return;
                var d = res.data || {};
                var processed = d.processed || 0;
                var regenerated = d.regenerated || 0;
                $('#ai-fr-action-status').text(
                    'Batch completato. Processati: ' + processed + ', rigenerati: ' + regenerated + '.'
                );
                refreshOverview();
                refreshTimeline();
            });
        });

        $('#ai-fr-regenerate').on('click', function () {
            ajax('ai_fr_regenerate_all', { force: 0, mode: 'full' }).done(function (res) {
                if (!res || !res.success) return;
                $('#ai-fr-action-status').text('Rigenerazione completata.');
                refreshOverview();
                refreshTimeline();
            });
        });

        $('#ai-fr-regenerate-force').on('click', function () {
            ajax('ai_fr_regenerate_all', { force: 1, mode: 'full' }).done(function () {
                $('#ai-fr-action-status').text('Rigenerazione forzata completata.');
                refreshOverview();
                refreshTimeline();
            });
        });

        $('#ai-fr-clear-versions').on('click', function () {
            if (!window.confirm('Eliminare tutti i file MD salvati?')) return;
            ajax('ai_fr_clear_versions', {}).done(function (res) {
                if (!res || !res.success) return;
                $('#ai-fr-action-status').text('Eliminati file: ' + ((res.data || {}).deleted || 0));
                refreshOverview();
                refreshTimeline();
            });
        });

        $('#llms_content').on('input', function () {
            updateTocFromEditor();
            clearTimeout(previewTimer);
            previewTimer = setTimeout(refreshPreview, 200);
        });

        $('.ai-fr-insert-snippet').on('click', function () {
            var $t = $('#llms_content');
            $t.val(($t.val() || '') + '\n' + ($(this).data('snippet') || '') + '\n').trigger('input');
        });

        $('#ai-fr-content-apply').on('click', function () {
            contentState.page = 1;
            loadContentItems();
        });
        $('#ai-fr-prev-page').on('click', function () {
            contentState.page = Math.max(1, contentState.page - 1);
            loadContentItems();
        });
        $('#ai-fr-next-page').on('click', function () {
            var totalPages = Math.max(1, Math.ceil(contentState.total / contentState.perPage));
            contentState.page = Math.min(totalPages, contentState.page + 1);
            loadContentItems();
        });

        $(document).on('click', '.ai-fr-toggle-exclusion', function () {
            var $btn = $(this);
            ajax('ai_fr_toggle_content_exclusion', {
                post_id: $btn.data('post-id'),
                exclude: $btn.data('exclude')
            }).done(function () {
                loadContentItems();
                refreshOverview();
                refreshTimeline();
            });
        });

        $('#ai-fr-refresh-timeline').on('click', refreshTimeline);

        $('#ai-fr-create-snapshot').on('click', function () {
            ajax('ai_fr_create_llms_snapshot', {
                content: $('#llms_content').val() || '',
                reason: 'manual'
            }).done(function () {
                loadSnapshots();
                refreshTimeline();
            });
        });

        $('#ai-fr-load-snapshots').on('click', loadSnapshots);
        $('#ai-fr-compare-snapshots').on('click', compareSnapshots);

        $(document).on('click', '.ai-fr-restore-snapshot', function () {
            ajax('ai_fr_restore_llms_snapshot', {
                id: $(this).data('id')
            }).done(function (res) {
                if (!res || !res.success) return;
                $('#llms_content').val((res.data || {}).content || '').trigger('input');
                refreshTimeline();
            });
        });

        $('#ai-fr-run-simulation').on('click', runSimulation);

        $('#ai-fr-auto-regenerate').on('change', function () {
            if ($(this).is(':checked')) {
                $('#ai-fr-static-md-files').prop('checked', true);
            }
        });

        $('[data-ai-fr-preset]').on('click', function () {
            var preset = $(this).data('ai-fr-preset');
            var base = '# ' + document.title + '\n\n';
            if (preset === 'blog') {
                base += '> Hub contenuti blog con priorita su articoli recenti.\n';
            } else if (preset === 'azienda') {
                base += '> Hub contenuti istituzionali, servizi e contatti.\n';
            } else {
                base += '> Hub prodotto e catalogo e-commerce.\n';
            }
            $('#llms_content').val(base).trigger('input');
            if (markdownEditor && markdownEditor.codemirror) {
                markdownEditor.codemirror.setValue(base);
            }
            $('#ai-fr-wizard-step').val('2');
        });

        $('#ai-fr-wizard-include-base').on('click', function () {
            $('input[name="include_pages"]').prop('checked', true);
            $('input[name="include_posts"]').prop('checked', true);
            $('input[name="include_products"]').prop('checked', false);
            $('#ai-fr-wizard-step').val('3');
        });

        $('#ai-fr-wizard-include-all').on('click', function () {
            $('input[name="include_pages"]').prop('checked', true);
            $('input[name="include_posts"]').prop('checked', true);
            $('input[name="include_products"]').prop('checked', true);
            $('#ai-fr-wizard-step').val('3');
        });

        $('#ai-fr-wizard-generate').on('click', function () {
            if (!$('#llms_content').val()) {
                var draft = '# ' + document.title + '\n\n> Hub contenuti AI del sito.\n';
                $('#llms_content').val(draft).trigger('input');
                if (markdownEditor && markdownEditor.codemirror) {
                    markdownEditor.codemirror.setValue(draft);
                }
            }
            switchSection('content');
        });

        $('#ai-fr-onboarding-dismiss').on('click', function () {
            var $box = $(this).closest('.ai-fr-onboarding');
            ajax('ai_fr_set_onboarding_status', { done: 1 }).done(function (res) {
                if (!res || !res.success) return;
                $('#onboarding_done').val('1');
                $box.fadeOut(150);
            });
        });

        $('#ai-fr-reopen-wizard').on('click', function () {
            ajax('ai_fr_set_onboarding_status', { done: 0 }).done(function (res) {
                if (!res || !res.success) return;
                $('#onboarding_done').val('');
                if ($('.ai-fr-onboarding').length) {
                    $('.ai-fr-onboarding').fadeIn(120);
                } else {
                    window.location.reload();
                }
            });
        });

        switchSection('overview');
        updateTocFromEditor();
        refreshPreview();
        refreshOverview();
        contentState.page = 1;
        loadContentItems();
        refreshTimeline();
        loadSnapshots();
    });
}(jQuery));
