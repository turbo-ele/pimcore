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

namespace Pimcore\Model\Tool\CustomReport\Config;

use Pimcore\Model;
use Pimcore\Model\AbstractModel;
use Pimcore\Model\Listing\CallableFilterListingInterface;
use Pimcore\Model\Listing\CallableOrderListingInterface;
use Pimcore\Model\Listing\Traits\FilterListingTrait;
use Pimcore\Model\Listing\Traits\OrderListingTrait;

/**
 * @internal
 *
 * @method \Pimcore\Model\Tool\CustomReport\Config\Listing\Dao getDao()
 */
class Listing extends AbstractModel implements CallableFilterListingInterface, CallableOrderListingInterface
{
    use FilterListingTrait;
    use OrderListingTrait;

    /**
     * @var Model\Tool\CustomReport\Config[]|null
     */
    protected ?array $reports = null;

    /**
     * @return Model\Tool\CustomReport\Config[]
     */
    public function getReports(): array
    {
        if ($this->reports === null) {
            $this->reports = $this->getDao()->loadList();
        }

        return $this->reports;
    }

    /**
     * @param Model\Tool\CustomReport\Config[]|null $reports
     *
     * @return $this
     */
    public function setReports(?array $reports): static
    {
        $this->reports = $reports;

        return $this;
    }
}
