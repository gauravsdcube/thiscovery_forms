humhub.module('thiscoveryForms', function (module, require, $) {
    var OPTION_TYPES = ['dropdown', 'radio', 'checkbox'];

    var initBuilder = function (root) {
        var $root = $(root);
        if (!$root.length) {
            return;
        }

        if (module.config.optionTypes && module.config.optionTypes.length) {
            OPTION_TYPES = module.config.optionTypes;
        }

        var fieldIndex = 0;

        var refreshIndexes = function () {
            $root.find('.thiscovery-forms-field-row').each(function (i) {
                $(this).find('[data-cf-index]').text(String(i + 1));
                $(this).find('[data-cf-sort-order]').val(String(i));
            });
            $root.find('[data-cf-empty]').toggleClass('d-none', $root.find('.thiscovery-forms-field-row').length > 0);
        };

        var ownCard = function ($row) {
            return $row.children('.cf-field-card__header, .cf-field-card__body');
        };

        var ownGroupBody = function ($row) {
            return $row.children('[data-cf-group-shell]').find('[data-cf-group-body]').first();
        };

        var refreshTypeUi = function ($row) {
            var $own = ownCard($row);
            var type = $own.find('[data-cf-field-type]').val() || $row.find('[data-cf-field-type]').first().val();
            var needsOptions = OPTION_TYPES.indexOf(type) !== -1;
            var isRating = type === (module.config.ratingType || 'rating');
            var isRanking = type === 'ranking';
            var isChoice = ['dropdown', 'radio', 'checkbox'].indexOf(type) !== -1;
            var isPage = type === (module.config.pageBreakType || 'page_break');
            var isRich = type === (module.config.richTextType || 'rich_text');
            var isHtml = type === (module.config.htmlType || 'html');
            var isMeta = type === 'respondent_meta';
            var isPanelAttr = type === 'panel_attr';
            var hideRequired = isPage || isRich || isMeta || isPanelAttr && $own.find('[data-cf-hidden-field]').first().is(':checked') || type === 'question_group' || type === 'group_end';
            var hideHelp = isPage || type === 'group_end';
            var isHiddenQ = $own.find('[data-cf-hidden-field]').first().is(':checked') || isMeta;

            $row.attr('data-cf-type', type);
            $row.toggleClass('is-group', type === 'question_group');
            $row.toggleClass('is-group-end d-none', type === 'group_end');
            $row.children('[data-cf-group-shell]').toggleClass('d-none', type !== 'question_group');
            $own.find('[data-cf-options-panel]').toggleClass('d-none', !needsOptions);
            $own.find('[data-cf-rating-panel]').toggleClass('d-none', !isRating);
            $own.find('[data-cf-page-panel]').toggleClass('d-none', !isPage);
            $own.find('[data-cf-rich-panel]').toggleClass('d-none', !isRich);
            $own.find('[data-cf-html-panel]').toggleClass('d-none', !isHtml);
            $own.find('[data-cf-grid-panel]').toggleClass('d-none', type !== 'grid_single' && type !== 'grid_multi');
            $own.find('[data-cf-items-panel]').toggleClass('d-none', type !== 'best_worst' && type !== 'maxdiff');
            $own.find('[data-cf-maxdiff-panel], [data-cf-maxdiff-note]').toggleClass('d-none', type !== 'maxdiff');
            $own.find('[data-cf-drilldown-panel]').toggleClass('d-none', type !== 'drilldown');
            $own.find('[data-cf-image-panel]').toggleClass('d-none', type !== 'image_area');
            $own.find('[data-cf-map-panel]').toggleClass('d-none', type !== 'map');
            $own.find('[data-cf-carry-wrap]').toggleClass('d-none', ['dropdown', 'radio', 'checkbox'].indexOf(type) === -1);
            $own.find('[data-cf-justify-wrap]').toggleClass('d-none', ['dropdown', 'radio', 'checkbox', 'rating', 'ranking'].indexOf(type) === -1);
            $own.find('[data-cf-ranking-note]').toggleClass('d-none', !isRanking);
            $own.find('[data-cf-choice-note]').toggleClass('d-none', !isChoice);
            $own.find('[data-cf-max-select-wrap]').toggleClass('d-none', type !== 'checkbox');
            $own.find('[data-cf-options-hint-ranking]').toggleClass('d-none', !isRanking);
            $own.find('[data-cf-options-hint-choice]').toggleClass('d-none', isRanking);
            $own.find('[data-cf-required-wrap]').toggleClass('d-none', hideRequired);
            $own.find('[data-cf-help-wrap]').toggleClass('d-none', hideHelp);
            $own.find('[data-cf-hidden-wrap]').toggleClass('d-none', isPage || type === 'question_group' || type === 'group_end' || isRich);
            $own.find('[data-cf-attention-wrap]').toggleClass('d-none', hideRequired || isMeta || isPanelAttr);
            $own.find('[data-cf-hidden-field]').prop('disabled', isMeta);
            if (isMeta) {
                $own.find('[data-cf-hidden-field]').prop('checked', true);
            }
            $own.find('[data-cf-default-wrap]').toggleClass('d-none', !isHiddenQ || isMeta || isPanelAttr);
            $own.find('[data-cf-meta-note]').toggleClass('d-none', !isMeta);
            $own.find('[data-cf-panel-note]').toggleClass('d-none', !isPanelAttr);
            $own.find('[data-cf-email-note]').toggleClass('d-none', type !== 'email');
            $own.find('[data-cf-meta-key-wrap]').toggleClass('d-none', !isMeta);
            $own.find('[data-cf-panel-key-wrap]').toggleClass('d-none', !isPanelAttr);
            if (isMeta && !$.trim($own.find('[data-cf-meta-key]').val())) {
                $own.find('[data-cf-meta-key]').val('ip');
            }
            if (isPanelAttr && !$.trim($own.find('[data-cf-panel-key]').val())) {
                $own.find('[data-cf-panel-key]').val('first_name');
            }
            $own.find('[data-cf-logic-goto-wrap]').toggleClass('d-none', $own.find('[data-cf-logic-action]').val() !== 'goto_page');

            var $logicAction = $own.find('[data-cf-logic-action]');
            var groupActions = module.config.groupLogicActions || {};
            var allActions = module.config.logicActions || {};
            if ($logicAction.length) {
                $logicAction.find('option').each(function () {
                    var v = String($(this).val() || '');
                    if (type === 'question_group' && groupActions[v]) {
                        $(this).text(groupActions[v]).prop('hidden', false);
                    } else if (type === 'question_group') {
                        $(this).prop('hidden', true);
                    } else {
                        if (allActions[v]) {
                            $(this).text(allActions[v]);
                        }
                        $(this).prop('hidden', false);
                    }
                });
                if (type === 'question_group' && groupActions[$logicAction.val()] == null) {
                    $logicAction.val('show');
                }
            }

            if (isPage && !$.trim($own.find('[data-cf-page-key]').val())) {
                $own.find('[data-cf-page-key]').val('p' + Math.random().toString(36).slice(2, 8));
            }

            var typeLabels = module.config.types || {};
            var metaLabels = module.config.metaKeys || {};
            var panelLabels = module.config.panelKeys || {};
            var metaKey = String($own.find('[data-cf-meta-key]').val() || '');
            var panelKey = String($own.find('[data-cf-panel-key]').val() || '');
            $own.find('[data-cf-type-label]').first().text(
                isMeta ? (metaLabels[metaKey] || typeLabels[type] || type)
                    : (isPanelAttr ? (panelLabels[panelKey] || typeLabels[type] || type) : (typeLabels[type] || type))
            );

            var label = $.trim($own.find('[data-cf-field-label]').first().val());
            if (!label && (isPage || isRich || isHtml || type === 'question_group')) {
                label = typeLabels[type] || type;
            }
            $own.find('[data-cf-title]').first().text(label || module.text('untitled'));

            var required = $own.find('[data-cf-required]').first().is(':checked') && !hideRequired;
            var $req = $own.find('.cf-field-card__req').not('[data-cf-hidden-badge]');
            if (required) {
                if (!$req.length) {
                    $own.find('.cf-field-card__title').first().append(
                        $('<span class="cf-field-card__req"/>').text(module.text('required') || 'Required')
                    );
                }
            } else {
                $req.remove();
            }
            var $hiddenBadge = $own.find('[data-cf-hidden-badge]');
            if (isHiddenQ) {
                if (!$hiddenBadge.length) {
                    $own.find('.cf-field-card__title').first().append(
                        $('<span class="cf-field-card__req" data-cf-hidden-badge/>').text(module.config.hiddenBadge || 'Hidden')
                    );
                }
            } else {
                $hiddenBadge.remove();
            }

            if (isRich) {
                setTimeout(function () {
                    initRichEditors($row);
                }, 30);
            }
        };

        var selectedKeyCandidates = function (wanted) {
            wanted = String(wanted || '');
            if (!wanted) {
                return [];
            }
            var candidates = [wanted];
            if (/^\d+$/.test(wanted)) {
                candidates.push('id' + wanted);
            } else if (/^id\d+$/.test(wanted)) {
                candidates.push(wanted.slice(2));
            }
            return candidates;
        };

        var rememberedSelectKey = function ($select) {
            return String($select.val() || $select.attr('data-cf-selected-key') || '');
        };

        var rememberSelectKey = function ($select, value) {
            value = String(value || '');
            if (value) {
                $select.attr('data-cf-selected-key', value);
            } else {
                $select.removeAttr('data-cf-selected-key');
            }
        };

        var applySelectKey = function ($select, wanted, selfKey) {
            var candidates = selectedKeyCandidates(wanted).filter(function (key) {
                return key && key !== selfKey;
            });
            var i;
            for (i = 0; i < candidates.length; i++) {
                if ($select.find('option[value="' + candidates[i] + '"]').length) {
                    $select.val(candidates[i]);
                    rememberSelectKey($select, candidates[i]);
                    return candidates[i];
                }
            }
            if (wanted && wanted !== selfKey) {
                $select.append($('<option/>').val(wanted).text('#' + wanted));
                $select.val(wanted);
                rememberSelectKey($select, wanted);
                return wanted;
            }
            $select.val('');
            return '';
        };

        var retitleConditionOption = function (key, label) {
            if (!key) {
                return;
            }
            $root.find('[data-cf-condition-field], [data-cf-branch-field], [data-cf-carry-from]')
                .find('option[value="' + key + '"]')
                .text(label);
        };

        var refreshConditionOptions = function () {
            var items = [];
            $root.find('.thiscovery-forms-field-row').each(function () {
                var $row = $(this);
                var key = String($row.attr('data-cf-key') || '');
                var type = $row.find('[data-cf-field-type]').val();
                if (!key || type === 'page_break' || type === 'rich_text') {
                    return;
                }
                items.push({
                    key: key,
                    label: $.trim($row.find('[data-cf-field-label]').val()) || ('#' + key)
                });
            });
            var noneLabel = module.text('none');
            $root.find('[data-cf-condition-field], [data-cf-branch-field], [data-cf-carry-from]').each(function () {
                var $select = $(this);
                var selfKey = String($select.closest('.thiscovery-forms-field-row').attr('data-cf-key') || '');
                var wanted = rememberedSelectKey($select);
                var $tmp = $('<select/>');
                $tmp.append($('<option/>').val('').text(noneLabel));
                items.forEach(function (item) {
                    if (item.key === selfKey) {
                        return;
                    }
                    $tmp.append($('<option/>').val(item.key).text(item.label));
                });
                $select.empty().append($tmp.children());
                applySelectKey($select, wanted, selfKey);
            });
        };

        var syncLogicKeep = function ($row, force) {
            var $own = ownCard($row);
            var $keep = $row.children('[data-cf-logic-keep]');
            if (!$keep.length) {
                $keep = $own.find('[data-cf-logic-keep]').first();
            }
            if (!$keep.length) {
                return;
            }
            var rules = [];
            $own.find('[data-cf-logic-rule-row]').each(function () {
                var $rule = $(this);
                var $field = $rule.find('[data-cf-condition-field]');
                var fk = rememberedSelectKey($field);
                if (!fk) {
                    return;
                }
                rules.push({
                    fieldKey: fk,
                    operator: String($rule.find('[data-cf-logic-operator]').val() || 'equals'),
                    value: String($rule.find('[data-cf-logic-value]').val() || '')
                });
            });
            if (!rules.length && !force) {
                try {
                    var parsed = JSON.parse(String($keep.val() || ''));
                    if (parsed && parsed.rules && parsed.rules.length) {
                        return;
                    }
                } catch (e) {}
            }
            $keep.val(JSON.stringify({
                action: String($own.find('[data-cf-logic-action]').val() || 'show'),
                combinator: String($own.find('[data-cf-logic-combinator]').val() || 'and'),
                gotoPageKey: String($own.find('[name$="[logic_goto]"]').val() || ''),
                rules: rules
            }));
        };

        var syncAllLogicKeep = function () {
            $root.find('.thiscovery-forms-field-row').each(function () {
                var $row = $(this);
                $row.find('[data-cf-condition-field], [data-cf-branch-field], [data-cf-carry-from]').each(function () {
                    var $select = $(this);
                    var selfKey = String($row.attr('data-cf-key') || '');
                    applySelectKey($select, rememberedSelectKey($select), selfKey);
                });
                syncLogicKeep($row);
            });
        };

        var reindexBranches = function ($row) {
            ownCard($row).find('[data-cf-branch-row]').each(function (i) {
                $(this).find('select, input').each(function () {
                    var name = $(this).attr('name');
                    if (!name) {
                        return;
                    }
                    $(this).attr('name', name.replace(/\[branches\]\[\d+\]/, '[branches][' + i + ']'));
                });
            });
        };

        var reindexLogicRules = function ($row) {
            ownCard($row).find('[data-cf-logic-rule-row]').each(function (i) {
                $(this).find('select, input').each(function () {
                    var name = $(this).attr('name');
                    if (!name) {
                        return;
                    }
                    $(this).attr('name', name.replace(/\[logic_rules\]\[\d+\]/, '[logic_rules][' + i + ']'));
                });
            });
        };

        var reindexNamedRows = function ($list, rowSel, pattern) {
            $list.find(rowSel).each(function (i) {
                $(this).find('select, input').each(function () {
                    var name = $(this).attr('name');
                    if (!name) {
                        return;
                    }
                    $(this).attr('name', name.replace(pattern, function (m, pre) {
                        return pre + i + ']';
                    }));
                });
            });
        };

        var refreshActionParams = function ($scope) {
            var fn = $scope.find('[data-cf-action-fn]').val();
            $scope.find('[data-cf-action-param]').each(function () {
                var key = $(this).attr('data-cf-action-param');
                var show = false;
                if (fn === 'send_email' && key === 'send_email') {
                    show = true;
                } else if (fn === 'goto_page' && key === 'goto_page') {
                    show = true;
                } else if ((fn === 'set_variable' || fn === 'custom') && (key === 'name' || key === 'value')) {
                    show = true;
                }
                $(this).toggleClass('d-none', !show);
            });
            $scope.find('[data-cf-action-hint]').toggleClass('d-none', fn !== 'custom' && fn !== 'set_variable');
        };

        var parseRegions = function ($row) {
            try {
                var parsed = JSON.parse($row.find('[data-cf-image-regions]').val() || '[]');
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        };

        var writeRegions = function ($row, regions) {
            $row.find('[data-cf-image-regions]').val(JSON.stringify(regions));
        };

        var renderHotspotBuilder = function ($row) {
            var $panel = $row.find('[data-cf-hotspot-builder]');
            if (!$panel.length) {
                return;
            }
            var url = $.trim(String($row.find('[data-cf-image-src]').val() || $row.find('[data-cf-image-url]').val() || ''));
            var $img = $panel.find('[data-cf-hotspot-img]');
            if (url) {
                $img.attr('src', url).removeClass('d-none');
            } else {
                $img.addClass('d-none').attr('src', '');
            }
            var regions = parseRegions($row);
            var $overlay = $panel.find('[data-cf-hotspot-overlay]');
            $overlay.empty();
            regions.forEach(function (region, idx) {
                var $box = $('<button type="button" class="cf-hotspot-region"/>')
                    .css({
                        left: (region.x || 0) + '%',
                        top: (region.y || 0) + '%',
                        width: (region.w || 10) + '%',
                        height: (region.h || 10) + '%'
                    })
                    .attr('data-cf-region-index', idx)
                    .append($('<span/>').text(region.label || ('R' + (idx + 1))));
                $overlay.append($box);
            });
            var $list = $panel.find('[data-cf-hotspot-list]');
            $list.empty();
            regions.forEach(function (region, idx) {
                var $item = $('<div class="cf-hotspot-edit row g-2 mb-2"/>');
                $item.append($('<div class="col-md-4"/>').append(
                    $('<input class="form-control" data-cf-region-label>').val(region.label || '').attr('placeholder', 'Label')
                ));
                $item.append($('<div class="col-md-2"/>').append(
                    $('<input class="form-control" type="number" data-cf-region-score>').val(region.score || 0)
                ));
                $item.append($('<div class="col-md-3"/>').append(
                    $('<label class="cf-switch"/>').append(
                        $('<input type="checkbox" data-cf-region-correct>').prop('checked', !!region.correct),
                        ' Correct'
                    )
                ));
                $item.append($('<div class="col-md-3"/>').append(
                    $('<button type="button" class="btn btn-sm btn-light" data-cf-remove-region/>').html('<i class="fa fa-times"></i>')
                ));
                $item.attr('data-cf-region-index', idx);
                $list.append($item);
            });
        };

        var bindHotspotBuilder = function ($row) {
            var $panel = $row.find('[data-cf-hotspot-builder]');
            if (!$panel.length || $panel.data('cf-bound')) {
                return;
            }
            $panel.data('cf-bound', 1);
            var start = null;
            $panel.on('mousedown', '[data-cf-hotspot-overlay]', function (e) {
                if ($(e.target).closest('[data-cf-region-index]').length) {
                    return;
                }
                var rect = this.getBoundingClientRect();
                if (!rect.width || !rect.height) {
                    return;
                }
                start = {
                    x: ((e.clientX - rect.left) / rect.width) * 100,
                    y: ((e.clientY - rect.top) / rect.height) * 100
                };
            });
            $(document).on('mouseup.cfHotspot' + $row.data('cf-key'), function (e) {
                if (!start) {
                    return;
                }
                var overlay = $panel.find('[data-cf-hotspot-overlay]')[0];
                if (!overlay) {
                    start = null;
                    return;
                }
                var rect = overlay.getBoundingClientRect();
                var x2 = ((e.clientX - rect.left) / rect.width) * 100;
                var y2 = ((e.clientY - rect.top) / rect.height) * 100;
                var x = Math.max(0, Math.min(start.x, x2));
                var y = Math.max(0, Math.min(start.y, y2));
                var w = Math.min(100 - x, Math.abs(x2 - start.x));
                var h = Math.min(100 - y, Math.abs(y2 - start.y));
                start = null;
                if (w < 3 || h < 3) {
                    return;
                }
                var regions = parseRegions($row);
                regions.push({
                    id: 'r' + Math.random().toString(36).slice(2, 8),
                    label: 'Region ' + (regions.length + 1),
                    x: Math.round(x * 10) / 10,
                    y: Math.round(y * 10) / 10,
                    w: Math.round(w * 10) / 10,
                    h: Math.round(h * 10) / 10,
                    correct: false,
                    score: 0
                });
                writeRegions($row, regions);
                renderHotspotBuilder($row);
            });
        };

        var expandCard = function ($row) {
            $root.find('.thiscovery-forms-field-row').addClass('is-collapsed').removeClass('is-expanded');
            $row.removeClass('is-collapsed').addClass('is-expanded');
            initRichEditors($row);
        };

        var editorApi = function () {
            try {
                return require('thiscoveryEditor');
            } catch (e) {
                return null;
            }
        };

        var initRichEditors = function ($scope) {
            var api = editorApi();
            var $ctx = ($scope && $scope.length) ? $scope : $root;
            if (api && typeof api.initEditors === 'function') {
                api.initEditors($ctx);
                return;
            }
            if (window.ThiscoveryEditor && typeof window.ThiscoveryEditor.init === 'function') {
                $ctx.find('[data-te-editor]').each(function () {
                    try { window.ThiscoveryEditor.init(this); } catch (err) {}
                });
            }
        };

        var destroyRichEditors = function ($scope) {
            var api = editorApi();
            var $ctx = ($scope && $scope.length) ? $scope : $root;
            if (api && typeof api.saveEditors === 'function') {
                api.saveEditors($ctx);
            }
            if (api && typeof api.destroyEditors === 'function') {
                api.destroyEditors($ctx);
                return;
            }
            if (window.ThiscoveryEditor && typeof window.ThiscoveryEditor.destroy === 'function') {
                $ctx.find('[data-te-editor]').each(function () {
                    try { window.ThiscoveryEditor.destroy(this); } catch (err) {}
                });
            }
        };

        var syncRichEditors = function () {
            var api = editorApi();
            if (api && typeof api.saveEditors === 'function') {
                api.saveEditors($root);
                return;
            }
            if (window.ThiscoveryEditor && typeof window.ThiscoveryEditor.saveAll === 'function') {
                try { window.ThiscoveryEditor.saveAll(); } catch (e) {}
            }
        };

        var remountRichEditors = function ($scope) {
            destroyRichEditors($scope);
        };

        var refreshGroupEmpty = function ($body) {
            if (!$body || !$body.length) {
                return;
            }
            var hasKids = $body.children('.thiscovery-forms-field-row').not('[data-cf-type="group_end"]').length > 0;
            $body.children('[data-cf-group-empty]').toggleClass('d-none', hasKids);
        };

        var createFieldRow = function (type, opts) {
            type = type || 'text';
            opts = opts || {};
            var template = $root.find('#cf-field-template').html();
            var html = template.replace(/__INDEX__/g, String(fieldIndex++));
            var $row = $(html);
            var typeLabels = module.config.types || {};
            var metaLabels = module.config.metaKeys || {};
            $row.find('[data-cf-field-type]').val(type);
            var metaKey = String(opts.metaKey || '');
            var panelKey = String(opts.panelKey || '');
            if (type === 'respondent_meta') {
                if (!metaKey) {
                    metaKey = 'ip';
                }
                $row.find('[data-cf-meta-key]').val(metaKey);
                $row.find('[data-cf-field-label]').val(metaLabels[metaKey] || typeLabels[type] || '');
            } else if (type === 'panel_attr') {
                var panelLabels = module.config.panelKeys || {};
                if (!panelKey) {
                    panelKey = 'first_name';
                }
                $row.find('[data-cf-panel-key]').val(panelKey);
                $row.find('[data-cf-field-label]').val(panelLabels[panelKey] || typeLabels[type] || '');
                $row.find('[data-cf-hidden-field]').prop('checked', true);
            } else if (!$.trim($row.find('[data-cf-field-label]').val())) {
                $row.find('[data-cf-field-label]').val(typeLabels[type] || '');
            }
            $row.find('[data-cf-action-row]').each(function () {
                $(this).find('[data-cf-action-fn]').val('none');
                refreshActionParams($(this));
            });
            refreshTypeUi($row);
            return $row;
        };

        var nestGroups = function () {
            var $list = $root.find('[data-cf-fields]');
            $list.children('.thiscovery-forms-field-row').each(function () {
                var $group = $(this);
                if (String($group.attr('data-cf-type') || '') !== 'question_group') {
                    return;
                }
                var $body = ownGroupBody($group);
                if (!$body.length) {
                    return;
                }
                var $anchor = $group;
                $body.children('.thiscovery-forms-field-row').each(function () {
                    $anchor.after(this);
                    $anchor = $(this);
                });
            });
            var typeOf = function (el) {
                var $row = $(el);
                return String($row.attr('data-cf-type') || ownCard($row).find('[data-cf-field-type]').val() || '');
            };
            var rows = $list.children('.thiscovery-forms-field-row').get();
            for (var i = 0; i < rows.length; i++) {
                if (typeOf(rows[i]) !== 'question_group') {
                    continue;
                }
                var $group = $(rows[i]);
                var $body = ownGroupBody($group);
                if (!$body.length) {
                    continue;
                }
                var depth = 1;
                var endAt = -1;
                for (var j = i + 1; j < rows.length; j++) {
                    var t = typeOf(rows[j]);
                    if (t === 'page_break') {
                        break;
                    }
                    if (t === 'question_group') {
                        depth++;
                    } else if (t === 'group_end') {
                        depth--;
                        if (depth === 0) {
                            endAt = j;
                            break;
                        }
                    }
                }
                if (endAt < 0) {
                    if (!$body.children('[data-cf-type="group_end"]').length) {
                        $body.append(createFieldRow('group_end'));
                    }
                    refreshGroupEmpty($body);
                    continue;
                }
                for (var k = i + 1; k < endAt; k++) {
                    $body.append(rows[k]);
                }
                $body.append(rows[endAt]);
                refreshGroupEmpty($body);
            }
            $root.find('[data-cf-group-body]').each(function () {
                refreshGroupEmpty($(this));
            });
        };

        var addField = function (type, $beforeEl, $intoBody, opts) {
            type = type || 'text';
            opts = opts || {};
            var maxAnswerable = parseInt(module.config.maxAnswerable, 10) || 0;
            var skipTypes = ['page_break', 'rich_text', 'question_group', 'group_end'];
            var isAnswerable = skipTypes.indexOf(type) === -1;
            if (maxAnswerable > 0 && isAnswerable) {
                var count = 0;
                $root.find('.thiscovery-forms-field-row').each(function () {
                    var t = $(this).find('[data-cf-field-type]').val();
                    if (skipTypes.indexOf(t) === -1) {
                        count++;
                    }
                });
                if (count >= maxAnswerable) {
                    window.alert(module.config.pollLimit || 'A quick poll can have only one question.');
                    return null;
                }
            }

            var $list = $root.find('[data-cf-fields]');
            var $row = createFieldRow(type, opts);

            if ($intoBody && $intoBody.length && type !== 'question_group' && type !== 'page_break' && type !== 'group_end') {
                var $end = $intoBody.children('[data-cf-type="group_end"]');
                if ($beforeEl && $beforeEl.length && $beforeEl.closest('[data-cf-group-body]')[0] === $intoBody[0]) {
                    $row.insertBefore($beforeEl);
                } else if ($end.length) {
                    $row.insertBefore($end);
                } else {
                    $intoBody.append($row);
                }
                refreshGroupEmpty($intoBody);
            } else if ($beforeEl && $beforeEl.length) {
                $row.insertBefore($beforeEl);
            } else {
                $list.append($row);
            }

            if (type === 'question_group') {
                var $endRow = createFieldRow('group_end');
                $row.find('[data-cf-group-body]').first().append($endRow);
                refreshGroupEmpty($row.find('[data-cf-group-body]').first());
            }

            refreshIndexes();
            refreshConditionOptions();
            if (type !== 'group_end') {
                expandCard($row);
            }
            if (type === 'question_group') {
                ownCard($row).find('[data-cf-logic-panel]').addClass('is-open');
            }
            bindHotspotBuilder($row);
            renderHotspotBuilder($row);
            setTimeout(function () {
                initRichEditors($row);
                if (type !== 'group_end') {
                    $row.find('[data-cf-field-label]').trigger('focus');
                }
            }, 50);
            return $row;
        };

        // Tabs: Form builder | Settings | Response integrity | CSS | Share
        $root.on('click', '[data-cf-tab]', function (e) {
            e.preventDefault();
            var tab = $(this).data('cf-tab');
            $root.find('[data-cf-tab]').removeClass('is-active').attr('aria-selected', 'false');
            $(this).addClass('is-active').attr('aria-selected', 'true');
            $root.find('[data-cf-panel]').removeClass('is-active');
            $root.find('[data-cf-panel="' + tab + '"]').addClass('is-active');
            $root.find('.cf-studio__footer').toggle(tab === 'builder' || tab === 'settings' || tab === 'integrity' || tab === 'css' || tab === 'share');
            $root.find('[data-cf-studio-tab]').val(tab);
            var $help = $root.find('[data-cf-studio-help]');
            if ($help.length) {
                var pages = {};
                try {
                    pages = JSON.parse($help.attr('data-cf-help-pages') || '{}');
                } catch (err) {}
                if (pages[tab]) {
                    $help.attr('href', pages[tab]);
                }
            }
            if (tab === 'settings' || tab === 'builder' || tab === 'translations' || tab === 'rounds') {
                setTimeout(function () {
                    initRichEditors($root.find('[data-cf-panel="' + tab + '"]'));
                }, 30);
            }
            setTimeout(layoutStudioPanes, 0);
        });

        var syncEnrol = function () {
            var $box = $root.find('[data-cf-enrol]');
            if (!$box.length) {
                return;
            }
            var mode = $box.find('[data-cf-enrol-mode]').val() || 'none';
            $box.find('[data-cf-enrol-existing]').toggle(mode === 'existing');
            $box.find('[data-cf-enrol-create]').toggle(mode === 'create');
        };
        $root.on('change', '[data-cf-enrol-mode]', syncEnrol);
        syncEnrol();

        $root.on('click', '[data-cf-guide-toggle]', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var expanded = $btn.attr('aria-expanded') === 'true';
            var next = !expanded;
            var $panel = $('#' + $btn.attr('aria-controls'));
            $btn.attr('aria-expanded', next ? 'true' : 'false');
            $btn.toggleClass('is-open', next);
            $panel.prop('hidden', !next);
        });

        $root.on('click', '[data-cf-acc-all]', function () {
            var open = $(this).attr('data-cf-acc-all') === 'open';
            $root.find('[data-cf-panel="settings"] details.cf-set-acc').prop('open', open);
        });

        try {
            var params = new URLSearchParams(window.location.search);
            var openTab = params.get('tab');
            if (openTab) {
                $root.find('[data-cf-tab="' + openTab + '"]').trigger('click');
            }
        } catch (e) {}

        var toSwatchHex = function (value) {
            var v = String(value || '').trim();
            var short = v.match(/^#([0-9a-f]{3})$/i);
            if (short) {
                return '#' + short[1].split('').map(function (c) { return c + c; }).join('');
            }
            var full = v.match(/^#([0-9a-f]{6})(?:[0-9a-f]{2})?$/i);
            return full ? ('#' + full[1]) : '';
        };

        $root.on('input', '[data-cf-style-swatch]', function () {
            var $text = $(this).closest('.cf-style-color').find('[data-cf-style-color]');
            $text.val(this.value);
        });

        $root.on('input change', '[data-cf-style-color]', function () {
            var hex = toSwatchHex(this.value);
            if (hex) {
                $(this).closest('.cf-style-color').find('[data-cf-style-swatch]').val(hex);
            }
        });

        $root.on('click', '[data-cf-style-clear]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.cf-style-color');
            $row.find('[data-cf-style-color]').val('');
            $row.find('[data-cf-style-swatch]').val('#ffffff');
        });

        $root.on('click', '[data-cf-copy-url]', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var $input = $btn.closest('.input-group').find('input').first();
            if (!$input.length) {
                $input = $root.find('[data-cf-share-url]').first();
            }
            var url = $input.val() || '';
            var $fb = $btn.closest('.form-group').find('[data-cf-copy-feedback]');
            if (!$fb.length) {
                $fb = $root.find('[data-cf-copy-feedback]').first();
            }
            var done = function () {
                $fb.removeClass('d-none');
                setTimeout(function () {
                    $fb.addClass('d-none');
                }, 1800);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(done).catch(function () {
                    $input.trigger('select');
                    try {
                        document.execCommand('copy');
                    } catch (err) {
                    }
                    done();
                });
            } else {
                $input.trigger('select');
                try {
                    document.execCommand('copy');
                } catch (err) {
                }
                done();
            }
        });

        var parseFieldName = function (name) {
            var match = String(name || '').match(/^fields\[([^\]]+)\](.*)$/);
            if (!match) {
                return null;
            }
            var keys = [match[1]];
            var rest = match[2] || '';
            var re = /\[([^\]]*)]/g;
            var part;
            while ((part = re.exec(rest))) {
                keys.push(part[1]);
            }
            return keys;
        };

        var assignPath = function (obj, keys, value) {
            var cur = obj;
            var i;
            for (i = 0; i < keys.length; i++) {
                var key = keys[i];
                var last = i === keys.length - 1;
                if (last) {
                    cur[key] = value;
                    return;
                }
                if (cur[key] == null || typeof cur[key] !== 'object') {
                    cur[key] = {};
                }
                cur = cur[key];
            }
        };

        var fieldControlValue = function (el) {
            var $el = $(el);
            var type = String(el.type || '').toLowerCase();
            if (type === 'button' || type === 'submit' || type === 'file' || type === 'reset') {
                return null;
            }
            if (type === 'checkbox' || type === 'radio') {
                return el.checked ? $el.val() : null;
            }
            return $el.val();
        };

        var collectFieldPost = function ($row) {
            var data = {};
            var rowEl = $row[0];
            $row.find('input, select, textarea').each(function () {
                if ($(this).closest('.thiscovery-forms-field-row')[0] !== rowEl) {
                    return;
                }
                var name = $(this).attr('name');
                var keys = parseFieldName(name);
                if (!keys || keys.length < 2) {
                    return;
                }
                var value = fieldControlValue(this);
                if (value === null) {
                    return;
                }
                assignPath(data, keys.slice(1), value);
            });
            return data;
        };

        var collectLibraryFields = function ($row) {
            var fields = {};
            var n = 0;
            var add = function ($node) {
                fields['f' + (n++)] = collectFieldPost($node);
                if (String($node.attr('data-cf-type') || '') === 'question_group') {
                    ownGroupBody($node).children('.thiscovery-forms-field-row').each(function () {
                        add($(this));
                    });
                }
            };
            add($row);
            return fields;
        };

        var collectFieldsPayload = function () {
            var fields = {};
            $root.find('[data-cf-fields] .thiscovery-forms-field-row').each(function () {
                $(this).find('input, select, textarea').each(function () {
                    var name = $(this).attr('name');
                    var keys = parseFieldName(name);
                    if (!keys) {
                        return;
                    }
                    var value = fieldControlValue(this);
                    if (value === null) {
                        return;
                    }
                    assignPath(fields, keys, value);
                });
            });
            return fields;
        };

        $root.on('submit', 'form.cf-studio__form', function () {
            refreshIndexes();
            syncRichEditors();
            syncAllLogicKeep();
            var $form = $(this);
            var payload = collectFieldsPayload();
            var $json = $form.find('[data-cf-fields-json]');
            if (!$json.length) {
                $json = $('<input type="hidden" name="fields_json" data-cf-fields-json="true">').appendTo($form);
            }
            $json.prop('disabled', false).val(JSON.stringify(payload));
            $form.find('[data-cf-fields] .thiscovery-forms-field-row').find('input, select, textarea').each(function () {
                var name = this.name;
                if (name && name.indexOf('fields[') === 0) {
                    this.disabled = true;
                }
            });
        });

        $root.on('submit', '[data-cf-panel="rounds"] form', function () {
            var api = editorApi();
            if (api && typeof api.saveEditors === 'function') {
                api.saveEditors($(this));
            }
        });

        // Palette: click to add
        $root.on('click', '[data-cf-palette-tab]', function (e) {
            e.preventDefault();
            var tab = $(this).data('cf-palette-tab');
            $root.find('[data-cf-palette-tab]').removeClass('is-active');
            $(this).addClass('is-active');
            $root.find('[data-cf-palette-panel]').addClass('d-none');
            $root.find('[data-cf-palette-panel="' + tab + '"]').removeClass('d-none');
            if (tab === 'library') {
                loadLibrary();
            }
        });

        var libraryItemHtml = function (item) {
            var typeLabels = module.config.libraryTypes || {};
            var typeLabel = typeLabels[item.type] || item.type;
            var count = parseInt(item.fieldCount, 10) || 0;
            var countTpl = count === 1
                ? (module.config.libraryFieldOne || '{n} field')
                : (module.config.libraryFieldMany || '{n} fields');
            var meta = typeLabel + ' · ' + countTpl.replace('{n}', String(count));
            if (item.global) {
                meta += ' · ' + (module.config.libraryGlobal || 'global');
            }
            var del = item.canManage
                ? '<button type="button" class="btn btn-sm btn-light cf-library-item__delete" data-cf-library-delete="' + item.id + '" title="' +
                    $('<div>').text(module.config.deleteConfirm || 'Delete').html() + '"><i class="fa fa-trash"></i></button>'
                : '';
            return '<div class="cf-library-item">' +
                '<button type="button" class="cf-palette__item cf-library-item__add" draggable="true" data-cf-library-insert="' + item.id + '">' +
                '<span class="cf-palette__icon"><i class="fa fa-bookmark-o"></i></span>' +
                '<span class="cf-library-item__text">' +
                '<span class="cf-palette__label">' + $('<div>').text(item.title).html() + '</span>' +
                '<span class="cf-library-item__meta">' + $('<div>').text(meta).html() + '</span>' +
                '</span>' +
                '</button>' + del +
                '</div>';
        };

        var loadLibrary = function () {
            var url = module.config.libraryListUrl;
            if (!url) {
                return;
            }
            $.getJSON(url).done(function (data) {
                var $list = $root.find('[data-cf-library-list]');
                var items = (data && data.items) ? data.items : [];
                if (!items.length) {
                    $list.html('<p class="cf-hint text-muted">' + (module.config.libraryEmpty || '') + '</p>');
                    return;
                }
                $list.html(items.map(libraryItemHtml).join(''));
            });
        };

        var csrfData = function () {
            var d = {};
            $root.find('form.cf-studio__form').find('input[type="hidden"]').each(function () {
                var name = $(this).attr('name');
                if (!name || name.indexOf('fields[') === 0 || name === 'fields_json') {
                    return;
                }
                d[name] = $(this).val();
            });
            return d;
        };

        var saveLibrary = function (title, type, fields) {
            $.post(module.config.librarySaveUrl, $.extend(csrfData(), {
                title: title,
                type: type,
                fields: fields
            })).done(function (data) {
                if (data && data.success) {
                    loadLibrary();
                    if (window.alert) {
                        // brief confirmation
                    }
                } else if (data && data.error) {
                    window.alert(data.error);
                }
            });
        };

        $root.on('click', '[data-cf-save-question]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var title = window.prompt(module.config.libraryTitlePrompt || 'Name');
            if (!title) {
                return;
            }
            var $row = $(this).closest('.thiscovery-forms-field-row');
            syncRichEditors();
            saveLibrary(title, 'question', collectLibraryFields($row));
        });

        $root.on('click', '[data-cf-library-save-block]', function (e) {
            e.preventDefault();
            var title = window.prompt(module.config.libraryTitlePrompt || 'Name');
            if (!title) {
                return;
            }
            var fields = {};
            syncRichEditors();
            $root.find('.thiscovery-forms-field-row').each(function (i) {
                fields['b' + i] = collectFieldPost($(this));
            });
            saveLibrary(title, 'block', fields);
        });

        var dropPlacement = function (e) {
            var $body = $(e.target).closest('[data-cf-group-body]');
            var $inTarget = $body.length
                ? $(e.target).closest('[data-cf-group-body] > .thiscovery-forms-field-row')
                : $();
            var $target = $(e.target).closest('.thiscovery-forms-field-row');
            var $before = null;
            if ($target.length) {
                var rect = $target[0].getBoundingClientRect();
                if (e.originalEvent.clientY > rect.top + rect.height / 2) {
                    var $next = $target.next('.thiscovery-forms-field-row');
                    $before = $next.length ? $next : null;
                } else {
                    $before = $target;
                }
            }
            return {
                $intoBody: $body,
                $beforeEl: $inTarget.length ? $inTarget : $before
            };
        };

        var libraryRowsAreStructural = function ($rows) {
            var found = false;
            $rows.each(function () {
                var t = String($(this).attr('data-cf-type') || '');
                if (t === 'question_group' || t === 'page_break' || t === 'group_end') {
                    found = true;
                    return false;
                }
            });
            return found;
        };

        var mountLibraryRows = function ($html, $beforeEl, $intoBody) {
            var $rows = $html.filter('.thiscovery-forms-field-row');
            if (!$rows.length) {
                $rows = $html.find('> .thiscovery-forms-field-row');
            }
            if (!$rows.length) {
                $rows = $html.find('.thiscovery-forms-field-row').filter(function () {
                    return $(this).parent().closest('.thiscovery-forms-field-row').length === 0;
                });
            }
            if (!$rows.length) {
                return $();
            }
            if ($intoBody && $intoBody.length && libraryRowsAreStructural($rows)) {
                var $groupCard = $intoBody.closest('.thiscovery-forms-field-row');
                $intoBody = $();
                var $afterGroup = $groupCard.next('.thiscovery-forms-field-row');
                $beforeEl = $afterGroup.length ? $afterGroup : null;
            }
            var $list = $root.find('[data-cf-fields]');
            if ($intoBody && $intoBody.length) {
                var $end = $intoBody.children('[data-cf-type="group_end"]');
                if ($beforeEl && $beforeEl.length && $beforeEl.closest('[data-cf-group-body]')[0] === $intoBody[0]) {
                    $beforeEl.before($rows);
                } else if ($end.length) {
                    $end.before($rows);
                } else {
                    $intoBody.append($rows);
                }
                refreshGroupEmpty($intoBody);
            } else if ($beforeEl && $beforeEl.length) {
                $beforeEl.before($rows);
            } else {
                $list.append($rows);
            }
            $rows.each(function () {
                var $row = $(this);
                refreshTypeUi($row);
                bindHotspotBuilder($row);
                renderHotspotBuilder($row);
                initRichEditors($row);
            });
            nestGroups();
            refreshIndexes();
            refreshConditionOptions();
            $root.find('[data-cf-empty]').addClass('d-none');
            return $rows;
        };

        var insertLibraryItem = function (id, $beforeEl, $intoBody) {
            id = String(id || '').replace(/^cf-library:/, '');
            if (!id || !module.config.libraryInsertUrl) {
                return;
            }
            $.getJSON(module.config.libraryInsertUrl, { itemId: id }).done(function (data) {
                if (!data || !data.success || !data.html) {
                    window.alert(module.config.libraryInsertError || 'Error');
                    return;
                }
                mountLibraryRows($(data.html), $beforeEl, $intoBody);
            }).fail(function () {
                window.alert(module.config.libraryInsertError || 'Error');
            });
        };

        $root.on('click', '[data-cf-library-insert]', function (e) {
            e.preventDefault();
            insertLibraryItem($(this).attr('data-cf-library-insert'));
        });

        $root.on('dragstart', '[data-cf-library-insert]', function (e) {
            var id = String($(this).attr('data-cf-library-insert') || '');
            if (!id) {
                return;
            }
            e.originalEvent.dataTransfer.setData('text/cf-library-id', id);
            e.originalEvent.dataTransfer.setData('text/plain', 'cf-library:' + id);
            e.originalEvent.dataTransfer.effectAllowed = 'copy';
            $(this).addClass('is-dragging');
            $root.find('[data-cf-canvas]').addClass('is-drop-target');
        });

        $root.on('dragend', '[data-cf-library-insert]', function () {
            $(this).removeClass('is-dragging');
            $root.find('[data-cf-canvas]').removeClass('is-drop-target is-drag-over');
        });

        $root.on('click', '[data-cf-insert-health-status]', function (e) {
            e.preventDefault();
            var url = module.config.healthStatusInsertUrl;
            if (!url) {
                return;
            }
            $.getJSON(url).done(function (data) {
                if (!data || !data.success || !data.html) {
                    window.alert(module.config.healthStatusInsertError || 'Error');
                    return;
                }
                var $html = $(data.html);
                $root.find('[data-cf-fields]').append($html);
                $html.each(function () {
                    refreshTypeUi($(this));
                    initRichEditors($(this));
                });
                refreshIndexes();
                refreshConditionOptions();
                $root.find('[data-cf-empty]').addClass('d-none');
            }).fail(function () {
                window.alert(module.config.healthStatusInsertError || 'Error');
            });
        });

        $root.on('click', '[data-cf-library-delete]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!window.confirm(module.config.deleteConfirm || 'Delete?')) {
                return;
            }
            var id = String($(this).attr('data-cf-library-delete') || '');
            if (!id || !module.config.libraryDeleteUrl) {
                return;
            }
            $.post(module.config.libraryDeleteUrl, $.extend(csrfData(), { itemId: id })).done(function (data) {
                if (data && data.success === false) {
                    window.alert(module.config.libraryDeleteError || 'Could not delete that library item.');
                    return;
                }
                loadLibrary();
            }).fail(function () {
                window.alert(module.config.libraryDeleteError || 'Could not delete that library item.');
            });
        });

        $root.on('click', '[data-cf-add-in-group]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $body = $(this).closest('.thiscovery-forms-field-row').find('[data-cf-group-body]').first();
            addField('text', null, $body);
        });

        $root.on('click', '[data-cf-palette-type]', function (e) {
            e.preventDefault();
            addField(String($(this).data('cf-palette-type')), null, null, {
                metaKey: String($(this).attr('data-cf-palette-meta') || ''),
                panelKey: String($(this).attr('data-cf-palette-panel') || '')
            });
        });

        // Palette: HTML5 drag onto canvas
        $root.on('dragstart', '[data-cf-palette-type]', function (e) {
            var type = String($(this).data('cf-palette-type'));
            var metaKey = String($(this).attr('data-cf-palette-meta') || '');
            var panelKey = String($(this).attr('data-cf-palette-panel') || '');
            e.originalEvent.dataTransfer.setData('text/cf-field-type', type);
            e.originalEvent.dataTransfer.setData('text/cf-field-meta', metaKey);
            e.originalEvent.dataTransfer.setData('text/cf-field-panel', panelKey);
            e.originalEvent.dataTransfer.setData('text/plain', type);
            e.originalEvent.dataTransfer.effectAllowed = 'copy';
            $(this).addClass('is-dragging');
            $root.find('[data-cf-canvas]').addClass('is-drop-target');
        });

        $root.on('dragend', '[data-cf-palette-type]', function () {
            $(this).removeClass('is-dragging');
            $root.find('[data-cf-canvas]').removeClass('is-drop-target is-drag-over');
        });

        var $canvas = $root.find('[data-cf-canvas]');
        $canvas.on('dragover', function (e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'copy';
            $(this).addClass('is-drag-over');
        });
        $canvas.on('dragleave', function (e) {
            if (!$.contains(this, e.relatedTarget)) {
                $(this).removeClass('is-drag-over');
            }
        });
        $canvas.on('drop', function (e) {
            e.preventDefault();
            $(this).removeClass('is-drag-over is-drop-target');
            var dt = e.originalEvent.dataTransfer;
            var libraryId = dt.getData('text/cf-library-id') || '';
            var plain = dt.getData('text/plain') || '';
            if (!libraryId && String(plain).indexOf('cf-library:') === 0) {
                libraryId = String(plain).slice('cf-library:'.length);
            }
            var place = dropPlacement(e);
            if (libraryId) {
                insertLibraryItem(libraryId, place.$beforeEl, place.$intoBody);
                return;
            }
            var type = dt.getData('text/cf-field-type') || plain;
            var metaKey = dt.getData('text/cf-field-meta') || '';
            var panelKey = dt.getData('text/cf-field-panel') || '';
            var fieldOpts = { metaKey: metaKey, panelKey: panelKey };
            if (!type || String(type).indexOf('cf-reorder:') === 0 || String(type).indexOf('cf-library:') === 0) {
                return;
            }
            var $body = place.$intoBody;
            if ($body.length && type !== 'question_group' && type !== 'page_break' && type !== 'group_end') {
                addField(type, place.$beforeEl, $body, fieldOpts);
                return;
            }
            if ($body.length && (type === 'question_group' || type === 'page_break')) {
                var $groupCard = $body.closest('.thiscovery-forms-field-row');
                var $after = $groupCard.next('.thiscovery-forms-field-row');
                addField(type, $after.length ? $after : null, null, fieldOpts);
                return;
            }
            addField(type, place.$beforeEl, null, fieldOpts);
        });

        // Canvas reorder via handle + up/down buttons
        (function initCanvasReorder() {
            var dragging = null;
            var didDrag = false;
            var startY = 0;
            var $list = $root.find('[data-cf-fields]');

            var finishDrag = function () {
                if (!dragging) {
                    return;
                }
                var $moved = $(dragging);
                var moved = didDrag;
                $moved.removeClass('is-reordering');
                $list.removeClass('is-sorting');
                dragging = null;
                refreshIndexes();
                $root.find('[data-cf-group-body]').each(function () {
                    refreshGroupEmpty($(this));
                });
                $(document).off('.cfBuilderSort');
                if (moved) {
                    remountRichEditors($moved);
                    setTimeout(function () {
                        initRichEditors($moved);
                    }, 30);
                }
            };

            var moveDragging = function (clientY) {
                if (!dragging) {
                    return;
                }
                var $drag = $(dragging);
                var dragType = String($drag.attr('data-cf-type') || $drag.find('[data-cf-field-type]').val() || '');
                var lockInside = dragType === 'question_group' || dragType === 'page_break' || dragType === 'group_end';
                if (!lockInside) {
                    var $hitBody = null;
                    $root.find('[data-cf-group-body]').each(function () {
                        var rect = this.getBoundingClientRect();
                        if (clientY >= rect.top && clientY <= rect.bottom) {
                            $hitBody = $(this);
                            return false;
                        }
                    });
                    if ($hitBody && $hitBody.length) {
                        var $kids = $hitBody.children('.thiscovery-forms-field-row').not('[data-cf-type="group_end"]');
                        var placed = false;
                        $kids.each(function () {
                            if (this === dragging) {
                                return;
                            }
                            var rect = this.getBoundingClientRect();
                            if (clientY < rect.top + rect.height / 2) {
                                if (dragging.nextElementSibling !== this) {
                                    this.parentNode.insertBefore(dragging, this);
                                }
                                placed = true;
                                return false;
                            }
                        });
                        if (!placed) {
                            var $end = $hitBody.children('[data-cf-type="group_end"]');
                            if ($end.length) {
                                $end.before(dragging);
                            } else {
                                $hitBody.append(dragging);
                            }
                        }
                        refreshGroupEmpty($hitBody);
                        return;
                    }
                }
                var $others = $list.children('.thiscovery-forms-field-row').filter(function () {
                    return this !== dragging;
                });
                var placedTop = false;
                $others.each(function () {
                    var rect = this.getBoundingClientRect();
                    if (clientY < rect.top + rect.height / 2) {
                        if (dragging.nextElementSibling !== this) {
                            this.parentNode.insertBefore(dragging, this);
                        }
                        placedTop = true;
                        return false;
                    }
                });
                if (!placedTop && dragging.parentNode === $list[0]) {
                    $list[0].appendChild(dragging);
                } else if (!placedTop) {
                    $list.append(dragging);
                }
            };

            $list.on('mousedown touchstart', '[data-cf-drag-handle]', function (e) {
                if (e.type === 'mousedown' && e.which !== 1) {
                    return;
                }
                var $item = $(this).closest('.thiscovery-forms-field-row');
                if (!$item.length) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();

                dragging = $item[0];
                didDrag = false;
                startY = e.type === 'touchstart' ? e.originalEvent.touches[0].clientY : e.clientY;
                $item.addClass('is-reordering');
                $list.addClass('is-sorting');

                $(document).on('mousemove.cfBuilderSort touchmove.cfBuilderSort', function (ev) {
                    if (!dragging) {
                        return;
                    }
                    var y = ev.type === 'touchmove' ? ev.originalEvent.touches[0].clientY : ev.clientY;
                    if (Math.abs(y - startY) > 4) {
                        didDrag = true;
                    }
                    ev.preventDefault();
                    moveDragging(y);
                });

                $(document).on('mouseup.cfBuilderSort touchend.cfBuilderSort touchcancel.cfBuilderSort', function () {
                    finishDrag();
                });
            });

            // Suppress expand/collapse click right after a drag
            $list.on('click', '[data-cf-drag-handle]', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (didDrag) {
                    didDrag = false;
                }
            });

            $root.on('click', '[data-cf-move-up]', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $row = $(this).closest('.thiscovery-forms-field-row');
                var $prev = $row.prev('.thiscovery-forms-field-row');
                if ($prev.length) {
                    remountRichEditors($row);
                    $row.insertBefore($prev);
                    refreshIndexes();
                    setTimeout(function () {
                        initRichEditors($row);
                    }, 30);
                }
            });

            $root.on('click', '[data-cf-move-down]', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $row = $(this).closest('.thiscovery-forms-field-row');
                var $next = $row.next('.thiscovery-forms-field-row');
                if ($next.length) {
                    remountRichEditors($row);
                    $row.insertAfter($next);
                    refreshIndexes();
                    setTimeout(function () {
                        initRichEditors($row);
                    }, 30);
                }
            });
        })();

        // Expand / collapse field cards
        $root.on('click', '[data-cf-toggle-card], [data-cf-toggle-card-btn]', function (e) {
            if ($(e.target).closest('[data-cf-drag-handle], [data-cf-remove-field], [data-cf-toggle-advanced], [data-cf-move-up], [data-cf-move-down]').length) {
                return;
            }
            e.preventDefault();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            if ($row.hasClass('is-expanded')) {
                $row.addClass('is-collapsed').removeClass('is-expanded');
            } else {
                expandCard($row);
            }
        });

        $root.on('click', '[data-cf-clear-fields]', function (e) {
            e.preventDefault();
            var msg = module.config.clearConfirm || 'Remove all fields from this form?';
            if (!window.confirm(msg)) {
                return;
            }
            $root.find('[data-cf-fields]').empty();
            refreshIndexes();
            refreshConditionOptions();
        });

        $root.on('click', '[data-cf-remove-field]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var type = String($row.attr('data-cf-type') || '');
            if (type === 'question_group') {
                var $body = $row.find('[data-cf-group-body]').first();
                var $end = $body.children('[data-cf-type="group_end"]');
                $body.children('.thiscovery-forms-field-row').not('[data-cf-type="group_end"]').insertAfter($row);
                $end.remove();
            }
            var $oldBody = $row.parent('[data-cf-group-body]');
            $row.remove();
            if ($oldBody.length) {
                refreshGroupEmpty($oldBody);
            }
            refreshIndexes();
            refreshConditionOptions();
        });

        $root.on('click', '[data-cf-toggle-advanced]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            expandCard($row);
            ownCard($row).find('[data-cf-logic-panel]').addClass('is-open');
        });

        $root.on('click', '[data-cf-add-branch]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var $list = ownCard($row).find('[data-cf-branch-list]').first();
            var $proto = $list.find('[data-cf-branch-row]').first();
            if (!$proto.length) {
                return;
            }
            var $clone = $proto.clone();
            $clone.find('input').val('');
            $clone.find('select').prop('selectedIndex', 0);
            $clone.find('[data-cf-selected-key]').removeAttr('data-cf-selected-key');
            $list.append($clone);
            reindexBranches($row);
            refreshConditionOptions();
        });

        $root.on('click', '[data-cf-remove-branch]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var $list = ownCard($row).find('[data-cf-branch-list]').first();
            if ($list.find('[data-cf-branch-row]').length <= 1) {
                $list.find('input').val('');
                $list.find('select').prop('selectedIndex', 0);
                return;
            }
            $(this).closest('[data-cf-branch-row]').remove();
            reindexBranches($row);
        });

        $root.on('click', '[data-cf-add-logic-rule]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var $list = ownCard($row).find('[data-cf-logic-rule-list]').first();
            var $proto = $list.find('[data-cf-logic-rule-row]').first();
            if (!$proto.length) {
                return;
            }
            var $clone = $proto.clone();
            $clone.find('input').val('');
            $clone.find('select').prop('selectedIndex', 0);
            $clone.find('[data-cf-selected-key]').removeAttr('data-cf-selected-key');
            $list.append($clone);
            reindexLogicRules($row);
            refreshConditionOptions();
        });

        $root.on('click', '[data-cf-remove-logic-rule]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var $list = ownCard($row).find('[data-cf-logic-rule-list]').first();
            if ($list.find('[data-cf-logic-rule-row]').length <= 1) {
                $list.find('input').val('');
                $list.find('select').prop('selectedIndex', 0);
                return;
            }
            $(this).closest('[data-cf-logic-rule-row]').remove();
            reindexLogicRules($row);
        });

        $root.on('change', '[data-cf-logic-action]', function () {
            var $row = $(this).closest('.thiscovery-forms-field-row');
            ownCard($row).find('[data-cf-logic-goto-wrap]').toggleClass('d-none', $(this).val() !== 'goto_page');
        });

        $root.on('change', '[data-cf-action-fn]', function () {
            refreshActionParams($(this).closest('[data-cf-action-row]'));
        });

        $root.on('click', '[data-cf-add-action]', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('[data-cf-field-actions], .cf-studio__settings, #cf-builder');
            var $list = $(this).siblings('[data-cf-action-list]').length
                ? $(this).siblings('[data-cf-action-list]')
                : $(this).prevAll('[data-cf-action-list]').first();
            if (!$list.length) {
                $list = $(this).parent().find('[data-cf-action-list]').first();
            }
            var $proto = $list.find('[data-cf-action-row]').first();
            if (!$proto.length) {
                return;
            }
            var $clone = $proto.clone();
            $clone.find('input').val('');
            $clone.find('[data-cf-action-fn]').val('none');
            $clone.find('select').not('[data-cf-action-fn]').prop('selectedIndex', 0);
            $list.append($clone);
            refreshActionParams($clone);
            reindexNamedRows($list, '[data-cf-action-row]', /(\[actions\]\[|submit_actions\[)\d+\]/);
        });

        $root.on('click', '[data-cf-remove-action]', function (e) {
            e.preventDefault();
            var $list = $(this).closest('[data-cf-action-list]');
            if ($list.find('[data-cf-action-row]').length <= 1) {
                $list.find('input').val('');
                $list.find('[data-cf-action-fn]').val('none');
                $list.find('select').not('[data-cf-action-fn]').prop('selectedIndex', 0);
                refreshActionParams($list.find('[data-cf-action-row]').first());
                return;
            }
            $(this).closest('[data-cf-action-row]').remove();
            reindexNamedRows($list, '[data-cf-action-row]', /(\[actions\]\[|submit_actions\[)\d+\]/);
        });

        $root.on('click', '[data-cf-add-fn]', function (e) {
            e.preventDefault();
            var $list = $root.find('[data-cf-fn-list]');
            var $proto = $list.find('[data-cf-fn-row]').first();
            if (!$proto.length) {
                return;
            }
            var $clone = $proto.clone();
            $clone.find('input').val('');
            $list.append($clone);
            reindexNamedRows($list, '[data-cf-fn-row]', /(custom_functions\[)\d+\]/);
        });

        $root.on('click', '[data-cf-remove-fn]', function (e) {
            e.preventDefault();
            var $list = $root.find('[data-cf-fn-list]');
            if ($list.find('[data-cf-fn-row]').length <= 1) {
                $list.find('input').val('');
                return;
            }
            $(this).closest('[data-cf-fn-row]').remove();
            reindexNamedRows($list, '[data-cf-fn-row]', /(custom_functions\[)\d+\]/);
        });

        var reindexConsistency = function () {
            $root.find('[data-cf-consistency-rule]').each(function (ri) {
                $(this).find('[name^="integrity[consistency_rules]"]').each(function () {
                    var name = $(this).attr('name') || '';
                    name = name.replace(/integrity\[consistency_rules\]\[\d+\]/, 'integrity[consistency_rules][' + ri + ']');
                    $(this).attr('name', name);
                });
                $(this).find('[data-cf-consistency-cond]').each(function (ci) {
                    $(this).find('[name]').each(function () {
                        var name = $(this).attr('name') || '';
                        name = name.replace(/\[conditions\]\[\d+\]/, '[conditions][' + ci + ']');
                        $(this).attr('name', name);
                    });
                });
            });
        };
        $root.on('click', '[data-cf-add-consistency]', function (e) {
            e.preventDefault();
            var $list = $root.find('[data-cf-consistency-rules]');
            var $proto = $list.find('[data-cf-consistency-rule]').first();
            if (!$proto.length) {
                return;
            }
            var $clone = $proto.clone();
            $clone.find('input[type="text"]').val('');
            $clone.find('select').prop('selectedIndex', 0);
            $list.append($clone);
            reindexConsistency();
        });
        $root.on('click', '[data-cf-remove-consistency]', function (e) {
            e.preventDefault();
            var $list = $root.find('[data-cf-consistency-rules]');
            if ($list.find('[data-cf-consistency-rule]').length <= 1) {
                $(this).closest('[data-cf-consistency-rule]').find('input[type="text"]').val('');
                $(this).closest('[data-cf-consistency-rule]').find('select').prop('selectedIndex', 0);
                return;
            }
            $(this).closest('[data-cf-consistency-rule]').remove();
            reindexConsistency();
        });

        $root.on('input change', '[data-cf-image-url]', function () {
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var url = $.trim(String($(this).val() || ''));
            if (url) {
                $row.find('[data-cf-image-guid]').val('');
                $row.find('[data-cf-image-src]').val(url);
                $row.find('[data-cf-image-clear]').removeClass('d-none');
            }
            renderHotspotBuilder($row);
        });

        $root.on('change', '[data-cf-image-file]', function () {
            var input = this;
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var file = input.files && input.files[0];
            if (!file) {
                return;
            }
            if (file.type && file.type.indexOf('image/') !== 0) {
                window.alert(module.config.notImage || 'Please choose an image file.');
                input.value = '';
                return;
            }
            var $status = $row.find('[data-cf-image-status]');
            $status.text(module.config.uploading || 'Uploading…');
            var fd = new FormData();
            fd.append('files[]', file);
            var csrf = csrfData();
            Object.keys(csrf).forEach(function (key) {
                fd.append(key, csrf[key]);
            });
            if (module.config.uploadModel && module.config.uploadModelId) {
                fd.append('objectModel', module.config.uploadModel);
                fd.append('objectId', module.config.uploadModelId);
            }
            $.ajax({
                url: module.config.uploadUrl,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function (data) {
                var files = (data && data.files) ? data.files : [];
                var info = files[0] || {};
                if (info.error || !info.guid) {
                    var err = info.errors;
                    if (Array.isArray(err)) {
                        err = err.join(' ');
                    }
                    $status.text(err || module.config.uploadError || 'Could not upload.');
                    return;
                }
                if (info.mimeType && String(info.mimeType).indexOf('image/') !== 0) {
                    $status.text(module.config.notImage || 'Please choose an image file.');
                    return;
                }
                $row.find('[data-cf-image-guid]').val(info.guid);
                $row.find('[data-cf-image-src]').val(info.url || info.thumbnailUrl || '');
                $row.find('[data-cf-image-url]').val('');
                $row.find('[data-cf-image-clear]').removeClass('d-none');
                $status.text(info.name || '');
                renderHotspotBuilder($row);
            }).fail(function () {
                $status.text(module.config.uploadError || 'Could not upload.');
            }).always(function () {
                input.value = '';
            });
        });

        $root.on('click', '[data-cf-image-clear]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            $row.find('[data-cf-image-guid], [data-cf-image-src], [data-cf-image-url]').val('');
            $row.find('[data-cf-image-status]').text('');
            $(this).addClass('d-none');
            renderHotspotBuilder($row);
        });

        $root.on('input change', '[data-cf-region-label], [data-cf-region-score], [data-cf-region-correct]', function () {
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var regions = parseRegions($row);
            $row.find('[data-cf-hotspot-list] [data-cf-region-index]').each(function () {
                var idx = parseInt($(this).attr('data-cf-region-index'), 10);
                if (!regions[idx]) {
                    return;
                }
                regions[idx].label = $.trim(String($(this).find('[data-cf-region-label]').val() || ''));
                regions[idx].score = parseInt($(this).find('[data-cf-region-score]').val() || '0', 10) || 0;
                regions[idx].correct = $(this).find('[data-cf-region-correct]').is(':checked');
            });
            writeRegions($row, regions);
        });

        $root.on('click', '[data-cf-remove-region]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var idx = parseInt($(this).closest('[data-cf-region-index]').attr('data-cf-region-index'), 10);
            var regions = parseRegions($row);
            regions.splice(idx, 1);
            writeRegions($row, regions);
            renderHotspotBuilder($row);
        });

        $root.on('change', '[data-cf-field-type]', function () {
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var type = String($(this).val() || '');
            refreshTypeUi($row);
            if (type === 'question_group') {
                var $body = $row.find('[data-cf-group-body]').first();
                if ($body.length && !$body.children('[data-cf-type="group_end"]').length) {
                    $body.append(createFieldRow('group_end'));
                }
                refreshGroupEmpty($body);
            } else {
                var $leaveBody = $row.find('[data-cf-group-body]').first();
                if ($leaveBody.length) {
                    $leaveBody.children('[data-cf-type="group_end"]').remove();
                    $leaveBody.children('.thiscovery-forms-field-row').insertAfter($row);
                }
            }
            refreshConditionOptions();
        });

        $root.on('input', '[data-cf-field-label]', function () {
            var $row = $(this).closest('.thiscovery-forms-field-row');
            refreshTypeUi($row);
            var key = String($row.attr('data-cf-key') || '');
            var label = $.trim(String($(this).val() || '')) || ('#' + key);
            retitleConditionOption(key, label);
        });

        $root.on('change', '[data-cf-required]', function () {
            refreshTypeUi($(this).closest('.thiscovery-forms-field-row'));
        });

        $root.on('change', '[data-cf-hidden-field]', function () {
            var $row = $(this).closest('.thiscovery-forms-field-row');
            if ($(this).is(':checked')) {
                $row.find('[data-cf-required]').first().prop('checked', false);
            }
            refreshTypeUi($row);
        });

        $root.on('change', '[data-cf-meta-key]', function () {
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var $own = ownCard($row);
            var labels = module.config.metaKeys || {};
            var typeLabels = module.config.types || {};
            var next = String($(this).val() || '');
            var $label = $own.find('[data-cf-field-label]').first();
            var cur = $.trim($label.val());
            var known = Object.keys(labels).map(function (k) { return labels[k]; });
            known.push(typeLabels.respondent_meta || 'Respondent metadata');
            if (!cur || known.indexOf(cur) !== -1) {
                $label.val(labels[next] || cur);
            }
            refreshTypeUi($row);
        });

        $root.on('change', '[data-cf-panel-key]', function () {
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var $own = ownCard($row);
            var labels = module.config.panelKeys || {};
            var typeLabels = module.config.types || {};
            var next = String($(this).val() || '');
            var $label = $own.find('[data-cf-field-label]').first();
            var cur = $.trim($label.val());
            var known = Object.keys(labels).map(function (k) { return labels[k]; });
            known.push(typeLabels.panel_attr || 'Panel member field');
            if (!cur || known.indexOf(cur) !== -1) {
                $label.val(labels[next] || cur);
            }
            refreshTypeUi($row);
        });

        $root.on('change', '[data-cf-condition-field], [data-cf-branch-field], [data-cf-carry-from]', function () {
            var $select = $(this);
            rememberSelectKey($select, $select.val());
            syncLogicKeep($select.closest('.thiscovery-forms-field-row'), true);
        });

        $root.on('change input', '[data-cf-logic-action], [data-cf-logic-combinator], [data-cf-logic-operator], [data-cf-logic-value], [name$="[logic_goto]"]', function () {
            syncLogicKeep($(this).closest('.thiscovery-forms-field-row'), true);
        });

        var layoutStudioPanes = function () {
            var $workspace = $root.find('.cf-studio__workspace');
            if (!$workspace.length) {
                return;
            }
            $workspace.css('height', '');
            if (!$root.find('[data-cf-panel="builder"]').hasClass('is-active')) {
                return;
            }
            if (window.matchMedia && window.matchMedia('(max-width: 991px)').matches) {
                return;
            }
            var el = $workspace.get(0);
            var top = el.getBoundingClientRect().top;
            if (top < 0) {
                top = 0;
            }
            var $footer = $root.find('.cf-studio__footer:visible');
            var footerH = $footer.length ? $footer.outerHeight(true) : 0;
            var gap = 12;
            var height = Math.floor(window.innerHeight - top - footerH - gap);
            if (height < 280) {
                height = 280;
            }
            $workspace.css('height', height + 'px');
        };

        $(window).off('resize.cfStudioPanes').on('resize.cfStudioPanes', layoutStudioPanes);

        $root.find('.thiscovery-forms-field-row').each(function () {
            var $row = $(this);
            refreshTypeUi($row);
            bindHotspotBuilder($row);
            renderHotspotBuilder($row);
            $row.find('[data-cf-condition-field], [data-cf-branch-field], [data-cf-carry-from]').each(function () {
                var $select = $(this);
                rememberSelectKey($select, $select.val() || $select.attr('data-cf-selected-key'));
            });
        });
        nestGroups();
        refreshIndexes();
        refreshConditionOptions();
        $root.find('.thiscovery-forms-field-row').each(function () {
            syncLogicKeep($(this));
        });
        layoutStudioPanes();
        initIntegritySettings();
    };

    var initFill = function (root) {
        var $root = $(root);
        if (!$root.length) {
            return;
        }

        var clearUnsavedChoicePrefill = function () {
            $root.find('.cf-choice-list, [data-cf-rating], .cf-grid, .cf-best-worst, .cf-maxdiff').each(function () {
                $(this).find('input[type="radio"], input[type="checkbox"]').each(function () {
                    if (!this.hasAttribute('checked')) {
                        this.checked = false;
                    }
                });
                $(this).find('.cf-rating-option.is-selected').each(function () {
                    if (!$(this).find('input[type="radio"]').is(':checked')) {
                        $(this).removeClass('is-selected');
                    }
                });
            });
            $root.find('select.cf-input').each(function () {
                var $select = $(this);
                var hasSaved = $select.find('option').filter(function () {
                    return this.hasAttribute('selected') && this.value !== '';
                }).length > 0;
                if (!hasSaved) {
                    $select.prop('selectedIndex', 0);
                    if ($select.find('option[value=""]').length) {
                        $select.val('');
                    }
                }
            });
        };
        clearUnsavedChoicePrefill();

        var autosaveTimer = null;
        var autosaveXhr = null;
        var autosaveQueued = false;
        var fillSubmitting = false;
        var applyResumeCode = function (code) {
            var $form = $root.find('[data-cf-fill-form]');
            if (!$form.length || !code) {
                return;
            }
            var $code = $form.find('input[name="resume_code"]');
            if ($code.length) {
                $code.val(code);
            } else {
                $form.prepend($('<input type="hidden" name="resume_code">').val(code));
            }
        };
        var autosaveProgress = function () {
            var $form = $root.find('[data-cf-fill-form]');
            if (fillSubmitting || !$form.length || $form.attr('data-cf-autosave') !== '1') {
                return;
            }
            if (autosaveXhr) {
                autosaveQueued = true;
                return;
            }
            var saveUrl = $form.attr('data-cf-save-url');
            if (!saveUrl) {
                return;
            }
            try {
                if (typeof syncHtmlValues === 'function') {
                    syncHtmlValues();
                }
                if (typeof syncFileGuids === 'function') {
                    syncFileGuids();
                }
            } catch (e) {}
            if (typeof writeTimingField === 'function') {
                flushPageTime(typeof currentPage !== 'undefined' ? currentPage : 0);
                flushQuestionTimes();
                writeTimingField();
            }
            autosaveQueued = false;
            autosaveXhr = $.ajax({
                url: saveUrl,
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            }).done(function (res) {
                if (!res || !res.success || !res.resume_code) {
                    return;
                }
                applyResumeCode(res.resume_code);
            }).always(function () {
                autosaveXhr = null;
                if (autosaveQueued && !fillSubmitting) {
                    autosaveQueued = false;
                    autosaveProgress();
                }
            });
        };
        var cancelAutosave = function () {
            fillSubmitting = true;
            autosaveQueued = false;
            if (autosaveTimer) {
                clearTimeout(autosaveTimer);
                autosaveTimer = null;
            }
            if (autosaveXhr && typeof autosaveXhr.abort === 'function') {
                autosaveXhr.abort();
                autosaveXhr = null;
            }
        };
        var scheduleAutosave = function () {
            if (fillSubmitting) {
                return;
            }
            if (autosaveTimer) {
                clearTimeout(autosaveTimer);
            }
            autosaveTimer = setTimeout(autosaveProgress, 900);
        };

        var timingState = {
            startedAt: Date.now(),
            pages: {},
            questions: {},
            pageEntered: Date.now(),
            questionEntered: {}
        };
        var writeTimingField = function () {
            var $field = $root.find('[data-cf-integrity-timing]');
            if (!$field.length) {
                return;
            }
            $field.val(JSON.stringify({
                startedAt: timingState.startedAt,
                pages: timingState.pages,
                questions: timingState.questions
            }));
        };
        var flushPageTime = function (idx) {
            var now = Date.now();
            var spent = now - timingState.pageEntered;
            var key = String(idx);
            timingState.pages[key] = (timingState.pages[key] || 0) + Math.max(0, spent);
            timingState.pageEntered = now;
        };
        var flushQuestionTimes = function () {
            var now = Date.now();
            Object.keys(timingState.questionEntered).forEach(function (id) {
                var spent = now - timingState.questionEntered[id];
                timingState.questions[id] = (timingState.questions[id] || 0) + Math.max(0, spent);
            });
            timingState.questionEntered = {};
        };
        if (module.config.questionTiming) {
            $root.on('focusin', '[data-cf-conditional]', function () {
                var id = $(this).attr('data-cf-field-id');
                if (id) {
                    timingState.questionEntered[id] = Date.now();
                }
            });
            $root.on('focusout', '[data-cf-conditional]', function () {
                var id = $(this).attr('data-cf-field-id');
                if (!id || !timingState.questionEntered[id]) {
                    return;
                }
                var spent = Date.now() - timingState.questionEntered[id];
                timingState.questions[id] = (timingState.questions[id] || 0) + Math.max(0, spent);
                delete timingState.questionEntered[id];
                writeTimingField();
            });
        }
        writeTimingField();

        if (module.config.fillFileDeleteUrl && window.humhub && humhub.modules && humhub.modules.file
            && humhub.modules.file.config && humhub.modules.file.config.upload) {
            humhub.modules.file.config.upload.deleteUrl = module.config.fillFileDeleteUrl;
        }

        var filesFromUpload = function (response) {
            if (!response) {
                return [];
            }
            if (response.result && $.isArray(response.result.files)) {
                return response.result.files;
            }
            if ($.isArray(response.files)) {
                return response.files;
            }
            return [];
        };

        var applyUploadGuid = function ($from, response) {
            var files = filesFromUpload(response);
            if (!files.length || !files[0] || !files[0].guid || files[0].error) {
                return;
            }
            var $guid = $();
            var id = $from.attr('id');
            if (id) {
                $guid = $root.find('#' + id + '_guid');
            }
            if (!$guid.length) {
                $guid = $from.closest('[data-cf-file-field], .cf-question').find('[data-cf-file-guid]');
            }
            if ($guid.length) {
                $guid.val(files[0].guid);
                updateProgress();
                scheduleAutosave();
            }
        };

        var syncFileGuids = function () {
            $root.find('[data-cf-file-guid]').each(function () {
                var $guidInput = $(this);
                if ($.trim(String($guidInput.val() || '')) !== '') {
                    return;
                }
                var name = $guidInput.attr('name');
                if (name) {
                    var fromHidden = '';
                    $root.find('input[type="hidden"][name="' + name + '"]').each(function () {
                        var v = $.trim(String($(this).val() || ''));
                        if (v) {
                            fromHidden = v;
                        }
                    });
                    if (fromHidden) {
                        $guidInput.val(fromHidden);
                        return;
                    }
                }
                var previewGuid = $guidInput.closest('[data-cf-file-field], .cf-question')
                    .find('[data-preview-guid]').last().attr('data-preview-guid');
                if (previewGuid) {
                    $guidInput.val(previewGuid);
                }
            });
        };

        $root.on('uploadEnd', function (evt, response) {
            applyUploadGuid($(evt.target), response);
        });
        $root.on('fileDeleted', function () {
            var $guid = $(this).closest('[data-cf-file-field], .cf-question').find('[data-cf-file-guid]');
            if ($guid.length) {
                $guid.val('');
                updateProgress();
                scheduleAutosave();
            }
        });

        var isEmailFormat = function (value) {
            var v = $.trim(String(value || ''));
            if (v === '') {
                return true;
            }
            return /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/.test(v);
        };

        var emailInputs = function ($field) {
            return $field.find('input[type="email"], input[data-cf-email="1"]').not('[type="hidden"]');
        };

        var emailInvalid = function ($field) {
            var bad = false;
            emailInputs($field).each(function () {
                if (!isEmailFormat($(this).val())) {
                    bad = true;
                    return false;
                }
            });
            return bad;
        };

        var setFieldMessage = function ($field, msg) {
            var $err = $field.find('[data-cf-field-error]').first();
            if (!msg) {
                $field.removeClass('cf-page-error');
                $err.addClass('d-none').text('');
                $field.find('[data-cf-email-error]').addClass('d-none');
                emailInputs($field).removeClass('is-invalid').attr('aria-invalid', 'false');
                return;
            }
            if (!$err.length) {
                $err = $('<p class="cf-question__error" data-cf-field-error role="alert"/>');
                var $control = $field.find('.cf-question__control');
                ($control.length ? $control : $field).append($err);
            }
            $err.removeClass('d-none').text(msg);
            $field.find('[data-cf-email-error]').addClass('d-none');
            $field.addClass('cf-page-error');
            emailInputs($field).toggleClass('is-invalid', /email/i.test(msg)).attr('aria-invalid', 'true');
        };

        var resetFillButtons = function () {
            var $btns = $root.find('[data-cf-page-next], [data-cf-submit-wrap] button, [data-cf-submit-wrap] input[type="submit"], [data-cf-fill-form] button[type="submit"]');
            $btns.prop('disabled', false).removeClass('disabled').removeAttr('disabled').removeAttr('aria-disabled');
            try {
                var loader = require('ui.loader');
                if (loader && typeof loader.reset === 'function') {
                    $btns.add($root.find('[data-cf-page-back]')).each(function () {
                        loader.reset(this);
                    });
                }
            } catch (err) {}
        };

        var showPageErrorSummary = function (hasErrors) {
            var $box = $root.find('[data-cf-page-errors]');
            if (!$box.length) {
                $box = $('<div class="cf-fill-errors" data-cf-page-errors role="alert"/>');
                var $nav = $root.find('.cf-fill-nav');
                if ($nav.length) {
                    $nav.before($box);
                } else {
                    $root.find('[data-cf-fill-form]').prepend($box);
                }
            }
            if (!hasErrors) {
                $box.addClass('d-none').empty();
                return;
            }
            $box.removeClass('d-none').text(module.config.fixPageErrors || 'Please fix the highlighted questions, then try again.');
        };

        var fieldProblem = function ($field) {
            var required = $field.find('.cf-question__required').length > 0
                || $field.find('[required]').length > 0
                || $field.find('[data-cf-html-required="1"]').length > 0;
            if (required && !isFilled($field)) {
                return module.config.requiredField || 'This question is required.';
            }
            if (typeof otherSpecifyMissing === 'function' && otherSpecifyMissing($field)) {
                return module.config.specifyLabel || 'Please specify your answer.';
            }
            if (typeof checkboxMinMet === 'function' && !checkboxMinMet($field)) {
                return module.config.checkboxMin || 'Select the required number of options.';
            }
            if (emailInvalid($field)) {
                return module.config.invalidEmail || 'Enter a valid email address, for example name@example.com.';
            }
            return '';
        };

        var isFilled = function ($field) {
            if ($field.hasClass('cf-hidden')) {
                return false;
            }
            if (!$field.is('[data-cf-answerable]')) {
                return false;
            }
            var type = String($field.data('cf-field-type') || '');
            if ($field.find('[data-cf-html-value]').length) {
                return $.trim(String($field.find('[data-cf-html-value]').val() || '')) !== '';
            }
            if ($field.find('[data-cf-ranking-value]').length) {
                var ranked = [];
                try {
                    ranked = JSON.parse($field.find('[data-cf-ranking-value]').val() || '[]');
                } catch (e) {
                    ranked = [];
                }
                return Array.isArray(ranked) && ranked.length > 0;
            }
            if ($field.find('[data-cf-drilldown-value]').length) {
                try {
                    var path = JSON.parse($field.find('[data-cf-drilldown-value]').val() || '[]');
                    return Array.isArray(path) && path.length > 0;
                } catch (e) {
                    return false;
                }
            }
            if ($field.find('[data-cf-hotspot-value]').length) {
                try {
                    var hot = JSON.parse($field.find('[data-cf-hotspot-value]').val() || '{}');
                    var regs = hot.regions || hot;
                    return Array.isArray(regs) && regs.length > 0;
                } catch (e) {
                    return false;
                }
            }
            if (type === 'grid_single' || type === 'grid_multi') {
                var ok = true;
                $field.find('tbody tr').each(function () {
                    if (type === 'grid_multi') {
                        if ($(this).find('input[type="checkbox"]:checked').length < 1) {
                            ok = false;
                        }
                    } else if (!$(this).find('input[type="radio"]:checked').length) {
                        ok = false;
                    }
                });
                return ok && $field.find('tbody tr').length > 0;
            }
            if (type === 'best_worst') {
                return !!$field.find('input[name$="[best]"]:checked').val()
                    && !!$field.find('input[name$="[worst]"]:checked').val();
            }
            if (type === 'maxdiff') {
                var setsOk = true;
                $field.find('.cf-maxdiff-set').each(function () {
                    if (!$(this).find('input[name*="[best]"]:checked').val() || !$(this).find('input[name*="[worst]"]:checked').val()) {
                        setsOk = false;
                    }
                });
                return setsOk && $field.find('.cf-maxdiff-set').length > 0;
            }
            if ($field.find('input[type="checkbox"]').length) {
                if ($field.find('input[type="checkbox"]:checked').length < 1) {
                    return false;
                }
                return !otherSpecifyMissing($field);
            }
            if ($field.find('input[type="radio"]').length) {
                if ($field.find('input[type="radio"]:checked').length < 1) {
                    return false;
                }
                return !otherSpecifyMissing($field);
            }
            var $input = $field.find('input, select, textarea').filter(':not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([data-cf-other-text])').first();
            if ($input.length) {
                if ($.trim(String($input.val() || '')) === '') {
                    return false;
                }
                return !otherSpecifyMissing($field);
            }
            var guid = $.trim(String($field.find('[data-cf-file-guid]').val() || ''));
            return guid !== '';
        };

        var choicePairsFor = function (fieldKey) {
            var key = String(fieldKey || '');
            var $q = $root.find('[data-cf-field-id="' + key + '"]');
            if (!$q.length && /^id\d+$/.test(key)) {
                $q = $root.find('[data-cf-field-id="' + key.slice(2) + '"]');
            }
            if (!$q.length && /^\d+$/.test(key)) {
                $q = $root.find('[data-cf-field-id="' + key + '"]');
            }
            try {
                var pairs = JSON.parse($q.attr('data-cf-choice-pairs') || '[]');
                return Array.isArray(pairs) ? pairs : [];
            } catch (e) {
                return [];
            }
        };

        var asChoicePair = function (opt) {
            if (opt && typeof opt === 'object' && opt.code !== undefined) {
                return { code: String(opt.code), label: String(opt.label != null ? opt.label : opt.code) };
            }
            return { code: String(opt), label: String(opt) };
        };

        var isOtherOption = function (opt, pairs) {
            opt = $.trim(String(opt || ''));
            if (!opt || opt.indexOf(':') !== -1) {
                return false;
            }
            if (/^other\b/i.test(opt)) {
                return true;
            }
            pairs = pairs || [];
            return pairs.some(function (p) {
                p = asChoicePair(p);
                return (p.code === opt || p.label === opt) && (/^other\b/i.test(p.code) || /^other\b/i.test(p.label));
            });
        };

        var otherSpecifyMissing = function ($field) {
            var missing = false;
            $field.find('[data-cf-other-wrap]').not('.d-none').each(function () {
                var $input = $(this).find('[data-cf-other-text]');
                if ($input.length && $.trim(String($input.val() || '')) === '') {
                    missing = true;
                }
            });
            return missing;
        };

        var syncOtherSpecify = function ($scope) {
            ($scope && $scope.length ? $scope : $root).find('[data-cf-other-wrap]').each(function () {
                var $wrap = $(this);
                var opt = String($wrap.attr('data-cf-other-option') || '');
                var $q = $wrap.closest('[data-cf-field-id]');
                var selected = false;
                var $select = $q.find('select[data-cf-other-select], select.cf-input').first();
                if ($select.length && $select.is('select')) {
                    selected = String($select.val() || '') === opt;
                } else {
                    selected = $q.find('input[type="radio"], input[type="checkbox"]').filter(function () {
                        return String(this.value) === opt && this.checked;
                    }).length > 0;
                }
                var fieldVisible = !$q.hasClass('cf-hidden');
                $wrap.toggleClass('d-none', !selected);
                $wrap.find('[data-cf-other-text]').prop('disabled', !selected || !fieldVisible);
            });
        };

        var otherPrefix = function (opt) {
            return String(opt || '').replace(/[\s:]+$/, '') + ': ';
        };

        var valueMatchesOption = function (value, opt, pairs) {
            value = String(value);
            opt = String(opt);
            pairs = pairs || [];
            if (value === opt) {
                return true;
            }
            var keys = [opt];
            pairs.forEach(function (p) {
                p = asChoicePair(p);
                if (p.code === opt || p.label === opt) {
                    keys.push(p.code, p.label);
                }
            });
            keys = keys.filter(function (v, i, a) { return a.indexOf(v) === i; });
            if (keys.indexOf(value) !== -1) {
                return true;
            }
            return keys.some(function (key) {
                return isOtherOption(key, pairs) && value.indexOf(otherPrefix(key)) === 0;
            });
        };

        var selectedIncludes = function (selected, opt) {
            return selected.some(function (v) {
                return valueMatchesOption(v, opt);
            });
        };

        var makeOtherSpecifyWrap = function (opt, fieldId, savedText) {
            var id = 'cf-input-' + fieldId + '-other';
            var label = (module.config && module.config.specifyLabel) || 'Please specify';
            var placeholder = (module.config && module.config.specifyPlaceholder) || 'Type your answer';
            var $wrap = $('<div class="cf-other-specify d-none"/>')
                .attr('data-cf-other-wrap', true)
                .attr('data-cf-other-option', opt);
            $wrap.append($('<label class="cf-other-specify__label"/>').attr('for', id).text(label));
            $wrap.append($('<input type="text" class="form-control cf-input"/>').attr({
                id: id,
                name: 'SubmitForm[other_text][' + fieldId + ']',
                placeholder: placeholder,
                disabled: true
            }).attr('data-cf-other-text', true).val(savedText || ''));
            return $wrap;
        };

        var syncHtmlValues = function () {
            $root.find('[data-cf-html-block]').each(function () {
                var $block = $(this);
                var $hidden = $block.find('[data-cf-html-value]');
                if (!$hidden.length) {
                    return;
                }
                var varName = String($block.data('cf-html-var') || '');
                var $control = $block.find('[data-cf-html-var="' + varName + '"]').filter('input, select, textarea').first();
                if (!$control.length && varName) {
                    $control = $block.find('[name="' + varName + '"]').filter('input, select, textarea').first();
                }
                if (!$control.length) {
                    $control = $block.find('input, select, textarea').not('[data-cf-html-value]').first();
                }
                if (!$control.length) {
                    return;
                }
                if ($control.is(':checkbox')) {
                    var vals = [];
                    $block.find('input[type="checkbox"][data-cf-html-var="' + varName + '"]:checked, input[type="checkbox"][name="' + varName + '"]:checked').each(function () {
                        vals.push(String($(this).val()));
                    });
                    $hidden.val(vals.join(', '));
                } else if ($control.is(':radio')) {
                    $hidden.val(String($block.find('input[type="radio"][data-cf-html-var="' + varName + '"]:checked, input[type="radio"][name="' + varName + '"]:checked').val() || ''));
                } else {
                    $hidden.val(String($control.val() || ''));
                }
            });
        };

        var syncRankingValue = function ($list) {
            var values = [];
            $list.find('.cf-ranking-item').each(function (index) {
                values.push(String($(this).attr('data-value') || ''));
                $(this).find('.cf-ranking-order').text(String(index + 1));
                $(this).find('[data-cf-rank-up]').prop('disabled', index === 0);
                $(this).find('[data-cf-rank-down]').prop('disabled', index === $list.find('.cf-ranking-item').length - 1);
            });
            $list.closest('[data-cf-ranking]').find('[data-cf-ranking-value]')
                .val(JSON.stringify(values))
                .trigger('change');
        };

        var childrenOf = function (nodes, label) {
            var i;
            for (i = 0; i < nodes.length; i++) {
                if (String(nodes[i].label || '') === String(label)) {
                    return nodes[i].children || [];
                }
            }
            return [];
        };

        var initDrilldown = function () {
            $root.find('[data-cf-drilldown]').each(function () {
                var $wrap = $(this);
                var tree = [];
                try {
                    tree = JSON.parse($wrap.attr('data-cf-tree') || '[]');
                } catch (e) {
                    tree = [];
                }
                var $hidden = $wrap.find('[data-cf-drilldown-value]');
                var path = [];
                try {
                    path = JSON.parse($hidden.val() || '[]');
                } catch (e) {
                    path = [];
                }

                var render = function () {
                    var $levels = $wrap.find('[data-cf-drilldown-levels]');
                    $levels.empty();
                    var nodes = tree;
                    var i;
                    for (i = 0; i <= path.length; i++) {
                        if (!nodes || !nodes.length) {
                            break;
                        }
                        var current = path[i] || '';
                        var $sel = $('<select class="form-control cf-input mb-2" data-cf-drill-level="' + i + '"/>');
                        $sel.append($('<option value="">').text('Please select'));
                        nodes.forEach(function (node) {
                            var label = String(node.label || '');
                            $sel.append($('<option/>').val(label).text(label).prop('selected', label === current));
                        });
                        $levels.append($sel);
                        if (!current) {
                            break;
                        }
                        nodes = childrenOf(nodes, current);
                    }
                    $hidden.val(JSON.stringify(path.filter(Boolean)));
                };

                $wrap.on('change', 'select', function () {
                    var level = parseInt($(this).attr('data-cf-drill-level'), 10) || 0;
                    path = path.slice(0, level);
                    var val = String($(this).val() || '');
                    if (val) {
                        path[level] = val;
                    }
                    render();
                    evaluate();
                });
                render();
            });
        };

        var initHotspots = function () {
            $root.find('[data-cf-hotspot]').each(function () {
                var $wrap = $(this);
                var multi = $wrap.attr('data-cf-multi') === '1';
                var $hidden = $wrap.find('[data-cf-hotspot-value]');
                var sync = function () {
                    var ids = [];
                    $wrap.find('.cf-hotspot-region.is-selected').each(function () {
                        ids.push(String($(this).attr('data-cf-region') || ''));
                    });
                    $hidden.val(JSON.stringify({regions: ids}));
                };
                $wrap.on('click', '[data-cf-region]', function (e) {
                    e.preventDefault();
                    var $btn = $(this);
                    if (multi) {
                        $btn.toggleClass('is-selected');
                    } else {
                        $wrap.find('.cf-hotspot-region').removeClass('is-selected');
                        $btn.addClass('is-selected');
                    }
                    sync();
                    evaluate();
                });
            });
        };

        var initBestWorst = function () {
            $root.on('change', '[data-cf-best-worst] input[type="radio"], [data-cf-maxdiff] input[type="radio"]', function () {
                var $table = $(this).closest('table');
                var name = String($(this).attr('name') || '');
                var val = String($(this).val() || '');
                var other = name.indexOf('[best]') !== -1 ? '[worst]' : '[best]';
                $table.find('input[name$="' + other + '"]').filter(function () {
                    return String($(this).val()) === val && this.checked;
                }).prop('checked', false);
            });
        };

        var initRanking = function () {
            $root.find('[data-cf-ranking-list]').each(function () {
                var $list = $(this);
                var dragging = null;

                syncRankingValue($list);

                var finishDrag = function () {
                    if (!dragging) {
                        return;
                    }
                    $(dragging).removeClass('is-dragging');
                    dragging = null;
                    $list.removeClass('is-sorting');
                    syncRankingValue($list);
                    evaluate();
                };

                var reorderFromPointer = function (clientY) {
                    if (!dragging || clientY === undefined || clientY === null) {
                        return;
                    }
                    var $others = $list.find('.cf-ranking-item').not(dragging);
                    var moved = false;
                    $others.each(function () {
                        var rect = this.getBoundingClientRect();
                        var mid = rect.top + rect.height / 2;
                        if (clientY < mid) {
                            if (dragging.nextSibling !== this) {
                                this.parentNode.insertBefore(dragging, this);
                            }
                            moved = true;
                            return false;
                        }
                    });
                    if (!moved) {
                        var last = $others.last()[0];
                        if (last && dragging.parentNode) {
                            $list[0].appendChild(dragging);
                        }
                    }
                    $list.find('.cf-ranking-item').each(function (index) {
                        $(this).find('.cf-ranking-order').text(String(index + 1));
                    });
                };

                $list.on('click', '[data-cf-rank-up]', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $item = $(this).closest('.cf-ranking-item');
                    var $prev = $item.prev('.cf-ranking-item');
                    if ($prev.length) {
                        $item.insertBefore($prev);
                        syncRankingValue($list);
                        evaluate();
                    }
                });

                $list.on('click', '[data-cf-rank-down]', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $item = $(this).closest('.cf-ranking-item');
                    var $next = $item.next('.cf-ranking-item');
                    if ($next.length) {
                        $item.insertAfter($next);
                        syncRankingValue($list);
                        evaluate();
                    }
                });

                $list.on('mousedown touchstart', '[data-cf-rank-handle]', function (e) {
                    if (e.type === 'mousedown' && e.which !== 1) {
                        return;
                    }
                    var $item = $(this).closest('.cf-ranking-item');
                    if (!$item.length) {
                        return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                    dragging = $item[0];
                    $list.addClass('is-sorting');
                    $item.addClass('is-dragging');

                    var onMove = function (ev) {
                        if (!dragging) {
                            return;
                        }
                        if (ev.cancelable) {
                            ev.preventDefault();
                        }
                        var y;
                        if (ev.touches && ev.touches.length) {
                            y = ev.touches[0].clientY;
                        } else if (ev.changedTouches && ev.changedTouches.length) {
                            y = ev.changedTouches[0].clientY;
                        } else {
                            y = ev.clientY;
                        }
                        reorderFromPointer(y);
                    };

                    var onUp = function () {
                        document.removeEventListener('mousemove', onMove, true);
                        document.removeEventListener('touchmove', onMove, true);
                        document.removeEventListener('mouseup', onUp, true);
                        document.removeEventListener('touchend', onUp, true);
                        document.removeEventListener('touchcancel', onUp, true);
                        finishDrag();
                    };

                    // Capture + non-passive touchmove so preventDefault works on mobile.
                    document.addEventListener('mousemove', onMove, true);
                    document.addEventListener('touchmove', onMove, {capture: true, passive: false});
                    document.addEventListener('mouseup', onUp, true);
                    document.addEventListener('touchend', onUp, true);
                    document.addEventListener('touchcancel', onUp, true);
                });

                // Keyboard: Enter/Space activates reorder via arrows focus
                $list.on('keydown', '[data-cf-rank-handle]', function (e) {
                    var $item = $(this).closest('.cf-ranking-item');
                    if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        if (e.key === 'ArrowUp') {
                            $item.find('[data-cf-rank-up]').trigger('click');
                        } else {
                            $item.find('[data-cf-rank-down]').trigger('click');
                        }
                    }
                });
            });
        };

        $root.on('change', '[data-cf-rating] input[type="radio"]', function () {
            var $scale = $(this).closest('[data-cf-rating]');
            $scale.find('.cf-rating-option').removeClass('is-selected');
            $(this).closest('.cf-rating-option').addClass('is-selected');
            evaluate();
        });

        var initVas = function () {
            $root.find('[data-cf-vas]').each(function () {
                var $vas = $(this);
                if ($vas.data('cfVasReady')) {
                    return;
                }
                $vas.data('cfVasReady', true);
                var min = parseInt($vas.attr('data-min'), 10);
                var max = parseInt($vas.attr('data-max'), 10);
                var step = parseInt($vas.attr('data-step'), 10) || 1;
                if (isNaN(min)) { min = 0; }
                if (isNaN(max) || max <= min) { max = min + 1; }
                var $input = $vas.find('[data-cf-vas-input]');
                var $track = $vas.find('[data-cf-vas-track]');
                var $marker = $vas.find('[data-cf-vas-marker]');
                var dragging = false;

                var snap = function (raw) {
                    var n = Math.round((raw - min) / step) * step + min;
                    if (n < min) { n = min; }
                    if (n > max) { n = max; }
                    return n;
                };

                var paint = function (value, empty) {
                    if (empty || value === '' || value === null || typeof value === 'undefined') {
                        $marker.attr('hidden', true);
                        $track.removeAttr('aria-valuenow');
                        return;
                    }
                    var n = snap(parseFloat(value));
                    var pctFromTop = ((max - n) / (max - min)) * 100;
                    $marker.removeAttr('hidden').css('top', pctFromTop + '%');
                    $track.attr('aria-valuenow', String(n));
                };

                var setValue = function (raw, fromInput, silent) {
                    if ($input.prop('disabled') || $input.prop('readonly') || $vas.closest('.is-frozen').length) {
                        return;
                    }
                    var n = snap(raw);
                    if (!fromInput) {
                        $input.val(String(n));
                    }
                    paint(n, false);
                    if (!silent) {
                        $input.trigger('change');
                    }
                };

                var valueFromPointer = function (clientY) {
                    var rect = $track[0].getBoundingClientRect();
                    var y = clientY - rect.top;
                    var ratio = y / Math.max(1, rect.height);
                    if (ratio < 0) { ratio = 0; }
                    if (ratio > 1) { ratio = 1; }
                    return max - ratio * (max - min);
                };

                paint($input.val(), $input.val() === '');

                $input.on('blur', function () {
                    var v = $.trim(String($input.val() || ''));
                    if (v === '' || !isFinite(Number(v))) {
                        return;
                    }
                    $input.val(String(snap(Number(v))));
                    paint(snap(Number(v)), false);
                });

                $track.on('pointerdown', function (e) {
                    if ($vas.closest('.is-frozen').length) {
                        return;
                    }
                    e.preventDefault();
                    dragging = true;
                    if ($track[0].setPointerCapture) {
                        $track[0].setPointerCapture(e.originalEvent.pointerId);
                    }
                    setValue(valueFromPointer(e.originalEvent.clientY), false, true);
                    $track.trigger('focus');
                });
                $track.on('pointermove', function (e) {
                    if (!dragging) {
                        return;
                    }
                    setValue(valueFromPointer(e.originalEvent.clientY), false, true);
                });
                $track.on('pointerup pointercancel', function () {
                    if (dragging) {
                        dragging = false;
                        $input.trigger('change');
                    }
                });
                $track.on('keydown', function (e) {
                    if ($vas.closest('.is-frozen').length) {
                        return;
                    }
                    var cur = $input.val() === '' ? min : snap(Number($input.val()));
                    var delta = e.shiftKey ? step * 10 : step;
                    if (e.key === 'ArrowUp' || e.key === 'ArrowRight' || e.key === 'PageUp') {
                        e.preventDefault();
                        setValue(cur + (e.key === 'PageUp' ? step * 10 : delta));
                    } else if (e.key === 'ArrowDown' || e.key === 'ArrowLeft' || e.key === 'PageDown') {
                        e.preventDefault();
                        setValue(cur - (e.key === 'PageDown' ? step * 10 : delta));
                    } else if (e.key === 'Home') {
                        e.preventDefault();
                        setValue(min);
                    } else if (e.key === 'End') {
                        e.preventDefault();
                        setValue(max);
                    }
                });
            });
        };
        initVas();

        var updateProgress = function () {
            var $visible = $root.find('[data-cf-conditional][data-cf-answerable]').filter(function () {
                return $(this).closest('.cf-hidden').length === 0
                    && $(this).closest('.cf-page-offpath').length === 0
                    && $(this).closest('[data-cf-respondent-hidden]').length === 0
                    && !$(this).is('[data-cf-respondent-hidden]');
            });
            var total = $visible.length;
            var done = 0;
            $visible.each(function () {
                if (isFilled($(this))) {
                    done++;
                }
            });
            var pct = total ? Math.round((done / total) * 100) : 0;
            $root.find('[data-cf-progress-bar]').css('width', pct + '%');
            $root.find('[data-cf-progress-text]').text(done + ' / ' + total);
        };

        var readAnswers = function () {
            var values = {};
            $root.find('[data-cf-field-id][data-cf-answerable]').each(function () {
                var $field = $(this);
                var id = String($field.data('cf-field-id'));
                var type = String($field.data('cf-field-type') || '');
                if ($field.find('[data-cf-html-value]').length) {
                    values[id] = String($field.find('[data-cf-html-value]').val() || '');
                    return;
                }
                if ($field.find('[data-cf-ranking-value]').length) {
                    try {
                        values[id] = JSON.parse($field.find('[data-cf-ranking-value]').val() || '[]');
                    } catch (e) {
                        values[id] = [];
                    }
                    return;
                }
                if ($field.find('[data-cf-drilldown-value]').length) {
                    try {
                        values[id] = JSON.parse($field.find('[data-cf-drilldown-value]').val() || '[]');
                    } catch (e) {
                        values[id] = [];
                    }
                    return;
                }
                if ($field.find('[data-cf-hotspot-value]').length) {
                    try {
                        values[id] = JSON.parse($field.find('[data-cf-hotspot-value]').val() || '{}');
                    } catch (e) {
                        values[id] = {};
                    }
                    return;
                }
                if (type === 'grid_single' || type === 'grid_multi') {
                    var grid = {};
                    $field.find('tbody tr').each(function () {
                        var row = $.trim(String($(this).find('th').first().text() || ''));
                        if (type === 'grid_multi') {
                            var cols = [];
                            $(this).find('input[type="checkbox"]:checked').each(function () {
                                cols.push(String($(this).val()));
                            });
                            grid[row] = cols;
                        } else {
                            grid[row] = String($(this).find('input[type="radio"]:checked').val() || '');
                        }
                    });
                    values[id] = grid;
                    return;
                }
                if (type === 'best_worst') {
                    values[id] = {
                        best: String($field.find('input[name$="[best]"]:checked').val() || ''),
                        worst: String($field.find('input[name$="[worst]"]:checked').val() || '')
                    };
                    return;
                }
                if (type === 'maxdiff') {
                    var sets = [];
                    $field.find('.cf-maxdiff-set').each(function () {
                        sets.push({
                            best: String($(this).find('input[name*="[best]"]:checked').val() || ''),
                            worst: String($(this).find('input[name*="[worst]"]:checked').val() || '')
                        });
                    });
                    values[id] = {sets: sets};
                    return;
                }
                if ($field.find('input[type="checkbox"]').length) {
                    var checked = [];
                    $field.find('input[type="checkbox"]:checked').each(function () {
                        checked.push(String($(this).val()));
                    });
                    values[id] = checked;
                    return;
                }
                if ($field.find('input[type="radio"]').length) {
                    values[id] = String($field.find('input[type="radio"]:checked').val() || '');
                    return;
                }
                var $input = $field.find('input, select, textarea').filter(':not([type="hidden"]):not([type="checkbox"]):not([type="radio"])').first();
                if ($input.length) {
                    values[id] = String($input.val() || '');
                    return;
                }
                values[id] = String($field.find('[data-cf-file-guid]').val() || '');
            });
            return values;
        };

        var formatPipeValue = function (raw) {
            if (raw === undefined || raw === null || raw === '') {
                return '';
            }
            if (Array.isArray(raw)) {
                return raw.join(', ');
            }
            if (typeof raw === 'object') {
                if (raw.regions) {
                    return (raw.regions || []).join(', ');
                }
                if (raw.best || raw.worst) {
                    return [raw.best ? 'best: ' + raw.best : '', raw.worst ? 'worst: ' + raw.worst : ''].filter(Boolean).join(', ');
                }
                if (raw.sets) {
                    return (raw.sets || []).map(function (s, i) {
                        return '#' + (i + 1) + ' ' + (s.best || '') + '/' + (s.worst || '');
                    }).join('; ');
                }
                return Object.keys(raw).map(function (k) {
                    var v = raw[k];
                    return k + ': ' + (Array.isArray(v) ? v.join(', ') : String(v || ''));
                }).join('; ');
            }
            return String(raw);
        };

        var applyPiping = function (values) {
            values = values || readAnswers();
            var extras = module.config.pipeVars || {};
            var replaceTpl = function (tpl) {
                return String(tpl || '').replace(/\{\{\s*(?:(answer|field):)?([^}]+)\s*\}\}/gi, function (full, kind, key) {
                    key = $.trim(String(key).split(':')[0]);
                    if (kind) {
                        if (values[key] !== undefined) {
                            return formatPipeValue(values[key]);
                        }
                        return '';
                    }
                    var lower = key.toLowerCase();
                    if (extras[lower] != null) {
                        return String(extras[lower]);
                    }
                    if (extras[key] != null) {
                        return String(extras[key]);
                    }
                    return full;
                });
            };
            $root.find('[data-cf-pipe]').each(function () {
                var tpl = String($(this).attr('data-cf-pipe') || '');
                var html = replaceTpl(tpl);
                if ($(this).find('.text-danger').length) {
                    $(this).contents().filter(function () { return this.nodeType === 3; }).first().replaceWith(html + ' ');
                } else {
                    $(this).text(html);
                }
            });
        };

        var selectedList = function (raw) {
            if (raw === undefined || raw === null || raw === '') {
                return [];
            }
            if (Array.isArray(raw)) {
                return raw.map(String);
            }
            return [String(raw)];
        };

        var applyCarryForward = function (values) {
            $root.find('[data-cf-carry-from]').each(function () {
                var $el = $(this);
                var from = String($el.attr('data-cf-carry-from') || '');
                var mode = String($el.attr('data-cf-carry-mode') || 'selected');
                var all = [];
                try {
                    all = JSON.parse($el.attr('data-cf-carry-options') || '[]');
                } catch (e) {
                    all = [];
                }
                if (!Array.isArray(all)) {
                    all = [];
                }
                all = all.map(asChoicePair);
                var selected = selectedList(values[from]);
                if (!selected.length && /^id\d+$/.test(from)) {
                    selected = selectedList(values[from.slice(2)]);
                }
                var next = all;
                if (mode === 'selected') {
                    next = all.filter(function (o) { return selectedIncludes(selected, o.code) || selectedIncludes(selected, o.label); });
                } else if (mode === 'unselected') {
                    next = all.filter(function (o) { return !selectedIncludes(selected, o.code) && !selectedIncludes(selected, o.label); });
                }
                var rendered = next.map(function (o) { return o.code; }).join('\0');
                if ($el.attr('data-cf-carry-rendered') === rendered) {
                    return;
                }
                $el.attr('data-cf-carry-rendered', rendered);
                var $q = $el.closest('[data-cf-field-id]');
                var fieldId = String($q.attr('data-cf-field-id') || '');
                var savedOther = String($q.find('[data-cf-other-text]').val() || '');
                var otherPair = next.filter(function (o) { return isOtherOption(o.code, next) || isOtherOption(o.label, next); })[0];
                var otherOpt = otherPair ? otherPair.code : '';
                if ($el.is('select')) {
                    var cur = String($el.val() || '');
                    $el.find('option').not('[value=""]').remove();
                    next.forEach(function (opt) {
                        $el.append($('<option/>').val(opt.code).text(opt.label));
                    });
                    if (cur !== '' && next.some(function (o) { return o.code === cur; })) {
                        $el.val(cur);
                    } else {
                        $el.val('');
                    }
                    $q.find('[data-cf-other-wrap]').remove();
                    if (otherOpt) {
                        $el.attr('data-cf-other-select', otherOpt);
                        $el.after(makeOtherSpecifyWrap(otherOpt, fieldId, savedOther));
                    } else {
                        $el.removeAttr('data-cf-other-select');
                    }
                    return;
                }
                var name = String($el.find('input[type="checkbox"], input[type="radio"]').first().attr('name') || '');
                var isCheck = $el.find('input[type="checkbox"]').length > 0;
                var current = [];
                $el.find('input[type="checkbox"]:checked, input[type="radio"]:checked').each(function () {
                    current.push(String($(this).val()));
                });
                $el.find('label.cf-choice, [data-cf-other-wrap]').remove();
                next.forEach(function (opt) {
                    var $lab = $('<label class="cf-choice"/>');
                    var $input = $('<input>').attr({
                        type: isCheck ? 'checkbox' : 'radio',
                        name: name,
                        value: opt.code,
                        autocomplete: 'off'
                    });
                    if (current.indexOf(String(opt.code)) !== -1) {
                        $input.attr('checked', 'checked').prop('checked', true);
                    }
                    $lab.append($input).append($('<span/>').text(opt.label));
                    $el.append($lab);
                    if (otherPair && opt.code === otherPair.code) {
                        $el.append(makeOtherSpecifyWrap(opt.code, fieldId, savedOther));
                    }
                });
            });
        };

        var parseFieldLogic = function ($field) {
            var logic = null;
            var raw = $field.attr('data-cf-logic');
            if (raw) {
                try {
                    logic = JSON.parse(raw);
                } catch (e) {}
            }
            if (!logic) {
                var dep = $field.data('cf-depends');
                if (!dep) {
                    return null;
                }
                logic = {
                    action: 'show',
                    combinator: 'and',
                    rules: [{
                        fieldKey: String(dep),
                        operator: String($field.data('cf-operator') || 'equals'),
                        value: String($field.data('cf-value') || '')
                    }]
                };
            }
            if (logic.rules && logic.rules.length) {
                logic.rules = logic.rules.filter(function (rule) {
                    return String((rule && rule.fieldKey) || '') !== '';
                });
            }
            return logic;
        };

        var logicMet = function (logic, values) {
            if (!logic || !logic.rules || !logic.rules.length) {
                return true;
            }
            var results = logic.rules.map(function (rule) {
                return branchMatches(rule, values);
            });
            if (String(logic.combinator || 'and') === 'or') {
                return results.indexOf(true) !== -1;
            }
            return results.indexOf(false) === -1;
        };

        var isLogicVisible = function (logic, values) {
            if (!logic || !logic.rules || !logic.rules.length) {
                return true;
            }
            var met = logicMet(logic, values);
            var action = String(logic.action || 'show');
            if (action === 'hide' || action === 'skip') {
                return !met;
            }
            if (action === 'show') {
                return met;
            }
            return true;
        };

        var normalizeLogicValue = function (value) {
            value = String(value == null ? '' : value).replace(/^\s+|\s+$/g, '');
            if (value.length >= 2) {
                var q = value.charAt(0);
                if ((q === '"' || q === "'") && value.charAt(value.length - 1) === q) {
                    return value.slice(1, -1).replace(/^\s+|\s+$/g, '');
                }
            }
            return value;
        };

        var branchMatches = function (branch, values) {
            var fieldKey = String(branch.fieldKey || '');
            var op = String(branch.operator || 'equals');
            var expected = normalizeLogicValue(branch.value || '');
            var raw = values[fieldKey];
            if (raw === undefined && /^id\d+$/.test(fieldKey)) {
                raw = values[fieldKey.slice(2)];
            }
            if (raw === undefined) {
                return false;
            }
            var pairs = choicePairsFor(fieldKey);
            var matchOpt = function (v, expectedVal) {
                return valueMatchesOption(v, expectedVal, pairs);
            };
            var list = [];
            var flatten = function (node) {
                if (Array.isArray(node)) {
                    node.forEach(flatten);
                    return;
                }
                if (node && typeof node === 'object') {
                    Object.keys(node).forEach(function (k) { flatten(node[k]); });
                    return;
                }
                if (node !== undefined && node !== null && String(node) !== '') {
                    list.push(String(node));
                }
            };
            if (raw && typeof raw === 'object') {
                flatten(raw);
                if (op === 'checked') {
                    return expected ? list.some(function (v) { return matchOpt(v, expected); }) : list.length > 0;
                }
                if (op === 'equals') {
                    return list.some(function (v) { return matchOpt(v, expected); });
                }
                if (op === 'not_equals') {
                    return !list.some(function (v) { return matchOpt(v, expected); });
                }
                if (op === 'contains') {
                    return list.some(function (v) { return String(v).indexOf(expected) !== -1; });
                }
                return false;
            }
            var val = String(raw);
            if (op === 'equals') {
                return matchOpt(val, expected);
            }
            if (op === 'not_equals') {
                return !matchOpt(val, expected);
            }
            if (op === 'contains') {
                return expected !== '' && val.toLowerCase().indexOf(expected.toLowerCase()) !== -1;
            }
            if (op === 'checked') {
                return val !== '' && val !== '0';
            }
            return false;
        };

        var pageHistory = [0];
        var currentPage = 0;
        var pagesConfig = module.config.pages || [];
        var pageKeyIndex = module.config.pageKeyIndex || {};
        var multiPage = $root.attr('data-cf-multipage') === '1' && pagesConfig.length > 1;

        var pageHasVisibleContent = function (idx) {
            var $page = $root.find('[data-cf-page="' + idx + '"]');
            return $page.find('[data-cf-conditional]').filter(function () {
                return $(this).closest('.cf-hidden').length === 0
                    && $(this).closest('[data-cf-respondent-hidden]').length === 0
                    && !$(this).is('[data-cf-respondent-hidden]');
            }).length > 0;
        };

        var pageShouldSkip = function (idx, values) {
            values = values || readAnswers();
            var page = pagesConfig[idx] || {};
            var fieldLogic = page.fieldLogic || [];
            for (var i = 0; i < fieldLogic.length; i++) {
                var logic = fieldLogic[i].logic || {};
                if (logic.action === 'skip_page' && logic.rules && logic.rules.length && logicMet(logic, values)) {
                    return true;
                }
            }
            return !pageHasVisibleContent(idx);
        };

        var resolveNavigation = function (fromIndex) {
            var values = readAnswers();
            var page = pagesConfig[fromIndex] || {};
            var fieldLogic = page.fieldLogic || [];
            for (var i = 0; i < fieldLogic.length; i++) {
                var logic = fieldLogic[i].logic || {};
                if (!logic.rules || !logic.rules.length || !logicMet(logic, values)) {
                    continue;
                }
                if (logic.action === 'goto_end') {
                    return { index: null, explicit: true, end: true };
                }
                if (logic.action === 'goto_page') {
                    var gotoKey = String(logic.gotoPageKey || '');
                    if (gotoKey && pageKeyIndex[gotoKey] !== undefined) {
                        return { index: parseInt(pageKeyIndex[gotoKey], 10), explicit: true, end: false };
                    }
                }
            }
            var branches = page.branches || [];
            for (var b = 0; b < branches.length; b++) {
                if (branchMatches(branches[b], values)) {
                    var gotoPage = String(branches[b].gotoPageKey || '');
                    if (gotoPage && pageKeyIndex[gotoPage] !== undefined) {
                        return { index: parseInt(pageKeyIndex[gotoPage], 10), explicit: true, end: false };
                    }
                }
            }
            var next = fromIndex + 1;
            return {
                index: next < pagesConfig.length ? next : null,
                explicit: false,
                end: false
            };
        };

        var nextVisiblePage = function (fromIndex) {
            var nav = resolveNavigation(fromIndex);
            if (nav.end || nav.explicit) {
                return nav;
            }
            var probe = nav.index;
            while (probe !== null && pageShouldSkip(probe)) {
                var further = resolveNavigation(probe);
                if (further.explicit || further.end) {
                    return further;
                }
                if (further.index === null) {
                    return { index: null, explicit: false, end: true };
                }
                probe = further.index;
            }
            return { index: probe, explicit: false, end: probe === null };
        };

        var reachablePageSet = function () {
            var seen = {};
            if (!multiPage) {
                return seen;
            }
            var idx = 0;
            var guard = 0;
            while (idx !== null && idx !== undefined && !seen[idx] && guard++ < 80) {
                seen[idx] = true;
                var nav = nextVisiblePage(idx);
                if (nav.end || nav.index === null || nav.index === undefined) {
                    break;
                }
                idx = nav.index;
            }
            return seen;
        };

        var applyUnreachablePages = function () {
            if (!multiPage) {
                return;
            }
            var reachable = reachablePageSet();
            $root.find('[data-cf-page]').each(function () {
                var $page = $(this);
                var p = parseInt($page.attr('data-cf-page'), 10);
                var onPath = !isNaN(p) && !!reachable[p];
                $page.toggleClass('cf-page-offpath', !onPath);
                if (!onPath) {
                    $page.find('input, select, textarea').prop('disabled', true);
                }
            });
        };

        var showPage = function (idx, options) {
            options = options || {};
            var pageChanged = idx !== currentPage;
            if (pageChanged && typeof flushPageTime === 'function') {
                flushPageTime(currentPage);
                writeTimingField();
            }
            currentPage = idx;
            var $targetPage = $root.find('[data-cf-page="' + idx + '"]');
            if (pageChanged || !$targetPage.hasClass('is-active')) {
                $root.find('[data-cf-page]').removeClass('is-active');
                $targetPage.addClass('is-active');
            }

            var nav = nextVisiblePage(idx);
            var isLast = nav.index === null || nav.end === true;

            var canGoBack = pageHistory.length > 1;
            $root.find('[data-cf-page-back]').toggle(canGoBack);
            $root.find('[data-cf-page-next]').toggle(multiPage && !isLast);
            $root.find('[data-cf-submit-wrap]').toggle(!multiPage || isLast);
            $root.find('[data-cf-current-page]').val(String(idx));
            if (pageChanged && idx > 0) {
                scheduleAutosave();
            }

            var label = module.config.pageLabel || 'Page {current} of {total}';
            $root.find('[data-cf-page-indicator]').text(
                label.replace('{current}', String(idx + 1)).replace('{total}', String(pagesConfig.length))
            );

            var shouldScroll = options.scroll === true || (options.scroll !== false && pageChanged);
            if (shouldScroll) {
                try {
                    var top = $root.offset() ? $root.offset().top - 20 : 0;
                    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                } catch (e) {}
            }
            window.requestAnimationFrame(syncGridOverflow);
        };

        var writeActionVars = function (vars) {
            $root.find('[data-cf-action-vars]').val(JSON.stringify(vars || {}));
        };

        var applyActionResult = function (res) {
            if (!res || !res.ok) {
                return '';
            }
            if (res.vars && typeof res.vars === 'object') {
                writeActionVars(res.vars);
            }
            if (res.gotoEnd) {
                $root.find('[data-cf-page-next]').hide();
                $root.find('[data-cf-submit-wrap]').show();
                return 'end';
            }
            if (res.gotoPageKey && module.config.pageKeyIndex && Object.prototype.hasOwnProperty.call(module.config.pageKeyIndex, res.gotoPageKey)) {
                var gotoIdx = parseInt(module.config.pageKeyIndex[res.gotoPageKey], 10);
                if (!isNaN(gotoIdx)) {
                    pageHistory.push(gotoIdx);
                    showPage(gotoIdx);
                    return 'goto';
                }
            }
            return '';
        };

        var runActions = function (trigger, fieldId) {
            var $form = $root.find('[data-cf-fill-form]');
            var url = (module.config.runActionsUrl || $form.attr('data-cf-run-actions-url') || '');
            if (!url || !$form.length) {
                return $.Deferred().resolve({ok: true}).promise();
            }
            return $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: $form.serialize() + '&trigger=' + encodeURIComponent(trigger) + '&field_id=' + encodeURIComponent(fieldId || '')
            });
        };

        var fieldActionTimer = null;
        $root.on('change', '[data-cf-field-actions] input, [data-cf-field-actions] select, [data-cf-field-actions] textarea', function () {
            var fieldId = $(this).closest('[data-cf-field-actions]').attr('data-cf-field-id');
            if (!fieldId) {
                return;
            }
            clearTimeout(fieldActionTimer);
            fieldActionTimer = setTimeout(function () {
                runActions('field', fieldId).done(applyActionResult);
            }, 400);
        });

        var validateCurrentPage = function () {
            syncHtmlValues();
            var $page = $root.find('[data-cf-page="' + currentPage + '"]');
            if (!$page.length) {
                $page = $root.find('[data-cf-fill-form]');
            }
            var hasErrors = false;
            $page.find('[data-cf-conditional][data-cf-answerable]').each(function () {
                var $field = $(this);
                if ($field.closest('.cf-hidden').length || $field.is('[data-cf-respondent-hidden]') || $field.hasClass('cf-hidden')) {
                    setFieldMessage($field, '');
                    return;
                }
                var msg = fieldProblem($field);
                setFieldMessage($field, msg);
                if (msg) {
                    hasErrors = true;
                }
            });
            showPageErrorSummary(hasErrors);
            resetFillButtons();
            if (hasErrors) {
                var $first = $page.find('.cf-page-error').first();
                if ($first.length && $first[0].scrollIntoView) {
                    $first[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            return true;
        };

        var captureRespondentMeta = function () {
            $root.find('[data-cf-respondent-hidden] input, [data-cf-respondent-hidden] select, [data-cf-respondent-hidden] textarea').prop('disabled', false);
            var ua = navigator.userAgent || '';
            var browser = 'Other';
            if (/Edg\//i.test(ua)) {
                browser = 'Edge';
            } else if (/(OPR|Opera)\//i.test(ua)) {
                browser = 'Opera';
            } else if (/Firefox\//i.test(ua)) {
                browser = 'Firefox';
            } else if (/Chrome\//i.test(ua)) {
                browser = 'Chrome';
            } else if (/Safari\//i.test(ua)) {
                browser = 'Safari';
            }
            var os = 'Other';
            if (/Android/i.test(ua)) {
                os = 'Android';
            } else if (/iPhone|iPad|iPod/i.test(ua)) {
                os = 'iOS';
            } else if (/Windows NT/i.test(ua)) {
                os = 'Windows';
            } else if (/Mac OS X/i.test(ua)) {
                os = 'macOS';
            } else if (/Linux/i.test(ua)) {
                os = 'Linux';
            }
            var device = 'desktop';
            if (/iPad|Tablet|PlayBook/i.test(ua) || (/Android/i.test(ua) && !/Mobile/i.test(ua))) {
                device = 'tablet';
            } else if (/Mobi|iPhone|Android.+Mobile/i.test(ua)) {
                device = 'mobile';
            }
            var meta = {
                browser: browser,
                os: os,
                device: device,
                language: navigator.language || '',
                screen: String(window.screen ? (screen.width + 'x' + screen.height) : ''),
                timezone: '',
                userAgent: ua
            };
            try {
                meta.timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            } catch (e) {}
            $root.find('[data-cf-respondent-meta]').each(function () {
                this.disabled = false;
                var key = String($(this).attr('data-cf-meta-key') || '');
                if (!key) {
                    $(this).val(JSON.stringify(meta));
                    return;
                }
                if (key === 'ip') {
                    $(this).val('');
                    return;
                }
                $(this).val(meta[key] != null ? String(meta[key]) : '');
            });
        };

        var syncGridOverflow = function () {
            $root.find('[data-cf-grid]').each(function () {
                var $wrap = $(this);
                var $scroll = $wrap.find('[data-cf-grid-scroll]');
                var el = $scroll[0];
                if (!el) {
                    return;
                }
                if ($wrap.closest('.cf-hidden').length) {
                    $wrap.removeClass('is-overflowing has-more-end has-more-start');
                    $wrap.find('[data-cf-grid-hint]').prop('hidden', true);
                    return;
                }
                var $page = $wrap.closest('[data-cf-page]');
                if ($page.length && !$page.hasClass('is-active')) {
                    return;
                }
                var overflowing = el.scrollWidth > el.clientWidth + 1;
                var sl = el.scrollLeft;
                var maxPos = el.scrollWidth - el.clientWidth;
                var atStart;
                var atEnd;
                if (sl < 0) {
                    atStart = sl >= -1;
                    atEnd = sl <= -maxPos + 1;
                } else {
                    atStart = sl <= 1;
                    atEnd = sl >= maxPos - 1;
                }
                $wrap.toggleClass('is-overflowing', overflowing);
                $wrap.toggleClass('has-more-end', overflowing && !atEnd);
                $wrap.toggleClass('has-more-start', overflowing && !atStart);
                $wrap.find('[data-cf-grid-hint]').prop('hidden', !overflowing);
            });
        };

        var evaluate = function () {
            captureRespondentMeta();
            syncHtmlValues();
            var values = readAnswers();
            var newlyShown = [];
            var applyVisibility = function ($field) {
                var logic = parseFieldLogic($field);
                var visible = isLogicVisible(logic, values);
                if ($field.parents('.cf-hidden').length) {
                    visible = false;
                }
                var rawEnc = $field.attr('data-cf-enclosing-groups');
                if (rawEnc) {
                    try {
                        JSON.parse(rawEnc).forEach(function (id) {
                            if ($root.find('.cf-question-group[data-cf-field-id="' + id + '"]').hasClass('cf-hidden')) {
                                visible = false;
                            }
                        });
                    } catch (e) {}
                }
                var wasHidden = $field.hasClass('cf-hidden');
                $field.toggleClass('cf-hidden', !visible);
                if (visible && wasHidden && $field.closest('[data-cf-page].is-active, .cf-form-page.is-active').length) {
                    newlyShown.push($field.get(0));
                }
                if ($field.is('[data-cf-respondent-hidden]')) {
                    $field.find('input, select, textarea').prop('disabled', false);
                    return;
                }
                if ($field.hasClass('cf-question-group')) {
                    if (!visible) {
                        $field.find('input, select, textarea').prop('disabled', true);
                    }
                    return;
                }
                $field.find('input, select, textarea').prop('disabled', !visible);
            };
            $root.find('.cf-question-group[data-cf-conditional]').each(function () {
                applyVisibility($(this));
            });
            $root.find('[data-cf-conditional]').not('.cf-question-group').each(function () {
                applyVisibility($(this));
            });
            applyPiping(values);
            applyCarryForward(values);
            syncOtherSpecify();
            if (multiPage) {
                applyUnreachablePages();
            }
            updateProgress();
            if (multiPage) {
                showPage(currentPage);
            }
            syncGridOverflow();
            if (newlyShown.length) {
                window.requestAnimationFrame(function () {
                    try {
                        newlyShown[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    } catch (e) {}
                });
            }
        };

        initRanking();
        initDrilldown();
        initHotspots();
        initBestWorst();

        var parseExclusive = function ($list) {
            var raw = String($list.attr('data-cf-exclusive') || '');
            if (!raw) {
                return [];
            }
            return raw.split('|').map(function (s) { return $.trim(s); }).filter(Boolean);
        };

        var checkboxMinMet = function ($field) {
            var $list = $field.find('.cf-choice-list').first();
            if (!$list.length) {
                return true;
            }
            var exclusiveList = parseExclusive($list);
            var minAll = $list.attr('data-cf-min-select-all') === '1';
            var min = parseInt($list.attr('data-cf-min-select') || '0', 10);
            var $boxes = $list.find('input[type="checkbox"]').filter(function () {
                return $(this).closest('.cf-choice, label').is(':visible');
            });
            if (minAll) {
                min = $boxes.filter(function () {
                    return exclusiveList.indexOf(String(this.value)) === -1;
                }).length;
            }
            if (!(min > 0)) {
                return true;
            }
            var checked = $boxes.filter(':checked').map(function () {
                return String(this.value);
            }).get();
            var exclusiveHit = checked.filter(function (v) {
                return exclusiveList.indexOf(v) !== -1;
            }).length > 0;
            if (exclusiveHit) {
                return true;
            }
            return checked.length >= min;
        };

        var applyCheckboxLimits = function ($list) {
            if (!$list || !$list.length) {
                return;
            }
            var max = parseInt($list.attr('data-cf-max-select') || '0', 10);
            var selected = $list.find('input[type="checkbox"]:checked').length;
            $list.find('input[type="checkbox"]').each(function () {
                if (this.checked) {
                    this.disabled = false;
                    return;
                }
                this.disabled = max > 0 && selected >= max;
            });
        };

        $root.find('.cf-choice-list[data-cf-max-select], .cf-choice-list[data-cf-exclusive]').each(function () {
            applyCheckboxLimits($(this));
        });

        $root.on('click', '.cf-choice-list[data-cf-max-select] input[type="checkbox"], .cf-choice-list[data-cf-exclusive] input[type="checkbox"]', function (e) {
            var $list = $(this).closest('.cf-choice-list');
            var exclusiveList = parseExclusive($list);
            var max = parseInt($list.attr('data-cf-max-select') || '0', 10);
            var val = String(this.value);
            var willCheck = !this.checked;
            var isExclusive = exclusiveList.indexOf(val) !== -1;

            if (willCheck && isExclusive) {
                $list.find('input[type="checkbox"]').not(this).prop('checked', false);
            } else if (willCheck && exclusiveList.length) {
                $list.find('input[type="checkbox"]').filter(function () {
                    return exclusiveList.indexOf(String(this.value)) !== -1;
                }).prop('checked', false);
            }

            if (willCheck && max > 0) {
                var selected = $list.find('input[type="checkbox"]:checked').length;
                if (selected >= max) {
                    e.preventDefault();
                    return;
                }
            }
        });

        $root.on('change', '.cf-choice-list[data-cf-max-select] input[type="checkbox"], .cf-choice-list[data-cf-exclusive] input[type="checkbox"]', function () {
            applyCheckboxLimits($(this).closest('.cf-choice-list'));
        });

        if (multiPage) {
            $root.on('click', '[data-cf-page-next]', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (!validateCurrentPage()) {
                    return false;
                }
                var goNext = function () {
                    var nav = nextVisiblePage(currentPage);
                    if (nav.end || nav.index === null) {
                        $root.find('[data-cf-page-next]').hide();
                        $root.find('[data-cf-submit-wrap]').show();
                        return;
                    }
                    pageHistory.push(nav.index);
                    showPage(nav.index);
                };
                var breakId = $root.find('[data-cf-page="' + currentPage + '"]').attr('data-cf-page-break-id');
                if (breakId) {
                    runActions('page', breakId).done(function (res) {
                        var nav = applyActionResult(res);
                        if (nav === 'goto' || nav === 'end') {
                            return;
                        }
                        goNext();
                    }).fail(goNext);
                    return;
                }
                goNext();
            });

            $root.on('click', '[data-cf-page-back]', function (e) {
                e.preventDefault();
                if (pageHistory.length < 2) {
                    return;
                }
                pageHistory.pop();
                showPage(pageHistory[pageHistory.length - 1]);
            });

            var startPage = parseInt(module.config.startPage, 10);
            if (isNaN(startPage) || startPage < 0 || startPage >= pagesConfig.length) {
                startPage = 0;
            }
            pageHistory = [startPage];
            showPage(startPage);
        }

        $root.on('change keyup input', 'input, select, textarea', function () {
            evaluate();
            scheduleAutosave();
        });

        // Mobile browsers sometimes miss change events on custom-styled radios
        // when the tap lands on the label text; force selection + re-evaluate.
        $root.on('click', 'label.cf-choice', function () {
            var $input = $(this).find('input[type="radio"], input[type="checkbox"]').first();
            if (!$input.length || $input.prop('disabled')) {
                return;
            }
            window.setTimeout(function () {
                evaluate();
            }, 0);
        });

        $root.on('click', '[data-cf-save-progress]', function (e) {
            e.preventDefault();
            $root.find('[data-cf-save-panel]').prop('hidden', false);
        });

        $root.on('click', '[data-cf-save-cancel]', function (e) {
            e.preventDefault();
            $root.find('[data-cf-save-panel]').prop('hidden', true);
        });

        $root.on('click', '[data-cf-save-confirm]', function (e) {
            e.preventDefault();
            var $form = $root.find('[data-cf-fill-form]');
            var saveUrl = $form.attr('data-cf-save-url');
            if (!$form.length || !saveUrl) {
                return;
            }
            syncHtmlValues();
            $form.find('[name="resume_email"], [name="email_code"]').remove();
            var email = $.trim(String($root.find('[data-cf-save-email]').val() || ''));
            var sendEmail = $root.find('[data-cf-save-email-toggle]').is(':checked');
            if (email) {
                $('<input type="hidden" name="resume_email">').val(email).appendTo($form);
            }
            if (sendEmail && email) {
                $('<input type="hidden" name="email_code" value="1">').appendTo($form);
            }
            $form.attr('action', saveUrl);
            $form.get(0).submit();
        });

        $root.on('click', '[data-cf-copy-code]', function (e) {
            e.preventDefault();
            var code = $.trim(String($root.find('[data-cf-resume-code]').first().text() || ''));
            var $btn = $(this);
            var done = function (ok) {
                var original = $btn.text();
                $btn.text(ok
                    ? (module.config.copiedLabel || 'Copied')
                    : (module.config.copyFailedLabel || 'Could not copy'));
                setTimeout(function () { $btn.text(original); }, 1600);
            };
            if (!code) {
                done(false);
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(function () { done(true); }).catch(function () { done(false); });
                return;
            }
            var $tmp = $('<textarea>').val(code).css({ position: 'fixed', left: '-9999px' }).appendTo('body');
            $tmp[0].select();
            try {
                done(document.execCommand('copy'));
            } catch (err) {
                done(false);
            }
            $tmp.remove();
        });

        $root.on('change input', '[data-cf-conditional][data-cf-answerable]', function (e) {
            var $field = $(e.target).closest('[data-cf-conditional]');
            if (!$field.length || !$field.hasClass('cf-page-error')) {
                return;
            }
            var msg = fieldProblem($field);
            setFieldMessage($field, msg);
            if (!$root.find('.cf-page-error').length) {
                showPageErrorSummary(false);
            }
        });

        $root.on('blur', 'input[type="email"], input[data-cf-email="1"]', function () {
            var $field = $(this).closest('[data-cf-conditional]');
            if ($field.length) {
                setFieldMessage($field, fieldProblem($field));
            }
        });

        $root.on('submit', '[data-cf-fill-form]', function (e) {
            cancelAutosave();
            captureRespondentMeta();
            syncHtmlValues();
            syncFileGuids();
            if (typeof writeTimingField === 'function') {
                flushPageTime(typeof currentPage !== 'undefined' ? currentPage : 0);
                flushQuestionTimes();
                writeTimingField();
            }
            var $form = $(this);
            var saveUrl = $form.attr('data-cf-save-url') || '';
            if (saveUrl && $form.attr('action') === saveUrl) {
                return;
            }
            if (!validateCurrentPage()) {
                e.preventDefault();
                e.stopImmediatePropagation();
                resetFillButtons();
                setTimeout(resetFillButtons, 30);
                return false;
            }
        });

        $root.find('[data-cf-grid-scroll]').on('scroll.cfGridOverflow', syncGridOverflow);
        $(window).off('resize.cfGridOverflow').on('resize.cfGridOverflow', function () {
            window.requestAnimationFrame(syncGridOverflow);
        });

        evaluate();
        clearUnsavedChoicePrefill();
        window.requestAnimationFrame(syncGridOverflow);
    };

    var initPollEmbed = function (root) {
        var $root = $(root);
        if (!$root.length || $root.data('cf-poll-ready')) {
            return;
        }
        $root.data('cf-poll-ready', 1);

        $root.find('input[type="radio"], input[type="checkbox"]').each(function () {
            if (!this.hasAttribute('checked')) {
                this.checked = false;
            }
        });
        $root.find('select').each(function () {
            var hasSaved = $(this).find('option').filter(function () {
                return this.hasAttribute('selected') && this.value !== '';
            }).length > 0;
            if (!hasSaved) {
                this.selectedIndex = 0;
                if ($(this).find('option[value=""]').length) {
                    $(this).val('');
                }
            }
        });

        var syncPollOtherSpecify = function () {
            $root.find('[data-cf-other-wrap]').each(function () {
                var $wrap = $(this);
                var opt = String($wrap.attr('data-cf-other-option') || '');
                var $q = $wrap.closest('.cf-poll-embed__question');
                var selected = false;
                var $select = $q.find('select').first();
                if ($select.length) {
                    selected = String($select.val() || '') === opt;
                } else {
                    selected = $q.find('input[type="radio"], input[type="checkbox"]').filter(function () {
                        return String(this.value) === opt && this.checked;
                    }).length > 0;
                }
                $wrap.toggleClass('d-none', !selected);
                $wrap.find('[data-cf-other-text]').prop('disabled', !selected);
            });
        };
        $root.on('change', 'select, input[type="radio"], input[type="checkbox"]', syncPollOtherSpecify);
        syncPollOtherSpecify();

        $root.on('submit', '[data-cf-poll-form]', function (e) {
            e.preventDefault();
            var $form = $(this);
            var url = $form.attr('action') || $root.data('cf-submit-url');
            var $btn = $form.find('button[type="submit"]');
            $btn.prop('disabled', true);
            $.ajax({
                url: url,
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            }).done(function (data) {
                if (data && data.success) {
                    $root.find('[data-cf-poll-body]').html(data.html);
                    return;
                }
                var errors = (data && data.errors) ? data.errors.join(' ') : 'Could not submit.';
                window.alert(errors);
                $btn.prop('disabled', false);
            }).fail(function () {
                window.alert('Could not submit.');
                $btn.prop('disabled', false);
            });
        });
    };

    var initAnswers = function (root) {
        var $root = $(root);
        if (!$root.length) {
            return;
        }

        var $body = $root.find('[data-cf-answer-drawer-body]');
        var loadingText = module.config.loadingAnswer || 'Loading…';

        var close = function () {
            $root.removeClass('is-drawer-open');
            $root.find('[data-cf-answer-drawer]').attr('aria-hidden', 'true');
            $root.find('.cf-answer-row.is-active').removeClass('is-active');
            try {
                var url = new URL(window.location.href);
                url.searchParams.delete('answer');
                history.replaceState({}, '', url.toString());
            } catch (e) {}
        };

        var open = function (id, url) {
            if (!id || !url) {
                return;
            }
            $root.addClass('is-drawer-open');
            $root.find('[data-cf-answer-drawer]').attr('aria-hidden', 'false');
            $root.find('.cf-answer-row').removeClass('is-active');
            $root.find('.cf-answer-row[data-cf-answer-id="' + id + '"]').addClass('is-active');
            $body.html('<div class="cf-answer-drawer__loading">' + $('<div>').text(loadingText).html() + '</div>');
            $.getJSON(url).done(function (res) {
                if (res && res.html) {
                    $body.html(res.html);
                    if (humhub.modules && humhub.modules.thiscoveryMapping && humhub.modules.thiscoveryMapping.init) {
                        humhub.modules.thiscoveryMapping.init();
                    }
                    return;
                }
                $body.html('<p class="cf-answer-empty">Could not load this answer.</p>');
            }).fail(function () {
                $body.html('<p class="cf-answer-empty">Could not load this answer.</p>');
            });
            try {
                var loc = new URL(window.location.href);
                loc.searchParams.set('answer', String(id));
                history.replaceState({}, '', loc.toString());
            } catch (e) {}
        };

        $root.off('.cfAnswers');
        $root.on('click.cfAnswers', '[data-cf-answer-open]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            open($btn.data('cf-answer-id'), $btn.data('cf-answer-url'));
        });
        $root.on('click.cfAnswers', '.cf-answer-row', function (e) {
            if ($(e.target).closest('a, button').length && !$(e.target).closest('[data-cf-answer-open]').length) {
                return;
            }
            var $row = $(this);
            open($row.data('cf-answer-id'), $row.data('cf-answer-url'));
        });
        $root.on('click.cfAnswers', '[data-cf-answer-close], [data-cf-answer-overlay]', function (e) {
            e.preventDefault();
            close();
        });
        $(document).off('keydown.cfAnswers').on('keydown.cfAnswers', function (e) {
            if (e.key === 'Escape' && $root.hasClass('is-drawer-open')) {
                close();
            }
        });

        var pre = String($root.attr('data-cf-open-answer') || '');
        if (pre) {
            var $row = $root.find('.cf-answer-row[data-cf-answer-id="' + pre + '"]');
            var url = $row.data('cf-answer-url');
            if (!url) {
                url = String($root.attr('data-cf-answer-detail-template') || '').replace('__ID__', pre);
            }
            open(pre, url);
        }
    };

    var submitClosestForm = function (el) {
        var form = (el && el.closest && el.closest('form')) || (el && el.form) || null;
        if (!form) {
            return;
        }
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }
        $(form).trigger('submit');
    };

    var initListForms = function () {
        $(document).off('change.cfListSubmit').on('change.cfListSubmit', 'select[data-cf-auto-submit]', function () {
            submitClosestForm(this);
        });
    };

    var editorModule = function () {
        try {
            return require('thiscoveryEditor');
        } catch (e) {
            return null;
        }
    };

    var utf8ToB64 = function (str) {
        try {
            return btoa(unescape(encodeURIComponent(str)));
        } catch (e) {
            return '';
        }
    };

    var encodeEmailTemplateFields = function (form) {
        if (!form) {
            return;
        }
        $(form).find('input[name^="FormEmailTemplate["], textarea[name^="FormEmailTemplate["]').each(function () {
            var name = String(this.name || '');
            if (!/(title|subject|header_html|body_html|footer_html)\]$/.test(name)) {
                return;
            }
            if (this.getAttribute('data-cf-b64') === '1') {
                return;
            }
            var value = this.value || '';
            if (value === '') {
                return;
            }
            var encoded = utf8ToB64(value);
            if (!encoded) {
                return;
            }
            this.value = 'cfb64:' + encoded;
            this.setAttribute('data-cf-b64', '1');
        });
    };

    var initEmailTemplate = function (root) {
        var $root = $(root);
        if (!$root.length) {
            return;
        }

        var api = editorModule();
        if (api && typeof api.initEditors === 'function') {
            api.initEditors($root);
        } else if (window.ThiscoveryEditor && typeof window.ThiscoveryEditor.init === 'function') {
            $root.find('[data-te-editor]').each(function () {
                try { window.ThiscoveryEditor.init(this); } catch (err) {}
            });
        }

        $root.off('submit.cfEmailTpl').on('submit.cfEmailTpl', 'form', function () {
            if (api && typeof api.saveEditors === 'function') {
                api.saveEditors($(this));
            } else if (window.ThiscoveryEditor && typeof window.ThiscoveryEditor.saveAll === 'function') {
                window.ThiscoveryEditor.saveAll();
            }
            encodeEmailTemplateFields(this);
        });

        $root.off('click.cfEmailVar').on('click.cfEmailVar', '[data-cf-copy-var]', function (e) {
            e.preventDefault();
            var text = String($(this).attr('data-cf-copy-var') || '');
            var $btn = $(this);
            var done = function () {
                $btn.addClass('is-copied');
                setTimeout(function () {
                    $btn.removeClass('is-copied');
                }, 1400);
            };
            if (text && navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(done);
            } else if (text) {
                done();
            }
        });
    };

    var initPanelEdit = function (root) {
        var $root = $(root);
        if (!$root.length) {
            return;
        }
        var slug = function (text) {
            return String(text || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').slice(0, 40);
        };
        var reindex = function () {
            $root.find('[data-cf-panel-field-list] [data-cf-panel-field-row]').each(function (i) {
                $(this).find('input, select, textarea').each(function () {
                    var name = $(this).attr('name');
                    if (!name) {
                        return;
                    }
                    $(this).attr('name', name.replace(/fields\[[^\]]+\]/, 'fields[' + i + ']'));
                });
            });
        };
        $root.on('click', '[data-cf-add-panel-field]', function (e) {
            e.preventDefault();
            var $proto = $root.find('[data-cf-panel-field-proto] [data-cf-panel-field-row]').first().clone();
            $proto.find('input, textarea').val('');
            $proto.find('select').prop('selectedIndex', 0);
            $proto.find('[data-cf-panel-field-options-wrap]').addClass('d-none');
            $root.find('[data-cf-panel-field-list]').append($proto);
            reindex();
        });
        $root.on('click', '[data-cf-remove-panel-field]', function (e) {
            e.preventDefault();
            var $list = $root.find('[data-cf-panel-field-list]');
            if ($list.find('[data-cf-panel-field-row]').length <= 1) {
                $(this).closest('[data-cf-panel-field-row]').find('input, textarea').val('');
                return;
            }
            $(this).closest('[data-cf-panel-field-row]').remove();
            reindex();
        });
        $root.on('change', '[data-cf-panel-field-type]', function () {
            $(this).closest('[data-cf-panel-field-row]').find('[data-cf-panel-field-options-wrap]')
                .toggleClass('d-none', $(this).val() !== 'dropdown');
        });
        $root.on('blur', '[data-cf-panel-field-label]', function () {
            var $row = $(this).closest('[data-cf-panel-field-row]');
            var $key = $row.find('[data-cf-panel-field-key]');
            if (!$.trim($key.val())) {
                $key.val(slug($(this).val()));
            }
        });
    };

    var initIntegritySettings = function () {
        $('[data-cf-integrity-settings]').each(function () {
            var $root = $(this);
            var sync = function () {
                var $inherit = $root.find('[data-cf-integrity-inherit]');
                var $enabled = $root.find('[data-cf-integrity-enabled]');
                var inheritOn = $inherit.length && $inherit.is(':checked');
                var on = false;
                if ($enabled.length) {
                    if (inheritOn) {
                        on = $enabled.attr('data-cf-default-on') === '1';
                        $enabled.prop('checked', on).prop('disabled', true);
                    } else {
                        $enabled.prop('disabled', false);
                        on = $enabled.is(':checked');
                    }
                }
                $root.find('[data-cf-integrity-when-on]').toggleClass('is-disabled', !on);
                $root.find('[data-cf-integrity-quality-hint]').toggle(!on);
            };
            $root.off('change.cfIntegrity').on('change.cfIntegrity', '[data-cf-integrity-inherit], [data-cf-integrity-enabled]', sync);
            sync();
        });
    };

    var init = function () {
        initListForms();
        initIntegritySettings();
        $('[data-cf-poll-embed]').each(function () {
            initPollEmbed(this);
        });
        if ($('#cf-email-template-edit').length) {
            initEmailTemplate('#cf-email-template-edit');
        }
    };

    module.initBuilder = initBuilder;
    module.initFill = initFill;
    module.initPanelEdit = initPanelEdit;
    module.initAnswers = initAnswers;
    module.initListForms = initListForms;
    module.initEmailTemplate = initEmailTemplate;
    module.text = function (key) {
        return module.config[key] || key;
    };

    module.export({
        init: init,
        initOnPjaxLoad: true,
        initBuilder: initBuilder,
        initFill: initFill,
        initPanelEdit: initPanelEdit,
        initAnswers: initAnswers,
        initListForms: initListForms,
        initEmailTemplate: initEmailTemplate,
        initPollEmbed: initPollEmbed
    });
});
