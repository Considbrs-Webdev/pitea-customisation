(function($) {
    if (typeof acf === 'undefined') return;

    var FontAwesomeIconField = acf.Field.extend({
        type: 'fontawesome_icon',
        
        events: {
            'input .acf-fontawesome-icon-search': 'onSearch',
            'focus .acf-fontawesome-icon-search': 'onFocus',
            'blur .acf-fontawesome-icon-search': 'onBlur',
            'click .acf-fontawesome-icon-clear': 'onClear',
            'click .acf-fontawesome-icon-option': 'onSelect',
            'mousedown .acf-fontawesome-icon-option': 'onOptionMousedown',
        },

        $input: function() {
            return this.$('.acf-fontawesome-icon-value');
        },

        $search: function() {
            return this.$('.acf-fontawesome-icon-search');
        },

        $dropdown: function() {
            return this.$('.acf-fontawesome-icon-dropdown');
        },

        $preview: function() {
            return this.$('.acf-fontawesome-icon-preview');
        },

        initialize: function() {
            this.searchTimeout = null;
            this.currentPage = 1;
            this.hasMore = false;
            this.isLoading = false;
            this.preventBlur = false;
            
            // Set up scroll loading
            var self = this;
            this.$dropdown().on('scroll', function() {
                self.onScroll();
            });
        },

        onSearch: function(e) {
            var self = this;
            clearTimeout(this.searchTimeout);
            
            this.searchTimeout = setTimeout(function() {
                self.currentPage = 1;
                self.doSearch();
            }, 300);
        },

        onFocus: function(e) {
            this.$dropdown().addClass('is-open');
            if (this.$dropdown().children().length === 0) {
                this.currentPage = 1;
                this.doSearch();
            }
        },

        onBlur: function(e) {
            if (this.preventBlur) {
                this.preventBlur = false;
                return;
            }
            
            var self = this;
            setTimeout(function() {
                self.$dropdown().removeClass('is-open');
            }, 200);
        },

        onOptionMousedown: function(e) {
            this.preventBlur = true;
        },

        onSelect: function(e) {
            var $option = $(e.currentTarget);
            var value = $option.data('value');
            var label = $option.data('label');
            
            this.$input().val(value).trigger('change');
            this.$search().val('');
            this.$dropdown().removeClass('is-open').empty();
            
            // Update preview
            this.$preview().html('<i class="' + acf.escAttr(value) + '"></i>');
            
            // Show clear button if allow_null
            if (this.$el.data('allow_null')) {
                if (!this.$('.acf-fontawesome-icon-clear').length) {
                    this.$el.append('<button type="button" class="acf-fontawesome-icon-clear button">Clear</button>');
                }
            }
            // Notify other fields that the icon changed.
            // Trigger on the element so the event bubbles up the DOM
            // (row-scoped listeners can catch it), and on document for
            // any global listeners.
            try {
                this.$el.trigger('acf:fontawesome_icon:change', [value]);
            } catch (e) {
                // ignore
            }
        },

        onClear: function(e) {
            e.preventDefault();
            this.$input().val('').trigger('change');
            this.$preview().html('<span class="acf-fontawesome-icon-no-selection">No icon selected</span>');
            $(e.currentTarget).remove();
            try {
                this.$el.trigger('acf:fontawesome_icon:change', ['']);
                $(document).trigger('acf:fontawesome_icon:change', ['', this.$el]);
            } catch (e) {
                // ignore
            }
        },

        onScroll: function() {
            if (this.isLoading || !this.hasMore) return;
            
            var $dropdown = this.$dropdown();
            var scrollTop = $dropdown.scrollTop();
            var scrollHeight = $dropdown[0].scrollHeight;
            var height = $dropdown.height();
            
            if (scrollTop + height >= scrollHeight - 50) {
                this.currentPage++;
                this.doSearch(true);
            }
        },

        doSearch: function(append) {
            var self = this;
            var search = this.$search().val();
            
            this.isLoading = true;
            
            if (!append) {
                this.$dropdown().html('<div class="acf-fontawesome-icon-loading">Loading...</div>');
            } else {
                this.$dropdown().find('.acf-fontawesome-icon-loading').remove();
                this.$dropdown().append('<div class="acf-fontawesome-icon-loading">Loading more...</div>');
            }

            $.ajax({
                url: acf.get('ajaxurl'),
                type: 'POST',
                data: {
                    action: 'acf/fields/fontawesome_icon/query',
                    s: search,
                    paged: this.currentPage,
                },
                success: function(response) {
                    self.isLoading = false;
                    self.$dropdown().find('.acf-fontawesome-icon-loading').remove();
                    
                    if (!append) {
                        self.$dropdown().empty();
                    }
                    
                    if (response.results && response.results.length) {
                        response.results.forEach(function(item) {
                            var $option = $(
                                '<div class="acf-fontawesome-icon-option" data-value="' + acf.escAttr(item.id) + '" data-label="' + acf.escAttr(item.label) + '">' +
                                    '<i class="' + acf.escAttr(item.id) + '"></i>' +
                                    '<span>' + acf.escHtml(item.label) + '</span>' +
                                '</div>'
                            );
                            self.$dropdown().append($option);
                        });
                        
                        self.hasMore = response.more;
                    } else if (!append) {
                        self.$dropdown().html('<div class="acf-fontawesome-icon-no-results">No icons found</div>');
                    }
                },
                error: function() {
                    self.isLoading = false;
                    self.$dropdown().find('.acf-fontawesome-icon-loading').remove();
                }
            });
        }
    });

    acf.registerFieldType(FontAwesomeIconField);

})(jQuery);
