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

pimcore.registerNS("pimcore.object.classes.data.targetGroup");
/**
 * @private
 */
pimcore.object.classes.data.targetGroup = Class.create(pimcore.object.classes.data.data, {

    type: "targetGroup",

    /**
     * define where this datatype is allowed
     */
    allowIn: {
        object: true,
        objectbrick: true,
        fieldcollection: true,
        localizedfield: false,
        classificationstore: false,
        block: true,
        encryptedField: true
    },

    initialize: function (treeNode, initData) {
        this.type = "targetGroup";

        if (!initData["name"]) {
            initData = {
                title: t("target_group")
            };
        }

        initData.fieldtype = "targetGroup";
        initData.datatype = "data";
        initData.name = "targetGroup";
        treeNode.set("text", "targetGroup");

        this.initData(initData);

        this.treeNode = treeNode;
    },

    getTypeName: function () {
        return t("target_group");
    },

    getLayout: function ($super) {

        $super();

        var nameField = this.layout.getComponent("standardSettings").getComponent("name");
        nameField.disable();

        return this.layout;
    },

    getGroup: function () {
        return "crm";
    },

    getIconClass: function () {
        return "pimcore_icon_targetGroup";
    }
});
