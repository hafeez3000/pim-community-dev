define(
    ['jquery', 'underscore', 'backbone', 'oro/translator', 'routing', 'oro/mediator', 'oro/loading-mask', 'pim/dialog', 'jquery-ui-full'],
    function ($, _, Backbone, __, Routing, mediator, LoadingMask, Dialog) {
        'use strict';

        var AttributeOptionItem = Backbone.Model.extend({
            defaults: {
                code: '',
                optionValues: {}
            }
        });

        var AttributeOptionValueCollection = Backbone.Model.extend();


        var ItemCollection = Backbone.Collection.extend({
            model: AttributeOptionItem,
            initialize: function(options) {
                this.url = options.url;
            }
        });

        var EditableItemView = Backbone.View.extend({
            tagName: 'tr',
            className: 'editable-item-row',
            showTemplate: _.template(
                '<td>' +
                    '<span class="handle"><i class="icon-reorder"></i></span>' +
                    '<span class="option-code"><%= item.code %></span>' +
                '</td>' +
                '<% _.each(locales, function(locale) { %>' +
                '<td >' +
                    '<% if (item.optionValues[locale]) { %>' +
                        '<span title="<%= item.optionValues[locale].value %>"><%= item.optionValues[locale].value %></span>' +
                    '<% } %>' +
                '</td>' +
                '<% }); %>' +
                '<td>' +
                    '<span class="btn btn-small edit-row"><i class="icon-pencil"></i></span>' +
                    '<span class="btn btn-small delete-row"><i class="icon-trash"></i></span>' +
                '</td>'
            ),
            editTemplate: _.template(
                '<td class="field-cell">' +
                    '<input type="text" class="attribute_option_code exclude" value="<%= item.code %>"/>' +
                    '<i class="validation-tooltip hidden" data-placement="top" data-toggle="tooltip"></i>' +
                '</td>' +
                '<% _.each(locales, function(locale) { %>' +
                '<td class="field-cell">' +
                    '<% if (item.optionValues[locale]) { %>' +
                        '<input type="text" class="attribute-option-value exclude" data-locale="<%= locale %>" value="<%= item.optionValues[locale].value %>"/>' +
                    '<% } else { %>' +
                        '<input type="text" class="attribute-option-value exclude" data-locale="<%= locale %>" value=""/>' +
                    '<% } %>' +
                '</td>' +
                '<% }); %>' +
                '<td>' +
                    '<span class="btn btn-small update-row"><i class="icon-ok"></i></span>' +
                    '<span class="btn btn-small show-row"><i class="icon-remove"></i></span>' +
                '</td>'
            ),
            events: {
                'click .show-row':   'stopEditItem',
                'click .edit-row':   'startEditItem',
                'click .delete-row': 'deleteItem',
                'click .update-row': 'updateItem',
                'keydown input':     'soil',
                'keydown':           'cancelSubmit'
            },
            editable: false,
            parent: null,
            loading: false,
            locales: [],
            initialize: function(options) {
                this.locales       = options.locales;
                this.parent        = options.parent;
                this.model.urlRoot = this.parent.updateUrl;

                this.render();
            },
            render: function() {
                var template = null;

                if (this.editable) {
                    this.clean();
                    this.$el.addClass('in-edition');
                    template = this.editTemplate;
                } else {
                    this.$el.removeClass('in-edition');
                    template = this.showTemplate;
                }

                this.$el.html(template({
                    item: this.model.toJSON(),
                    locales: this.locales
                }));

                this.$el.attr('data-item-id', this.model.id);

                return this;
            },
            showReadableItem: function() {
                this.editable = false;
                this.parent.showReadableItem(this);
                this.clean();
                this.render();
            },
            showEditableItem: function() {
                this.editable = true;
                this.render();
                this.model.set(this.loadModelFromView().attributes);
            },
            startEditItem: function() {
                var rowIsEditable = this.parent.requestRowEdition(this);

                if (rowIsEditable) {
                    this.showEditableItem();
                }
            },
            stopEditItem: function() {
                if (!this.model.id || this.dirty) {
                    if (this.dirty) {
                        Dialog.confirm(
                            __('confirm.attribute_option.cancel_edition_on_new_option_text'),
                            __('confirm.attribute_option.cancel_edition_on_new_option_title'),
                            _.bind(function() {
                                this.showReadableItem(this);
                                this.deleteItem(this);
                            }, this));
                    } else {
                        if (!this.model.id) {
                            this.deleteItem();
                        } else {
                            this.showReadableItem();
                        }
                    }
                } else {
                    this.showReadableItem();
                }
            },
            deleteItem: function() {
                this.parent.deleteItem(this);
            },
            updateItem: function() {
                this.inLoading(true);

                var editedModel = this.loadModelFromView();

                editedModel.save(
                    {},
                    {
                        url: this.model.url(),
                        success: _.bind(function(data) {
                            this.inLoading(false);
                            this.model.set(editedModel.attributes);
                            this.clean();
                            this.stopEditItem();
                        }, this),
                        error: _.bind(function(data, xhr) {
                            this.inLoading(false);

                            var response = xhr.responseJSON;

                            if (response.children &&
                                response.children.code &&
                                response.children.code.errors &&
                                response.children.code.errors.length > 0
                            ) {
                                var message = response.children.code.errors.join('<br/>');
                                this.$el.find('.validation-tooltip')
                                    .addClass('visible')
                                    .tooltip('destroy')
                                    .tooltip({title: message})
                                    .tooltip('show');
                            } else {
                                Dialog.alert(
                                    __('alert.attribute_option.error_occured_during_submission'),
                                    __('error.saving.attribute_option')
                                );
                            }
                        }, this)
                    }
                );
            },
            cancelSubmit: function(e) {
                if(e.keyCode == 13) {
                    this.updateItem();

                    return false;
                }
            },
            loadModelFromView: function()
            {
                var attributeOptions = {};
                var editedModel = this.model.clone();

                editedModel.urlRoot = this.model.urlRoot;

                _.each(this.$el.find('.attribute-option-value'), function(input) {
                    var locale = input.dataset.locale;

                    attributeOptions[locale] = {
                        locale: locale,
                        value:  input.value,
                        id:     this.model.get('optionValues')[locale] ?
                            this.model.get('optionValues')[locale].id :
                            null
                    };
                }, this);

                editedModel.set('code', this.$el.find('.attribute_option_code').val())
                editedModel.set('optionValues', attributeOptions);

                return editedModel;
            },
            inLoading: function(loading) {
                this.parent.inLoading(loading);
            },
            soil: function() {
                if (JSON.stringify(this.model.attributes) !== JSON.stringify(this.loadModelFromView().attributes)) {
                    this.dirty = true;
                } else {
                    this.dirty = false;
                }
            },
            clean: function() {
                this.dirty = false;
            }
        });

        var ItemCollectionView = Backbone.View.extend({
            tagName: 'table',
            className: 'table table-bordered table-stripped attribute-option-view',
            template: _.template(
                '<!-- Pim/Bundle/EnrichBundle/Resources/public/js/pim-attributeoptionview.js -->' +
                '<colgroup>' +
                    '<col class="code" span="1"></col>' +
                    '<col class="fields" span="<%= locales.length %>"></col>' +
                    '<col class="action" span="1"></col>' +
                '</colgroup>' +
                '<thead>' +
                    '<tr>' +
                        '<th><%= code_label %></th>' +
                        '<% _.each(locales, function(locale) { %>' +
                        '<th>' +
                            '<%= locale %>' +
                        '</th>' +
                        '<% }); %>' +
                        '<th>Action</th>' +
                    '</tr>' +
                '</thead>' +
                '<tbody></tbody>' +
                '<tfoot>' +
                    '<tr>' +
                        '<td colspan="<%= 2 + locales.length %>">' +
                            '<span class="btn option-add pull-right"><%= add_option_label %></span>' +
                        '</td>' +
                    '</tr>' +
                '</tfoot>'
            ),
            events: {
                'click .option-add': 'addItem'
            },
            $target: null,
            locales: [],
            sortable: true,
            sortingUrl: '',
            updateUrl: '',
            currentlyEditedItemView: null,
            itemViews: [],
            rendered: false,
            initialize: function(options) {
                this.$target    = options.$target;
                this.collection = new ItemCollection({url: options.updateUrl});
                this.locales    = options.locales;
                this.updateUrl  = options.updateUrl;
                this.sortingUrl = options.sortingUrl;
                this.sortable   = options.sortable;

                this.render();
                this.load();
            },
            render: function() {
                this.$el.empty();

                this.currentlyEditedItemView = null;
                this.updateEditionStatus();

                this.$el.html(this.template({
                    locales: this.locales,
                    add_option_label: __('label.attribute_option.add_option'),
                    code_label: __('Code')
                }));

                _.each(this.collection.models, function(attributeOptionItem) {
                    this.addItem({item: attributeOptionItem});
                }, this);

                if (0 === this.collection.length) {
                    this.addItem();
                }

                if (!this.rendered) {
                    this.$target.html(this.$el);

                    this.rendered = true;
                }

                this.$el.sortable({
                    items: "tbody tr",
                    axis: 'y',
                    connectWith: this.$el,
                    containment: this.$el,
                    distance: 5,
                    cursor: 'move',
                    helper: function(e, ui) {
                        ui.children().each(function() {
                            $(this).width($(this).width());
                        });

                        return ui;
                    },
                    stop: _.bind(function(e, ui) {
                        this.updateSorting();
                    }, this)
                });

                this.updateSortableStatus(this.sortable);

                return this;
            },
            load: function() {
                this.itemViews = [];
                this.inLoading(true);
                this.collection
                    .fetch({
                        success: _.bind(function() {
                            this.inLoading(false);
                            this.render();
                        }, this)
                    });
            },
            addItem: function(options) {
                var options = options || {};

                //If no item model provided we create one
                if (!options.item) {
                    var itemToAdd = new AttributeOptionItem();
                } else {
                    var itemToAdd = options.item;
                }

                var newItemView = this.createItemView(itemToAdd);

                if (newItemView) {
                    this.$el.children('tbody').append(newItemView.$el);
                }
            },
            createItemView: function(item) {
                var itemView = new EditableItemView({
                    model:    item,
                    url:      this.updateUrl,
                    locales:  this.locales,
                    parent:   this
                });

                //If the item is new the view is changed to edit mode
                if (!item.id) {
                    if (!this.requestRowEdition(itemView)) {
                        return;
                    } else {
                        itemView.showEditableItem();
                    }
                }

                this.collection.add(item);
                this.itemViews.push(itemView);

                return itemView;
            },
            requestRowEdition: function (attributeOptionRow) {
                if (this.currentlyEditedItemView) {
                    if (this.currentlyEditedItemView.dirty) {
                        Dialog.alert(__('alert.attribute_option.save_before_edit_other'));

                        return false;
                    } else {
                        this.currentlyEditedItemView.stopEditItem();
                        this.currentlyEditedItemView = null;
                        this.updateEditionStatus();
                    }
                }

                this.currentlyEditedItemView = attributeOptionRow;
                this.updateEditionStatus();

                return true;
            },
            showReadableItem: function (item) {
                if (item === this.currentlyEditedItemView) {
                    this.currentlyEditedItemView = null;
                    this.updateEditionStatus();
                }
            },
            deleteItem: function(item) {
                this.inLoading(true);

                item.model.destroy({
                    success: _.bind(function() {
                        this.inLoading(false);

                        this.collection.remove(item);
                        this.currentlyEditedItemView = null;
                        this.updateEditionStatus();

                        if (0 === this.collection.length) {
                            this.addItem();
                            item.$el.hide(0);
                        } else if (!item.model.id) {
                            item.$el.hide(0);
                        } else {
                            item.$el.hide(500);
                        }
                    }, this),
                    error: _.bind(function(data, response) {
                        this.inLoading(false);
                        var message;

                        if (response.responseJSON) {
                            message = response.responseJSON;
                        } else {
                            message = response.responseText;
                        }

                        Dialog.alert(message, __('error.removing.attribute_option'));
                    }, this)
                });
            },
            updateEditionStatus: function() {
                if (this.currentlyEditedItemView) {
                    this.$el.addClass('in-edition');
                } else {
                    this.$el.removeClass('in-edition');
                }
            },
            updateSortableStatus: function(sortable) {
                this.sortable = sortable;

                if (sortable) {
                    this.$el.sortable('enable');
                } else {
                    this.$el.sortable('disable');
                }
            },
            updateSorting: function() {
                this.inLoading(true);
                var sorting = [];

                var rows = this.$el.find('tbody tr');
                for (var i = rows.length - 1; i >= 0; i--) {
                    sorting[i] = rows[i].dataset.itemId;
                }

                $.ajax({
                    url: this.sortingUrl,
                    type: 'PUT',
                    data: JSON.stringify(sorting)
                }).done(_.bind(function() {
                    this.inLoading(false);
                }, this));
            },
            inLoading: function(loading) {
                if (loading) {
                    var loadingMask = new LoadingMask();
                    loadingMask.render().$el.appendTo(this.$el);
                    loadingMask.show();
                } else {
                    this.$el.find('.loading-mask').remove();
                }
            }
        });

        return function($element) {
            var itemCollectionView = new ItemCollectionView(
            {
                $target: $element,
                updateUrl: Routing.generate(
                    'pim_enrich_attributeoption_index',
                    {attributeId: $element.data('attribute-id')}
                ),
                sortingUrl: Routing.generate(
                    'pim_enrich_attributeoption_update_sorting',
                    {attributeId: $element.data('attribute-id')}
                ),
                locales: $element.data('locales'),
                sortable: $element.data('sortable')
            });

            mediator.on('attribute:auto_option_sorting:changed', _.bind(function(autoSorting) {
                itemCollectionView.updateSortableStatus(!autoSorting);
            }, this));
        };
    }
);
