<?php
declare(strict_types=1);

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

namespace Pimcore\Model\DataObject\ClassDefinition\Data\Extension;

/**
 * @deprecated please implement getQueryColumnType() on your data-type
 */
trait QueryColumnType
{
    /**
     * {@inheritdoc}
     */
    public function getQueryColumnType(): array|string|null
    {
        if (property_exists($this, 'queryColumnType')) {
            return $this->queryColumnType;
        }

        return null;
    }

    /**
     * @param string | array $queryColumnType
     *
     * @return $this
     */
    public function setQueryColumnType($queryColumnType): static
    {
        if (property_exists($this, 'queryColumnType')) {
            $this->queryColumnType = $queryColumnType;
        }

        return $this;
    }
}
