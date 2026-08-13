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
            $row.find('[data-cf-ranking-note]').toggleClass('d-none', !isRanking);
            $row.find('[data-cf-choice-note]').toggleClass('d-none', !isChoice);
            $row.find('[data-cf-max-select-wrap]').toggleClass('d-none', type !== 'checkbox');
            $row.find('[data-cf-options-hint-ranking]').toggleClass('d-none', !isRanking);
            $row.find('[data-cf-options-hint-choice]').toggleClass('d-none', isRanking);
            $row.find('[data-cf-required-wrap]').toggleClass('d-none', hideRequired);
            $row.find('[data-cf-help-wrap]').toggleClass('d-none', hideHelp);

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
            if (tab === 'settings' || tab === 'builder') {
                setTimeout(function () {
                    initRichEditors($root.find('[data-cf-panel="' + tab + '"]'));
                }, 30);
            }
        });

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
            refreshTypeUi($(this));
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
                var id = String($(this).data('cf-field-id'));
                if ($(this).hasClass('cf-hidden')) {
                    return;
                }
                if ($(this).find('[data-cf-html-value]').length) {
                    values[id] = String($(this).find('[data-cf-html-value]').val() || '');
                    return;
                }
                if ($(this).find('[data-cf-ranking-value]').length) {
                    try {
                        values[id] = JSON.parse($(this).find('[data-cf-ranking-value]').val() || '[]');
                    } catch (e) {
                        values[id] = [];
                    }
                    return;
                }
                if ($(this).find('input[type="checkbox"]').length) {
                    var checked = [];
                    $(this).find('input[type="checkbox"]:checked').each(function () {
                        checked.push(String($(this).val()));
                    });
                    values[id] = checked;
                    return;
                }
                if ($(this).find('input[type="radio"]').length) {
                    values[id] = String($(this).find('input[type="radio"]:checked').val() || '');
                    return;
                }
                var $input = $(this).find('input, select, textarea').filter(':not([type="hidden"]):not([type="checkbox"]):not([type="radio"])').first();
                if ($input.length) {
                    values[id] = String($input.val() || '');
                    return;
                }
                values[id] = String($(this).find('[data-cf-file-guid]').val() || '');
            });
            return values;
        };

        var branchMatches = function (branch, values) {
            var fieldKey = String(branch.fieldKey || '');
            var op = String(branch.operator || 'equals');
            var expected = String(branch.value || '');
            var raw = values[fieldKey];
            if (raw === undefined) {
                return false;
            }
            if (Array.isArray(raw)) {
                if (op === 'checked') {
                    return expected ? raw.indexOf(expected) !== -1 : raw.length > 0;
                }
                if (op === 'equals') {
                    return raw.indexOf(expected) !== -1;
                }
                if (op === 'not_equals') {
                    return raw.indexOf(expected) === -1;
                }
                if (op === 'contains') {
                    return raw.some(function (v) { return String(v).indexOf(expected) !== -1; });
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

        var resolveNextPage = function (fromIndex) {
            var values = readAnswers();
            var page = pagesConfig[fromIndex] || {};
            var branches = page.branches || [];
            for (var i = 0; i < branches.length; i++) {
                if (branchMatches(branches[i], values)) {
                    var gotoKey = String(branches[i].gotoPageKey || '');
                    if (gotoKey && pageKeyIndex[gotoKey] !== undefined) {
                        return parseInt(pageKeyIndex[gotoKey], 10);
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
            while (probe !== null && !pageHasVisibleContent(probe)) {
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
            $root.find('[data-cf-conditional]').each(function () {
                var $field = $(this);
                var dep = $field.data('cf-depends');
                var op = $field.data('cf-operator');
                var expected = String($field.data('cf-value') || '');
                if (!dep) {
                    $field.removeClass('cf-hidden');
                    return;
                }

                var $dep = $root.find('[data-cf-field-id="' + dep + '"]');
                var met = false;
                if ($dep.find('input[type="checkbox"]').length) {
                    var checked = [];
                    $dep.find('input[type="checkbox"]:checked').each(function () {
                        checked.push(String($(this).val()));
                    });
                    if (op === 'checked') {
                        met = expected ? checked.indexOf(expected) !== -1 : checked.length > 0;
                    } else if (op === 'equals') {
                        met = checked.indexOf(expected) !== -1;
                    } else if (op === 'not_equals') {
                        met = checked.indexOf(expected) === -1;
                    } else if (op === 'contains') {
                        met = checked.some(function (v) { return v.indexOf(expected) !== -1; });
                    }
                } else {
                    var val = '';
                    var $input = $dep.find('input, select, textarea').filter(':not([type="checkbox"]):not([type="radio"])').first();
                    if ($input.length) {
                        val = String($input.val() || '');
                    } else {
                        val = String($dep.find('input[type="radio"]:checked').val() || '');
                    }
                    if (op === 'equals') {
                        met = val === expected;
                    } else if (op === 'not_equals') {
                        met = val !== expected;
                    } else if (op === 'contains') {
                        met = expected !== '' && val.toLowerCase().indexOf(expected.toLowerCase()) !== -1;
                    } else if (op === 'checked') {
                        met = val !== '' && val !== '0';
                    }
                }

                $field.toggleClass('cf-hidden', !met);
                $field.find('input, select, textarea').prop('disabled', !met);
            });
            updateProgress();
            if (multiPage) {
                showPage(currentPage);
            }
        };

        initRanking();

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
                while (next !== null && !pageHasVisibleContent(next)) {
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

    module.initBuilder = initBuilder;
    module.initFill = initFill;
    module.text = function (key) {
        return module.config[key] || key;
    };

    module.export = {
        initBuilder: initBuilder,
        initFill: initFill
    };
});
