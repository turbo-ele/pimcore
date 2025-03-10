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

namespace Pimcore\Bundle\UuidBundle\Model\Tool\UUID;

use Exception;
use Pimcore\Bundle\UuidBundle\Model\Tool\UUID;
use Pimcore\Db\Helper;
use Pimcore\Model;

/**
 * @internal
 *
 * @property UUID $model
 */
class Dao extends Model\Dao\AbstractDao
{
    const TABLE_NAME = 'uuids';

    public function save(): void
    {
        $data = $this->getValidObjectVars();

        Helper::insertOrUpdate($this->db, self::TABLE_NAME, $data);
    }

    public function create(): void
    {
        $data = $this->getValidObjectVars();

        $this->db->insert(self::TABLE_NAME, $data);
    }

    private function getValidObjectVars(): array
    {
        $data = $this->model->getObjectVars();

        foreach ($data as $key => $value) {
            if (!in_array($key, $this->getValidTableColumns(static::TABLE_NAME))) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    public function delete(): void
    {
        $uuid = $this->model->getUuid();
        if (!$uuid) {
            throw new Exception("Couldn't delete UUID - no UUID specified.");
        }

        $itemId = $this->model->getItemId();
        $type = $this->model->getType();

        $this->db->delete(self::TABLE_NAME, ['itemId' => $itemId, 'type' => $type, 'uuid' => $uuid]);
    }

    public function getByUuid(string $uuid): UUID
    {
        $data = $this->db->fetchAssociative('SELECT * FROM ' . self::TABLE_NAME ." where uuid='" . $uuid . "'");
        $model = new UUID();
        $model->setValues($data);

        return $model;
    }

    public function exists(string $uuid): bool
    {
        return (bool) $this->db->fetchOne('SELECT uuid FROM ' . self::TABLE_NAME . ' where uuid = ?', [$uuid]);
    }
}
