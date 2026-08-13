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
        $('.ai-fr-nav-item').removeClass('is-active').attr('aria-selected', 'false');
        $('.ai-fr-nav-item[data-section="' + section + '"]').addClass('is-active').attr('aria-selected', 'true');
        $('.ai-fr-section').removeClass('is-active').attr('hidden', true);
        $('#ai-fr-section-' + section).addClass('is-active').removeAttr('hidden');
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
            html += '<li class="ai-fr-toc-level-' + item.level + '">' + esc(item.title) + '</li>';
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

    function serviceField(name, label, type, placeholder) {
        var tag = type === 'textarea'
            ? '<textarea rows="3" data-service-field="' + name + '" placeholder="' + esc(placeholder) + '"></textarea>'
            : '<input type="' + type + '" data-service-field="' + name + '" placeholder="' + esc(placeholder) + '">';

        return '<label class="ai-fr-field">' +
            '<span>' + esc(label) + '</span>' +
            tag +
            '</label>';
    }

    function renderServiceRow() {
        return '<div class="ai-fr-schema-service">' +
            '<div class="ai-fr-schema-service-head">' +
            '<strong>Servizio</strong>' +
            '<button type="button" class="button button-link-delete ai-fr-schema-service-remove">Rimuovi</button>' +
            '</div>' +
            '<div class="ai-fr-schema-service-grid">' +
            serviceField('name', 'Nome', 'text', 'UX e Graphic Design') +
            serviceField('url', 'URL pagina', 'url', 'https://example.com/servizio/') +
            serviceField('serviceType', 'Tipo servizio', 'text', 'Web design, UX/UI design') +
            serviceField('areaServed', 'Area servita', 'text', 'Italia') +
            '<label class="ai-fr-field ai-fr-schema-service-description">' +
            '<span>Descrizione</span>' +
            '<textarea rows="3" data-service-field="description" placeholder="Descrizione breve del servizio."></textarea>' +
            '</label>' +
            serviceField('price', 'Prezzo', 'text', '0') +
            serviceField('priceCurrency', 'Valuta', 'text', 'EUR') +
            '</div>' +
            '</div>';
    }

    function reindexSchemaServices() {
        $('#ai-fr-schema-services .ai-fr-empty-state').remove();
        $('#ai-fr-schema-services .ai-fr-schema-service').each(function (index) {
            $(this).attr('data-service-index', index);
            $(this).find('.ai-fr-schema-service-head strong').text('Servizio ' + (index + 1));
            $(this).find('.ai-fr-schema-service-remove')
                .removeClass('button-link-delete')
                .addClass('button ai-fr-button-danger')
                .html('<span class="dashicons dashicons-trash" aria-hidden="true"></span><span>Rimuovi</span>');
            $(this).find('[data-service-field]').each(function () {
                var field = $(this).data('service-field');
                $(this).attr('name', 'schema_services[' + index + '][' + field + ']');
            });
        });
        if (!$('#ai-fr-schema-services .ai-fr-schema-service').length) {
            $('#ai-fr-schema-services').append('<div class="ai-fr-empty-state">Nessun servizio manuale configurato.</div>');
        }
    }

    var schemaRepeaterTemplates = {
        types: '<input data-field="value" placeholder="EducationalOrganization"><button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>',
        contacts: '<input data-field="contactType" placeholder="segreteria corsi"><input data-field="telephone" placeholder="+39 02 ..."><input type="email" data-field="email" placeholder="email@example.com"><input data-field="availableLanguage" placeholder="it, en"><input data-field="hoursAvailable" placeholder="Mo-Fr 09:00-18:00"><button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>',
        hours: '<input data-field="dayOfWeek" placeholder="Monday, Tuesday"><input type="time" data-field="opens"><input type="time" data-field="closes"><input type="date" data-field="validFrom"><input type="date" data-field="validThrough"><button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>',
        certifications: '<input data-field="name" placeholder="ISO 9001"><input data-field="identifier" placeholder="Certificato n."><input data-field="issuedBy" placeholder="Ente certificatore"><input type="url" data-field="url" placeholder="https://..."><button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>',
        identifiers: '<input data-field="propertyID" placeholder="RUNTS"><input data-field="value" placeholder="Numero identificativo"><button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>',
        offerSources: '<input data-field="value" placeholder="ID termine, taxonomy:slug o permalink WordPress"><button type="button" class="button-link-delete ai-fr-repeater-remove">Rimuovi</button>'
    };

    var schemaRepeaterLabels = {
        types: { title: 'Tipo aggiuntivo', fields: { value: 'Tipo Schema.org' } },
        contacts: { title: 'Contatto', fields: { contactType: 'Reparto / funzione', telephone: 'Telefono', email: 'Email', availableLanguage: 'Lingue', hoursAvailable: 'Disponibilità' } },
        hours: { title: 'Fascia oraria', fields: { dayOfWeek: 'Giorni', opens: 'Apertura', closes: 'Chiusura', validFrom: 'Valida dal', validThrough: 'Valida fino al' } },
        certifications: { title: 'Certificazione', fields: { name: 'Nome', identifier: 'Identificativo', issuedBy: 'Ente certificatore', url: 'URL di verifica' } },
        identifiers: { title: 'Identificatore', fields: { propertyID: 'Registro / codice', value: 'Valore' } },
        offerSources: { title: 'Sorgente WordPress', fields: { value: 'ID, riferimento o permalink' } }
    };

    var schemaRepeaterEmptyLabels = {
        types: 'Nessun tipo aggiuntivo configurato.',
        contacts: 'Nessun contatto configurato.',
        hours: 'Nessuna fascia oraria configurata.',
        certifications: 'Nessuna certificazione configurata.',
        identifiers: 'Nessun identificatore aggiuntivo configurato.',
        offerSources: 'Nessuna sorgente WordPress configurata.'
    };

    function decorateSchemaRepeater($repeater) {
        var type = $repeater.data('repeater');
        var labels = schemaRepeaterLabels[type];
        if (!labels) return;
        $repeater.find('.ai-fr-schema-repeater-row').each(function (index) {
            var $row = $(this);
            if (!$row.children('.ai-fr-schema-repeater-head').length) {
                var $remove = $row.children('.ai-fr-repeater-remove').detach();
                var $head = $('<div class="ai-fr-schema-repeater-head"><strong></strong></div>');
                $head.append($remove);
                $row.prepend($head);
            }
            $row.find('.ai-fr-repeater-remove')
                .removeClass('button-link-delete')
                .addClass('button ai-fr-button-danger')
                .html('<span class="dashicons dashicons-trash" aria-hidden="true"></span><span>Rimuovi</span>');
            $row.children('[data-field]').each(function () {
                var $input = $(this);
                var field = $input.data('field');
                $input.wrap('<label class="ai-fr-repeat-field"></label>');
                $input.before('<span>' + esc(labels.fields[field] || field) + '</span>');
            });
            $row.find('.ai-fr-schema-repeater-head strong').text(labels.title + ' ' + (index + 1));
        });
    }

    function reindexSchemaRepeater($repeater) {
        var type = $repeater.data('repeater');
        var option = {
            types: 'schema_types', contacts: 'schema_contacts', hours: 'schema_opening_hours',
            certifications: 'schema_certifications', identifiers: 'schema_identifiers', offerSources: 'schema_offer_sources'
        }[type];
        $repeater.children('.ai-fr-empty-state').remove();
        decorateSchemaRepeater($repeater);
        $repeater.find('.ai-fr-schema-repeater-row').each(function (index) {
            $(this).find('[data-field]').each(function () {
                var field = $(this).data('field');
                $(this).attr('name', type === 'types' || type === 'offerSources' ? option + '[' + index + ']' : option + '[' + index + '][' + field + ']');
            });
            $(this).find('.ai-fr-schema-repeater-head strong').text(schemaRepeaterLabels[type].title + ' ' + (index + 1));
        });
        if (!$repeater.find('.ai-fr-schema-repeater-row').length) {
            $repeater.append('<div class="ai-fr-empty-state">' + esc(schemaRepeaterEmptyLabels[type] || 'Nessun elemento configurato.') + '</div>');
        }
    }

    function updateSchemaEntityScope() {
        var active = String($('#ai-fr-schema-entity-type').val() || 'Person').toLowerCase();
        $('#ai-fr-section-schema [data-entity-scope]').each(function () {
            var visible = String($(this).data('entity-scope')).toLowerCase() === active;
            $(this).prop('hidden', !visible).attr('aria-hidden', visible ? 'false' : 'true');
        });
    }

    function syncSchemaMediaControls() {
        [
            { id: '#ai-fr-schema-logo-id', clear: '#ai-fr-schema-logo-clear' },
            { id: '#ai-fr-schema-image-id', clear: '#ai-fr-schema-image-clear' }
        ].forEach(function (media) {
            var hasMedia = Number($(media.id).val() || 0) > 0;
            $(media.clear).prop('hidden', !hasMedia).prop('disabled', !hasMedia);
        });
    }

    var wizardStep = 1;
    var wizardStorageKey = 'ai-fr-setup-v2';
    var wizardInitialData = null;

    function collectWizardData() {
        var data = { content_types: [] };
        $('[data-wizard-field]').each(function () {
            var $field = $(this);
            var key = $field.data('wizard-field');
            if (key === 'content_types') {
                if ($field.is(':checked')) data.content_types.push(String($field.val()));
            } else if ($field.is(':checkbox')) {
                data[key] = $field.is(':checked') ? 1 : 0;
            } else {
                data[key] = $field.val();
            }
        });
        return data;
    }

    function applyWizardData(data) {
        if (!data) return;
        $('[data-wizard-field]').each(function () {
            var $field = $(this);
            var key = $field.data('wizard-field');
            if (!(key in data)) return;
            if (key === 'content_types') {
                $field.prop('checked', (data.content_types || []).indexOf(String($field.val())) !== -1);
            } else if ($field.is(':checkbox')) {
                $field.prop('checked', Boolean(Number(data[key])));
            } else {
                $field.val(data[key]);
            }
        });
    }

    function persistWizard() {
        try {
            window.sessionStorage.setItem(wizardStorageKey, JSON.stringify({ step: wizardStep, data: collectWizardData() }));
        } catch (e) {}
    }

    function renderWizardSummary() {
        var data = collectWizardData();
        var selected = $('[data-wizard-field="content_types"]:checked').map(function () {
            return $(this).closest('.ai-fr-choice').find('strong').text();
        }).get();
        var output = Number(data.static_md_files) ? 'File Markdown statici' : 'Output dinamico';
        var automation = Number(data.auto_regenerate)
            ? 'Ogni ' + data.regenerate_interval + ' ore, batch da ' + data.regenerate_batch_size
            : 'Rigenerazione pianificata disattivata';
        var schema = Number(data.schema_enabled)
            ? data.schema_entity_type + ': ' + (data.schema_name || 'nome non indicato')
            : 'Disattivato';
        $('#ai-fr-wizard-summary').html(
            '<div><small>Contenuti inclusi</small><strong>' + esc(selected.join(', ') || 'Nessuno') + '</strong></div>' +
            '<div><small>Output</small><strong>' + esc(output) + '</strong></div>' +
            '<div><small>Automazione</small><strong>' + esc(automation) + '</strong></div>' +
            '<div><small>Semantic Schema</small><strong>' + esc(schema) + '</strong></div>'
        );
    }

    function showWizardStep(step) {
        wizardStep = Math.max(1, Math.min(5, Number(step) || 1));
        $('.ai-fr-wizard-panel').attr('hidden', true).filter('[data-wizard-step="' + wizardStep + '"]').removeAttr('hidden');
        $('[data-wizard-marker]').each(function () {
            var marker = Number($(this).data('wizard-marker'));
            $(this).toggleClass('is-active', marker === wizardStep).toggleClass('is-complete', marker < wizardStep);
            if (marker === wizardStep) $(this).attr('aria-current', 'step'); else $(this).removeAttr('aria-current');
        });
        $('#ai-fr-wizard-prev').prop('hidden', wizardStep === 1);
        $('#ai-fr-wizard-next').prop('hidden', wizardStep === 5);
        $('#ai-fr-wizard-complete').prop('hidden', wizardStep !== 5);
        if (wizardStep === 5) renderWizardSummary();
        persistWizard();
    }

    function openWizard(resetSession) {
        if (resetSession) {
            try { window.sessionStorage.removeItem(wizardStorageKey); } catch (e) {}
            applyWizardData(wizardInitialData);
            wizardStep = 1;
        }
        $('#ai-fr-main-form').addClass('is-hidden');
        $('#ai-fr-onboarding').removeClass('is-hidden');
        $('.ai-fr-header-wizard').addClass('is-hidden');
        showWizardStep(wizardStep);
        $('#ai-fr-onboarding').attr('tabindex', '-1').trigger('focus');
    }

    function closeWizard() {
        $('#ai-fr-onboarding').addClass('is-hidden');
        $('#ai-fr-main-form, .ai-fr-header-wizard').removeClass('is-hidden');
    }

    function completeWizard() {
        var $button = $('#ai-fr-wizard-complete');
        var data = collectWizardData();
        $button.prop('disabled', true).text('Salvataggio in corso…');
        $('#ai-fr-wizard-result').removeClass('is-error is-success').text('Salvataggio delle impostazioni e verifica dell’output in corso.');
        ajax('ai_fr_complete_setup', data).done(function (res) {
            if (res && res.success) {
                $('#ai-fr-wizard-result').addClass('is-success').text('Configurazione completata. L’output è stato verificato.');
                try { window.sessionStorage.removeItem(wizardStorageKey); } catch (e) {}
                window.setTimeout(function () { window.location.reload(); }, 700);
                return;
            }
            var payload = (res || {}).data || {};
            $('#ai-fr-wizard-result').addClass('is-error').text(payload.message || 'Generazione non riuscita. Le impostazioni salvate restano attive.');
            $('#ai-fr-wizard-retry').prop('hidden', false);
        }).fail(function () {
            $('#ai-fr-wizard-result').addClass('is-error').text('Impossibile completare la richiesta. Riprova senza perdere le scelte effettuate.');
            $('#ai-fr-wizard-retry').prop('hidden', false);
        }).always(function () {
            $button.prop('disabled', false).text('Salva e genera');
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
        $('.ai-fr-nav-item').each(function () {
            var section = $(this).data('section');
            var tabId = 'ai-fr-tab-' + section;
            $(this).attr({ id: tabId, role: 'tab', 'aria-controls': 'ai-fr-section-' + section, 'aria-selected': $(this).hasClass('is-active') ? 'true' : 'false' });
            $('#ai-fr-section-' + section).attr({ role: 'tabpanel', 'aria-labelledby': tabId });
        });
        $('.ai-fr-nav').on('keydown', '.ai-fr-nav-item', function (event) {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            event.preventDefault();
            var $tabs = $('.ai-fr-nav-item');
            var index = $tabs.index(this) + (event.key === 'ArrowRight' ? 1 : -1);
            var $target = $tabs.eq((index + $tabs.length) % $tabs.length);
            $target.trigger('focus').trigger('click');
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

        $('#ai-fr-schema-image-select').on('click', function () {
            if (!window.wp || !wp.media) return;
            var frame = wp.media({
                title: 'Seleziona immagine identitaria',
                button: { text: 'Usa questa immagine' },
                multiple: false
            });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#ai-fr-schema-image-id').val(attachment.id || 0).trigger('change');
                var url = (attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url) || '';
                $('#ai-fr-schema-entity-image-preview').html(url ? '<img src="' + esc(url) + '" alt="">' : '');
                syncSchemaMediaControls();
            });
            frame.open();
        });

        $('#ai-fr-schema-image-clear').on('click', function () {
            $('#ai-fr-schema-image-id').val('0').trigger('change');
            $('#ai-fr-schema-entity-image-preview').html('<span>Nessuna immagine</span>');
            syncSchemaMediaControls();
        });

        $('#ai-fr-schema-logo-select').on('click', function () {
            if (!window.wp || !wp.media) return;
            var frame = wp.media({ title: 'Seleziona logo aziendale', button: { text: 'Usa questo logo' }, multiple: false });
            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                $('#ai-fr-schema-logo-id').val(attachment.id || 0).trigger('change');
                $('#ai-fr-schema-logo-preview').html(url ? '<img src="' + esc(url) + '" alt="">' : '');
                syncSchemaMediaControls();
            });
            frame.open();
        });

        $('#ai-fr-schema-logo-clear').on('click', function () {
            $('#ai-fr-schema-logo-id').val('0').trigger('change');
            $('#ai-fr-schema-logo-preview').html('<span>Nessun logo</span>');
            syncSchemaMediaControls();
        });

        reindexSchemaServices();
        $('.ai-fr-schema-repeaters').each(function () { reindexSchemaRepeater($(this)); });
        $('.ai-fr-repeater-add, #ai-fr-schema-service-add').each(function () {
            if (!$(this).children('.dashicons').length) {
                $(this).prepend('<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>');
            }
        });
        $('#ai-fr-schema-entity-type').on('change', updateSchemaEntityScope);
        updateSchemaEntityScope();
        syncSchemaMediaControls();

        $(document).on('click', '.ai-fr-repeater-add', function () {
            var type = $(this).data('target');
            var $repeater = $('.ai-fr-schema-repeaters[data-repeater="' + type + '"]');
            var gridClass = type === 'types' || type === 'identifiers' || type === 'offerSources' ? '' : ' ai-fr-schema-repeater-grid';
            $repeater.append('<div class="ai-fr-schema-repeater-row' + gridClass + '">' + schemaRepeaterTemplates[type] + '</div>');
            reindexSchemaRepeater($repeater);
        });

        $(document).on('click', '.ai-fr-repeater-remove', function () {
            var $repeater = $(this).closest('.ai-fr-schema-repeaters');
            $(this).closest('.ai-fr-schema-repeater-row').remove();
            reindexSchemaRepeater($repeater);
        });

        $(document).on('click', '#ai-fr-schema-service-add', function () {
            $('#ai-fr-schema-services').append(renderServiceRow());
            reindexSchemaServices();
        });

        $(document).on('click', '.ai-fr-schema-service-remove', function () {
            $(this).closest('.ai-fr-schema-service').remove();
            reindexSchemaServices();
        });

        $('#ai-fr-auto-regenerate').on('change', function () {
            if ($(this).is(':checked')) {
                $('#ai-fr-static-md-files').prop('checked', true);
            }
        });

        $('#ai-fr-onboarding-dismiss').on('click', function () {
            closeWizard();
        });

        $('#ai-fr-reopen-wizard').on('click', function () {
            openWizard(true);
        });

        $('#ai-fr-wizard-next').on('click', function () { showWizardStep(wizardStep + 1); });
        $('#ai-fr-wizard-prev').on('click', function () { showWizardStep(wizardStep - 1); });
        $('#ai-fr-wizard-complete, #ai-fr-wizard-retry').on('click', completeWizard);
        $('[data-wizard-field]').on('change input', persistWizard);
        $('[data-wizard-field="auto_regenerate"]').on('change', function () {
            if ($(this).is(':checked')) $('[data-wizard-field="static_md_files"]').prop('checked', true);
            persistWizard();
        });
        $('[data-wizard-field="static_md_files"]').on('change', function () {
            if (!$(this).is(':checked')) $('[data-wizard-field="auto_regenerate"]').prop('checked', false);
            persistWizard();
        });

        wizardInitialData = collectWizardData();
        var storedWizard = null;
        try { storedWizard = JSON.parse(window.sessionStorage.getItem(wizardStorageKey) || 'null'); } catch (e) {}
        if (!$('#ai-fr-onboarding').hasClass('is-hidden') && storedWizard && storedWizard.data) {
            applyWizardData(storedWizard.data);
            wizardStep = Number(storedWizard.step) || 1;
        } else if ($('#ai-fr-onboarding').hasClass('is-hidden')) {
            try { window.sessionStorage.removeItem(wizardStorageKey); } catch (e) {}
        }
        if (!$('#ai-fr-onboarding').hasClass('is-hidden')) openWizard(false); else showWizardStep(1);

        var initialFormState = $('#ai-fr-main-form').serialize();
        function updateDirtyState() {
            var dirty = $('#ai-fr-main-form').serialize() !== initialFormState;
            $('#ai-fr-dirty-state').text(dirty ? 'Modifiche non salvate' : 'Tutte le modifiche sono salvate').toggleClass('is-dirty', dirty);
        }
        $('#ai-fr-main-form').on('input change', ':input', updateDirtyState);
        $('#ai-fr-main-form').on('click', '.ai-fr-repeater-add, .ai-fr-repeater-remove, #ai-fr-schema-service-add, .ai-fr-schema-service-remove, #ai-fr-schema-image-clear, #ai-fr-schema-logo-clear', function () {
            window.setTimeout(updateDirtyState, 0);
        });

        $('.ai-fr-save-notice-dismiss').on('click', function () {
            $(this).closest('.ai-fr-save-notice').attr('hidden', true);
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
