(function ($) {
    'use strict';

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
            page: 1,
            per_page: 12,
            search: $('#ai-fr-content-search').val() || '',
            post_type: $('#ai-fr-content-type').val() || 'all',
            status: $('#ai-fr-content-status').val() || 'any'
        }).done(function (res) {
            if (!res || !res.success) return;
            renderContentRows((res.data || {}).items || []);
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
                html += '<li><strong>' + esc(item.created_at || '') + '</strong> - ' + esc(item.reason || '') +
                    ' (' + esc(String(item.tokens || 0)) + ' token) ' +
                    '<button type="button" class="button-link ai-fr-restore-snapshot" data-id="' + esc(item.id || '') + '">Ripristina</button></li>';
            });
            $('#ai-fr-snapshot-list').html(html || '<li>Nessuno snapshot.</li>');
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

        $('#ai-fr-regenerate-overview,#ai-fr-run-now,#ai-fr-regenerate').on('click', function () {
            ajax('ai_fr_regenerate_all', { force: 0 }).done(function (res) {
                if (!res || !res.success) return;
                $('#ai-fr-action-status').text('Rigenerazione completata.');
                refreshOverview();
                refreshTimeline();
            });
        });

        $('#ai-fr-regenerate-force').on('click', function () {
            ajax('ai_fr_regenerate_all', { force: 1 }).done(function () {
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

        $('#ai-fr-content-apply').on('click', loadContentItems);

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
        });

        $('#ai-fr-onboarding-dismiss').on('click', function () {
            $('#onboarding_done').val('1');
            $(this).closest('.ai-fr-onboarding').fadeOut(150);
        });

        updateTocFromEditor();
        refreshPreview();
        refreshOverview();
        loadContentItems();
        refreshTimeline();
        loadSnapshots();
    });
}(jQuery));
