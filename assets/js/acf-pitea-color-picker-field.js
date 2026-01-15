(function($) {
    if (typeof acf === 'undefined') return;

    var PiteaColorPickerField = acf.Field.extend({
        type: 'pitea_color_picker',
        
        // Store reference to the modal when moved to body
        _$modal: null,
        
        events: {
            'click .acf-pitea-color-picker-trigger': 'onOpenModal',
            'click .acf-pitea-color-picker-clear-trigger': 'onClear',
        },

        $input: function() {
            return this.$('.acf-pitea-color-picker-value');
        },

        $preview: function() {
            return this.$('.acf-pitea-color-picker-selected');
        },

        $customInput: function() {
            return this._$modal ? this._$modal.find('.acf-pitea-color-picker-custom-input') : $();
        },

        $hexInput: function() {
            return this._$modal ? this._$modal.find('.acf-pitea-color-picker-hex-input') : $();
        },

        $modal: function() {
            // Return the modal reference (which may be in body)
            return this._$modal || this.$('.acf-pitea-color-picker-modal');
        },

        $trigger: function() {
            return this.$('.acf-pitea-color-picker-trigger');
        },

        initialize: function() {
            var self = this;
            
            // Update preview on load if value exists
            var value = this.$input().val();
            if (value) {
                // Get the name from the preview if it exists
                var $nameEl = this.$('.acf-pitea-color-picker-name');
                var name = $nameEl.length ? $nameEl.text() : null;
                this.updatePreview(value, name);
            }
            
            // Get the modal element and move it to body for proper positioning
            var $modal = this.$('.acf-pitea-color-picker-modal');
            if ($modal.length) {
                // Store field reference on modal for event handling
                $modal.data('acf-field', this);
                
                // Move modal to body to escape any container constraints
                $modal.appendTo('body');
                this._$modal = $modal;
                
                // Initialize all groups as collapsed
                $modal.find('.acf-pitea-color-picker-group').each(function() {
                    var $group = $(this);
                    var $header = $group.find('.acf-pitea-color-picker-group-header');
                    var isExpanded = $header.attr('aria-expanded') === 'true';
                    
                    if (!isExpanded) {
                        $group.addClass('is-collapsed');
                    }
                });
                
                // Bind modal events since it's now outside the field element
                this.bindModalEvents($modal);
                
                // Update modal preview with current value
                if (value) {
                    var $nameEl = this.$('.acf-pitea-color-picker-name');
                    var name = $nameEl.length ? $nameEl.text() : null;
                    this.updateModalPreview(value, name);
                }
            }
        },
        
        bindModalEvents: function($modal) {
            var self = this;
            
            // Close modal events
            $modal.on('click', '.acf-pitea-color-picker-modal-close', function(e) {
                self.onCloseModal(e);
            });
            
            $modal.on('click', '.acf-pitea-color-picker-modal-overlay', function(e) {
                self.onCloseModal(e);
            });
            
            // Select color
            $modal.on('click', '.acf-pitea-color-picker-option', function(e) {
                self.onSelectColor(e);
            });
            
            // Custom color toggle
            $modal.on('click', '.acf-pitea-color-picker-custom-toggle', function(e) {
                self.onToggleCustom(e);
            });
            
            // Apply custom color
            $modal.on('click', '.acf-pitea-color-picker-custom-apply', function(e) {
                self.onApplyCustom(e);
            });
            
            // Clear button in modal
            $modal.on('click', '.acf-pitea-color-picker-clear', function(e) {
                self.onClear(e);
            });
            
            // Group toggle
            $modal.on('click', '.acf-pitea-color-picker-group-header', function(e) {
                self.onToggleGroup(e);
            });
            
            // Hex input keypress
            $modal.on('keypress', '.acf-pitea-color-picker-hex-input', function(e) {
                self.onHexKeypress(e);
            });
            
            // Escape key to close
            $(document).on('keydown.acf-pitea-color-picker-' + this.cid, function(e) {
                if (e.keyCode === 27 && self.$modal().is(':visible')) {
                    self.onCloseModal(e);
                }
            });
        },

        onOpenModal: function(e) {
            e.preventDefault();
            // Update modal preview with current value before opening
            var value = this.$input().val();
            if (value) {
                var $nameEl = this.$('.acf-pitea-color-picker-name');
                var name = $nameEl.length ? $nameEl.text() : null;
                this.updateModalPreview(value, name);
            }
            this.$modal().fadeIn(200);
            $('body').addClass('acf-pitea-color-picker-modal-open');
        },

        onCloseModal: function(e) {
            e.preventDefault();
            this.$modal().fadeOut(200);
            $('body').removeClass('acf-pitea-color-picker-modal-open');
        },

        onSelectColor: function(e) {
            e.preventDefault();
            var $option = $(e.currentTarget);
            var color = $option.data('color');
            var name = $option.data('name');
            
            // Update selection state in modal (which is in body)
            this.$modal().find('.acf-pitea-color-picker-option').removeClass('is-selected');
            $option.addClass('is-selected');
            
            // Update value
            this.$input().val(color).trigger('change');
            
            // Update previews
            this.updatePreview(color, name);
            this.updateModalPreview(color, name);
            
            // Hide custom input if open
            this.$customInput().slideUp();
            
            // Don't close modal - let user continue selecting or click Done
        },

        onToggleCustom: function(e) {
            e.preventDefault();
            this.$customInput().slideToggle();
        },

        onApplyCustom: function(e) {
            e.preventDefault();
            var hex = this.$hexInput().val().trim();
            
            // Validate hex color
            if (!this.isValidHex(hex)) {
                alert('Please enter a valid hex color (e.g., #000000)');
                return;
            }
            
            // Normalize hex (ensure uppercase and # prefix)
            hex = this.normalizeHex(hex);
            
            // Update value
            this.$input().val(hex).trigger('change');
            
            // Update previews
            this.updatePreview(hex, 'Custom');
            this.updateModalPreview(hex, 'Custom');
            
            // Remove selection from palette options (in modal which is in body)
            this.$modal().find('.acf-pitea-color-picker-option').removeClass('is-selected');
            
            // Hide custom input
            this.$customInput().slideUp();
            
            // Don't close modal - let user continue or click Done
        },

        onClear: function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.$input().val('').trigger('change');
            this.updatePreview('');
            this.updateModalPreview('');
            
            // Clear selection state in modal (which is in body)
            this.$modal().find('.acf-pitea-color-picker-option').removeClass('is-selected');
            
            // Update clear button visibility
            var $clearBtn = this.$('.acf-pitea-color-picker-clear-trigger');
            if ($clearBtn.length) {
                $clearBtn.fadeOut(200, function() {
                    $(this).remove();
                });
            }
        },

        onHexKeypress: function(e) {
            // Allow Enter key to apply custom color
            if (e.which === 13) {
                e.preventDefault();
                this.onApplyCustom(e);
            }
        },

        onToggleGroup: function(e) {
            e.preventDefault();
            var $header = $(e.currentTarget);
            var $group = $header.closest('.acf-pitea-color-picker-group');
            var $content = $group.find('.acf-pitea-color-picker-group-content');
            var isExpanded = $header.attr('aria-expanded') === 'true';
            
            // Toggle the collapsed class instead of using slideToggle
            // This ensures CSS controls the display properly
            $group.toggleClass('is-collapsed', isExpanded);
            $header.attr('aria-expanded', !isExpanded);
            
            // Use slideToggle for smooth animation, but let CSS handle the final state
            if (isExpanded) {
                $content.slideUp(200, function() {
                    // Ensure it stays hidden via CSS class
                    $group.addClass('is-collapsed');
                });
            } else {
                $group.removeClass('is-collapsed');
                $content.slideDown(200);
            }
        },

        updatePreview: function(color, name) {
            var $preview = this.$preview();
            var $trigger = this.$trigger();
            
            if (!color) {
                $preview.html(
                    '<span class="acf-pitea-color-picker-no-selection">' + 
                    acf.__('Select Color') + 
                    '</span>'
                );
                // Update trigger title
                $trigger.attr('title', acf.__('Select Color'));
                // Remove clear button if exists
                this.$('.acf-pitea-color-picker-clear-trigger').fadeOut(200, function() {
                    $(this).remove();
                });
                return;
            }
            
            // Use provided name or try to find it from localized flat colors
            if (!name && typeof piteaColorPicker !== 'undefined' && piteaColorPicker.flatColors) {
                for (var colorName in piteaColorPicker.flatColors) {
                    if (piteaColorPicker.flatColors[colorName].toLowerCase() === color.toLowerCase()) {
                        name = colorName;
                        break;
                    }
                }
            }
            
            name = name || color;
            
            $preview.html(
                '<div class="acf-pitea-color-picker-preview">' +
                    '<span class="acf-pitea-color-picker-swatch" style="background-color: ' + acf.escAttr(color) + ';"></span>' +
                    '<span class="acf-pitea-color-picker-name">' + acf.escHtml(name) + '</span>' +
                '</div>'
            );
            
            // Update trigger title with full info for tooltip
            $trigger.attr('title', name + ' (' + color + ')');
            
            // Show clear button if allow_null and doesn't exist
            if (this.$el.data('allow_null')) {
                if (!this.$('.acf-pitea-color-picker-clear-trigger').length) {
                    var $clearBtn = $('<button type="button" class="acf-pitea-color-picker-clear-trigger button button-link" style="margin-top: 8px;">' + 
                        acf.__('Clear') + '</button>');
                    $trigger.after($clearBtn);
                    $clearBtn.hide().fadeIn(200);
                }
            }
        },

        updateModalPreview: function(color, name) {
            var $modalPreview = this.$modal().find('.acf-pitea-color-picker-modal-preview');
            
            if (!$modalPreview.length) {
                return;
            }
            
            if (!color) {
                $modalPreview.html(
                    '<div class="acf-pitea-color-picker-modal-preview-empty">' +
                        acf.__('No color selected') +
                    '</div>'
                );
                return;
            }
            
            // Use provided name or try to find it from localized flat colors
            if (!name && typeof piteaColorPicker !== 'undefined' && piteaColorPicker.flatColors) {
                for (var colorName in piteaColorPicker.flatColors) {
                    if (piteaColorPicker.flatColors[colorName].toLowerCase() === color.toLowerCase()) {
                        name = colorName;
                        break;
                    }
                }
            }
            
            name = name || color;
            
            $modalPreview.html(
                '<div class="acf-pitea-color-picker-modal-preview-content">' +
                    '<span class="acf-pitea-color-picker-modal-preview-swatch" style="background-color: ' + acf.escAttr(color) + ';"></span>' +
                    '<div class="acf-pitea-color-picker-modal-preview-info">' +
                        '<span class="acf-pitea-color-picker-modal-preview-name">' + acf.escHtml(name) + '</span>' +
                        '<span class="acf-pitea-color-picker-modal-preview-hex">' + acf.escHtml(color) + '</span>' +
                    '</div>' +
                '</div>'
            );
        },

        isValidHex: function(hex) {
            return /^#?[0-9A-Fa-f]{6}$/.test(hex);
        },

        normalizeHex: function(hex) {
            // Remove # if present
            hex = hex.replace('#', '');
            // Ensure uppercase
            hex = hex.toUpperCase();
            // Add # prefix
            return '#' + hex;
        }
    });

    acf.registerFieldType(PiteaColorPickerField);

})(jQuery);
