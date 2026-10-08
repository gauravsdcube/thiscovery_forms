humhub.module('thiscoveryForms', function (module, require, $) {
    var OPTION_TYPES = ['dropdown', 'radio', 'checkbox'];

    var parseCssColor = function (value) {
        var v = String(value || '').trim();
        if (!v || /^transparent$/i.test(v)) {
            return null;
        }
        var hex = v.match(/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i);
        if (hex) {
            var h = hex[1];
            if (h.length === 3) {
                h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
            }
            var a = h.length === 8 ? (parseInt(h.slice(6, 8), 16) / 255) : 1;
            return {
                r: parseInt(h.slice(0, 2), 16),
                g: parseInt(h.slice(2, 4), 16),
                b: parseInt(h.slice(4, 6), 16),
                a: a
            };
        }
        var rgb = v.match(/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)$/i);
        if (rgb) {
            return {
                r: Math.round(parseFloat(rgb[1])),
                g: Math.round(parseFloat(rgb[2])),
                b: Math.round(parseFloat(rgb[3])),
                a: rgb[4] !== undefined ? parseFloat(rgb[4]) : 1
            };
        }
        return null;
    };

    var toHex6 = function (r, g, b) {
        var h = function (n) {
            var s = Math.max(0, Math.min(255, n | 0)).toString(16);
            return s.length === 1 ? '0' + s : s;
        };
        return '#' + h(r) + h(g) + h(b);
    };

    var formatCssColor = function (r, g, b, a) {
        if (a >= 0.999) {
            return toHex6(r, g, b);
        }
        var alpha = Math.round(a * 1000) / 1000;
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    };

    var checkerPreview = 'linear-gradient(45deg, #cbd5e1 25%, transparent 25%), linear-gradient(-45deg, #cbd5e1 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #cbd5e1 75%), linear-gradient(-45deg, transparent 75%, #cbd5e1 75%)';

    var syncStyleColorUi = function ($wrap) {
        var $input = $wrap.find('[data-cf-style-color]');
        var $swatch = $wrap.find('[data-cf-style-swatch]');
        var $preview = $wrap.find('[data-cf-style-preview]');
        var $slider = $wrap.find('[data-cf-style-alpha-slider]');
        var $label = $wrap.find('[data-cf-style-alpha-label]');
        var $transparent = $wrap.find('[data-cf-style-transparent]');
        var value = String($input.val() || '').trim();
        var transparent = /^transparent$/i.test(value);
        $transparent.prop('checked', transparent);
        $swatch.prop('disabled', transparent);
        $slider.prop('disabled', transparent);
        if (transparent) {
            $preview.css({
                backgroundImage: checkerPreview,
                backgroundSize: '10px 10px',
                backgroundPosition: '0 0, 0 5px, 5px -5px, -5px 0',
                backgroundColor: '#fff'
            });
            $label.text('0%');
            $slider.val(0);
            return;
        }
        var parsed = parseCssColor(value);
        if (!parsed) {
            $preview.css({
                backgroundImage: 'none',
                backgroundColor: '#ffffff'
            });
            $swatch.val('#ffffff');
            $slider.val(100);
            $label.text('100%');
            return;
        }
        var hex = toHex6(parsed.r, parsed.g, parsed.b);
        $swatch.val(hex);
        var pct = Math.round(Math.max(0, Math.min(1, parsed.a)) * 100);
        $slider.val(pct);
        $label.text(pct + '%');
        $preview.css({
            backgroundImage: 'none',
            backgroundColor: value || hex
        });
    };

    var writeStyleColorFromPicker = function ($wrap) {
        var $input = $wrap.find('[data-cf-style-color]');
        var $swatch = $wrap.find('[data-cf-style-swatch]');
        var $slider = $wrap.find('[data-cf-style-alpha-slider]');
        if ($wrap.find('[data-cf-style-transparent]').is(':checked')) {
            $input.val('transparent');
            syncStyleColorUi($wrap);
            return;
        }
        var hex = String($swatch.val() || '#ffffff');
        var parsed = parseCssColor(hex) || {r: 255, g: 255, b: 255, a: 1};
        var a = parseInt($slider.val(), 10);
        if (isNaN(a)) {
            a = 100;
        }
        $input.val(formatCssColor(parsed.r, parsed.g, parsed.b, a / 100));
        syncStyleColorUi($wrap);
    };

    var closeStyleColorPanels = function ($except) {
        $('[data-cf-style-color-wrap]').each(function () {
            var $wrap = $(this);
            if ($except && $wrap[0] === $except[0]) {
                return;
            }
            $wrap.find('[data-cf-style-picker-panel]').prop('hidden', true);
            $wrap.find('[data-cf-style-picker-toggle]').attr('aria-expanded', 'false');
        });
    };

    var initStyleColors = function (root) {
        var $root = $(root);
        if (!$root.length) {
            return;
        }
        $root.find('[data-cf-style-color-wrap]').each(function () {
            syncStyleColorUi($(this));
        });
        if ($root.data('cf-style-colors-bound')) {
            return;
        }
        $root.data('cf-style-colors-bound', 1);

        $root.on('click', '[data-cf-style-picker-toggle]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $wrap = $(this).closest('[data-cf-style-color-wrap]');
            var $panel = $wrap.find('[data-cf-style-picker-panel]');
            var open = $panel.prop('hidden');
            closeStyleColorPanels(open ? $wrap : null);
            $panel.prop('hidden', !open);
            $(this).attr('aria-expanded', open ? 'true' : 'false');
            if (open) {
                syncStyleColorUi($wrap);
            }
        });

        $root.on('click', '[data-cf-style-picker-panel]', function (e) {
            e.stopPropagation();
        });

        $root.on('input', '[data-cf-style-swatch], [data-cf-style-alpha-slider]', function () {
            writeStyleColorFromPicker($(this).closest('[data-cf-style-color-wrap]'));
        });

        $root.on('change', '[data-cf-style-transparent]', function () {
            var $wrap = $(this).closest('[data-cf-style-color-wrap]');
            if ($(this).is(':checked')) {
                $wrap.data('cf-style-pre-transparent', $wrap.find('[data-cf-style-color]').val());
                $wrap.find('[data-cf-style-color]').val('transparent');
            } else {
                var prev = $wrap.data('cf-style-pre-transparent');
                if (prev && !/^transparent$/i.test(String(prev))) {
                    $wrap.find('[data-cf-style-color]').val(prev);
                } else {
                    writeStyleColorFromPicker($wrap);
                    return;
                }
            }
            syncStyleColorUi($wrap);
        });

        $root.on('input change', '[data-cf-style-color]', function () {
            syncStyleColorUi($(this).closest('[data-cf-style-color-wrap]'));
        });

        $root.on('click', '[data-cf-style-clear]', function (e) {
            e.preventDefault();
            var $wrap = $(this).closest('[data-cf-style-color-wrap]');
            $wrap.find('[data-cf-style-color]').val('');
            $wrap.find('[data-cf-style-transparent]').prop('checked', false);
            $wrap.find('[data-cf-style-swatch]').val('#ffffff');
            $wrap.find('[data-cf-style-alpha-slider]').val(100);
            syncStyleColorUi($wrap);
        });

        $(document).off('click.cfStyleColor').on('click.cfStyleColor', function () {
            closeStyleColorPanels(null);
        });
    };

    var initBuilder = function (root) {
        var $root = $(root);
        if (!$root.length) {
            return;
        }

        if (module.config.optionTypes && module.config.optionTypes.length) {
            OPTION_TYPES = module.config.optionTypes;
        }

        var fieldIndex = 0;
        var studioDirty = false;
        var studioSaving = false;
        var studioWatch = false;
        var studioLeaveOpen = false;
        var studioBeforeUnload = function (e) {
            if (!studioDirty) {
                return;
            }
            e.preventDefault();
            e.returnValue = '';
        };
        var markStudioDirty = function () {
            if (studioDirty || studioSaving) {
                return;
            }
            studioDirty = true;
            window.addEventListener('beforeunload', studioBeforeUnload);
            try {
                history.pushState({ cfStudioGuard: 1 }, '');
            } catch (err) {}
        };
        var clearStudioDirty = function () {
            studioSaving = true;
            studioDirty = false;
            window.removeEventListener('beforeunload', studioBeforeUnload);
        };

        var refreshIndexes = function () {
            $root.find('.thiscovery-forms-field-row').each(function (i) {
                $(this).find('[data-cf-index]').text(String(i + 1));
                $(this).find('[data-cf-sort-order]').val(String(i));
            });
            $root.find('[data-cf-empty]').toggleClass('d-none', $root.find('.thiscovery-forms-field-row').length > 0);
        };

        var slugVariable = function (label) {
            var s = String(label || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
            if (!s) {
                s = 'field';
            }
            if (!/^[a-z]/.test(s)) {
                s = 'f_' + s;
            }
            return s.slice(0, 100);
        };

        var reindexOptionItems = function ($list) {
            $list.find('[data-cf-option-item]').each(function (i) {
                $(this).find('input').each(function () {
                    var name = String($(this).attr('name') || '');
                    $(this).attr('name', name.replace(/\[option_items\]\[\d+\]/, '[option_items][' + i + ']'));
                });
            });
        };

        var reindexGridItems = function ($list, which) {
            var key = which === 'columns' ? 'grid_column_items' : 'grid_row_items';
            var re = new RegExp('\\[' + key + '\\]\\[\\d+\\]');
            $list.find('[data-cf-grid-item]').each(function (i) {
                $(this).find('input').each(function () {
                    var name = String($(this).attr('name') || '');
                    $(this).attr('name', name.replace(re, '[' + key + '][' + i + ']'));
                });
            });
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
            $own.find('[data-cf-number-panel]').toggleClass('d-none', type !== 'number');
            $own.find('[data-cf-text-rules-panel]').toggleClass('d-none', type !== 'text' && type !== 'textarea');
            $own.find('[data-cf-date-rules-panel]').toggleClass('d-none', type !== 'date');
            $own.find('[data-cf-file-rules-panel]').toggleClass('d-none', type !== 'file');
            $own.find('[data-cf-consensus-panel]').toggleClass('d-none', ['radio', 'dropdown', 'rating'].indexOf(type) === -1);
            $own.find('[data-cf-check-panel]').toggleClass('d-none', isPage || isRich || isMeta || ['question_group', 'group_end', 'calculated'].indexOf(type) !== -1);
            $own.find('[data-cf-formula-panel]').toggleClass('d-none', type !== 'calculated');
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
            $own.find('[data-cf-option-items]').toggleClass('is-checkbox', type === 'checkbox').toggleClass('is-ranking', isRanking);
            $own.find('[data-cf-option-open-col], [data-cf-option-flag-hint]').toggleClass('d-none', !isChoice);
            $own.find('[data-cf-option-exclusive-col]').toggleClass('d-none', type !== 'checkbox');
            $own.find('[data-cf-options-hint-ranking]').toggleClass('d-none', !isRanking);
            $own.find('[data-cf-options-hint-choice]').toggleClass('d-none', isRanking);
            $own.find('[data-cf-required-wrap]').toggleClass('d-none', hideRequired);
            $own.find('[data-cf-help-wrap]').toggleClass('d-none', hideHelp);
            $own.find('[data-cf-hidden-wrap]').toggleClass('d-none', isPage || type === 'question_group' || type === 'group_end' || isRich);
            $own.find('[data-cf-attention-wrap]').toggleClass('d-none', hideRequired || isMeta || isPanelAttr);
            var htmlCollect = isHtml && $own.find('[name$="[html_collect]"]').is(':checked');
            $own.find('[data-cf-pii-wrap]').toggleClass('d-none', isPage || type === 'question_group' || type === 'group_end' || isRich || (isHtml && !htmlCollect));
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

            var label = $.trim($own.find('[data-cf-internal-label]').first().val())
                || $.trim($own.find('[data-cf-field-label]').first().val());
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
            $own.find('[data-cf-logic-compound]').each(function () {
                try {
                    var compound = JSON.parse(String($(this).val() || ''));
                    if (compound && typeof compound === 'object') {
                        rules.push(compound);
                    }
                } catch (e) {}
            });
            $own.find('[data-cf-logic-rule-row]').not('[data-cf-logic-compound-row]').each(function () {
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
            markStudioDirty();
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

        var applyDefaultPii = function ($row) {
            var $own = ownCard($row);
            var type = $own.find('[data-cf-field-type]').val() || '';
            var metaKey = String($own.find('[data-cf-meta-key]').val() || '');
            var panelKey = String($own.find('[data-cf-panel-key]').val() || '');
            var identity = ['first_name', 'last_name', 'email', 'display_name'];
            var on = type === 'email'
                || (type === 'respondent_meta' && (metaKey === 'ip' || !metaKey))
                || (type === 'panel_attr' && identity.indexOf(panelKey) !== -1);
            if (on) {
                $own.find('[data-cf-pii-field]').prop('checked', true);
            }
        };

        var ensureFormulaButtons = function () {};

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
            var labelNow = $.trim($row.find('[data-cf-field-label]').val());
            if (labelNow && !$.trim($row.find('[data-cf-variable]').val())) {
                $row.find('[data-cf-variable]').val(slugVariable(labelNow));
            }
            if (labelNow && !$.trim($row.find('[data-cf-internal-label]').val())) {
                $row.find('[data-cf-internal-label]').val(labelNow);
            }
            $row.find('[data-cf-action-row]').each(function () {
                $(this).find('[data-cf-action-fn]').val('none');
                refreshActionParams($(this));
            });
            refreshTypeUi($row);
            applyDefaultPii($row);
            ensureFormulaButtons();
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
            markStudioDirty();
            return $row;
        };

        // Top tabs: Form builder | Settings. Settings has a left rail of sections.
        var formPanes = {
            basics: 1, variables: 1, end: 1, access: 1, display: 1,
            consent: 1, loops: 1, randomisation: 1, quotas: 1,
            sharing: 1, enrol: 1, email: 1, languages: 1, actions: 1, consensus: 1,
            integrity: 1, css: 1, share: 1, export: 1
        };
        var extraSections = {
            panel: 1, rounds: 1, approval: 1, translations: 1, versions: 1, route: 1
        };
        var footerSections = {
            basics: 1, variables: 1, end: 1, access: 1, display: 1,
            consent: 1, loops: 1, randomisation: 1, quotas: 1,
            sharing: 1, enrol: 1, email: 1, languages: 1, actions: 1, consensus: 1,
            integrity: 1, css: 1, share: 1, export: 1
        };

        var activateTopTab = function (tab, section) {
            tab = String(tab || 'builder');
            section = section ? String(section) : '';
            if (tab === 'settings' && !section) {
                section = $root.find('[data-cf-studio-section-input]').val() || 'basics';
                if (!formPanes[section] && !extraSections[section]) {
                    section = 'basics';
                }
            }
            if (tab !== 'settings') {
                section = '';
            }

            $root.find('[data-cf-tab]').removeClass('is-active').attr('aria-selected', 'false');
            $root.find('[data-cf-tab="' + tab + '"]').addClass('is-active').attr('aria-selected', 'true');

            $root.find('[data-cf-panel]').removeClass('is-active');
            $root.toggleClass('is-settings-extra', false);
            $root.find('[data-cf-studio-content]').removeClass('is-settings-extra');

            if (tab === 'builder') {
                $root.find('[data-cf-panel="builder"]').addClass('is-active');
                $root.find('.cf-studio__footer').show();
            } else {
                activateSettingsSection(section);
            }

            $root.find('[data-cf-studio-tab]').val(tab);
            $root.find('[data-cf-studio-section-input]').val(section || '');

            var $help = $root.find('[data-cf-studio-help]');
            if ($help.length) {
                var pages = {};
                try {
                    pages = JSON.parse($help.attr('data-cf-help-pages') || '{}');
                } catch (err) {}
                var helpKey = tab === 'settings' ? (pages[section] ? section : 'settings') : 'builder';
                if (pages[helpKey]) {
                    $help.attr('href', pages[helpKey]);
                }
            }

            if (tab === 'builder' || (tab === 'settings' && (section === 'translations' || section === 'rounds'))) {
                setTimeout(function () {
                    var $scope = tab === 'builder'
                        ? $root.find('[data-cf-panel="builder"]')
                        : $root.find('[data-cf-panel="' + section + '"], [data-cf-settings-pane="' + section + '"]');
                    initRichEditors($scope);
                }, 30);
            }
            setTimeout(layoutStudioPanes, 0);
        };

        var activateSettingsSection = function (section) {
            section = String(section || 'basics');
            var isExtra = !!extraSections[section];

            $root.find('[data-cf-tab]').removeClass('is-active').attr('aria-selected', 'false');
            $root.find('[data-cf-tab="settings"]').addClass('is-active').attr('aria-selected', 'true');

            $root.find('[data-cf-panel]').removeClass('is-active');
            $root.find('[data-cf-panel="settings"]').addClass('is-active');

            var $rail = $root.find('[data-cf-settings-rail]');
            $rail.find('[data-cf-settings-nav]').removeClass('is-active').attr('aria-selected', 'false');
            $rail.find('[data-cf-settings-nav="' + section + '"]').addClass('is-active').attr('aria-selected', 'true');

            $root.find('[data-cf-settings-pane]').removeClass('is-active').attr('hidden', true);

            if (isExtra) {
                $root.addClass('is-settings-extra');
                $root.find('[data-cf-studio-content]').addClass('is-settings-extra');
                $root.find('[data-cf-panel="' + section + '"]').addClass('is-active');
                $root.find('.cf-studio__footer').hide();
            } else {
                $root.removeClass('is-settings-extra');
                $root.find('[data-cf-studio-content]').removeClass('is-settings-extra');
                var $pane = $root.find('[data-cf-settings-pane="' + section + '"]');
                $pane.addClass('is-active').removeAttr('hidden');
                $root.find('.cf-studio__footer').toggle(!!footerSections[section]);
                setTimeout(function () {
                    initRichEditors($pane);
                }, 30);
            }

            $root.find('[data-cf-studio-tab]').val('settings');
            $root.find('[data-cf-studio-section-input]').val(section);

            var $help = $root.find('[data-cf-studio-help]');
            if ($help.length) {
                var pages = {};
                try {
                    pages = JSON.parse($help.attr('data-cf-help-pages') || '{}');
                } catch (err) {}
                var helpKey = pages[section] ? section : 'settings';
                if (pages[helpKey]) {
                    $help.attr('href', pages[helpKey]);
                }
            }

            setTimeout(layoutStudioPanes, 0);
        };

        $root.on('click', '[data-cf-tab]', function (e) {
            e.preventDefault();
            activateTopTab($(this).data('cf-tab'), null);
        });

        $root.on('click', '[data-cf-settings-nav]', function (e) {
            e.preventDefault();
            activateSettingsSection($(this).attr('data-cf-settings-nav'));
        });

        var syncExportLocks = function () {
            var on = $root.find('[data-cf-export-scrub]').is(':checked');
            $root.find('[data-cf-export-pii-note]').toggleClass('d-none', !on);
            $root.find('[data-cf-export-col]').each(function () {
                var $cb = $(this);
                var lock = $cb.attr('data-cf-export-lock') === '1';
                var $label = $cb.closest('.cf-export-col');
                var pref = $cb.attr('data-cf-export-pref') === '1';
                $label.find('[data-cf-export-lock-hold]').remove();
                if (lock && on) {
                    $cb.prop('checked', false).prop('disabled', true);
                    $label.addClass('is-locked');
                    if (pref) {
                        $('<input type="hidden" name="export_include[]" data-cf-export-lock-hold="true">')
                            .val($cb.val())
                            .insertBefore($cb);
                    }
                } else {
                    $cb.prop('disabled', false).prop('checked', pref);
                    $label.removeClass('is-locked');
                }
            });
        };
        $root.on('change', '[data-cf-export-scrub]', syncExportLocks);
        $root.on('change', '[data-cf-export-col]', function () {
            $(this).attr('data-cf-export-pref', $(this).is(':checked') ? '1' : '0');
        });
        $root.on('click', '[data-cf-export-all], [data-cf-export-none]', function (e) {
            e.preventDefault();
            var group = $(this).attr('data-cf-export-all') || $(this).attr('data-cf-export-none');
            var check = $(this).is('[data-cf-export-all]');
            $root.find('[data-cf-export-group="' + group + '"] [data-cf-export-col]:not(:disabled)').each(function () {
                $(this).prop('checked', check).attr('data-cf-export-pref', check ? '1' : '0');
            });
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

        $root.on('click', '[data-cf-acc-all]', function () {
            var open = $(this).attr('data-cf-acc-all') === 'open';
            $root.find('[data-cf-panel="settings"] details.cf-set-acc').prop('open', open);
        });

        var syncCompletionMode = function () {
            var $box = $root.find('[data-cf-completion-mode]');
            if (!$box.length) {
                return;
            }
            var mode = $box.find('[data-cf-completion-choice]:checked').val()
                || $box.find('input[name="CustomForm[completion_mode]"]:checked').val()
                || 'message';
            $box.find('[data-cf-completion-panel]').attr('hidden', true);
            $box.find('[data-cf-completion-panel="' + mode + '"]').removeAttr('hidden');
            var showBtn = $box.find('input[name="CustomForm[completion_button_enabled]"]').is(':checked');
            $box.find('[data-cf-completion-button-fields]').toggle(mode === 'message' && showBtn);
        };

        var syncAlreadyButton = function () {
            var $panel = $root.find('[data-cf-settings-pane="end"]');
            if (!$panel.length) {
                return;
            }
            var on = $panel.find('input[name="CustomForm[already_submitted_button_enabled]"]').is(':checked');
            $panel.find('[data-cf-already-button-fields]').toggle(on);
        };

        $root.on('change', '[data-cf-completion-choice], input[name="CustomForm[completion_mode]"], input[name="CustomForm[completion_button_enabled]"]', syncCompletionMode);
        $root.on('change', 'input[name="CustomForm[already_submitted_button_enabled]"]', syncAlreadyButton);
        syncCompletionMode();
        syncAlreadyButton();

        try {
            var params = new URLSearchParams(window.location.search);
            var openTab = params.get('tab') || $root.find('[data-cf-studio-tab]').val() || 'builder';
            var openSection = params.get('section') || $root.find('[data-cf-studio-section-input]').val() || '';
            if (openTab !== 'builder' && openTab !== 'settings') {
                openSection = openTab;
                openTab = 'settings';
            }
            if (formPanes[openTab] || extraSections[openTab]) {
                openSection = openTab;
                openTab = 'settings';
            }
            activateTopTab(openTab, openSection || null);
        } catch (e) {
            activateTopTab('builder', null);
        }

        $root.on('change', '[data-cf-theme-select]', function () {
            var $sel = $(this);
            var val = String($sel.val() || '');
            if (val !== '') {
                return;
            }
            var raw = $sel.attr('data-cf-default-theme-style') || '{}';
            var css = $sel.attr('data-cf-default-theme-css') || '';
            var style = {};
            try {
                style = JSON.parse(raw) || {};
            } catch (err) {
                style = {};
            }
            Object.keys(style).forEach(function (groupId) {
                var group = style[groupId] || {};
                Object.keys(group).forEach(function (name) {
                    var $input = $root.find('[name="CustomForm[style][' + groupId + '][' + name + ']"]');
                    if ($input.length) {
                        $input.val(group[name]);
                        if ($input.is('[data-cf-style-color]')) {
                            $input.trigger('change');
                        }
                    }
                });
            });
            $root.find('[name="CustomForm[custom_css]"]').val(css);
        });

        initStyleColors($root);

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

        // Controls for delete, import, and secure send used to carry form="…"
        // while sitting inside this form. Safari then posted the form on every
        // character typed in Title or those fields. They are plain controls now
        // and only submit their own form when their button is clicked.
        var allowStudioSubmit = false;
        var armStudioSubmit = function () {
            allowStudioSubmit = true;
            window.setTimeout(function () {
                allowStudioSubmit = false;
            }, 400);
        };
        $root.find('form.cf-studio__form [form]').each(function () {
            var owner = this.getAttribute('form');
            this.removeAttribute('form');
            if (owner && owner !== 'cf-studio-form' && !this.getAttribute('data-cf-target')) {
                this.setAttribute('data-cf-target', owner);
            }
            if (this.tagName === 'BUTTON' && owner && owner !== 'cf-studio-form') {
                this.type = 'button';
            }
        });
        var ownedFieldEmpty = function (el) {
            if (el.type === 'checkbox' || el.type === 'radio') {
                return !el.checked;
            }
            if (el.type === 'file') {
                return !el.files || !el.files.length;
            }
            return $.trim(String(el.value || '')) === '';
        };
        var submitOwnedForm = function (targetId) {
            if (!/^[A-Za-z0-9_-]+$/.test(targetId)) {
                return;
            }
            var form = document.getElementById(targetId);
            if (!form) {
                return;
            }
            var fields = [];
            document.querySelectorAll('[data-cf-target]').forEach(function (el) {
                if (el.getAttribute('data-cf-target') !== targetId) {
                    return;
                }
                if (!el.matches('input, select, textarea')) {
                    return;
                }
                fields.push(el);
            });
            var i;
            for (i = 0; i < fields.length; i++) {
                var el = fields[i];
                if (el.getAttribute('data-cf-required') === '1' && ownedFieldEmpty(el)) {
                    el.required = true;
                    el.reportValidity();
                    el.required = false;
                    return;
                }
                if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
                    el.reportValidity();
                    return;
                }
            }
            form.querySelectorAll('[data-cf-cloned]').forEach(function (node) {
                node.remove();
            });
            var fileNode = null;
            fields.forEach(function (el) {
                if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
                    return;
                }
                if (el.type === 'file') {
                    fileNode = el;
                    return;
                }
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = el.name;
                hidden.value = el.value;
                hidden.setAttribute('data-cf-cloned', '1');
                form.appendChild(hidden);
            });
            if (fileNode && fileNode.parentNode) {
                var mark = document.createComment('cf-file');
                fileNode.parentNode.insertBefore(mark, fileNode);
                form.appendChild(fileNode);
            }
            form.submit();
        };
        $root.off('click.cfForeignSubmit').on('click.cfForeignSubmit', 'button[data-cf-target]', function (e) {
            if (e.isDefaultPrevented()) {
                return;
            }
            e.preventDefault();
            submitOwnedForm(String(this.getAttribute('data-cf-target') || ''));
        });
        $root.off('mousedown.cfStudioSubmit').on('mousedown.cfStudioSubmit', 'form.cf-studio__form button[type="submit"]', armStudioSubmit);
        $root.off('keydown.cfStudioSubmit').on('keydown.cfStudioSubmit', 'form.cf-studio__form button[type="submit"]', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                armStudioSubmit();
            }
        });
        $root.off('click.cfStudioNav').on('click.cfStudioNav', '[data-cf-studio-submit]', function (e) {
            e.preventDefault();
            var $form = $root.find('form.cf-studio__form');
            if (!$form.length) {
                return;
            }
            $form.find('input[name="after_save"]').remove();
            if (this.getAttribute('data-cf-studio-submit') === 'preview') {
                $('<input type="hidden" name="after_save" value="preview">').appendTo($form);
            }
            allowStudioSubmit = true;
            if (typeof $form.get(0).requestSubmit === 'function') {
                $form.get(0).requestSubmit();
            } else {
                $form.trigger('submit');
                $form.get(0).submit();
            }
            window.setTimeout(function () {
                allowStudioSubmit = false;
            }, 0);
        });
        $root.find('form.cf-studio__form').each(function () {
            this.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' || !e.target) {
                    return;
                }
                var tag = e.target.tagName;
                if (tag === 'INPUT' || tag === 'SELECT') {
                    e.preventDefault();
                }
            }, true);
        });

        $root.on('submit', 'form.cf-studio__form', function (e) {
            if (!allowStudioSubmit) {
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
            clearStudioDirty();
            allowStudioSubmit = false;
            $(this).find('[data-cf-target]').filter('input, select, textarea').prop('disabled', true);
            refreshIndexes();
            syncRichEditors();
            syncAllLogicKeep();
            var $form = $(this);
            // Re-enable any fields left disabled by a previous aborted submit.
            $form.find('[data-cf-fields] .thiscovery-forms-field-row').find('input, select, textarea').each(function () {
                var name = this.name;
                if (name && name.indexOf('fields[') === 0) {
                    this.disabled = false;
                }
            });
            var payload = collectFieldsPayload();
            var $json = $form.find('[data-cf-fields-json]');
            if (!$json.length) {
                $json = $('<input type="hidden" name="fields_json" data-cf-fields-json="true">').appendTo($form);
            }
            $json.prop('disabled', false).val(JSON.stringify(payload));
            $form.find('input[name="clear_fields_confirmed"]').remove();
            if ($form.data('cf-clear-fields-confirmed') && Object.keys(payload).length === 0) {
                $('<input type="hidden" name="clear_fields_confirmed" value="1">').appendTo($form);
            }
            $form.removeData('cf-clear-fields-confirmed');
            $form.find('[data-cf-fields] .thiscovery-forms-field-row').find('input, select, textarea').each(function () {
                var name = this.name;
                if (name && name.indexOf('fields[') === 0) {
                    this.disabled = true;
                }
            });
        });

        // Versions "Publish current draft" must save the studio first, otherwise
        // unpublished builder changes are left out of the live edition.
        $root.on('submit', '.tc-versions__publish-current', function (e) {
            var $studio = $root.find('form.cf-studio__form');
            if (!$studio.length) {
                return;
            }
            e.preventDefault();
            $studio.find('input[name="after_save"][value="publish"]').remove();
            $('<input type="hidden" name="after_save" value="publish">').appendTo($studio);
            allowStudioSubmit = true;
            if (typeof $studio.get(0).requestSubmit === 'function') {
                $studio.get(0).requestSubmit();
            } else {
                $studio.trigger('submit');
            }
            window.setTimeout(function () {
                allowStudioSubmit = false;
            }, 0);
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
                    markStudioDirty();
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
                    markStudioDirty();
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
                    markStudioDirty();
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
            e.stopPropagation();
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
            $root.find('form.cf-studio__form').data('cf-clear-fields-confirmed', true);
            markStudioDirty();
            refreshIndexes();
            refreshConditionOptions();
        });

        var removeFieldRow = function ($row) {
            var type = String($row.attr('data-cf-type') || '');
            if (type === 'question_group') {
                var $body = $row.find('[data-cf-group-body]').first();
                var $end = $body.children('[data-cf-type="group_end"]');
                $body.children('.thiscovery-forms-field-row').not('[data-cf-type="group_end"]').insertAfter($row);
                $end.remove();
            }
            var $oldBody = $row.parent('[data-cf-group-body]');
            $row.remove();
            markStudioDirty();
            if ($oldBody.length) {
                refreshGroupEmpty($oldBody);
            }
            refreshIndexes();
            refreshConditionOptions();
        };

        $root.on('click', '[data-cf-remove-field]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            if ($row.data('cf-remove-pending')) {
                return;
            }
            var label = $.trim($row.children('.cf-field-card__header').find('[data-cf-title]').first().text());
            if (!label) {
                label = module.config.untitled || 'Untitled field';
            }
            var template = module.config.removeFieldConfirm || 'Remove "{label}" from this form?';
            var safeLabel = $('<div>').text(label).html();
            var msg = template.split('{label}').join(safeLabel);
            $row.data('cf-remove-pending', true);
            require('ui.modal').confirm({
                header: module.config.removeFieldHeader || 'Remove this question?',
                body: msg,
                confirmText: module.config.removeFieldConfirmText || 'Remove'
            }).then(function (confirmed) {
                $row.removeData('cf-remove-pending');
                if (confirmed) {
                    removeFieldRow($row);
                }
            }).catch(function () {
                $row.removeData('cf-remove-pending');
            });
        });

        $root.on('click', '[data-cf-toggle-advanced]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            expandCard($row);
            var $panel = ownCard($row).find('[data-cf-logic-panel]');
            $panel.addClass('is-open');
            var panelNode = $panel.get(0);
            if (panelNode && panelNode.scrollIntoView) {
                panelNode.scrollIntoView({ block: 'nearest' });
            }
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

        var studioTypeTimer = null;
        var queueStudioType = function (fn) {
            clearTimeout(studioTypeTimer);
            studioTypeTimer = setTimeout(fn, 40);
        };

        $root.on('input change', '[data-cf-image-url]', function () {
            var input = this;
            queueStudioType(function () {
                var $row = $(input).closest('.thiscovery-forms-field-row');
                var url = $.trim(String($(input).val() || ''));
                if (url) {
                    $row.find('[data-cf-image-guid]').val('');
                    $row.find('[data-cf-image-src]').val(url);
                    $row.find('[data-cf-image-clear]').removeClass('d-none');
                }
                renderHotspotBuilder($row);
            });
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

        $root.on('change', '[name$="[html_collect]"]', function () {
            refreshTypeUi($(this).closest('.thiscovery-forms-field-row'));
        });

        var formulaDepNames = function (text) {
            var found = {};
            var re = /\[(?:(var:|panel:|meta:|url:|fn:))?([A-Za-z_][A-Za-z0-9_]*)/g;
            var match;
            while ((match = re.exec(String(text || '')))) {
                found[(match[1] || '') + match[2]] = true;
            }
            if (/\[arm\]/.test(String(text || ''))) {
                found.arm = true;
            }
            return Object.keys(found).sort();
        };
        var paintFormulaDeps = function ($input) {
            var names = formulaDepNames($input.val());
            var $list = $input.nextAll('[data-cf-formula-deps]').first();
            if (!$list.length) {
                $list = $input.parent().children('[data-cf-formula-deps]').first();
            }
            if (!$list.length) {
                return;
            }
            $list.empty();
            names.forEach(function (name) {
                $list.append($('<li>').text(name));
            });
        };
        $root.on('input', '[data-cf-formula-text]', function () {
            paintFormulaDeps($(this));
        });
        $root.find('[data-cf-formula-text]').each(function () {
            paintFormulaDeps($(this));
        });

        $root.on('click', '[data-cf-formula-test]', function () {
            var $panel = $(this).closest('[data-cf-formula-panel]');
            var url = String($('#cf-studio-form').attr('data-cf-formula-preview') || '');
            var $result = $panel.find('[data-cf-formula-result]');
            if (!url) {
                $result.text('The formula test is not available on this page.');
                return;
            }
            $.ajax({
                url: url,
                method: 'POST',
                data: $.extend(csrfData(), {
                    formula: $panel.find('[data-cf-formula-text]').val() || '',
                    values: '{}'
                })
            }).done(function (data) {
                if (data && data.ok) {
                    $result.text((data.result === '' ? 'Empty' : data.result) + ' (today ' + data.today + ')');
                } else {
                    $result.text(data && data.error ? data.error : 'The formula could not be checked.');
                }
            }).fail(function () {
                $result.text('The formula could not be checked.');
            });
        });

        (function initFormulaBuilder() {
            var cfg = module.config || {};
            var t = function (key, fallback) {
                return cfg[key] || fallback;
            };
            var OPS = [
                ['=', t('formulaEquals', 'equals')],
                ['!=', t('formulaNotEquals', 'does not equal')],
                ['in', t('formulaOneOf', 'is one of')],
                ['not_in', t('formulaNotOneOf', 'is not one of')],
                ['>', t('formulaGt', 'is greater than')],
                ['>=', t('formulaGte', 'is at least')],
                ['<', t('formulaLt', 'is less than')],
                ['<=', t('formulaLte', 'is at most')]
            ];
            var formulaTarget = null;

            var studioQuestions = function () {
                var seen = {};
                var out = [];
                $root.find('[data-cf-variable]').each(function () {
                    var name = $.trim($(this).val() || '');
                    if (!name || seen[name]) {
                        return;
                    }
                    seen[name] = true;
                    var $row = $(this).closest('.thiscovery-forms-field-row');
                    var $card = ownCard($row);
                    var label = $.trim($card.find('[data-cf-field-label]').first().val() || '') || name;
                    var options = [];
                    var optSeen = {};
                    $card.find('[data-cf-option-item]').each(function () {
                        var code = $.trim($(this).find('[data-cf-option-code]').val() || '');
                        var lab = $.trim($(this).find('[data-cf-option-label]').val() || '');
                        if (!code && !lab) {
                            return;
                        }
                        var value = code || lab;
                        if (optSeen[value]) {
                            return;
                        }
                        optSeen[value] = true;
                        options.push({ value: value, label: lab || value });
                    });
                    out.push({ name: name, label: label, options: options });
                });
                return out;
            };

            var quote = function (value) {
                return '"' + String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"';
            };
            var isNumber = function (value) {
                return /^-?\d+(\.\d+)?$/.test(String(value));
            };
            var literalText = function (value, op) {
                if (['>', '>=', '<', '<='].indexOf(op) !== -1 && isNumber(value)) {
                    return String(value);
                }
                return quote(value);
            };

            var lexFormula = function (text) {
                var tokens = [];
                var i = 0;
                var length = text.length;
                while (i < length) {
                    var ch = text.charAt(i);
                    if (ch === ' ' || ch === '\t' || ch === '\n' || ch === '\r') {
                        i++;
                        continue;
                    }
                    var last = tokens.length ? tokens[tokens.length - 1] : null;
                    if (ch === '[' && last && last.t === 'ident' && (last.v === 'in' || last.v === 'not_in')) {
                        tokens.push({ t: '[', v: '[' });
                        i++;
                        continue;
                    }
                    if (ch === '[') {
                        i++;
                        var body = '';
                        var depth = 1;
                        while (i < length && depth > 0) {
                            if (text.charAt(i) === '[') {
                                depth++;
                            } else if (text.charAt(i) === ']') {
                                depth--;
                                if (depth === 0) {
                                    i++;
                                    break;
                                }
                            }
                            body += text.charAt(i);
                            i++;
                        }
                        if (depth !== 0) {
                            throw new Error('unclosed');
                        }
                        tokens.push({ t: 'ref', v: body });
                        continue;
                    }
                    if (ch === '"') {
                        i++;
                        var value = '';
                        while (i < length && text.charAt(i) !== '"') {
                            if (text.charAt(i) === '\\' && i + 1 < length) {
                                value += text.charAt(i + 1);
                                i += 2;
                                continue;
                            }
                            value += text.charAt(i);
                            i++;
                        }
                        if (i >= length || text.charAt(i) !== '"') {
                            throw new Error('quote');
                        }
                        i++;
                        tokens.push({ t: 'string', v: value });
                        continue;
                    }
                    if (ch >= '0' && ch <= '9') {
                        var num = '';
                        while (i < length && ((text.charAt(i) >= '0' && text.charAt(i) <= '9') || text.charAt(i) === '.')) {
                            num += text.charAt(i);
                            i++;
                        }
                        tokens.push({ t: 'number', v: num });
                        continue;
                    }
                    if (/[A-Za-z_]/.test(ch)) {
                        var ident = '';
                        while (i < length && /[A-Za-z0-9_]/.test(text.charAt(i))) {
                            ident += text.charAt(i);
                            i++;
                        }
                        tokens.push({ t: 'ident', v: ident });
                        continue;
                    }
                    var two = text.substr(i, 2);
                    if (two === '!=' || two === '>=' || two === '<=') {
                        tokens.push({ t: two, v: two });
                        i += 2;
                        continue;
                    }
                    tokens.push({ t: ch, v: ch });
                    i++;
                }
                tokens.push({ t: 'eof', v: '' });
                return tokens;
            };

            var parseFormula = function (text) {
                var tokens = lexFormula(text);
                var index = 0;
                var peek = function () {
                    return tokens[index];
                };
                var eat = function (type) {
                    if (peek().t === type) {
                        index++;
                        return true;
                    }
                    return false;
                };
                var keyword = function (word) {
                    if (peek().t === 'ident' && peek().v === word) {
                        index++;
                        return true;
                    }
                    return false;
                };
                var parseOr = function () {
                    var left = parseAnd();
                    while (keyword('or')) {
                        left = { type: 'or', args: [left, parseAnd()] };
                    }
                    return left;
                };
                var parseAnd = function () {
                    var left = parseNot();
                    while (keyword('and')) {
                        left = { type: 'and', args: [left, parseNot()] };
                    }
                    return left;
                };
                var parseNot = function () {
                    if (keyword('not')) {
                        return { type: 'not', args: [parseNot()] };
                    }
                    return parseCmp();
                };
                var parseList = function () {
                    if (!eat('[')) {
                        throw new Error('list');
                    }
                    var args = [];
                    if (peek().t !== ']') {
                        args.push(parseOr());
                        while (eat(',')) {
                            args.push(parseOr());
                        }
                    }
                    if (!eat(']')) {
                        throw new Error('list');
                    }
                    return { type: 'list', args: args };
                };
                var parsePrimary = function () {
                    if (eat('(')) {
                        var inner = parseOr();
                        if (!eat(')')) {
                            throw new Error('paren');
                        }
                        return inner;
                    }
                    if (peek().t === '-' && tokens[index + 1] && tokens[index + 1].t === 'number') {
                        index++;
                        var neg = peek().v;
                        index++;
                        return { type: 'num', v: '-' + neg };
                    }
                    if (peek().t === 'ref') {
                        var ref = peek().v;
                        index++;
                        return { type: 'ref', name: ref };
                    }
                    if (peek().t === 'string') {
                        var str = peek().v;
                        index++;
                        return { type: 'str', v: str };
                    }
                    if (peek().t === 'number') {
                        var num = peek().v;
                        index++;
                        return { type: 'num', v: num };
                    }
                    throw new Error('primary');
                };
                var parseCmp = function () {
                    var left = parsePrimary();
                    if (keyword('not_in')) {
                        return { type: 'in', op: 'not_in', left: left, list: parseList() };
                    }
                    if (keyword('in')) {
                        return { type: 'in', op: 'in', left: left, list: parseList() };
                    }
                    var cmp = ['=', '!=', '>', '>=', '<', '<='];
                    if (cmp.indexOf(peek().t) !== -1) {
                        var op = peek().t;
                        index++;
                        return { type: 'cmp', op: op, left: left, right: parsePrimary() };
                    }
                    return left;
                };
                var tree = parseOr();
                if (peek().t !== 'eof') {
                    throw new Error('trailing');
                }
                return tree;
            };

            var literalOf = function (node) {
                if (node && (node.type === 'str' || node.type === 'num')) {
                    return String(node.v);
                }
                throw new Error('literal');
            };
            var condFrom = function (tree, notFlag) {
                if (tree.type !== 'cmp' && tree.type !== 'in') {
                    throw new Error('cond');
                }
                if (!tree.left || tree.left.type !== 'ref' || !tree.left.name) {
                    throw new Error('left');
                }
                var values = [];
                var valueKind = 'literal';
                if (tree.type === 'in') {
                    if (!tree.list || tree.list.type !== 'list') {
                        throw new Error('in');
                    }
                    tree.list.args.forEach(function (item) {
                        values.push(literalOf(item));
                    });
                } else if (tree.right && tree.right.type === 'ref') {
                    valueKind = 'question';
                    values = [tree.right.name];
                } else {
                    values = [literalOf(tree.right)];
                }
                return {
                    kind: 'cond',
                    not: notFlag,
                    variable: tree.left.name,
                    op: tree.type === 'in' ? tree.op : tree.op,
                    valueKind: valueKind,
                    values: values
                };
            };
            var absorbNot = function (node) {
                var not = false;
                while (node && node.type === 'not') {
                    not = !not;
                    node = node.args[0];
                }
                return { not: not, node: node };
            };
            var flatten = function (tree) {
                var absorbed = absorbNot(tree);
                tree = absorbed.node;
                if (tree && (tree.type === 'and' || tree.type === 'or')) {
                    var items = [];
                    var collect = function (node) {
                        var part = absorbNot(node);
                        if (!part.not && part.node && part.node.type === tree.type) {
                            collect(part.node.args[0]);
                            collect(part.node.args[1]);
                            return;
                        }
                        items.push(flatten(node));
                    };
                    collect(tree);
                    return { kind: 'group', not: absorbed.not, joinInside: tree.type, items: items };
                }
                if (tree && (tree.type === 'cmp' || tree.type === 'in')) {
                    return condFrom(tree, absorbed.not);
                }
                throw new Error('shape');
            };
            var toVisual = function (text) {
                var trimmed = $.trim(text || '');
                if (!trimmed) {
                    return { model: emptyGroup(), unsupported: false };
                }
                var model = flatten(parseFormula(trimmed));
                if (model.kind === 'cond') {
                    model = { kind: 'group', not: false, joinInside: 'and', items: [model] };
                }
                return { model: model, unsupported: false };
            };
            var emptyCond = function () {
                return { kind: 'cond', not: false, variable: '', op: '=', valueKind: 'literal', values: [''] };
            };
            var emptyGroup = function () {
                return { kind: 'group', not: false, joinInside: 'and', items: [emptyCond()] };
            };

            var questionByName = function (questions, name) {
                for (var i = 0; i < questions.length; i++) {
                    if (questions[i].name === name) {
                        return questions[i];
                    }
                }
                return null;
            };
            var fillQuestionSelect = function ($select, questions, selected, extras) {
                $select.empty();
                $select.append($('<option>').val('').text(t('formulaChoose', 'Choose a question')));
                var seen = {};
                questions.forEach(function (q) {
                    seen[q.name] = true;
                    var text = q.label === q.name ? q.name : (q.label + ' (' + q.name + ')');
                    $select.append($('<option>').val(q.name).text(text));
                });
                (extras || []).forEach(function (name) {
                    if (!name || seen[name]) {
                        return;
                    }
                    seen[name] = true;
                    $select.append($('<option>').val(name).text(name));
                });
                if (selected && !seen[selected]) {
                    $select.append($('<option>').val(selected).text(selected));
                }
                $select.val(selected || '');
            };

            var renderValue = function ($row, questions, seed, kindOverride) {
                var op = String($row.find('[data-cf-fb-op]').val() || '=');
                var kind = kindOverride || String($row.find('[data-cf-fb-kind]').val() || 'literal');
                var variable = String($row.find('[data-cf-fb-var]').val() || '');
                var question = questionByName(questions, variable);
                var options = question ? question.options : [];
                var $slot = $row.find('[data-cf-fb-value]');
                var previous = seed || readValues($row);
                if (op === 'in' || op === 'not_in') {
                    kind = 'literal';
                }
                $slot.empty();
                if (op !== 'in' && op !== 'not_in') {
                    var $kind = $('<select class="form-select form-select-sm" data-cf-fb-kind>').append(
                        $('<option>').val('literal').text(t('formulaAnswer', 'An answer')),
                        $('<option>').val('question').text(t('formulaAnother', 'Another question'))
                    );
                    $kind.val(kind === 'question' ? 'question' : 'literal');
                    $slot.append($kind);
                }
                if ((op === 'in' || op === 'not_in')) {
                    var $checks = $('<div class="cf-fb__checks" data-cf-fb-checks>');
                    var known = {};
                    options.forEach(function (opt) {
                        known[opt.value] = true;
                        var $label = $('<label>');
                        var $box = $('<input type="checkbox">').val(opt.value);
                        if (previous.indexOf(opt.value) !== -1) {
                            $box.prop('checked', true);
                        }
                        $label.append($box).append($('<span>').text(opt.label === opt.value ? opt.label : (opt.label + ' (' + opt.value + ')')));
                        $checks.append($label);
                    });
                    $slot.append($checks);
                    var extras = previous.filter(function (value) {
                        return value !== '' && !known[value];
                    });
                    $row.attr('data-cf-fb-extra', JSON.stringify(extras));
                    var $extra = $('<div class="cf-fb__extra" data-cf-fb-extra-list>');
                    extras.forEach(function (value) {
                        $extra.append(extraChip(value));
                    });
                    var $add = $('<div class="cf-fb__addval">');
                    $add.append($('<input type="text" class="form-control form-control-sm" data-cf-fb-new>').attr('placeholder', t('formulaTypeValue', 'Type a value')));
                    $add.append($('<button type="button" class="btn btn-default btn-sm" data-cf-fb-add-value>').text(t('formulaAddValue', 'Add value')));
                    $slot.append($extra).append($add);
                    return;
                }
                $row.removeAttr('data-cf-fb-extra');
                if (kind === 'question') {
                    var $other = $('<select class="form-select form-select-sm" data-cf-fb-other>');
                    fillQuestionSelect($other, questions, previous[0] || '', []);
                    $slot.append($other);
                    return;
                }
                if (options.length) {
                    var $pick = $('<select class="form-select form-select-sm" data-cf-fb-pick>');
                    $pick.append($('<option>').val('').text(t('formulaTypeValue', 'Type a value')));
                    var matched = false;
                    options.forEach(function (opt) {
                        var text = opt.label === opt.value ? opt.label : (opt.label + ' (' + opt.value + ')');
                        $pick.append($('<option>').val(opt.value).text(text));
                        if (previous[0] === opt.value) {
                            matched = true;
                        }
                    });
                    $slot.append($pick);
                    var $custom = $('<input type="text" class="form-control form-control-sm" data-cf-fb-custom>').attr('placeholder', t('formulaTypeValue', 'Type a value'));
                    if (matched) {
                        $pick.val(previous[0]);
                        $custom.addClass('d-none');
                    } else if (previous[0]) {
                        $custom.val(previous[0]);
                    } else {
                        $custom.addClass('d-none');
                    }
                    $slot.append($custom);
                    return;
                }
                $slot.append($('<input type="text" class="form-control form-control-sm" data-cf-fb-custom>').val(previous[0] || '').attr('placeholder', t('formulaTypeValue', 'Type a value')));
            };
            var extraChip = function (value) {
                var $chip = $('<span class="cf-fb__chip">');
                $chip.append($('<span>').text(value));
                $chip.append($('<button type="button" data-cf-fb-drop-value>').text('×').attr('data-value', value));
                return $chip;
            };
            var readValues = function ($row) {
                var op = String($row.find('[data-cf-fb-op]').val() || '=');
                if (op === 'in' || op === 'not_in') {
                    var values = [];
                    $row.find('[data-cf-fb-checks] input:checked').each(function () {
                        values.push(String($(this).val() || ''));
                    });
                    var extras = [];
                    try {
                        extras = JSON.parse($row.attr('data-cf-fb-extra') || '[]');
                    } catch (e) {
                        extras = [];
                    }
                    extras.forEach(function (value) {
                        if (values.indexOf(value) === -1) {
                            values.push(value);
                        }
                    });
                    return values;
                }
                if (String($row.find('[data-cf-fb-kind]').val() || '') === 'question') {
                    return [String($row.find('[data-cf-fb-other]').val() || '')];
                }
                var picked = String($row.find('[data-cf-fb-pick]').val() || '');
                if (picked) {
                    return [picked];
                }
                return [String($row.find('[data-cf-fb-custom]').val() || '')];
            };
            var readShownValues = function ($row) {
                if ($row.find('[data-cf-fb-checks]').length) {
                    var values = [];
                    $row.find('[data-cf-fb-checks] input:checked').each(function () {
                        values.push(String($(this).val() || ''));
                    });
                    var extras = [];
                    try {
                        extras = JSON.parse($row.attr('data-cf-fb-extra') || '[]');
                    } catch (e) {
                        extras = [];
                    }
                    extras.forEach(function (value) {
                        if (values.indexOf(value) === -1) {
                            values.push(value);
                        }
                    });
                    return values;
                }
                if ($row.find('[data-cf-fb-other]').length) {
                    return [String($row.find('[data-cf-fb-other]').val() || '')];
                }
                var picked = String($row.find('[data-cf-fb-pick]').val() || '');
                if (picked) {
                    return [picked];
                }
                if ($row.find('[data-cf-fb-custom]').length) {
                    return [String($row.find('[data-cf-fb-custom]').val() || '')];
                }
                return [];
            };

            var renderCond = function (cond, questions) {
                var $row = $('<div class="cf-fb__row" data-cf-fb-item data-cf-fb-cond>');
                var $var = $('<select class="form-select form-select-sm" data-cf-fb-var>');
                fillQuestionSelect($var, questions, cond.variable, [cond.variable]);
                var $op = $('<select class="form-select form-select-sm" data-cf-fb-op>');
                OPS.forEach(function (pair) {
                    $op.append($('<option>').val(pair[0]).text(pair[1]));
                });
                $op.val(cond.op || '=');
                var $not = $('<label class="cf-fb__not">').append(
                    $('<input type="checkbox" data-cf-fb-not>').prop('checked', !!cond.not),
                    $('<span>').text(t('formulaNot', 'Not'))
                );
                var $remove = $('<button type="button" class="btn btn-default btn-sm" data-cf-fb-remove>').text(t('formulaRemove', 'Remove'));
                $row.append($var, $op, $('<div class="cf-fb__value" data-cf-fb-value>'), $not, $remove);
                renderValue($row, questions, cond.values || [], cond.valueKind === 'question' ? 'question' : 'literal');
                return $row;
            };
            var renderGroup = function (group, questions, isRoot) {
                var $group = $('<div class="cf-fb__group" data-cf-fb-group>').attr('data-cf-fb-item', isRoot ? null : '');
                if (!isRoot) {
                    $group.attr('data-cf-fb-item', '');
                }
                var $bar = $('<div class="cf-fb__bar">');
                var $join = $('<select class="form-select form-select-sm" data-cf-fb-join>');
                $join.append($('<option>').val('and').text(t('formulaAll', 'All of these')));
                $join.append($('<option>').val('or').text(t('formulaAny', 'Any of these')));
                $join.val(group.joinInside === 'or' ? 'or' : 'and');
                var $not = $('<label class="cf-fb__not">').append(
                    $('<input type="checkbox" data-cf-fb-group-not>').prop('checked', !!group.not),
                    $('<span>').text(t('formulaNot', 'Not'))
                );
                $bar.append($join, $not);
                if (!isRoot) {
                    $bar.append($('<button type="button" class="btn btn-default btn-sm" data-cf-fb-remove>').text(t('formulaRemove', 'Remove')));
                }
                var $items = $('<div class="cf-fb__items">');
                (group.items || []).forEach(function (item) {
                    if (item.kind === 'group') {
                        $items.append(renderGroup(item, questions, false));
                    } else {
                        $items.append(renderCond(item, questions));
                    }
                });
                var $tools = $('<div class="cf-fb__tools">');
                $tools.append($('<button type="button" class="btn btn-default btn-sm" data-cf-fb-add-cond>').text(t('formulaAddCondition', 'Add condition')));
                $tools.append($('<button type="button" class="btn btn-default btn-sm" data-cf-fb-add-group>').text(t('formulaAddGroup', 'Add group')));
                $group.append($bar, $items, $tools);
                return $group;
            };

            var writeCond = function (cond) {
                var ref = '[' + cond.variable + ']';
                var body;
                if (cond.op === 'in' || cond.op === 'not_in') {
                    body = ref + ' ' + cond.op + ' [' + (cond.values || []).map(function (value) {
                        return literalText(value, '=');
                    }).join(', ') + ']';
                } else if (cond.valueKind === 'question') {
                    body = ref + ' ' + cond.op + ' [' + cond.values[0] + ']';
                } else {
                    body = ref + ' ' + cond.op + ' ' + literalText(cond.values[0] || '', cond.op);
                }
                return cond.not ? ('not (' + body + ')') : body;
            };
            var writeGroup = function (group, isRoot) {
                var parts = [];
                (group.items || []).forEach(function (item) {
                    if (item.kind === 'group') {
                        var inner = writeGroup(item, false);
                        if (inner) {
                            parts.push(inner);
                        }
                        return;
                    }
                    if (!item.variable) {
                        return;
                    }
                    parts.push(writeCond(item));
                });
                if (!parts.length) {
                    return '';
                }
                var joiner = group.joinInside === 'or' ? ' or ' : ' and ';
                var body = parts.join(joiner);
                if (!isRoot && parts.length > 1) {
                    body = '(' + body + ')';
                }
                if (group.not) {
                    body = 'not (' + body + ')';
                }
                return body;
            };
            var readCond = function ($row) {
                var op = String($row.find('[data-cf-fb-op]').val() || '=');
                var kind = (op === 'in' || op === 'not_in') ? 'literal' : String($row.find('[data-cf-fb-kind]').val() || 'literal');
                return {
                    kind: 'cond',
                    not: $row.find('[data-cf-fb-not]').is(':checked'),
                    variable: String($row.find('[data-cf-fb-var]').val() || ''),
                    op: op,
                    valueKind: kind,
                    values: readValues($row)
                };
            };
            var readGroup = function ($group) {
                var items = [];
                $group.children('.cf-fb__items').children('[data-cf-fb-item]').each(function () {
                    var $item = $(this);
                    if ($item.is('[data-cf-fb-group]')) {
                        items.push(readGroup($item));
                    } else {
                        items.push(readCond($item));
                    }
                });
                return {
                    kind: 'group',
                    not: $group.children('.cf-fb__bar').find('[data-cf-fb-group-not]').is(':checked'),
                    joinInside: String($group.children('.cf-fb__bar').find('[data-cf-fb-join]').val() || 'and'),
                    items: items
                };
            };
            var groupError = function (group) {
                var seen = false;
                var missingValue = false;
                var walk = function (node) {
                    (node.items || []).forEach(function (item) {
                        if (item.kind === 'group') {
                            walk(item);
                            return;
                        }
                        if (!item.variable) {
                            return;
                        }
                        seen = true;
                        var values = (item.values || []).filter(function (value) { return String(value) !== ''; });
                        if ((item.op === 'in' || item.op === 'not_in') && !values.length) {
                            missingValue = true;
                        }
                        if (item.valueKind === 'question' && !values.length) {
                            missingValue = true;
                        }
                    });
                };
                walk(group);
                if (!seen) {
                    return t('formulaNeedCondition', 'Add at least one complete condition.');
                }
                if (missingValue) {
                    return t('formulaNeedValue', 'Choose at least one value.');
                }
                return '';
            };

            var $dialog = $('<div class="cf-fb" hidden>');
            var $panel = $('<div class="cf-fb__panel" role="dialog" aria-modal="true">');
            $panel.append($('<h3 class="cf-fb__title">').text(t('formulaBuildTitle', 'Build a formula')));
            $panel.append($('<p class="cf-hint">').text(t('formulaBuildHelp', 'Pick questions and answers. Use this formula to put the result in the box. You can change it here or in the box.')));
            $panel.append($('<p class="cf-fb__warn" data-cf-fb-warn hidden>'));
            $panel.append($('<div data-cf-fb-body>'));
            $panel.append($('<pre class="cf-fb__preview" data-cf-fb-preview>'));
            $panel.append($('<p class="cf-fb__error" data-cf-fb-error hidden>'));
            var $actions = $('<div class="cf-fb__actions">');
            $actions.append($('<button type="button" class="btn btn-default" data-cf-fb-cancel>').text(t('formulaCancel', 'Cancel')));
            $actions.append($('<button type="button" class="btn btn-primary" data-cf-fb-apply>').text(t('formulaUse', 'Use this formula')));
            $panel.append($actions);
            $dialog.append($('<div class="cf-fb__backdrop" data-cf-fb-close>'), $panel);
            $('body').append($dialog);

            var questionsNow = function () {
                return studioQuestions();
            };
            var paint = function (model, unsupported) {
                var questions = questionsNow();
                $dialog.find('[data-cf-fb-body]').empty().append(renderGroup(model, questions, true));
                var $warn = $dialog.find('[data-cf-fb-warn]');
                if (unsupported) {
                    $warn.text(t('formulaBuildUnsupported', 'This formula uses something the builder cannot show, so the box is left as it is until you use a formula from here.')).prop('hidden', false);
                } else {
                    $warn.prop('hidden', true).text('');
                }
                $dialog.find('[data-cf-fb-error]').prop('hidden', true).text('');
                refreshPreview();
            };
            var refreshPreview = function () {
                var $rootGroup = $dialog.find('[data-cf-fb-body] > [data-cf-fb-group]').first();
                if (!$rootGroup.length) {
                    return;
                }
                var text = writeGroup(readGroup($rootGroup), true);
                $dialog.find('[data-cf-fb-preview]').text(text);
            };
            var closeBuilder = function () {
                $dialog.prop('hidden', true);
                formulaTarget = null;
            };
            var openBuilder = function ($input) {
                formulaTarget = $input;
                var loaded;
                var unsupported = false;
                try {
                    loaded = toVisual($input.val());
                } catch (e) {
                    loaded = { model: emptyGroup(), unsupported: true };
                    unsupported = true;
                }
                if (loaded.unsupported) {
                    unsupported = true;
                }
                paint(loaded.model, unsupported);
                $dialog.prop('hidden', false);
                var title = $dialog.find('.cf-fb__title').get(0);
                if (title && title.focus) {
                    title.setAttribute('tabindex', '-1');
                    title.focus();
                }
            };

            ensureFormulaButtons = function () {
                $root.find('[data-cf-formula-text]').each(function () {
                    var $input = $(this);
                    if ($input.next('[data-cf-formula-build]').length || $input.nextAll('[data-cf-formula-build]').first().length && $input.parent().children('[data-cf-formula-build]').length) {
                        if ($input.parent().find('[data-cf-formula-build]').length) {
                            return;
                        }
                    }
                    if ($input.parent().find('[data-cf-formula-build]').length) {
                        return;
                    }
                    var $btn = $('<button type="button" class="btn btn-default btn-sm mt-2 me-2 cf-fb-open" data-cf-formula-build>')
                        .text(t('formulaBuild', 'Build visually'));
                    $input.after($btn);
                });
            };
            ensureFormulaButtons();

            $root.on('click', '[data-cf-formula-build]', function (e) {
                e.preventDefault();
                var $input = $(this).siblings('[data-cf-formula-text]').first();
                if (!$input.length) {
                    $input = $(this).parent().find('[data-cf-formula-text]').first();
                }
                if ($input.length) {
                    openBuilder($input);
                }
            });
            $dialog.on('click', '[data-cf-fb-close], [data-cf-fb-cancel]', function () {
                closeBuilder();
            });
            $dialog.on('change', '[data-cf-fb-var], [data-cf-fb-op], [data-cf-fb-kind]', function () {
                var $row = $(this).closest('[data-cf-fb-cond]');
                if ($row.length) {
                    var seed = $(this).is('[data-cf-fb-kind]') ? null : readShownValues($row);
                    var kind = $(this).is('[data-cf-fb-kind]') ? String($(this).val() || 'literal') : null;
                    renderValue($row, questionsNow(), seed, kind);
                }
                refreshPreview();
            });
            $dialog.on('change', '[data-cf-fb-pick]', function () {
                var $row = $(this).closest('[data-cf-fb-cond]');
                $row.find('[data-cf-fb-custom]').toggleClass('d-none', !!$(this).val());
                refreshPreview();
            });
            $dialog.on('input change', '[data-cf-fb-custom], [data-cf-fb-other], [data-cf-fb-checks] input, [data-cf-fb-not], [data-cf-fb-group-not], [data-cf-fb-join]', function () {
                refreshPreview();
            });
            $dialog.on('click', '[data-cf-fb-add-cond]', function () {
                var questions = questionsNow();
                $(this).closest('[data-cf-fb-group]').children('.cf-fb__items').append(renderCond(emptyCond(), questions));
                refreshPreview();
            });
            $dialog.on('click', '[data-cf-fb-add-group]', function () {
                var questions = questionsNow();
                $(this).closest('[data-cf-fb-group]').children('.cf-fb__items').append(renderGroup(emptyGroup(), questions, false));
                refreshPreview();
            });
            $dialog.on('click', '[data-cf-fb-remove]', function () {
                var $item = $(this).closest('[data-cf-fb-item]');
                var $items = $item.parent();
                $item.remove();
                if (!$items.children('[data-cf-fb-item]').length && $items.closest('[data-cf-fb-body]').length) {
                    $items.append(renderCond(emptyCond(), questionsNow()));
                }
                refreshPreview();
            });
            $dialog.on('click', '[data-cf-fb-add-value]', function () {
                var $row = $(this).closest('[data-cf-fb-cond]');
                var value = $.trim(String($row.find('[data-cf-fb-new]').val() || ''));
                if (!value) {
                    return;
                }
                var extras = [];
                try {
                    extras = JSON.parse($row.attr('data-cf-fb-extra') || '[]');
                } catch (e) {
                    extras = [];
                }
                if (extras.indexOf(value) === -1) {
                    extras.push(value);
                    $row.attr('data-cf-fb-extra', JSON.stringify(extras));
                    $row.find('[data-cf-fb-extra-list]').append(extraChip(value));
                }
                $row.find('[data-cf-fb-new]').val('');
                refreshPreview();
            });
            $dialog.on('click', '[data-cf-fb-drop-value]', function () {
                var value = String($(this).attr('data-value') || '');
                var $row = $(this).closest('[data-cf-fb-cond]');
                var extras = [];
                try {
                    extras = JSON.parse($row.attr('data-cf-fb-extra') || '[]');
                } catch (e) {
                    extras = [];
                }
                extras = extras.filter(function (item) { return item !== value; });
                $row.attr('data-cf-fb-extra', JSON.stringify(extras));
                $(this).closest('.cf-fb__chip').remove();
                refreshPreview();
            });
            $dialog.on('click', '[data-cf-fb-apply]', function () {
                if (!formulaTarget) {
                    return;
                }
                var $rootGroup = $dialog.find('[data-cf-fb-body] > [data-cf-fb-group]').first();
                var group = readGroup($rootGroup);
                var problem = groupError(group);
                var $error = $dialog.find('[data-cf-fb-error]');
                if (problem) {
                    $error.text(problem).prop('hidden', false);
                    return;
                }
                var text = writeGroup(group, true);
                if (text.length > 4000) {
                    $error.text(t('formulaTooLong', 'A formula can be at most 4,000 characters.')).prop('hidden', false);
                    return;
                }
                formulaTarget.val(text).trigger('input');
                closeBuilder();
            });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && !$dialog.prop('hidden')) {
                    closeBuilder();
                }
            });
        })();

        $root.on('change', '[data-cf-field-type]', function () {
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var type = String($(this).val() || '');
            refreshTypeUi($row);
            applyDefaultPii($row);
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
            var input = this;
            queueStudioType(function () {
                var $row = $(input).closest('.thiscovery-forms-field-row');
                var $own = ownCard($row);
                refreshTypeUi($row);
                var key = String($row.attr('data-cf-key') || '');
                var label = $.trim(String($(input).val() || '')) || ('#' + key);
                var internal = $.trim(String($own.find('[data-cf-internal-label]').val() || ''));
                retitleConditionOption(key, (internal || label) + (function () {
                    var v = $.trim(String($own.find('[data-cf-variable]').val() || ''));
                    return v ? (' [' + v + ']') : '';
                })());
                var $var = $own.find('[data-cf-variable]').first();
                if ($var.length && $var.attr('data-cf-variable-touched') !== '1') {
                    $var.val(slugVariable(label));
                }
                var $internal = $own.find('[data-cf-internal-label]').first();
                if ($internal.length && !$internal.attr('data-cf-internal-touched') && !$.trim($internal.val())) {
                    $internal.val(label.length > 255 ? label.slice(0, 255) : label);
                }
            });
        });

        $root.on('input', '[data-cf-internal-label]', function () {
            var input = this;
            $(input).attr('data-cf-internal-touched', '1');
            queueStudioType(function () {
                var $row = $(input).closest('.thiscovery-forms-field-row');
                refreshTypeUi($row);
                var key = String($row.attr('data-cf-key') || '');
                var $own = ownCard($row);
                var label = $.trim(String($(input).val() || ''))
                    || $.trim(String($own.find('[data-cf-field-label]').val() || ''))
                    || ('#' + key);
                var v = $.trim(String($own.find('[data-cf-variable]').val() || ''));
                retitleConditionOption(key, label + (v ? (' [' + v + ']') : ''));
            });
        });

        $root.on('input', '[data-cf-variable]', function () {
            var input = this;
            $(input).attr('data-cf-variable-touched', '1');
            queueStudioType(function () {
                var $row = $(input).closest('.thiscovery-forms-field-row');
                var $own = ownCard($row);
                var key = String($row.attr('data-cf-key') || '');
                var label = $.trim(String($own.find('[data-cf-internal-label]').val() || ''))
                    || $.trim(String($own.find('[data-cf-field-label]').val() || ''))
                    || ('#' + key);
                var v = $.trim(String($(input).val() || ''));
                retitleConditionOption(key, label + (v ? (' [' + v + ']') : ''));
            });
        });

        $root.on('click', '[data-cf-add-option]', function (e) {
            e.preventDefault();
            var $panel = $(this).closest('[data-cf-options-panel]');
            var $list = $panel.find('[data-cf-option-items]');
            var $first = $list.find('[data-cf-option-item]').first();
            if (!$first.length) {
                return;
            }
            var $clone = $first.clone();
            $clone.find('input[type="text"]').val('');
            $clone.find('input[type="checkbox"]').prop('checked', false);
            $clone.find('[data-cf-option-open-required-wrap]').addClass('d-none');
            $list.append($clone);
            reindexOptionItems($list);
        });

        $root.on('click', '[data-cf-remove-option]', function (e) {
            e.preventDefault();
            var $item = $(this).closest('[data-cf-option-item]');
            var $list = $item.closest('[data-cf-option-items]');
            if ($list.find('[data-cf-option-item]').length <= 1) {
                $item.find('input[type="text"], input:not([type])').val('');
                $item.find('input[type="checkbox"]').prop('checked', false);
                $item.find('[data-cf-option-open-required-wrap]').addClass('d-none');
                return;
            }
            $item.remove();
            reindexOptionItems($list);
        });

        $root.on('click', '[data-cf-add-grid-item]', function (e) {
            e.preventDefault();
            var which = String($(this).attr('data-cf-add-grid-item') || 'rows');
            var $panel = $(this).closest('[data-cf-grid-panel]');
            var $list = $panel.find('[data-cf-grid-items="' + which + '"]');
            var $first = $list.find('[data-cf-grid-item]').first();
            if (!$first.length) {
                return;
            }
            var $clone = $first.clone();
            $clone.find('input').val('');
            $list.append($clone);
            reindexGridItems($list, which);
        });

        $root.on('click', '[data-cf-remove-grid-item]', function (e) {
            e.preventDefault();
            var $item = $(this).closest('[data-cf-grid-item]');
            var $list = $item.closest('[data-cf-grid-items]');
            var which = String($list.attr('data-cf-grid-items') || 'rows');
            if ($list.find('[data-cf-grid-item]').length <= 1) {
                $item.find('input').val('');
                return;
            }
            $item.remove();
            reindexGridItems($list, which);
        });

        $root.on('change', '[data-cf-required]', function () {
            refreshTypeUi($(this).closest('.thiscovery-forms-field-row'));
        });

        $root.on('change', '[data-cf-option-open]', function () {
            var on = $(this).is(':checked');
            var $wrap = $(this).closest('[data-cf-option-item]').find('[data-cf-option-open-required-wrap]');
            $wrap.toggleClass('d-none', !on);
            if (on && !$wrap.find('[data-cf-option-open-required]').is(':checked')) {
                $wrap.find('[data-cf-option-open-required]').prop('checked', true);
            }
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
            applyDefaultPii($row);
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
            applyDefaultPii($row);
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
            var $scroll = $workspace.find('.cf-palette__scroll');
            if (!$workspace.length) {
                return;
            }
            $workspace.css('height', '');
            $scroll.css('max-height', '');
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
            // Edge sizes a flex child from its content unless the scroll box has a
            // pixel max-height, so the page scrolls and Add fields has no bar.
            var tabs = $workspace.find('.cf-palette__tabs').get(0);
            var tabsH = tabs ? tabs.offsetHeight : 0;
            $scroll.css('max-height', Math.max(120, height - tabsH) + 'px');
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

        var pendingLeave = null;
        var studioBaseline = '';
        var snapshotStudio = function () {
            var $form = $root.find('form.cf-studio__form');
            if (!$form.length) {
                return '';
            }
            try {
                syncRichEditors();
            } catch (err) {}
            return JSON.stringify($form.serializeArray().filter(function (item) {
                var name = String(item.name || '');
                return name !== 'studio_tab' && name !== 'studio_section' && name.indexOf('_csrf') !== 0;
            }));
        };
        var studioIsDirty = function () {
            if (studioSaving) {
                return false;
            }
            if (!studioWatch) {
                return studioDirty;
            }
            if (snapshotStudio() !== studioBaseline) {
                markStudioDirty();
            }
            return studioDirty;
        };
        var leaveStudio = function () {
            var pending = pendingLeave;
            pendingLeave = null;
            clearStudioDirty();
            studioBaseline = snapshotStudio();
            if (!pending) {
                return;
            }
            if (pending.type === 'back') {
                history.go(pending.steps || -2);
                return;
            }
            if (pending.url) {
                window.location.assign(pending.url);
            }
        };
        var askStayOrLeave = function () {
            if (studioLeaveOpen || !studioIsDirty()) {
                return;
            }
            studioLeaveOpen = true;
            var body = module.config.unsavedBody || 'You have changes that are not saved. Stay on this page if you want to save them yourself.';
            var note = $('<p class="cf-unsaved-note"></p>').text(body);
            require('ui.modal').confirm({
                header: module.config.unsavedHeader || 'Unsaved changes',
                body: note.prop('outerHTML'),
                confirmText: module.config.unsavedLeave || 'Leave without saving',
                cancelText: module.config.unsavedStay || 'Stay on this page'
            }).then(function (confirmed) {
                studioLeaveOpen = false;
                if (confirmed) {
                    leaveStudio();
                } else {
                    pendingLeave = null;
                }
            }).catch(function () {
                studioLeaveOpen = false;
                pendingLeave = null;
            });
        };
        var sameStudioPage = function (url) {
            try {
                var next = new URL(url, window.location.href);
                return next.origin === window.location.origin
                    && next.pathname === window.location.pathname
                    && next.search === window.location.search;
            } catch (err) {
                return false;
            }
        };
        var destinationUrl = function (link) {
            if (!link) {
                return '';
            }
            var href = link.getAttribute('href') || '';
            if (href === '' || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) {
                return '';
            }
            var target = (link.getAttribute('target') || '').toLowerCase();
            if (target === '_blank') {
                return '';
            }
            if (link.getAttribute('data-bs-toggle') || link.getAttribute('data-bs-target')) {
                return '';
            }
            if (sameStudioPage(link.href)) {
                return '';
            }
            return link.href;
        };
        var holdStudioNavigation = function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) {
                e.stopImmediatePropagation();
            }
        };
        var onStudioLeaveClick = function (e) {
            if (studioSaving) {
                return;
            }
            if (e.button && e.button !== 0) {
                return;
            }
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                return;
            }
            var node = e.target;
            if (node && node.nodeType === 3) {
                node = node.parentElement;
            }
            if (!node || !node.closest) {
                return;
            }
            var url = destinationUrl(node.closest('a[href]'));
            if (!url) {
                return;
            }
            if (studioLeaveOpen) {
                holdStudioNavigation(e);
                return;
            }
            if (!studioIsDirty()) {
                return;
            }
            pendingLeave = { type: 'href', url: url };
            holdStudioNavigation(e);
            askStayOrLeave();
        };
        var onStudioPop = function () {
            if (!studioIsDirty()) {
                return;
            }
            try {
                history.pushState({ cfStudioGuard: 1 }, '');
            } catch (err) {}
            pendingLeave = { type: 'back', steps: -2 };
            askStayOrLeave();
        };
        var onStudioPjax = function (e, xhr, settings) {
            if (studioSaving || !settings) {
                return;
            }
            var container = settings.container;
            var containerId = '';
            if (typeof container === 'string') {
                containerId = container;
            } else if (container && container.id) {
                containerId = '#' + container.id;
            }
            if (containerId && containerId.indexOf('layout-content') === -1) {
                return;
            }
            var url = settings.url || '';
            if (!url || sameStudioPage(url)) {
                return;
            }
            if (!studioIsDirty()) {
                return;
            }
            e.preventDefault();
            if (studioLeaveOpen) {
                return false;
            }
            pendingLeave = { type: 'href', url: url };
            askStayOrLeave();
            return false;
        };
        if (module._studioLeaveClick) {
            window.removeEventListener('click', module._studioLeaveClick, true);
            document.removeEventListener('click', module._studioLeaveClick, true);
        }
        if (module._studioPop) {
            window.removeEventListener('popstate', module._studioPop);
        }
        module._studioLeaveClick = onStudioLeaveClick;
        module._studioPop = onStudioPop;
        window.addEventListener('click', onStudioLeaveClick, true);
        window.addEventListener('popstate', onStudioPop);
        $(document).off('pjax:beforeSend.cfStudioLeave').on('pjax:beforeSend.cfStudioLeave', onStudioPjax);
        var noteStudioEdit = function (e) {
            if (!studioWatch || studioSaving) {
                return;
            }
            var field = e.target;
            if (!field || !field.closest || !field.closest('form.cf-studio__form')) {
                return;
            }
            if (field.isContentEditable) {
                markStudioDirty();
                return;
            }
            var name = field.getAttribute ? (field.getAttribute('name') || '') : '';
            if (!name || name === 'studio_tab' || name === 'studio_section') {
                return;
            }
            markStudioDirty();
        };
        var studioNode = $root.get(0);
        if (studioNode) {
            studioNode.addEventListener('input', noteStudioEdit, true);
            studioNode.addEventListener('change', noteStudioEdit, true);
        }
        window.setTimeout(function () {
            studioBaseline = snapshotStudio();
            studioWatch = true;
        }, 500);
    };

    // Smooth scrolling only when the person has not asked for less motion (A11Y-9).
    var cfScrollBehavior = function () {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
    };

    var initFill = function (root) {
        var $root = $(root);
        if (!$root.length) {
            return;
        }
        var fillButtonLabel = function ($btn) {
            var $label = $btn.find('[data-cf-btn-label]');
            if ($label.length) {
                return $.trim($label.text());
            }
            return $.trim($btn.is('input') ? $btn.val() : $btn.text());
        };
        var setFillButtonLabel = function ($btn, text) {
            var $label = $btn.find('[data-cf-btn-label]');
            if ($label.length) {
                $label.text(text);
                return;
            }
            if ($btn.is('input')) {
                $btn.val(text);
            } else {
                $btn.text(text);
            }
        };
        $root.find('[data-cf-consent-must-read]').each(function () {
            var box = this;
            var scrolled = box.querySelector('[data-cf-consent-scrolled]');
            var end = box.querySelector('[data-cf-consent-end]');
            if (!end || !scrolled) {
                return;
            }
            var sheet = box.querySelector('.cf-consent__sheet');
            var markRead = function () {
                scrolled.value = '1';
            };
            // Keyboard users reach the end marker by focus; mouse and touch users scroll it
            // into view (V3-28). A sheet too short to scroll counts once its end is visible.
            end.addEventListener('focus', markRead);
            if (window.IntersectionObserver) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            markRead();
                            observer.disconnect();
                        }
                    });
                }, {root: sheet && sheet.scrollHeight > sheet.clientHeight ? sheet : null, threshold: 1});
                observer.observe(end);
            }
            if (sheet) {
                sheet.addEventListener('scroll', function () {
                    if (sheet.scrollTop + sheet.clientHeight >= sheet.scrollHeight - 4) {
                        markRead();
                    }
                }, {passive: true});
            }
        });

        $root.find('[data-cf-consent-draw]').each(function () {
            var wrap = this;
            var canvas = wrap.querySelector('canvas');
            var box = wrap.closest('[data-cf-consent]');
            var target = box ? box.querySelector('[data-cf-consent-image]') : null;
            if (!canvas || !target || !canvas.getContext) {
                return;
            }
            var ctx = canvas.getContext('2d');
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#111';
            canvas.style.touchAction = 'none';
            var drawing = false;
            var drew = false;
            var point = function (event) {
                var rect = canvas.getBoundingClientRect();
                return {
                    x: (event.clientX - rect.left) * (canvas.width / rect.width),
                    y: (event.clientY - rect.top) * (canvas.height / rect.height)
                };
            };
            canvas.addEventListener('pointerdown', function (event) {
                drawing = true;
                var p = point(event);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                if (canvas.setPointerCapture) {
                    canvas.setPointerCapture(event.pointerId);
                }
                event.preventDefault();
            });
            canvas.addEventListener('pointermove', function (event) {
                if (!drawing) {
                    return;
                }
                var p = point(event);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                drew = true;
            });
            var finish = function () {
                if (!drawing) {
                    return;
                }
                drawing = false;
                if (drew) {
                    target.value = canvas.toDataURL('image/png');
                }
            };
            canvas.addEventListener('pointerup', finish);
            canvas.addEventListener('pointercancel', finish);
            var clear = wrap.querySelector('[data-cf-consent-clear]');
            if (clear) {
                clear.addEventListener('click', function () {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    drew = false;
                    target.value = '';
                });
            }
        });

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
            var payload = $form.serialize();
            $form.find('[name="roster_add"], [name="roster_parent"], [name="roster_remove"]').val('');
            autosaveXhr = $.ajax({
                url: saveUrl,
                type: 'POST',
                data: payload,
                dataType: 'json'
            }).done(function (res) {
                if (!res || !res.success || !res.resume_code) {
                    return;
                }
                applyResumeCode(res.resume_code);
                if (res.roster_changed) {
                    fillSubmitting = true;
                    autosaveQueued = false;
                    var next = new URL(window.location.href);
                    next.searchParams.delete('start');
                    next.searchParams.set('resume', res.resume_code);
                    if (res.roster_key) {
                        next.searchParams.set('roster', res.roster_key);
                    } else {
                        next.searchParams.delete('roster');
                    }
                    window.location.assign(next.toString());
                    return;
                }
                if (res.quota_closed_url) {
                    // The quota ended this response: nothing more can be filled.
                    fillSubmitting = true;
                    autosaveQueued = false;
                    window.location.assign(res.quota_closed_url);
                    return;
                }
                if (res.quota_halt === 'goto' && res.quota_page !== null && res.quota_page !== undefined && typeof showPage === 'function') {
                    var target = parseInt(res.quota_page, 10);
                    if (!isNaN(target) && target !== currentPage) {
                        pageHistory.push(target);
                        currentPage = target;
                        showPage(target);
                    }
                }
                if (res.quota_message) {
                    var $note = $form.find('[data-cf-quota-note]');
                    if (!$note.length) {
                        $note = $('<p data-cf-quota-note role="status"></p>');
                        $form.prepend($note);
                    }
                    $note.text(res.quota_message);
                }
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
        $root.on('click', '[data-cf-roster-add]', function () {
            var $button = $(this);
            var $form = $root.find('[data-cf-fill-form]');
            $form.find('[name="roster_remove"]').val('');
            $form.find('[name="roster_add"]').val(String($button.attr('data-cf-roster-variable') || ''));
            $form.find('[name="roster_parent"]').val(String($button.attr('data-cf-roster-parent') || ''));
            autosaveProgress();
        });
        $root.on('click', '[data-cf-roster-remove-ask]', function () {
            var $controls = $(this).closest('[data-cf-roster-controls]');
            $controls.find('[data-cf-roster-confirm]').removeAttr('hidden');
            $controls.find('[data-cf-roster-remove-confirm]').trigger('focus');
        });
        $root.on('click', '[data-cf-roster-remove-cancel]', function () {
            $(this).closest('[data-cf-roster-confirm]').attr('hidden', 'hidden');
        });
        $root.on('click', '[data-cf-roster-remove-confirm]', function () {
            var $form = $root.find('[data-cf-fill-form]');
            $form.find('[name="roster_add"]').val('');
            $form.find('[name="roster_parent"]').val('');
            $form.find('[name="roster_remove"]').val(String($(this).attr('data-cf-roster-key') || ''));
            autosaveProgress();
        });
        var rosterFocus = new URLSearchParams(window.location.search).get('roster');
        if (rosterFocus) {
            var $added = $root.find('.cf-form-page.is-active');
            var addedKey = String($added.attr('data-cf-instance') || '');
            if (addedKey === rosterFocus || addedKey.slice(-rosterFocus.length) === rosterFocus) {
                var $focus = $added.find('input, textarea, select').filter(':visible').first();
                if ($focus.length) {
                    $focus.trigger('focus');
                }
            }
        }
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

        var fieldControls = function ($field) {
            return $field.find('input, select, textarea').not('[type="hidden"]');
        };

        var fieldErrorId = function ($field) {
            // One id per question and repeat: repeats of a loop question no longer share it (V3-49).
            var inst = String($field.closest('[data-cf-instance]').attr('data-cf-instance') || '');
            return 'cf-field-error-' + String($field.attr('data-cf-field-id') || 'field')
                + (inst ? '-' + inst.replace(/[^A-Za-z0-9_-]/g, '_') : '');
        };

        var announceLive = function (text) {
            var $live = $root.find('[data-cf-page-live]');
            if (!$live.length) {
                $live = $('<div class="visually-hidden" data-cf-page-live aria-live="polite" aria-atomic="true"/>');
                $root.prepend($live);
            }
            $live.text('');
            window.setTimeout(function () {
                $live.text(text || '');
            }, 30);
        };

        var setFieldMessage = function ($field, msg) {
            var $err = $field.find('[data-cf-field-error]').first();
            var $controls = fieldControls($field);
            if (!msg) {
                $field.removeClass('cf-page-error');
                $err.addClass('d-none').text('');
                $field.find('[data-cf-email-error]').addClass('d-none');
                $controls.removeClass('is-invalid').attr('aria-invalid', 'false').removeAttr('aria-describedby');
                return;
            }
            if (!$err.length) {
                // Not an alert: the page summary announces once, and each control points at its
                // own message with aria-describedby, so several alerts never fire together (A11Y-4).
                $err = $('<p class="cf-question__error" data-cf-field-error/>');
                var $control = $field.find('.cf-question__control');
                ($control.length ? $control : $field).append($err);
            }
            $err.attr('id', fieldErrorId($field)).removeClass('d-none').text(msg);
            $field.find('[data-cf-email-error]').addClass('d-none');
            $field.addClass('cf-page-error');
            $controls.addClass('is-invalid').attr('aria-invalid', 'true').attr('aria-describedby', fieldErrorId($field));
        };

        var turnstileReady = !!window.cfCaptchaGateReady;

        var captchaSolved = function () {
            var widget = $root.find('altcha-widget').get(0);
            if (widget) {
                if ((widget.getAttribute('data-state') || '') === 'verified') {
                    return true;
                }
                var hidden = widget.querySelector('input[type="hidden"]');
                return !!(hidden && String(hidden.value || '') !== '');
            }
            if ($root.find('.cf-turnstile, .cf-turnstile-wrap').length) {
                return turnstileReady || String($root.find('input[name="cf-turnstile-response"]').val() || '') !== '';
            }
            return false;
        };

        var syncCaptchaSubmit = function () {
            var $btn = $root.find('[data-cf-captcha-gate]');
            if (!$btn.length) {
                return;
            }
            var ready = captchaSolved();
            $btn.prop('disabled', !ready).attr('aria-disabled', ready ? 'false' : 'true');
            $root.find('[data-cf-captcha-hint]').prop('hidden', ready);
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
            syncCaptchaSubmit();
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
            $box.removeClass('d-none').empty().append($('<p/>').text(module.config.fixPageErrors || 'Please fix the highlighted questions, then try again.'));
            // Each problem links to its question, so keyboard and screen-reader users can get
            // there directly (V3-49).
            if (Array.isArray(hasErrors) && hasErrors.length) {
                var $list = $('<ul class="cf-fill-errors__list"/>');
                hasErrors.forEach(function (item) {
                    var $control = item.$field.find('input, select, textarea, [tabindex]').not('[type="hidden"]').filter(':visible').first();
                    var target = $control.attr('id');
                    if (!target) {
                        target = 'cf-err-target-' + Math.random().toString(36).slice(2);
                        ($control.length ? $control : item.$field.attr('tabindex', '-1')).attr('id', target);
                    }
                    var label = $.trim(item.$field.find('.cf-question__label').first().text()).replace(/\s*\*$/, '');
                    var $a = $('<a/>').attr('href', '#' + target).text((label ? label + ': ' : '') + item.msg);
                    $a.on('click', function (ev) {
                        ev.preventDefault();
                        var el = document.getElementById(target);
                        if (el) {
                            el.focus();
                        }
                    });
                    $list.append($('<li/>').append($a));
                });
                $box.append($list);
            }
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
            var type = String($field.data('cf-field-type') || '');
            if (type === 'number') {
                var $num = $field.find('input[type="number"]').first();
                var raw = String($num.val() || '');
                if (raw !== '') {
                    var n = parseFloat(raw);
                    var minAttr = $num.attr('min');
                    var maxAttr = $num.attr('max');
                    if (minAttr !== undefined && minAttr !== '' && isFinite(n) && n < parseFloat(minAttr)) {
                        return (module.config.numberMin || 'Enter a number of at least {min}.').replace('{min}', minAttr);
                    }
                    if (maxAttr !== undefined && maxAttr !== '' && isFinite(n) && n > parseFloat(maxAttr)) {
                        return (module.config.numberMax || 'Enter a number of at most {max}.').replace('{max}', maxAttr);
                    }
                    if ($num.attr('min') === '0' && n < 0) {
                        return module.config.numberMin || 'Enter a number of at least 0.';
                    }
                }
            }
            // Text length and pattern, date limits and the answer check (LOG-12). The server
            // enforces the same on submit; this tells the respondent on the page.
            var $txt = $field.find('input[type="text"].cf-input, textarea.cf-input').first();
            if ((type === 'text' || type === 'textarea') && $txt.length) {
                var tv = $.trim(String($txt.val() || ''));
                if (tv !== '') {
                    var minLen = parseInt($txt.attr('minlength') || '0', 10);
                    if (minLen > 0 && tv.length < minLen) {
                        return (module.config.textMin || 'Enter at least {n} characters.').replace('{n}', String(minLen));
                    }
                    var pattern = $txt.attr('data-cf-pattern');
                    if (pattern) {
                        var re = null;
                        try {
                            re = new RegExp('^(?:' + pattern + ')$', 'u');
                        } catch (e) {
                            re = null;
                        }
                        if (re && !re.test(tv)) {
                            return $txt.attr('data-cf-pattern-message') || module.config.textPattern || 'This answer is not in the expected format.';
                        }
                    }
                }
            }
            if (type === 'date') {
                var $dt = $field.find('input[type="date"]').first();
                var dv = String($dt.val() || '');
                if (dv !== '') {
                    if ($dt.attr('min') && dv < $dt.attr('min')) {
                        return (module.config.dateMin || 'Enter a date on or after {date}.').replace('{date}', $dt.attr('min'));
                    }
                    if ($dt.attr('max') && dv > $dt.attr('max')) {
                        return (module.config.dateMax || 'Enter a date on or before {date}.').replace('{date}', $dt.attr('max'));
                    }
                }
            }
            var checkRaw = $field.attr('data-cf-check');
            if (checkRaw && isFilled($field)) {
                var engine = (typeof globalThis !== 'undefined' ? globalThis : window).thiscoveryFormula;
                var checkTree = null;
                try {
                    checkTree = JSON.parse(checkRaw);
                } catch (e) {
                    checkTree = null;
                }
                if (checkTree && engine && engine.treeTruth && !engine.treeTruth(checkTree, readAnswers())) {
                    return $field.attr('data-cf-check-message') || module.config.answerCheck || 'This answer does not fit with your other answers.';
                }
            }
            var $list = $field.find('.cf-choice-list').first();
            if ($list.length) {
                var maxSel = parseInt($list.attr('data-cf-max-select') || '0', 10);
                var checkedCount = $list.find('input[type="checkbox"]:checked').length;
                if (maxSel > 0 && checkedCount > maxSel) {
                    return (module.config.checkboxMax || 'Select at most {max} options.').replace('{max}', String(maxSel));
                }
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
                var $gridActive = $field.find('[data-cf-mobile-layout="stack"]').length && window.matchMedia('(max-width: 767.98px)').matches
                    ? $field.find('[data-cf-grid-stack]')
                    : $field.find('tbody');
                var rowKeys = {};
                var answered = {};
                $gridActive.find(type === 'grid_multi' ? 'input[type="checkbox"]' : 'input[type="radio"]').each(function () {
                    if (this.disabled) {
                        return;
                    }
                    var name = String(this.name || '');
                    var m = name.match(/\[([^\[\]]+)\](?:\[\])?$/);
                    if (!m) {
                        return;
                    }
                    rowKeys[m[1]] = true;
                    if (this.checked) {
                        answered[m[1]] = true;
                    }
                });
                var keys = Object.keys(rowKeys);
                if (!keys.length) {
                    return false;
                }
                for (var gi = 0; gi < keys.length; gi++) {
                    if (!answered[keys[gi]]) {
                        return false;
                    }
                }
                return true;
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
                return { code: String(opt.code), label: String(opt.label != null ? opt.label : opt.code), openEnd: !!opt.openEnd };
            }
            return { code: String(opt), label: String(opt) };
        };

        // Mirror of FormField::isOtherOption: "Other", "Other (please specify)" and the like,
        // not "Other people's views" (SCO-15).
        var OTHER_RE = /^other(?:\s*(?:\(\s*(?:please\s+)?(?:specify|state|describe|say|give details)[^)]*\)|[-\u2013,]\s*(?:please\s+)?(?:specify|state|describe|say|give details)\b.*))?\s*\.?$/i;
        var isOtherOption = function (opt, pairs) {
            opt = $.trim(String(opt || ''));
            if (!opt || opt.indexOf(':') !== -1) {
                return false;
            }
            if (OTHER_RE.test(opt)) {
                return true;
            }
            pairs = pairs || [];
            return pairs.some(function (p) {
                p = asChoicePair(p);
                return (p.code === opt || p.label === opt) && (OTHER_RE.test(p.code) || OTHER_RE.test(p.label));
            });
        };

        var otherSpecifyMissing = function ($field) {
            var missing = false;
            $field.find('[data-cf-other-wrap]').not('.d-none').each(function () {
                if (String($(this).attr('data-cf-other-optional') || '') === '1') {
                    return;
                }
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
                var marked = pairs.some(function (p) {
                    p = asChoicePair(p);
                    return !!p.openEnd && (p.code === key || p.label === key);
                });
                return (marked || isOtherOption(key, pairs)) && value.indexOf(otherPrefix(key)) === 0;
            });
        };

        var selectedIncludes = function (selected, opt) {
            return selected.some(function (v) {
                return valueMatchesOption(v, opt);
            });
        };

        var makeOtherSpecifyWrap = function (opt, fieldId, savedText, optional) {
            var id = 'cf-input-' + fieldId + '-other-' + String(opt).replace(/[^a-z0-9_-]/gi, '');
            var label = (module.config && module.config.specifyLabel) || 'Please specify';
            var placeholder = (module.config && module.config.specifyPlaceholder) || 'Type your answer';
            var $wrap = $('<div class="cf-other-specify d-none"/>')
                .attr('data-cf-other-wrap', true)
                .attr('data-cf-other-option', opt);
            if (optional) {
                $wrap.attr('data-cf-other-optional', '1');
            }
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
                    // Sanitised creator HTML prefixes names with cfhtml_ (SEC-19).
                    $control = $block.find('[name="' + varName + '"], [name="cfhtml_' + varName + '"]').filter('input, select, textarea').first();
                }
                if (!$control.length) {
                    $control = $block.find('input, select, textarea').not('[data-cf-html-value]').first();
                }
                if (!$control.length) {
                    return;
                }
                if ($control.is(':checkbox')) {
                    var vals = [];
                    $block.find('input[type="checkbox"][data-cf-html-var="' + varName + '"]:checked, input[type="checkbox"][name="' + varName + '"]:checked, input[type="checkbox"][name="cfhtml_' + varName + '"]:checked').each(function () {
                        vals.push(String($(this).val()));
                    });
                    $hidden.val(vals.join(', '));
                } else if ($control.is(':radio')) {
                    $hidden.val(String($block.find('input[type="radio"][data-cf-html-var="' + varName + '"]:checked, input[type="radio"][name="' + varName + '"]:checked, input[type="radio"][name="cfhtml_' + varName + '"]:checked').val() || ''));
                } else {
                    $hidden.val(String($control.val() || ''));
                }
            });
        };

        var syncRankingValue = function ($list) {
            var values = [];
            var count = $list.find('.cf-ranking-item').length;
            var tpl = String(module.config.rankHandleLabel || '{item}, position {n} of {count}. Use the up and down arrow keys to move it.');
            $list.find('.cf-ranking-item').each(function (index) {
                values.push(String($(this).attr('data-value') || ''));
                $(this).find('.cf-ranking-order').text(String(index + 1));
                var itemLabel = String($(this).attr('data-cf-rank-label') || '');
                $(this).find('[data-cf-rank-handle]').attr('aria-label', tpl.replace('{item}', itemLabel).replace('{n}', String(index + 1)).replace('{count}', String(count)));
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
                        // Each level is named (question, level n) and the prompt is translated (A11Y-8).
                        var questionLabel = $.trim($wrap.closest('[data-cf-field-id]').find('.cf-question__label').first().text()).replace(/\s*\*$/, '');
                        $sel.attr('aria-label', String(module.config.drillLevelLabel || '{question}, level {n}')
                            .replace('{question}', questionLabel).replace('{n}', String(i + 1)));
                        $sel.append($('<option value="">').text(String(module.config.pleaseSelect || 'Please select')));
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
                    $wrap.find('.cf-hotspot-region').each(function () {
                        this.setAttribute('aria-pressed', $(this).hasClass('is-selected') ? 'true' : 'false');
                    });
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

                // After a move: say where the item is now, and keep focus on the control that
                // moved it (or the handle, at either end) (A11Y-7).
                var afterMove = function ($item, focusEl) {
                    syncRankingValue($list);
                    evaluate();
                    var pos = $list.find('.cf-ranking-item').index($item) + 1;
                    var total = $list.find('.cf-ranking-item').length;
                    var msg = String(module.config.rankMovedLabel || '{item} moved to position {n} of {count}.')
                        .replace('{item}', String($item.attr('data-cf-rank-label') || ''))
                        .replace('{n}', String(pos)).replace('{count}', String(total));
                    if (typeof announceLive === 'function') {
                        announceLive(msg);
                    }
                    var $target = $(focusEl);
                    if (!$target.length || $target.prop('disabled')) {
                        $target = $item.find('[data-cf-rank-handle]');
                    }
                    $target.trigger('focus');
                };

                $list.on('click', '[data-cf-rank-up]', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $item = $(this).closest('.cf-ranking-item');
                    var $prev = $item.prev('.cf-ranking-item');
                    if ($prev.length) {
                        $item.insertBefore($prev);
                        afterMove($item, e.isTrigger ? $item.find('[data-cf-rank-handle]') : this);
                    }
                });

                $list.on('click', '[data-cf-rank-down]', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $item = $(this).closest('.cf-ranking-item');
                    var $next = $item.next('.cf-ranking-item');
                    if ($next.length) {
                        $item.insertAfter($next);
                        afterMove($item, e.isTrigger ? $item.find('[data-cf-rank-handle]') : this);
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
                    } else if (e.key === 'Enter' || e.key === ' ') {
                        // The handle is a button: Enter/Space says how to move it (A11Y-7).
                        e.preventDefault();
                        if (typeof announceLive === 'function') {
                            announceLive(String($(this).attr('aria-label') || ''));
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
                        $track.attr('aria-valuetext', String(module.config.vasNoValue || 'No answer yet'));
                        return;
                    }
                    var n = snap(parseFloat(value));
                    var pctFromTop = ((max - n) / (max - min)) * 100;
                    $marker.removeAttr('hidden').css('top', pctFromTop + '%');
                    $track.attr('aria-valuenow', String(n));
                    // Spoken as "72 out of 100", not a bare number (A11Y-8).
                    $track.attr('aria-valuetext', String(module.config.vasValueText || '{n} out of {max}')
                        .replace('{n}', String(n)).replace('{max}', String(max)));
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

        // field id => enclosing loop group ids (outermost first), from the server.
        var loopScopes = function () {
            var cfg = typeof window !== 'undefined' && window.thiscoveryFormulaConfig ? window.thiscoveryFormulaConfig : {};
            return cfg.loopScopes || {};
        };
        var variableNames = null;
        var variableNameOf = function (fieldId) {
            if (variableNames === null) {
                variableNames = {};
                $root.find('[data-cf-variable]').each(function () {
                    var name = String($(this).attr('data-cf-variable') || '');
                    var fid = String($(this).attr('data-cf-field-id') || '');
                    if (name && fid) {
                        variableNames[fid] = name;
                    }
                });
            }
            return variableNames[String(fieldId)] || '';
        };
        // The answers as one loop repeat sees them: answers in the same loop (or an
        // enclosing one) become that repeat's cell. Mirrors LoopService::scopedValues (V3-18).
        var scopedValues = function (values, fieldId, path) {
            var scopes = loopScopes();
            var target = scopes[String(fieldId)] || [];
            if (!target.length || !path) {
                return values;
            }
            var out = $.extend({}, values);
            var segments = String(path).split('/');
            Object.keys(scopes).forEach(function (fid) {
                var chain = scopes[fid] || [];
                if (chain.length > target.length) {
                    return;
                }
                for (var i = 0; i < chain.length; i++) {
                    if (String(chain[i]) !== String(target[i])) {
                        return;
                    }
                }
                if (!Object.prototype.hasOwnProperty.call(values, fid)) {
                    return;
                }
                var key = segments.slice(0, chain.length).join('/');
                var raw = values[fid];
                var cell = raw && typeof raw === 'object' && !Array.isArray(raw) && Object.prototype.hasOwnProperty.call(raw, key)
                    ? raw[key]
                    : null;
                out[fid] = cell;
                var name = variableNameOf(fid);
                if (name) {
                    out[name] = cell;
                }
            });
            return out;
        };
        var scopedFor = function ($el, values) {
            var path = String($el.closest('[data-cf-instance]').attr('data-cf-instance') || '');
            if (!path) {
                return values;
            }
            var fieldId = String($el.closest('[data-cf-field-id]').attr('data-cf-field-id') || '');
            return scopedValues(values, fieldId, path);
        };

        var readAnswers = function () {
            var values = {};
            var armCode = String($root.find('[data-cf-arm-code]').attr('data-cf-arm-code') || '');
            if (armCode) {
                values.arm = armCode;
            }
            var instanceMaps = {};
            $root.find('[data-cf-field-id][data-cf-answerable]').each(function () {
                var $field = $(this);
                var id = String($field.data('cf-field-id'));
                var type = String($field.data('cf-field-type') || '');
                // Each repeat of a loop question is read into an instance map keyed by the
                // repeat's path, instead of every repeat overwriting the last (V3-18).
                var one = {};
                (function (values) {
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
                        var $gridScope = $field.find('[data-cf-mobile-layout="stack"]').length && window.matchMedia('(max-width: 767.98px)').matches
                            ? $field.find('[data-cf-grid-stack]')
                            : $field.find('tbody');
                        $gridScope.find(type === 'grid_multi' ? 'input[type="checkbox"]' : 'input[type="radio"]').each(function () {
                            if (this.disabled) {
                                return;
                            }
                            var name = String(this.name || '');
                            var m = name.match(/\[([^\[\]]+)\](?:\[\])?$/);
                            if (!m) {
                                return;
                            }
                            var row = m[1];
                            if (type === 'grid_multi') {
                                if (!Array.isArray(grid[row])) {
                                    grid[row] = [];
                                }
                                if (this.checked) {
                                    grid[row].push(String(this.value));
                                }
                            } else if (this.checked) {
                                grid[row] = String(this.value || '');
                            } else if (grid[row] === undefined) {
                                grid[row] = '';
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
                })(one);
                var inst = String($field.closest('[data-cf-instance]').attr('data-cf-instance') || '');
                if (inst) {
                    instanceMaps[id] = instanceMaps[id] || {};
                    instanceMaps[id][inst] = one[id];
                    values[id] = instanceMaps[id];
                } else {
                    values[id] = one[id];
                }
            });
            $root.find('[data-cf-variable]').each(function () {
                var name = String($(this).attr('data-cf-variable') || '');
                var id = String($(this).attr('data-cf-field-id') || '');
                if (name && Object.prototype.hasOwnProperty.call(values, id)) {
                    values[name] = values[id];
                }
            });
            var rawUrl = String($root.find('[data-cf-url-values]').attr('data-cf-url-values') || '');
            if (rawUrl) {
                try {
                    var extra = JSON.parse(rawUrl);
                    Object.keys(extra || {}).forEach(function (key) {
                        values[key] = extra[key];
                    });
                } catch (e) {}
            }
            computeCalculated(values);
            return values;
        };

        // Calculated questions are recomputed every time answers are read, so show/hide,
        // routing and piping always see them (V3-14). Several passes let one calculated
        // question read another; the server's stored value is the authority.
        var computeCalculated = function (values) {
            var formulaApi = (typeof globalThis !== 'undefined' ? globalThis : window).thiscoveryFormula;
            var $outs = $root.find('[data-cf-calc-tree]');
            if (!formulaApi || !formulaApi.evaluateTree || !$outs.length) {
                return values;
            }
            var entries = [];
            $outs.each(function () {
                var $out = $(this);
                var tree = null;
                try {
                    tree = JSON.parse(String($out.attr('data-cf-calc-tree') || ''));
                } catch (e) {
                    tree = null;
                }
                var $question = $out.closest('[data-cf-field-id]');
                entries.push({
                    $out: $out,
                    tree: tree,
                    id: String($question.attr('data-cf-field-id') || ''),
                    name: String($question.attr('data-cf-variable') || ''),
                    result: String($out.attr('data-cf-calc-result') || 'number'),
                    places: parseInt($out.attr('data-cf-calc-places') || '0', 10) || 0,
                    hidden: $question.hasClass('cf-hidden')
                });
            });
            var formulaCfg = (typeof window !== 'undefined' && window.thiscoveryFormulaConfig) || {};
            var cyclic = (formulaCfg.calcCyclic || []).map(String);
            entries.forEach(function (entry) {
                if (cyclic.indexOf(entry.id) !== -1) {
                    entry.tree = null;
                }
            });
            entries.forEach(function (entry) {
                if (entry.id) values[entry.id] = '';
                if (entry.name) values[entry.name] = '';
            });
            for (var pass = 0; pass <= entries.length; pass++) {
                var changed = false;
                entries.forEach(function (entry) {
                    var shown = '';
                    if (entry.tree && !entry.hidden) {
                        var result = formulaApi.evaluateTree(entry.tree, values);
                        if (result && result.t && result.t !== 'empty') {
                            if (entry.result === 'boolean') {
                                shown = formulaApi.treeTruth(entry.tree, values) ? '1' : '0';
                            } else if (entry.result === 'number' && result.t === 'number') {
                                shown = formulaApi.round(result.v, entry.places) || '';
                            } else {
                                shown = String(result.v);
                            }
                        }
                    }
                    if ((entry.id && values[entry.id] !== shown) || (entry.name && values[entry.name] !== shown)) {
                        changed = true;
                    }
                    if (entry.id) values[entry.id] = shown;
                    if (entry.name) values[entry.name] = shown;
                    if (entry.$out.text() !== shown) {
                        entry.$out.text(shown);
                    }
                });
                if (!changed) {
                    break;
                }
            }
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
            var allValues = values;
            var extras = module.config.pipeVars || {};
            var loopToken = null;
            var replaceTpl = function (tpl) {
                return String(tpl || '').replace(/\{\{\s*(?:(answer|field):)?([^}]+)\s*\}\}/gi, function (full, kind, key) {
                    key = $.trim(String(key).split(':')[0]);
                    if (kind) {
                        // {{answer:q[code]}} names one repeat; {{answer:q}} in a repeat is that repeat's answer.
                        var bracket = key.match(/^([A-Za-z0-9_\-]+)\[([A-Za-z0-9_\/\-]+)\]$/);
                        if (bracket) {
                            var map = allValues[bracket[1]];
                            return map && typeof map === 'object' && !Array.isArray(map) ? formatPipeValue(map[bracket[2]]) : '';
                        }
                        if (values[key] !== undefined) {
                            return formatPipeValue(values[key]);
                        }
                        return '';
                    }
                    var loopMatch = key.match(/^loop\.(parent\.)?(label|index|count)$/i);
                    if (loopMatch) {
                        return loopToken ? loopToken((loopMatch[1] ? 'parent-' : '') + loopMatch[2].toLowerCase()) : '';
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
                var $page = $(this).closest('[data-cf-instance]');
                values = scopedFor($(this), allValues);
                loopToken = $page.length ? function (part) {
                    return String($page.attr('data-cf-instance-' + part) || '');
                } : null;
                var text = replaceTpl(tpl);
                if ($(this).find('.text-danger').length) {
                    var node = $(this).contents().filter(function () { return this.nodeType === 3; }).get(0);
                    var shown = text.charAt(text.length - 1) === ' ' ? text : text + ' ';
                    if (node) {
                        node.nodeValue = shown;
                    } else {
                        this.insertBefore(document.createTextNode(shown), this.firstChild);
                    }
                } else {
                    $(this).text(text);
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
                var otherPair = next.filter(function (o) { return o.openEnd || isOtherOption(o.code, next) || isOtherOption(o.label, next); })[0];
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
                        $el.after(makeOtherSpecifyWrap(otherOpt, fieldId, savedOther, String($q.attr('data-cf-other-optional') || '') === '1'));
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
                    if (opt.openEnd || (otherPair && opt.code === otherPair.code)) {
                        $el.append(makeOtherSpecifyWrap(opt.code, fieldId, savedOther, String($q.attr('data-cf-other-optional') || '') === '1'));
                    }
                });
            });
        };

        var parseFieldLogic = function ($field) {
            var raw = $field.attr('data-cf-logic') || '';
            var depKey = String($field.attr('data-cf-depends') || '');
            var cacheKey = raw + '\n' + depKey;
            var cached = $field.data('cfLogicCache');
            if (cached && cached.key === cacheKey) {
                return cached.logic;
            }
            var logic = null;
            if (raw) {
                try {
                    logic = JSON.parse(raw);
                } catch (e) {}
            }
            if (!logic) {
                if (!depKey) {
                    $field.data('cfLogicCache', {key: cacheKey, logic: null});
                    return null;
                }
                logic = {
                    action: 'show',
                    combinator: 'and',
                    rules: [{
                        fieldKey: depKey,
                        operator: String($field.data('cf-operator') || 'equals'),
                        value: String($field.data('cf-value') || '')
                    }]
                };
            }
            if (logic.rules && logic.rules.length) {
                logic.rules = logic.rules.filter(function (rule) {
                    if (rule && (rule.all || rule.any || rule.compound)) {
                        return true;
                    }
                    return String((rule && rule.fieldKey) || '') !== '' || !!(rule && (rule.op || rule.when));
                });
            }
            $field.data('cfLogicCache', {key: cacheKey, logic: logic});
            return logic;
        };

        var logicMet = function (logic, values) {
            var formula = (typeof globalThis !== 'undefined' ? globalThis : window).thiscoveryFormula;
            if (logic && logic.when && formula && formula.treeTruth) {
                return formula.treeTruth(logic.when, values);
            }
            return true;
        };

        var ruleMatches = function (rule, values) {
            if (!rule) {
                return false;
            }
            if (rule.compound) {
                try {
                    rule = typeof rule.compound === 'string' ? JSON.parse(rule.compound) : rule.compound;
                } catch (e) {
                    return false;
                }
            }
            if (rule && Array.isArray(rule.all)) {
                return false;
            }
            if (rule && Array.isArray(rule.any)) {
                return false;
            }
            var formula = (typeof globalThis !== 'undefined' ? globalThis : window).thiscoveryFormula;
            if (rule.when && formula && formula.treeTruth) {
                return formula.treeTruth(rule.when, values);
            }
            if (rule.fieldKey || rule.operator) {
                return false;
            }
            return formula && formula.ruleTruth ? formula.ruleTruth(rule, values) : false;
        };

        var isLogicVisible = function (logic, values) {
            if (!logic || ((!logic.rules || !logic.rules.length) && !logic.when)) {
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

        var pageHistory = [0];
        var currentPage = 0;
        var languageTouched = false;
        var languageWatch = false;
        var noteLanguageProgress = function () {};
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

        // A rule is set when it carries a formula tree (`when`). Stored rules no longer have `rules`.
        // Without the formula engine nothing can be evaluated, and logicMet() would say "met":
        // a routing rule then never fires, rather than firing for everyone (LOG-1). The server
        // still applies the real route on submit.
        var hasCondition = function (logic) {
            var formula = (typeof globalThis !== 'undefined' ? globalThis : window).thiscoveryFormula;
            return !!(logic && logic.when && typeof logic.when === 'object' && formula && formula.treeTruth);
        };

        // A rule on a question the respondent can't see doesn't route them anywhere (LOG-8).
        // Fields with no element on the page (a page break) count as shown.
        var ruleFieldShown = function (fieldId) {
            var $el = $root.find('[data-cf-field-id="' + String(fieldId) + '"]').first();
            if (!$el.length) {
                return true;
            }
            return $el.closest('.cf-hidden').length === 0 && !$el.is('[data-cf-respondent-hidden]');
        };

        var pageShouldSkip = function (idx, values) {
            values = values || readAnswers();
            var page = pagesConfig[idx] || {};
            if (hasCondition(page.skipLogic) && logicMet(page.skipLogic, values)) {
                return true;
            }
            var fieldLogic = page.fieldLogic || [];
            for (var i = 0; i < fieldLogic.length; i++) {
                var logic = fieldLogic[i].logic || {};
                if (logic.action === 'skip_page' && hasCondition(logic) && ruleFieldShown(fieldLogic[i].fieldId) && logicMet(logic, values)) {
                    return true;
                }
            }
            return !pageHasVisibleContent(idx);
        };

        var fieldHasAnswer = function (values, fieldId) {
            var value = values[String(fieldId)];
            var filled = function (v) {
                return v !== undefined && v !== null && v !== '' && !(Array.isArray(v) && v.length === 0);
            };
            var $active = $root.find('[data-cf-page].is-active[data-cf-instance]');
            if (value && typeof value === 'object' && !Array.isArray(value) && $active.length) {
                // A loop question: the repeat on the current page decides.
                return filled(value[String($active.attr('data-cf-instance'))]);
            }
            return filled(value);
        };

        var navFromAction = function (row, values) {
            if (!row) {
                return null;
            }
            // A condition must hold (LOG-11). One that can't be evaluated (engine missing, or
            // stored as invalid) doesn't route, as on the server.
            if (row.when !== null && row.when !== undefined) {
                var engine = (typeof globalThis !== 'undefined' ? globalThis : window).thiscoveryFormula;
                if (typeof row.when !== 'object' || !engine || !engine.treeTruth || !engine.treeTruth(row.when, values || readAnswers())) {
                    return null;
                }
            }
            if (row.fn === 'goto_end') {
                return { index: null, explicit: true, end: true };
            }
            if (row.fn === 'goto_page') {
                var key = String(row.pageKey || '');
                if (key && pageKeyIndex[key] !== undefined) {
                    return { index: parseInt(pageKeyIndex[key], 10), explicit: true, end: false };
                }
                if (key) {
                    return { index: null, explicit: true, end: true };
                }
            }
            return null;
        };

        var actionNavigation = function (fromIndex) {
            var values = readAnswers();
            var page = pagesConfig[fromIndex] || {};
            var chosen = null;
            (page.actionGotos || []).forEach(function (row) {
                if (fieldHasAnswer(values, row.fieldId)) {
                    var nav = navFromAction(row, values);
                    if (nav) {
                        chosen = nav;
                    }
                }
            });
            if (chosen) {
                return chosen;
            }
            (page.breakActions || []).forEach(function (row) {
                var nav = navFromAction(row, values);
                if (nav) {
                    chosen = nav;
                }
            });
            return chosen;
        };

        var resolveNavigation = function (fromIndex) {
            var actionNav = actionNavigation(fromIndex);
            if (actionNav) {
                return actionNav;
            }
            var values = readAnswers();
            var page = pagesConfig[fromIndex] || {};
            var fieldLogic = page.fieldLogic || [];
            for (var i = 0; i < fieldLogic.length; i++) {
                var logic = fieldLogic[i].logic || {};
                if (!hasCondition(logic) || !ruleFieldShown(fieldLogic[i].fieldId) || !logicMet(logic, values)) {
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
                    if (gotoKey) {
                        return { index: null, explicit: true, end: true };
                    }
                }
            }
            var branches = page.branches || [];
            for (var b = 0; b < branches.length; b++) {
                if (ruleMatches(branches[b], values)) {
                    var gotoPage = String(branches[b].gotoPageKey || '');
                    if (gotoPage && pageKeyIndex[gotoPage] !== undefined) {
                        return { index: parseInt(pageKeyIndex[gotoPage], 10), explicit: true, end: false };
                    }
                    if (gotoPage) {
                        return { index: null, explicit: true, end: true };
                    }
                }
            }
            // No branch matched: the page's "otherwise" target (LOG-10), as on the server.
            var otherwise = String(page.otherwise || '');
            if (otherwise) {
                return pageKeyIndex[otherwise] !== undefined
                    ? { index: parseInt(pageKeyIndex[otherwise], 10), explicit: true, end: false }
                    : { index: null, explicit: true, end: true };
            }
            var next = fromIndex + 1;
            return {
                index: next < pagesConfig.length ? next : null,
                explicit: false,
                end: false
            };
        };

        var forwardPastCycle = function (fromIndex) {
            console.warn('Thiscovery Forms page cycle detected.');
            var probe = fromIndex + 1;
            while (probe < pagesConfig.length && pageShouldSkip(probe)) {
                probe++;
            }
            return probe < pagesConfig.length ? probe : null;
        };

        var nextVisiblePage = function (fromIndex) {
            var nav = resolveNavigation(fromIndex);
            if (nav.explicit && !nav.end && nav.index !== null && nav.index <= fromIndex) {
                var forward = forwardPastCycle(fromIndex);
                return { index: forward, explicit: false, end: forward === null };
            }
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
            var limit = pagesConfig.length + 1;
            while (idx !== null && idx !== undefined && guard++ < limit) {
                if (seen[idx]) {
                    if (window.console) window.console.warn('Thiscovery Forms page cycle detected.');
                    break;
                }
                seen[idx] = true;
                var nav = nextVisiblePage(idx);
                if (nav.end || nav.index === null || nav.index === undefined) {
                    break;
                }
                if (seen[nav.index]) {
                    if (window.console) window.console.warn('Thiscovery Forms page cycle detected.');
                    break;
                }
                idx = nav.index;
            }
            return seen;
        };

        var clearFieldAnswers = function ($field) {
            if (!$field || !$field.length || $field.is('[data-cf-respondent-hidden]')) {
                return;
            }
            $field.find('input[type="radio"], input[type="checkbox"]').each(function () {
                this.checked = false;
                this.removeAttribute('checked');
            });
            $field.find('.cf-rating-option.is-selected').removeClass('is-selected');
            $field.find('select').each(function () {
                this.selectedIndex = 0;
                if ($(this).find('option[value=""]').length) {
                    $(this).val('');
                }
                $(this).find('option').prop('selected', false);
                $(this).find('option[value=""]').prop('selected', true);
            });
            $field.find('textarea, input[type="text"], input[type="number"], input[type="email"], input[type="url"], input[type="tel"], input[type="date"], input[type="time"], input[type="datetime-local"], input[type="search"]')
                .not('[data-cf-other-text]')
                .val('');
            $field.find('[data-cf-other-text]').val('');
            $field.find('[data-cf-html-value], [data-cf-drilldown-value], [data-cf-hotspot-value], [data-cf-rank-value], [data-cf-grid-value], [data-cf-map-value]').val('');
            setFieldMessage($field, '');
        };

        var applyUnreachablePages = function () {
            if (!multiPage) {
                return;
            }
            var reachable = reachablePageSet();
            $root.find('[data-cf-page]').each(function () {
                var $page = $(this);
                var p = parseInt($page.attr('data-cf-page'), 10);
                if (p === currentPage) {
                    $page.removeClass('cf-page-offpath');
                    return;
                }
                var onPath = !isNaN(p) && !!reachable[p];
                var wasOff = $page.hasClass('cf-page-offpath');
                $page.toggleClass('cf-page-offpath', !onPath);
                if (!onPath) {
                    if (!wasOff) {
                        $page.find('[data-cf-conditional][data-cf-answerable]').each(function () {
                            clearFieldAnswers($(this));
                        });
                    }
                    $page.find('input, select, textarea').each(function () {
                        if (!this.disabled) {
                            this.setAttribute('data-cf-offpath-disabled', '1');
                        }
                        this.disabled = true;
                    });
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
            // Typing re-evaluates the page that is already on screen. Skip the
            // page-wide enable/disable walk so the caret is not disturbed.
            var skipPageDom = !!(options.navOnly && !pageChanged && $targetPage.hasClass('is-active'));
            if (!skipPageDom) {
                $targetPage.removeClass('cf-page-offpath');
                $targetPage.find('[data-cf-offpath-disabled]').each(function () {
                    var $input = $(this);
                    $input.removeAttr('data-cf-offpath-disabled');
                    if ($input.is('[data-cf-other-text]')) {
                        return;
                    }
                    if ($input.closest('.cf-grid-fade, [data-cf-grid-stack]').length) {
                        return;
                    }
                    this.disabled = false;
                });

                if (pageChanged || !$targetPage.hasClass('is-active')) {
                    $root.find('[data-cf-page]').removeClass('is-active');
                    $targetPage.addClass('is-active');
                }
            }

            var nav = nextVisiblePage(idx);
            var isLast = nav.index === null || nav.end === true;
            var screenedOut = !!(nav.end && nav.explicit);

            var canGoBack = pageHistory.length > 1;
            $root.find('[data-cf-page-back]').toggle(canGoBack);
            $root.find('[data-cf-page-next]').toggle(multiPage && !isLast);
            $root.find('[data-cf-submit-wrap]').toggle(!multiPage || isLast);
            var $submitBtn = $root.find('[data-cf-submit-wrap] button[type="submit"], [data-cf-submit-wrap] input[type="submit"]');
            var defaultSubmit = module.config.submitLabel || 'Submit';
            var finishLabel = module.config.finishLabel || 'Finish';
            $submitBtn.each(function () {
                var $btn = $(this);
                if (!$btn.data('cfDefaultSubmit')) {
                    $btn.data('cfDefaultSubmit', fillButtonLabel($btn) || defaultSubmit);
                }
                if (this.tagName === 'INPUT') {
                    this.value = screenedOut ? finishLabel : $btn.data('cfDefaultSubmit');
                } else {
                    setFillButtonLabel($btn, screenedOut ? finishLabel : $btn.data('cfDefaultSubmit'));
                }
            });
            $root.find('[data-cf-current-page]').val(String(idx));
            $root.find('[data-cf-current-page-key]').val(String((pagesConfig[idx] || {}).pageKey || ''));
            var instanceKey = String($targetPage.attr('data-cf-instance') || '');
            $root.find('[data-cf-current-instance]').val(instanceKey);
            if (pageChanged && idx > 0) {
                scheduleAutosave();
            }

            var label = module.config.pageLabel || 'Page {current} of {total}';
            var pageText = label.replace('{current}', String(idx + 1)).replace('{total}', String(pagesConfig.length));
            var loopHeading = $.trim($targetPage.find('.cf-loop-instance').first().text() || '');
            $root.find('[data-cf-page-indicator]').text(pageText);
            if (pageChanged && options.scroll !== false) {
                announceLive(loopHeading || pageText);
                if (!$targetPage.attr('tabindex')) {
                    $targetPage.attr('tabindex', '-1');
                }
                $targetPage.trigger('focus');
            }

            var shouldScroll = options.scroll === true || (options.scroll !== false && pageChanged);
            if (shouldScroll) {
                try {
                    var top = $root.offset() ? $root.offset().top - 20 : 0;
                    window.scrollTo({ top: Math.max(0, top), behavior: cfScrollBehavior() });
                } catch (e) {}
            }
            window.requestAnimationFrame(syncGridOverflow);
            noteLanguageProgress();
        };

        var writeActionVars = function (vars) {
            $root.find('[data-cf-action-vars]').val(JSON.stringify(vars || {}));
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
                runActions('field', fieldId).done(function (res) {
                    if (res && res.vars && typeof res.vars === 'object') {
                        writeActionVars(res.vars);
                    }
                });
            }, 400);
        });

        var validateCurrentPage = function () {
            syncHtmlValues();
            var $page = $root.find('[data-cf-page="' + currentPage + '"]');
            if (!$page.length) {
                $page = $root.find('[data-cf-fill-form]');
            }
            var hasErrors = false;
            var problems = [];
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
                    problems.push({ $field: $field, msg: msg });
                }
            });
            showPageErrorSummary(hasErrors ? problems : false);
            resetFillButtons();
            if (hasErrors) {
                var $first = $page.find('.cf-page-error').first();
                if ($first.length && $first[0].scrollIntoView) {
                    $first[0].scrollIntoView({ behavior: cfScrollBehavior(), block: 'center' });
                }
                var $focus = $first.find('input, select, textarea').not('[type="hidden"]').filter(':visible').first();
                if ($focus.length) {
                    $focus.trigger('focus');
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
            var setEnabledState = function ($nodes, disabled) {
                $nodes.each(function () {
                    if (this.disabled !== disabled) {
                        this.disabled = disabled;
                    }
                });
            };
            var applyVisibility = function ($field) {
                var logic = parseFieldLogic($field);
                var visible = isLogicVisible(logic, scopedFor($field, values));
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
                if (!visible && !wasHidden && $field.is('[data-cf-answerable]')) {
                    clearFieldAnswers($field);
                }
                if (visible && wasHidden && $field.closest('[data-cf-page].is-active, .cf-form-page.is-active').length) {
                    newlyShown.push($field.get(0));
                }
                if ($field.is('[data-cf-respondent-hidden]')) {
                    setEnabledState($field.find('input, select, textarea'), false);
                    return;
                }
                if ($field.hasClass('cf-question-group')) {
                    if (!visible) {
                        setEnabledState($field.find('input, select, textarea'), true);
                    }
                    return;
                }
                setEnabledState($field.find('input, select, textarea'), !visible);
            };
            var guard = 0;
            var changed = true;
            var $logicGroups = $root.find('.cf-question-group[data-cf-conditional]');
            var $logicFields = $root.find('[data-cf-conditional]').not('.cf-question-group');
            while (changed && guard < 24) {
                guard++;
                changed = false;
                values = readAnswers();
                var noteChange = function () {
                    var $field = $(this);
                    var before = $field.hasClass('cf-hidden');
                    applyVisibility($field);
                    if ($field.hasClass('cf-hidden') !== before) {
                        changed = true;
                    }
                };
                $logicGroups.each(noteChange);
                $logicFields.each(noteChange);
            }
            newlyShown = newlyShown.filter(function (el) {
                return el && !el.classList.contains('cf-hidden');
            });
            values = readAnswers();
            applyPiping(values);
            applyCarryForward(values);
            syncOtherSpecify();
            if (multiPage) {
                applyUnreachablePages();
            }
            updateProgress();
            if (multiPage) {
                showPage(currentPage, {scroll: false, navOnly: true});
            }
            if (syncGridMobileInputs) {
                syncGridMobileInputs();
            }
            syncGridOverflow();
            if (newlyShown.length) {
                var shownLabels = newlyShown.map(function (el) {
                    return $.trim($(el).find('.cf-question__label, legend, .cf-label').first().text());
                }).filter(Boolean);
                if (shownLabels.length) {
                    announceLive(shownLabels.join('. '));
                }
                window.requestAnimationFrame(function () {
                    try {
                        newlyShown[0].scrollIntoView({ behavior: cfScrollBehavior(), block: 'nearest' });
                    } catch (e) {}
                });
            }
        };

        // Show/hide, piping and calculated values used to run inside the keystroke,
        // so the typed character waited until that walk finished. Choices update at
        // once. Text waits until the character has painted, and bursts collapse
        // into one pass.
        var evaluateTimer = null;
        var flushEvaluate = function () {
            if (!evaluateTimer) {
                return;
            }
            clearTimeout(evaluateTimer);
            evaluateTimer = null;
            evaluate();
        };
        var scheduleEvaluate = function (immediate) {
            if (evaluateTimer) {
                clearTimeout(evaluateTimer);
                evaluateTimer = null;
            }
            if (immediate) {
                evaluate();
                return;
            }
            evaluateTimer = setTimeout(function () {
                evaluateTimer = null;
                evaluate();
            }, 60);
        };

        $('#search-menu').attr('aria-label', module.config.searchLabel || 'Search');
        $('button.btn-icon-only, a.btn-icon-only').each(function () {
            var $el = $(this);
            if ($.trim($el.attr('aria-label') || '') || $.trim($el.attr('title') || '') || $.trim($el.text())) {
                return;
            }
            $el.attr('aria-label', module.config.menuLabel || 'Menu');
        });

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

        var enforceCheckboxRules = function ($list, $changed) {
            if (!$list || !$list.length) {
                return;
            }
            var exclusiveList = parseExclusive($list);
            var max = parseInt($list.attr('data-cf-max-select') || '0', 10);
            if ($changed && $changed.length && $changed.prop('checked')) {
                var val = String($changed.val());
                if (exclusiveList.indexOf(val) !== -1) {
                    $list.find('input[type="checkbox"]').not($changed).prop('checked', false);
                } else if (exclusiveList.length) {
                    $list.find('input[type="checkbox"]').filter(function () {
                        return exclusiveList.indexOf(String(this.value)) !== -1;
                    }).prop('checked', false);
                }
                if (max > 0) {
                    var checked = $list.find('input[type="checkbox"]:checked');
                    if (checked.length > max) {
                        $changed.prop('checked', false);
                    }
                }
            }
            applyCheckboxLimits($list);
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
            enforceCheckboxRules($(this).closest('.cf-choice-list'), $(this));
        });

        if (multiPage) {
            $root.on('click', '[data-cf-page-next]', function (e) {
                e.preventDefault();
                e.stopPropagation();
                flushEvaluate();
                if (!validateCurrentPage()) {
                    return false;
                }
                var goNext = function () {
                    var nav = actionNavigation(currentPage) || nextVisiblePage(currentPage);
                    if (nav.end || nav.index === null) {
                        showPage(currentPage, {scroll: false});
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
                        if (res && res.vars && typeof res.vars === 'object') {
                            writeActionVars(res.vars);
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

        $root.find('altcha-widget').each(function () {
            this.addEventListener('statechange', syncCaptchaSubmit);
            this.addEventListener('verified', syncCaptchaSubmit);
        });
        document.addEventListener('cf-captcha-state', function (ev) {
            turnstileReady = !!(ev.detail && ev.detail.ready);
            syncCaptchaSubmit();
        });
        syncCaptchaSubmit();

        $root.on('input', 'textarea, input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):not([type="button"]):not([type="submit"]):not([type="hidden"]):not([type="reset"])', function () {
            scheduleEvaluate(false);
            scheduleAutosave();
            if (languageWatch) {
                languageTouched = true;
                noteLanguageProgress();
            }
        });
        $root.on('change', 'input, select, textarea', function () {
            scheduleEvaluate(true);
            scheduleAutosave();
            if (languageWatch) {
                languageTouched = true;
                noteLanguageProgress();
            }
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
                var original = fillButtonLabel($btn);
                setFillButtonLabel($btn, ok
                    ? (module.config.copiedLabel || 'Copied')
                    : (module.config.copyFailedLabel || 'Could not copy'));
                setTimeout(function () { setFillButtonLabel($btn, original); }, 1600);
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
            if ($root.find('[data-cf-captcha-gate]').length && !captchaSolved()) {
                e.preventDefault();
                e.stopImmediatePropagation();
                syncCaptchaSubmit();
                var captchaMsg = $.trim($root.find('[data-cf-captcha-hint]').text());
                if (captchaMsg) {
                    announceLive(captchaMsg);
                }
                resetFillButtons();
                setTimeout(resetFillButtons, 30);
                return false;
            }
            flushEvaluate();
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
            try {
                if (fillHoldKey) {
                    sessionStorage.removeItem(fillHoldKey());
                }
            } catch (err) {}
        });

        $root.find('[data-cf-grid-scroll]').on('scroll.cfGridOverflow', syncGridOverflow);
        $(window).off('resize.cfGridOverflow').on('resize.cfGridOverflow', function () {
            window.requestAnimationFrame(syncGridOverflow);
            syncGridMobileInputs();
        });

        // A stacked grid has a desktop and a mobile copy. Only the copy in use carries the input
        // names, so the two never form one radio group or post twice; ticks are mirrored into
        // the other copy, and a layout switch (zoom to 200%, rotation) re-syncs (V3-49).
        var syncGridMobileInputs = function () {
            var mobile = window.matchMedia('(max-width: 767.98px)').matches;
            $root.find('[data-cf-mobile-layout="stack"]').each(function () {
                var $wrap = $(this);
                var $active = mobile ? $wrap.find('[data-cf-grid-stack]') : $wrap.find('.cf-grid-fade');
                var $inactive = mobile ? $wrap.find('.cf-grid-fade') : $wrap.find('[data-cf-grid-stack]');
                $inactive.find('input, select, textarea').each(function () {
                    if (this.name) {
                        this.setAttribute('data-cf-grid-name', this.name);
                        this.removeAttribute('name');
                    }
                    if (!this.disabled) {
                        this.disabled = true;
                    }
                });
                $active.find('input, select, textarea').each(function () {
                    var stored = this.getAttribute('data-cf-grid-name');
                    if (stored && !this.name) {
                        this.name = stored;
                    }
                    if (this.disabled) {
                        this.disabled = false;
                    }
                });
            });
        };
        $root.on('change', '[data-cf-mobile-layout="stack"] input', function () {
            var self = this;
            var name = this.name || this.getAttribute('data-cf-grid-name');
            var $wrap = $(this).closest('[data-cf-mobile-layout="stack"]');
            $wrap.find('input').each(function () {
                if (this === self || (this.name || this.getAttribute('data-cf-grid-name')) !== name) {
                    return;
                }
                if (this.type === 'radio') {
                    // A radio change is always a pick: the matching value is picked, the rest are not.
                    this.checked = this.value === self.value;
                } else if (this.value === self.value) {
                    this.checked = self.checked;
                }
            });
        });
        var gridQuery = window.matchMedia('(max-width: 767.98px)');
        if (gridQuery.addEventListener) {
            gridQuery.addEventListener('change', syncGridMobileInputs);
        } else if (gridQuery.addListener) {
            gridQuery.addListener(syncGridMobileInputs);
        }
        syncGridMobileInputs();

        // A language link reloads the form. Keep the answers and the current page in
        // this tab so the reload opens where the person was, instead of at the start.
        var fillHoldKey = function () {
            var id = String($root.attr('data-cf-form-id') || '');
            return id ? ('cf-fill-hold:' + id) : '';
        };
        var fillHoldSkip = {
            submit_token: true,
            roster_add: true,
            roster_parent: true,
            roster_remove: true,
            current_page: true,
            current_page_key: true,
            current_instance_key: true
        };
        var lastFocusField = '';
        $root.on('focusin', 'input, textarea, select', function () {
            var $q = $(this).closest('[data-cf-field-id]');
            if ($q.length) {
                lastFocusField = String($q.attr('data-cf-field-id') || '');
            }
        });
        var questionInView = function () {
            var bestId = '';
            var best = Infinity;
            var $scope = multiPage ? $root.find('.cf-form-page.is-active') : $root;
            $scope.find('[data-cf-field-id]').each(function () {
                if (!$(this).is(':visible')) {
                    return;
                }
                var rect = this.getBoundingClientRect();
                if (rect.bottom < 0 || rect.top > window.innerHeight) {
                    return;
                }
                var score = rect.top < 0 ? Math.abs(rect.top) + 10000 : rect.top;
                if (score < best) {
                    best = score;
                    bestId = String($(this).attr('data-cf-field-id') || '');
                }
            });
            return bestId || lastFocusField;
        };
        var rememberFill = function () {
            var key = fillHoldKey();
            var $form = $root.find('[data-cf-fill-form]');
            if (!key || !$form.length || !window.sessionStorage) {
                return;
            }
            var fields = [];
            $form.find('input, textarea, select').each(function () {
                if (!this.name || this.type === 'file' || fillHoldSkip[this.name] || this.name.indexOf('_csrf') === 0) {
                    return;
                }
                if (this.type === 'checkbox' || this.type === 'radio') {
                    fields.push({ n: this.name, t: this.type, v: this.value, c: !!this.checked });
                } else {
                    fields.push({ n: this.name, t: 'text', v: this.value });
                }
            });
            var ranks = [];
            $form.find('[data-cf-ranking]').each(function () {
                var order = [];
                $(this).find('[data-cf-ranking-list]').children('[data-value]').each(function () {
                    order.push(String($(this).attr('data-value') || ''));
                });
                var name = String($(this).find('[data-cf-ranking-value]').attr('name') || '');
                if (name) {
                    ranks.push({ n: name, order: order });
                }
            });
            var page = multiPage && typeof currentPage === 'number' ? currentPage : 0;
            try {
                sessionStorage.setItem(key, JSON.stringify({
                    pending: true,
                    page: page,
                    pageKey: multiPage ? String((pagesConfig[page] || {}).pageKey || '') : '',
                    instance: String($form.find('[data-cf-current-instance]').val() || ''),
                    history: (pageHistory || []).slice(),
                    focusField: questionInView(),
                    fields: fields,
                    ranks: ranks
                }));
            } catch (err) {}
        };
        var restoreFillHold = function () {
            var key = fillHoldKey();
            if (!key || !window.sessionStorage) {
                return false;
            }
            var params = new URLSearchParams(window.location.search);
            // start=new means a fresh visit. A language link keeps that parameter, so a
            // reload that also carries the current page is not a fresh start.
            if (params.get('start') === 'new' && !params.has('cfpage')) {
                try { sessionStorage.removeItem(key); } catch (err) {}
                return false;
            }
            var raw = '';
            try { raw = sessionStorage.getItem(key) || ''; } catch (err) { return false; }
            if (!raw) {
                return false;
            }
            var hold;
            try { hold = JSON.parse(raw); } catch (err) { return false; }
            if (!hold || !hold.pending) {
                return false;
            }
            try {
                sessionStorage.setItem(key, JSON.stringify($.extend({}, hold, { pending: false })));
            } catch (err) {}
            var $form = $root.find('[data-cf-fill-form]');
            if (!$form.length) {
                return false;
            }
            (hold.fields || []).forEach(function (field) {
                if (!field || !field.n || fillHoldSkip[field.n]) {
                    return;
                }
                var $els = $form.find('input, textarea, select').filter(function () {
                    return this.name === field.n;
                });
                if (!$els.length) {
                    return;
                }
                if (field.t === 'checkbox' || field.t === 'radio') {
                    $els.each(function () {
                        if (this.value !== String(field.v)) {
                            return;
                        }
                        this.checked = !!field.c;
                        if (field.c) {
                            this.setAttribute('checked', 'checked');
                            $(this).closest('.cf-rating-option').addClass('is-selected');
                        } else {
                            this.removeAttribute('checked');
                            $(this).closest('.cf-rating-option').removeClass('is-selected');
                        }
                    });
                } else {
                    $els.val(field.v == null ? '' : field.v);
                    $els.filter('select').each(function () {
                        var chosen = String(field.v == null ? '' : field.v);
                        Array.prototype.forEach.call(this.options, function (opt) {
                            if (opt.value === chosen) {
                                opt.setAttribute('selected', 'selected');
                            } else {
                                opt.removeAttribute('selected');
                            }
                        });
                    });
                }
            });
            (hold.ranks || []).forEach(function (rank) {
                var $hidden = $form.find('[data-cf-ranking-value]').filter(function () {
                    return this.name === rank.n;
                }).first();
                var $list = $hidden.closest('[data-cf-ranking]').find('[data-cf-ranking-list]');
                if (!$list.length) {
                    return;
                }
                (rank.order || []).forEach(function (value) {
                    var $item = $list.children('[data-value]').filter(function () {
                        return String($(this).attr('data-value') || '') === String(value);
                    }).first();
                    if ($item.length) {
                        $list.append($item);
                    }
                });
                $hidden.val(JSON.stringify(rank.order || []));
            });
            $root.find('[data-cf-consent-image]').each(function () {
                var src = String(this.value || '');
                if (src.indexOf('data:image') !== 0) {
                    return;
                }
                var canvas = $(this).closest('[data-cf-consent]').find('canvas').get(0);
                if (!canvas || !canvas.getContext) {
                    return;
                }
                var img = new Image();
                img.onload = function () {
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                };
                img.src = src;
            });
            syncGridMobileInputs();
            $form.find('[data-cf-vas-input]').trigger('blur');
            var focusId = String(hold.focusField || '');
            if (multiPage && typeof showPage === 'function') {
                var page = null;
                var pageKey = String(hold.pageKey || '');
                var instance = String(hold.instance || '');
                if (pageKey) {
                    $root.find('[data-cf-page]').each(function () {
                        if (page !== null) {
                            return;
                        }
                        if (String($(this).attr('data-cf-page-key') || '') !== pageKey) {
                            return;
                        }
                        if (String($(this).attr('data-cf-instance') || '') !== instance) {
                            return;
                        }
                        page = parseInt($(this).attr('data-cf-page'), 10);
                    });
                }
                if (page === null || isNaN(page)) {
                    page = parseInt(hold.page, 10);
                }
                if (isNaN(page) || page < 0 || page >= pagesConfig.length) {
                    page = parseInt(module.config.startPage, 10);
                }
                if (isNaN(page) || page < 0 || page >= pagesConfig.length) {
                    page = 0;
                }
                var history = (hold.history || []).filter(function (idx) {
                    return idx >= 0 && idx < pagesConfig.length;
                });
                if (!history.length || history[history.length - 1] !== page) {
                    history.push(page);
                }
                pageHistory = history;
                showPage(page, {scroll: !focusId});
            }
            if (focusId) {
                var $focus = $root.find('[data-cf-field-id]').filter(function () {
                    return String($(this).attr('data-cf-field-id') || '') === focusId;
                }).first();
                if ($focus.length) {
                    window.requestAnimationFrame(function () {
                        try {
                            $focus.get(0).scrollIntoView({block: 'nearest'});
                        } catch (err) {}
                    });
                }
            }
            return true;
        };
        noteLanguageProgress = function () {
            var $switch = $root.find('.cf-lang-switch');
            if (!$switch.length || $switch.attr('data-cf-lang-change') !== 'lock') {
                return;
            }
            var started = $switch.attr('data-cf-lang-started') === '1'
                || languageTouched
                || (multiPage && currentPage > 0);
            if (!started) {
                return;
            }
            $switch.attr('data-cf-lang-started', '1');
            $switch.find('a.cf-lang-switch__item').not('.is-active').each(function () {
                this.removeAttribute('href');
                this.setAttribute('aria-disabled', 'true');
                this.setAttribute('tabindex', '-1');
                $(this).addClass('is-disabled');
            });
        };
        $root.on('click', 'a.cf-lang-switch__item', function (e) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.which === 2) {
                return;
            }
            var mode = String($root.find('.cf-lang-switch').attr('data-cf-lang-change') || 'keep');
            if (mode === 'lock') {
                noteLanguageProgress();
                if ($root.find('.cf-lang-switch').attr('data-cf-lang-started') === '1') {
                    e.preventDefault();
                    return;
                }
            }
            if (mode !== 'keep') {
                try { sessionStorage.removeItem(fillHoldKey()); } catch (err) {}
                if (!this.href) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                try {
                    var fresh = new URL(this.href, window.location.href);
                    fresh.searchParams.delete('cfpage');
                    fresh.searchParams.delete('cfpagekey');
                    fresh.searchParams.delete('cfinstance');
                    fresh.searchParams.delete('resume');
                    fresh.searchParams.delete('continue');
                    if (mode === 'restart') {
                        fresh.searchParams.set('start', 'new');
                    }
                    window.location.assign(fresh.toString());
                } catch (err) {
                    window.location.assign(this.href);
                }
                return;
            }
            rememberFill();
            if (!this.href) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            try {
                var url = new URL(this.href, window.location.href);
                var page = multiPage && typeof currentPage === 'number' ? currentPage : 0;
                var pageKey = multiPage ? String((pagesConfig[page] || {}).pageKey || '') : '';
                var instance = String($root.find('[data-cf-current-instance]').val() || '');
                url.searchParams.set('cfpage', String(page));
                if (pageKey) {
                    url.searchParams.set('cfpagekey', pageKey);
                } else {
                    url.searchParams.delete('cfpagekey');
                }
                if (instance) {
                    url.searchParams.set('cfinstance', instance);
                } else {
                    url.searchParams.delete('cfinstance');
                }
                window.location.assign(url.toString());
            } catch (err) {
                window.location.assign(this.href);
            }
        });
        noteLanguageProgress();
        languageWatch = true;
        var restoredHold = restoreFillHold();
        try {
            var cleanHoldUrl = new URL(window.location.href);
            if (cleanHoldUrl.searchParams.has('cfpage') || cleanHoldUrl.searchParams.has('cfpagekey') || cleanHoldUrl.searchParams.has('cfinstance')) {
                cleanHoldUrl.searchParams.delete('cfpage');
                cleanHoldUrl.searchParams.delete('cfpagekey');
                cleanHoldUrl.searchParams.delete('cfinstance');
                window.history.replaceState(window.history.state, '', cleanHoldUrl.toString());
            }
        } catch (err) {}

        evaluate();
        if (!restoredHold) {
            clearUnsavedChoicePrefill();
        }
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

    var initGuides = function () {
        // Bind once on document. Binding again on the studio root caused the ? toggle
        // to fire twice (open then immediately close).
        $(document).off('click.cfGuide', '[data-cf-guide-toggle]').on('click.cfGuide', '[data-cf-guide-toggle]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $btn = $(this);
            var expanded = $btn.attr('aria-expanded') === 'true';
            var next = !expanded;
            var panelId = $btn.attr('aria-controls');
            var $panel = panelId ? $('#' + panelId) : $();
            $btn.attr('aria-expanded', next ? 'true' : 'false');
            $btn.toggleClass('is-open', next);
            if ($panel.length) {
                if (next) {
                    $panel.removeAttr('hidden');
                } else {
                    $panel.attr('hidden', 'hidden');
                }
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
        initGuides(document);
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
    module.initGuides = initGuides;
    module.initStyleColors = initStyleColors;
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
        initGuides: initGuides,
        initStyleColors: initStyleColors,
        initPollEmbed: initPollEmbed
    });
});
