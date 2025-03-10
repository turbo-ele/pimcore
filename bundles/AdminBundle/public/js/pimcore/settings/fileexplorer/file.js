/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

pimcore.registerNS("pimcore.settings.fileexplorer.file");
/**
 * @private
 */
pimcore.settings.fileexplorer.file = Class.create({

    initialize: function (path, explorer) {
        this.path = path;
        this.explorer = explorer;
        this.loadFileContents(path);
    },

    loadFileContents: function (path) {
        Ext.Ajax.request({
            url: Routing.generate('pimcore_admin_misc_fileexplorercontent'),
            success: this.loadFileContentsComplete.bind(this),
            params: {
                path: path
            }
        });
    },

    loadFileContentsComplete: function (response) {
        response = Ext.decode(response.responseText);
        if(response.success) {

            var toolbarItems = ["->"];
            this.responsePath = response.path;
            if(response.writeable) {
                toolbarItems.push({
                    text: t("save"),
                    handler: this.saveFile.bind(this),
                    iconCls: "pimcore_icon_save"
                });
            }

            let editorId = 'editor_' + this.path;
            var editorContainer = new Ext.Component({
                html: '<div id="' + editorId + '" style="height:100%;width:100%"></div>',
                listeners: {
                    afterrender: function (cmp) {
                        var editor = ace.edit(editorId);
                        editor.setTheme('ace/theme/chrome');

                        //set editor file mode
                        let modelist = ace.require('ace/ext/modelist');
                        let mode = modelist.getModeForPath(this.path).mode;
                        editor.getSession().setMode(mode);

                        editor.setOptions({
                            showLineNumbers: true,
                            showPrintMargin: false,
                            wrap: true,
                            fontFamily: 'Courier New, Courier, monospace;'
                        });

                        //set data
                        if (response.content) {
                            editor.setValue(response.content);
                            editor.clearSelection();
                            editor.resize();
                        }

                        this.textEditor = editor;
                    }.bind(this)
                }
            });

            var isNew = false;

            if (!this.editor) {
                isNew = true;
                this.editor = new Ext.Panel({
                    closable: true,
                    layout: "fit",
                    bbar: toolbarItems,
                    tbar: [{
                        xtype: "tbtext",
                        text: response.path
                    }],
                    bodyStyle: "position:relative;"
                });

                this.editor.on("beforedestroy", function () {
                    delete this.explorer.openfiles[this.path];
                }.bind(this));

                this.editor.on('resize', function (el, width, height) {
                    this.textEditor.resize();
                }.bind(this));

            }
            this.editor.removeAll();
            this.editor.setTitle(response.filename);
            this.editor.add(editorContainer);

            if (isNew) {
                this.explorer.editorPanel.add(this.editor);
            }
            this.explorer.editorPanel.setActiveTab(this.editor);
            this.explorer.editorPanel.updateLayout();
        }
    },

    saveFile: function () {
        var content = this.textEditor.getValue();
        Ext.Ajax.request({
            method: "put",
            url: Routing.generate('pimcore_admin_misc_fileexplorercontentsave'),
            params: {
                path: this.responsePath,
                content: content
            },
            success: function (response) {
                try{
                    var rdata = Ext.decode(response.responseText);
                    if (rdata && rdata.success) {
                        pimcore.helpers.showNotification(t("success"), t("file_explorer_saved_file_success"),
                                                                    "success");
                    }
                    else {
                        pimcore.helpers.showNotification(t("error"), t("file_explorer_saved_file_error"), "error");
                    }
                } catch (e) {
                    pimcore.helpers.showNotification(t("error"), t("file_explorer_saved_file_error"), "error");
                }
            }.bind(this)
        });
    },

    activate: function () {
        this.explorer.editorPanel.setActiveTab(this.editor);
    },

    updatePath: function(path) {
        this.path = path;
        this.loadFileContents(path);
    }

});
