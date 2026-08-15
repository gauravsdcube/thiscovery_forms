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

        var refreshTypeUi = function ($row) {
            var type = $row.find('[data-cf-field-type]').val();
            var needsOptions = OPTION_TYPES.indexOf(type) !== -1;
            var isRating = type === (module.config.ratingType || 'rating');
            var isRanking = type === 'ranking';
            var isChoice = ['dropdown', 'radio', 'checkbox'].indexOf(type) !== -1;
            var isPage = type === (module.config.pageBreakType || 'page_break');
            var isRich = type === (module.config.richTextType || 'rich_text');
            var isHtml = type === (module.config.htmlType || 'html');
            var hideRequired = isPage || isRich;
            var hideHelp = isPage;

            $row.attr('data-cf-type', type);
            $row.find('[data-cf-options-panel]').toggleClass('d-none', !needsOptions);
            $row.find('[data-cf-rating-panel]').toggleClass('d-none', !isRating);
            $row.find('[data-cf-page-panel]').toggleClass('d-none', !isPage);
            $row.find('[data-cf-rich-panel]').toggleClass('d-none', !isRich);
            $row.find('[data-cf-html-panel]').toggleClass('d-none', !isHtml);
            $row.find('[data-cf-grid-panel]').toggleClass('d-none', type !== 'grid_single' && type !== 'grid_multi');
            $row.find('[data-cf-items-panel]').toggleClass('d-none', type !== 'best_worst' && type !== 'maxdiff');
            $row.find('[data-cf-maxdiff-panel], [data-cf-maxdiff-note]').toggleClass('d-none', type !== 'maxdiff');
            $row.find('[data-cf-drilldown-panel]').toggleClass('d-none', type !== 'drilldown');
            $row.find('[data-cf-image-panel]').toggleClass('d-none', type !== 'image_area');
            $row.find('[data-cf-carry-wrap]').toggleClass('d-none', ['dropdown', 'radio', 'checkbox'].indexOf(type) === -1);
            $row.find('[data-cf-justify-wrap]').toggleClass('d-none', ['dropdown', 'radio', 'checkbox', 'rating', 'ranking'].indexOf(type) === -1);
            $row.find('[data-cf-ranking-note]').toggleClass('d-none', !isRanking);
            $row.find('[data-cf-choice-note]').toggleClass('d-none', !isChoice);
            $row.find('[data-cf-max-select-wrap]').toggleClass('d-none', type !== 'checkbox');
            $row.find('[data-cf-options-hint-ranking]').toggleClass('d-none', !isRanking);
            $row.find('[data-cf-options-hint-choice]').toggleClass('d-none', isRanking);
            $row.find('[data-cf-required-wrap]').toggleClass('d-none', hideRequired);
            $row.find('[data-cf-help-wrap]').toggleClass('d-none', hideHelp);
            $row.find('[data-cf-logic-goto-wrap]').toggleClass('d-none', $row.find('[data-cf-logic-action]').val() !== 'goto_page');

            if (isPage && !$.trim($row.find('[data-cf-page-key]').val())) {
                $row.find('[data-cf-page-key]').val('p' + Math.random().toString(36).slice(2, 8));
            }

            var typeLabels = module.config.types || {};
            $row.find('[data-cf-type-label]').text(typeLabels[type] || type);

            var label = $.trim($row.find('[data-cf-field-label]').val());
            if (!label && (isPage || isRich || isHtml)) {
                label = typeLabels[type] || type;
            }
            $row.find('[data-cf-title]').text(label || module.text('untitled'));

            var required = $row.find('[data-cf-required]').is(':checked') && !hideRequired;
            var $req = $row.find('.cf-field-card__req');
            if (required) {
                if (!$req.length) {
                    $row.find('.cf-field-card__title').append(
                        $('<span class="cf-field-card__req"/>').text(module.text('required') || 'Required')
                    );
                }
            } else {
                $req.remove();
            }

            if (isRich) {
                setTimeout(function () {
                    initRichEditors($row);
                }, 30);
            }
        };

        var refreshConditionOptions = function () {
            var options = ['<option value="">' + module.text('none') + '</option>'];
            $root.find('.thiscovery-forms-field-row').each(function () {
                var $row = $(this);
                var key = $row.data('cf-key');
                var type = $row.find('[data-cf-field-type]').val();
                if (type === 'page_break' || type === 'rich_text') {
                    return;
                }
                var label = $.trim($row.find('[data-cf-field-label]').val()) || ('#' + key);
                options.push('<option value="' + key + '">' + $('<div>').text(label).html() + '</option>');
            });
            var html = options.join('');
            $root.find('[data-cf-condition-field], [data-cf-branch-field]').each(function () {
                var $select = $(this);
                var current = $select.val();
                var selfKey = String($select.closest('.thiscovery-forms-field-row').data('cf-key'));
                $select.html(html);
                $select.find('option[value="' + selfKey + '"]').remove();
                if (current && current !== selfKey) {
                    $select.val(current);
                }
            });
        };

        var reindexBranches = function ($row) {
            $row.find('[data-cf-branch-row]').each(function (i) {
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
            $row.find('[data-cf-logic-rule-row]').each(function (i) {
                $(this).find('select, input').each(function () {
                    var name = $(this).attr('name');
                    if (!name) {
                        return;
                    }
                    $(this).attr('name', name.replace(/\[logic_rules\]\[\d+\]/, '[logic_rules][' + i + ']'));
                });
            });
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

        var initRichEditors = function ($scope) {
            var $targets = ($scope && $scope.length) ? $scope.find('[data-ui-widget], .ProsemirrorEditor') : $();
            if (!$targets.length && $scope && $scope.is('[data-cf-rich-editor], .ProsemirrorEditor')) {
                $targets = $scope;
            }
            try {
                var additions = require('ui.additions');
                if ($scope && $scope.length) {
                    additions.applyTo($scope);
                }
            } catch (e) {
                // Rich text assets may not be loaded yet on first paint.
            }
        };

        var syncRichEditors = function () {
            $root.find('.ProsemirrorEditor, [data-ui-widget="ui.richtext.prosemirror.RichTextEditor"]').trigger('focusout');
        };

        var addField = function (type, $beforeEl) {
            type = type || 'text';
            var maxAnswerable = parseInt(module.config.maxAnswerable, 10) || 0;
            var answerableTypes = ['page_break', 'rich_text'];
            var isAnswerable = answerableTypes.indexOf(type) === -1;
            if (maxAnswerable > 0 && isAnswerable) {
                var count = 0;
                $root.find('.thiscovery-forms-field-row').each(function () {
                    var t = $(this).find('[data-cf-field-type]').val();
                    if (answerableTypes.indexOf(t) === -1) {
                        count++;
                    }
                });
                if (count >= maxAnswerable) {
                    if (window.humhub && humhub.modules && humhub.modules.ui && humhub.modules.ui.status) {
                        // fall through
                    }
                    window.alert(module.config.pollLimit || 'A quick poll can have only one question.');
                    return null;
                }
            }

            var template = $root.find('#cf-field-template').html();
            var html = template.replace(/__INDEX__/g, String(fieldIndex++));
            var $row = $(html);
            var typeLabels = module.config.types || {};
            var $list = $root.find('[data-cf-fields]');

            if ($beforeEl && $beforeEl.length) {
                $row.insertBefore($beforeEl);
            } else {
                $list.append($row);
            }

            $row.find('[data-cf-field-type]').val(type || 'text');
            if (!$.trim($row.find('[data-cf-field-label]').val())) {
                $row.find('[data-cf-field-label]').val(typeLabels[type] || '');
            }
            refreshTypeUi($row);
            refreshIndexes();
            refreshConditionOptions();
            expandCard($row);
            bindHotspotBuilder($row);
            renderHotspotBuilder($row);
            setTimeout(function () {
                initRichEditors($row);
                $row.find('[data-cf-field-label]').trigger('focus');
            }, 50);
            return $row;
        };

        // Tabs: Form builder | Settings | CSS | Share
        $root.on('click', '[data-cf-tab]', function (e) {
            e.preventDefault();
            var tab = $(this).data('cf-tab');
            $root.find('[data-cf-tab]').removeClass('is-active').attr('aria-selected', 'false');
            $(this).addClass('is-active').attr('aria-selected', 'true');
            $root.find('[data-cf-panel]').removeClass('is-active');
            $root.find('[data-cf-panel="' + tab + '"]').addClass('is-active');
            $root.find('.cf-studio__footer').toggle(tab === 'builder' || tab === 'settings' || tab === 'css' || tab === 'share');
            if (tab === 'settings' || tab === 'builder' || tab === 'translations') {
                setTimeout(function () {
                    initRichEditors($root.find('[data-cf-panel="' + tab + '"]'));
                }, 30);
            }
        });

        try {
            var params = new URLSearchParams(window.location.search);
            var openTab = params.get('tab');
            if (openTab) {
                $root.find('[data-cf-tab="' + openTab + '"]').trigger('click');
            }
        } catch (e) {}

        $root.on('click', '[data-cf-copy-url]', function (e) {
            e.preventDefault();
            var $input = $root.find('[data-cf-share-url]');
            var url = $input.val() || '';
            var done = function () {
                var $fb = $root.find('[data-cf-copy-feedback]');
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

        $root.on('submit', 'form.cf-studio__form', function () {
            refreshIndexes();
            syncRichEditors();
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
            var badge = item.global ? ' · global' : '';
            var del = item.canManage
                ? '<button type="button" class="btn btn-sm btn-light" data-cf-library-delete="' + item.id + '"><i class="fa fa-trash"></i></button>'
                : '';
            return '<div class="cf-library-item">' +
                '<button type="button" class="cf-library-item__add" data-cf-library-insert="' + item.id + '">' +
                $('<div>').text(item.title).html() +
                '<span class="text-muted"> · ' + item.type + ' · ' + item.fieldCount + badge + '</span>' +
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

        var collectFieldPost = function ($row) {
            var data = {};
            $row.find('input, select, textarea').each(function () {
                var name = $(this).attr('name');
                if (!name) {
                    return;
                }
                var match = name.match(/fields\[[^\]]+\]\[(.+)\]/);
                if (!match) {
                    return;
                }
                var key = match[1];
                if ($(this).is(':checkbox')) {
                    if ($(this).is(':checked')) {
                        data[key] = $(this).val() || '1';
                    }
                    return;
                }
                data[key] = $(this).val();
            });
            return data;
        };

        var csrfData = function () {
            var d = {};
            $root.find('form.cf-studio__form').find('input[type="hidden"]').each(function () {
                var name = $(this).attr('name');
                if (!name || name.indexOf('fields[') === 0) {
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
            saveLibrary(title, 'question', { q: collectFieldPost($row) });
        });

        $root.on('click', '[data-cf-library-save-block]', function (e) {
            e.preventDefault();
            var title = window.prompt(module.config.libraryTitlePrompt || 'Name');
            if (!title) {
                return;
            }
            var fields = {};
            $root.find('.thiscovery-forms-field-row').each(function (i) {
                fields['b' + i] = collectFieldPost($(this));
            });
            saveLibrary(title, 'block', fields);
        });

        $root.on('click', '[data-cf-library-insert]', function (e) {
            e.preventDefault();
            var id = $(this).data('cf-library-insert');
            $.getJSON(module.config.libraryInsertUrl, { itemId: id }).done(function (data) {
                if (!data || !data.success || !data.html) {
                    window.alert(module.config.libraryInsertError || 'Error');
                    return;
                }
                var $html = $(data.html);
                $root.find('[data-cf-fields]').append($html);
                $html.each(function () {
                    refreshTypeUi($(this));
                });
                refreshIndexes();
                refreshConditionOptions();
                $root.find('[data-cf-empty]').addClass('d-none');
            });
        });

        $root.on('click', '[data-cf-library-delete]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (!window.confirm(module.config.deleteConfirm || 'Delete?')) {
                return;
            }
            var id = $(this).data('cf-library-delete');
            $.post(module.config.libraryDeleteUrl, $.extend(csrfData(), { itemId: id })).done(function () {
                loadLibrary();
            });
        });

        $root.on('click', '[data-cf-palette-type]', function (e) {
            e.preventDefault();
            addField(String($(this).data('cf-palette-type')));
        });

        // Palette: HTML5 drag onto canvas
        $root.on('dragstart', '[data-cf-palette-type]', function (e) {
            var type = String($(this).data('cf-palette-type'));
            e.originalEvent.dataTransfer.setData('text/cf-field-type', type);
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
            var type = e.originalEvent.dataTransfer.getData('text/cf-field-type')
                || e.originalEvent.dataTransfer.getData('text/plain');
            if (!type || String(type).indexOf('cf-reorder:') === 0) {
                return;
            }
            var $target = $(e.target).closest('.thiscovery-forms-field-row');
            if ($target.length) {
                var rect = $target[0].getBoundingClientRect();
                if (e.originalEvent.clientY > rect.top + rect.height / 2) {
                    var $next = $target.next('.thiscovery-forms-field-row');
                    if ($next.length) {
                        addField(type, $next);
                    } else {
                        addField(type);
                    }
                } else {
                    addField(type, $target);
                }
            } else {
                addField(type);
            }
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
                $(dragging).removeClass('is-reordering');
                $list.removeClass('is-sorting');
                dragging = null;
                refreshIndexes();
                $(document).off('.cfBuilderSort');
            };

            var moveDragging = function (clientY) {
                if (!dragging) {
                    return;
                }
                var $others = $list.children('.thiscovery-forms-field-row').filter(function () {
                    return this !== dragging;
                });
                var placed = false;
                $others.each(function () {
                    var rect = this.getBoundingClientRect();
                    if (clientY < rect.top + rect.height / 2) {
                        if (dragging.nextElementSibling !== this) {
                            this.parentNode.insertBefore(dragging, this);
                        }
                        placed = true;
                        return false;
                    }
                });
                if (!placed && dragging.parentNode === $list[0]) {
                    $list[0].appendChild(dragging);
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
                    $row.insertBefore($prev);
                    refreshIndexes();
                }
            });

            $root.on('click', '[data-cf-move-down]', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $row = $(this).closest('.thiscovery-forms-field-row');
                var $next = $row.next('.thiscovery-forms-field-row');
                if ($next.length) {
                    $row.insertAfter($next);
                    refreshIndexes();
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
            $(this).closest('.thiscovery-forms-field-row').remove();
            refreshIndexes();
            refreshConditionOptions();
        });

        $root.on('click', '[data-cf-toggle-advanced]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            expandCard($row);
            $row.find('[data-cf-advanced]').toggleClass('is-open');
        });

        $root.on('click', '[data-cf-add-branch]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var $list = $row.find('[data-cf-branch-list]');
            var $proto = $list.find('[data-cf-branch-row]').first();
            if (!$proto.length) {
                return;
            }
            var $clone = $proto.clone();
            $clone.find('input').val('');
            $clone.find('select').prop('selectedIndex', 0);
            $list.append($clone);
            reindexBranches($row);
            refreshConditionOptions();
        });

        $root.on('click', '[data-cf-remove-branch]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var $list = $row.find('[data-cf-branch-list]');
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
            var $list = $row.find('[data-cf-logic-rule-list]');
            var $proto = $list.find('[data-cf-logic-rule-row]').first();
            if (!$proto.length) {
                return;
            }
            var $clone = $proto.clone();
            $clone.find('input').val('');
            $clone.find('select').prop('selectedIndex', 0);
            $list.append($clone);
            reindexLogicRules($row);
            refreshConditionOptions();
        });

        $root.on('click', '[data-cf-remove-logic-rule]', function (e) {
            e.preventDefault();
            var $row = $(this).closest('.thiscovery-forms-field-row');
            var $list = $row.find('[data-cf-logic-rule-list]');
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
            $row.find('[data-cf-logic-goto-wrap]').toggleClass('d-none', $(this).val() !== 'goto_page');
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
            refreshTypeUi($row);
            refreshConditionOptions();
        });

        $root.on('input change', '[data-cf-field-label], [data-cf-required]', function () {
            var $row = $(this).closest('.thiscovery-forms-field-row');
            refreshTypeUi($row);
            refreshConditionOptions();
        });

        $root.find('.thiscovery-forms-field-row').each(function () {
            var $row = $(this);
            refreshTypeUi($row);
            bindHotspotBuilder($row);
            renderHotspotBuilder($row);
        });
        refreshIndexes();
        refreshConditionOptions();
    };

    var initFill = function (root) {
        var $root = $(root);
        if (!$root.length) {
            return;
        }

        $root.find('[data-cf-file-guid]').each(function () {
            var $guidInput = $(this);
            var uploadId = $guidInput.attr('id').replace(/_guid$/, '');
            var $upload = $('#' + uploadId);
            if (!$upload.length) {
                return;
            }
            $upload.on('uploadEnd', function (evt, response) {
                try {
                    var files = (response && response.files) ? response.files : [];
                    if (files.length && files[0].guid) {
                        $guidInput.val(files[0].guid);
                        updateProgress();
                    }
                } catch (e) {}
            });
        });

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
                return $field.find('input[type="checkbox"]:checked').length > 0;
            }
            if ($field.find('input[type="radio"]').length) {
                return $field.find('input[type="radio"]:checked').length > 0;
            }
            var $input = $field.find('input, select, textarea').filter(':not([type="hidden"]):not([type="checkbox"]):not([type="radio"])').first();
            if ($input.length) {
                return $.trim(String($input.val() || '')) !== '';
            }
            var guid = $.trim(String($field.find('[data-cf-file-guid]').val() || ''));
            return guid !== '';
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

        var updateProgress = function () {
            var $visible = $root.find('[data-cf-conditional][data-cf-answerable]').not('.cf-hidden');
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
            var replaceTpl = function (tpl) {
                return String(tpl || '').replace(/\{\{\s*(answer|field):([^}]+)\s*\}\}/gi, function (_, kind, key) {
                    key = $.trim(String(key).split(':')[0]);
                    if (values[key] !== undefined) {
                        return formatPipeValue(values[key]);
                    }
                    return '';
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
                var selected = selectedList(values[from]);
                var next = all;
                if (mode === 'selected') {
                    next = all.filter(function (o) { return selected.indexOf(String(o)) !== -1; });
                } else if (mode === 'unselected') {
                    next = all.filter(function (o) { return selected.indexOf(String(o)) === -1; });
                }
                var rendered = next.join('\0');
                if ($el.attr('data-cf-carry-rendered') === rendered) {
                    return;
                }
                $el.attr('data-cf-carry-rendered', rendered);
                if ($el.is('select')) {
                    var cur = String($el.val() || '');
                    $el.find('option').not('[value=""]').remove();
                    next.forEach(function (opt) {
                        $el.append($('<option/>').val(opt).text(opt));
                    });
                    if (next.indexOf(cur) !== -1) {
                        $el.val(cur);
                    }
                    return;
                }
                var name = String($el.find('input').first().attr('name') || '');
                var isCheck = $el.find('input[type="checkbox"]').length > 0;
                var current = [];
                $el.find('input:checked').each(function () {
                    current.push(String($(this).val()));
                });
                $el.find('label.cf-choice').remove();
                next.forEach(function (opt) {
                    var $lab = $('<label class="cf-choice"/>');
                    var $input = $('<input>').attr({
                        type: isCheck ? 'checkbox' : 'radio',
                        name: name,
                        value: opt
                    }).prop('checked', current.indexOf(String(opt)) !== -1);
                    $lab.append($input).append($('<span/>').text(opt));
                    $el.append($lab);
                });
            });
        };

        var parseFieldLogic = function ($field) {
            var raw = $field.attr('data-cf-logic');
            if (raw) {
                try {
                    return JSON.parse(raw);
                } catch (e) {}
            }
            var dep = $field.data('cf-depends');
            if (!dep) {
                return null;
            }
            return {
                action: 'show',
                combinator: 'and',
                rules: [{
                    fieldKey: String(dep),
                    operator: String($field.data('cf-operator') || 'equals'),
                    value: String($field.data('cf-value') || '')
                }]
            };
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

        var branchMatches = function (branch, values) {
            var fieldKey = String(branch.fieldKey || '');
            var op = String(branch.operator || 'equals');
            var expected = String(branch.value || '');
            var raw = values[fieldKey];
            if (raw === undefined) {
                return false;
            }
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
                    return expected ? list.indexOf(expected) !== -1 : list.length > 0;
                }
                if (op === 'equals') {
                    return list.indexOf(expected) !== -1;
                }
                if (op === 'not_equals') {
                    return list.indexOf(expected) === -1;
                }
                if (op === 'contains') {
                    return list.some(function (v) { return String(v).indexOf(expected) !== -1; });
                }
                return false;
            }
            var val = String(raw);
            if (op === 'equals') {
                return val === expected;
            }
            if (op === 'not_equals') {
                return val !== expected;
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
            return $page.find('[data-cf-conditional]').not('.cf-hidden').length > 0;
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

        var resolveNextPage = function (fromIndex) {
            var values = readAnswers();
            var page = pagesConfig[fromIndex] || {};
            var fieldLogic = page.fieldLogic || [];
            for (var i = 0; i < fieldLogic.length; i++) {
                var logic = fieldLogic[i].logic || {};
                if (!logic.rules || !logic.rules.length || !logicMet(logic, values)) {
                    continue;
                }
                if (logic.action === 'goto_end') {
                    return null;
                }
                if (logic.action === 'goto_page') {
                    var gotoKey = String(logic.gotoPageKey || '');
                    if (gotoKey && pageKeyIndex[gotoKey] !== undefined) {
                        return parseInt(pageKeyIndex[gotoKey], 10);
                    }
                }
            }
            var branches = page.branches || [];
            for (var b = 0; b < branches.length; b++) {
                if (branchMatches(branches[b], values)) {
                    var gotoPage = String(branches[b].gotoPageKey || '');
                    if (gotoPage && pageKeyIndex[gotoPage] !== undefined) {
                        return parseInt(pageKeyIndex[gotoPage], 10);
                    }
                }
            }
            var next = fromIndex + 1;
            return next < pagesConfig.length ? next : null;
        };

        var showPage = function (idx, options) {
            options = options || {};
            var pageChanged = idx !== currentPage;
            currentPage = idx;
            var $targetPage = $root.find('[data-cf-page="' + idx + '"]');
            if (pageChanged || !$targetPage.hasClass('is-active')) {
                $root.find('[data-cf-page]').removeClass('is-active');
                $targetPage.addClass('is-active');
            }

            var isLast = resolveNextPage(idx) === null;
            // Skip empty trailing logic: if next pages empty, treat as last when no next with content
            var probe = resolveNextPage(idx);
            while (probe !== null && pageShouldSkip(probe)) {
                var further = resolveNextPage(probe);
                if (further === null) {
                    isLast = true;
                    break;
                }
                probe = further;
                isLast = false;
            }
            if (probe === null) {
                isLast = true;
            }

            $root.find('[data-cf-page-back]').toggle(pageHistory.length > 1);
            $root.find('[data-cf-page-next]').toggle(multiPage && !isLast);
            $root.find('[data-cf-submit-wrap]').toggle(!multiPage || isLast);
            $root.find('[data-cf-current-page]').val(String(idx));

            var label = module.config.pageLabel || 'Page {current} of {total}';
            $root.find('[data-cf-page-indicator]').text(
                label.replace('{current}', String(idx + 1)).replace('{total}', String(pagesConfig.length))
            );

            // Only scroll when the visible page actually changes (Next/Back).
            // evaluate() calls showPage on every answer to refresh Next/Submit — scrolling
            // there jumps the user back to the top of the form after each question.
            var shouldScroll = options.scroll === true || (options.scroll !== false && pageChanged);
            if (shouldScroll) {
                try {
                    var top = $root.offset() ? $root.offset().top - 20 : 0;
                    window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
                } catch (e) {}
            }
        };

        var validateCurrentPage = function () {
            syncHtmlValues();
            var $page = $root.find('[data-cf-page="' + currentPage + '"]');
            var ok = true;
            $page.find('[data-cf-conditional][data-cf-answerable]').not('.cf-hidden').each(function () {
                var $field = $(this);
                var required = $field.find('.cf-question__required').length > 0
                    || $field.find('[required]').length > 0
                    || $field.find('[data-cf-html-required="1"]').length > 0;
                if (required && !isFilled($field)) {
                    ok = false;
                    $field.addClass('cf-page-error');
                } else {
                    $field.removeClass('cf-page-error');
                }
            });
            if (!ok) {
                alert(module.config.requiredPage || 'Please complete the required fields on this page.');
            }
            return ok;
        };

        var evaluate = function () {
            syncHtmlValues();
            var values = readAnswers();
            $root.find('[data-cf-conditional]').each(function () {
                var $field = $(this);
                var logic = parseFieldLogic($field);
                var visible = isLogicVisible(logic, values);
                $field.toggleClass('cf-hidden', !visible);
                $field.find('input, select, textarea').prop('disabled', !visible);
            });
            applyPiping(values);
            applyCarryForward(values);
            updateProgress();
            if (multiPage) {
                showPage(currentPage);
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
                if (!validateCurrentPage()) {
                    return;
                }
                var next = resolveNextPage(currentPage);
                while (next !== null && pageShouldSkip(next)) {
                    var after = resolveNextPage(next);
                    if (after === null) {
                        showPage(currentPage);
                        $root.find('[data-cf-page-next]').hide();
                        $root.find('[data-cf-submit-wrap]').show();
                        return;
                    }
                    next = after;
                }
                if (next === null) {
                    $root.find('[data-cf-page-next]').hide();
                    $root.find('[data-cf-submit-wrap]').show();
                    return;
                }
                pageHistory.push(next);
                showPage(next);
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

        $root.on('submit', '[data-cf-fill-form]', function () {
            syncHtmlValues();
        });

        evaluate();
    };

    var initPollEmbed = function (root) {
        var $root = $(root);
        if (!$root.length || $root.data('cf-poll-ready')) {
            return;
        }
        $root.data('cf-poll-ready', 1);

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

    var init = function () {
        $('[data-cf-poll-embed]').each(function () {
            initPollEmbed(this);
        });
    };

    module.initBuilder = initBuilder;
    module.initFill = initFill;
    module.text = function (key) {
        return module.config[key] || key;
    };

    module.export({
        init: init,
        initOnPjaxLoad: true,
        initBuilder: initBuilder,
        initFill: initFill,
        initPollEmbed: initPollEmbed
    });
});
