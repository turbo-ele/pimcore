<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Model\Tool\Targeting\Rule\Listing;

use Pimcore\Model;
use Pimcore\Model\Tool\Targeting\Rule;

/**
 * @internal
 *
 * @property \Pimcore\Model\Tool\Targeting\Rule\Listing $model
 */
class Dao extends Model\Listing\Dao\AbstractDao
{
    /**
     * @return Rule[]
     */
    public function load(): array
    {
        $ids = $this->db->fetchFirstColumn('SELECT id FROM targeting_rules' . $this->getCondition() . $this->getOrder() . $this->getOffsetLimit(), $this->model->getConditionVariables());

        $targets = [];
        foreach ($ids as $id) {
            $targets[] = Rule::getById($id);
        }

        $this->model->setTargets($targets);

        return $targets;
    }

    public function getTotalCount(): int
    {
        try {
            return (int) $this->db->fetchOne('SELECT COUNT(*) FROM targeting_rules ' . $this->getCondition(), $this->model->getConditionVariables());
        } catch (\Exception $e) {
            return 0;
        }
    }
}
