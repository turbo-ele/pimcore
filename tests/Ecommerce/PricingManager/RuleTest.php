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

namespace Pimcore\Tests\Ecommerce\PricingManager;

use Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\Action\CartDiscount;
use Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\Action\FreeShipping;
use Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\Action\Gift;
use Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\Action\ProductDiscount;
use Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\Condition\Bracket;
use Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\Condition\CartAmount;
use Pimcore\Tests\Ecommerce\PricingManager\Rule\AbstractRuleTest;

class RuleTest extends AbstractRuleTest
{
    public function testSimpleProductDiscount()
    {
        $ruleDefinitions = [
            'testrule' => [
                'actions' => [
                    [
                        'class' => ProductDiscount::class,
                        'amount' => 10,
                    ],
                ],
                'condition' => '',
            ],
        ];

        $productDefinitions = [
            'singleProduct' => [
                'id' => 4,
                'price' => 100,
            ],
            'cart' => [
                [
                    'id' => 4,
                    'price' => 100,
                ],
            ],

        ];

        $tests = [
            'productPriceSingle' => 90,
            'productPriceTotal' => 180,
            'cartSubTotal' => 90,
            'cartGrandTotal' => 90,
            'cartSubTotalModificators' => 90,
            'cartGrandTotalModificators' => 100,
        ];

        $this->doAssertions($ruleDefinitions, $productDefinitions, $tests);
    }

    public function testSimpleCartDiscount()
    {
        $ruleDefinitions = [
            'testrule' => [
                'actions' => [
                    [
                        'class' => CartDiscount::class,
                        'amount' => 10,
                    ],
                ],
                'condition' => '',
            ],
        ];

        $productDefinitions = [
            'singleProduct' => [
                'id' => 4,
                'price' => 100,
            ],
            'cart' => [
                [
                    'id' => 4,
                    'price' => 100,
                ],
                [
                    'id' => 5,
                    'price' => 40,
                ],
            ],

        ];

        $tests = [
            'productPriceSingle' => 100,
            'productPriceTotal' => 200,
            'cartSubTotal' => 140,
            'cartGrandTotal' => 130,
            'cartSubTotalModificators' => 140,
            'cartGrandTotalModificators' => 140,
        ];

        $this->doAssertions($ruleDefinitions, $productDefinitions, $tests);
    }

    public function testSimpleCartDiscountCartAmountWithCondition()
    {
//        $condition = new CartAmount();
//        $condition->setLimit(200);

        $ruleDefinitions = [
            'testrule' => [
                'actions' => [
                    [
                        'class' => CartDiscount::class,
                        'amount' => 10,
                    ],
                ],
//                'condition' => $condition
//                'condition' => [
//                    'class' => CartAmount::class,
//                    'limit' => 200
//                ]
                'condition' => [
                    'class' => Bracket::class,
                    'conditions' => [
                        [
                            'condition' => [
                                'class' => CartAmount::class,
                                'limit' => 200,
                            ],
                            'operator' => Bracket::OPERATOR_AND,
                        ],
                    ],
                ],
                //'condition' => 'O:72:"Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\Condition\Bracket":2:{s:13:" * conditions";a:1:{i:0;O:75:"Pimcore\Bundle\EcommerceFrameworkBundle\PricingManager\Condition\CartAmount":2:{s:8:" * limit";i:200;s:7:" * mode";s:9:"only_cart";}}s:11:" * operator";a:1:{i:0;s:3:"and";}}'
            ],
        ];

        $productDefinitions = [
            'singleProduct' => [
                'id' => 4,
                'price' => 100,
            ],
            'cart' => [
                [
                    'id' => 4,
                    'price' => 100,
                ],
                [
                    'id' => 5,
                    'price' => 40,
                ],
            ],

        ];

        $tests = [
            'productPriceSingle' => 100,
            'productPriceTotal' => 200,
            'cartSubTotal' => 140,
            'cartGrandTotal' => 140,
            'cartSubTotalModificators' => 140,
            'cartGrandTotalModificators' => 150,
        ];

        $this->doAssertions($ruleDefinitions, $productDefinitions, $tests);
    }

    public function testSimpleCartDiscountCartAmountWithCondition2()
    {
        $ruleDefinitions = [
            'testrule' => [
                'actions' => [
                    [
                        'class' => CartDiscount::class,
                        'amount' => 10,
                    ],
                ],
                'condition' => [
                    'class' => CartAmount::class,
                    'limit' => 200,
                ],
            ],
        ];

        $productDefinitions = [
            'singleProduct' => [
                'id' => 4,
                'price' => 100,
            ],
            'cart' => [
                [
                    'id' => 4,
                    'price' => 200,
                ],
                [
                    'id' => 5,
                    'price' => 40,
                ],
            ],

        ];

        $tests = [
            'productPriceSingle' => 100,
            'productPriceTotal' => 200,
            'cartSubTotal' => 240,
            'cartGrandTotal' => 230,
            'cartSubTotalModificators' => 240,
            'cartGrandTotalModificators' => 240,
        ];

        $this->doAssertions($ruleDefinitions, $productDefinitions, $tests);
    }

    public function testProductAndCartDiscount()
    {
        $ruleDefinitions = [
            'testrule' => [
                'actions' => [
                    [
                        'class' => CartDiscount::class,
                        'amount' => 10,
                    ],
                    [
                        'class' => ProductDiscount::class,
                        'amount' => 15,
                    ],
                ],
                'condition' => '',
            ],
        ];

        $productDefinitions = [
            'singleProduct' => [
                'id' => 4,
                'price' => 100,
            ],
            'cart' => [
                [
                    'id' => 4,
                    'price' => 100,
                ],
                [
                    'id' => 5,
                    'price' => 40,
                ],
            ],

        ];

        $tests = [
            'productPriceSingle' => 85,
            'productPriceTotal' => 170,
            'cartSubTotal' => 110,
            'cartGrandTotal' => 100,
            'cartSubTotalModificators' => 110,
            'cartGrandTotalModificators' => 110,
        ];

        $this->doAssertions($ruleDefinitions, $productDefinitions, $tests);
    }

    public function testGiftItem()
    {
        $ruleDefinitions = [
            'testrule' => [
                'actions' => [
                    [
                        'class' => Gift::class,
                        'product' => $this->setUpProduct(99, 100, null),
                    ],
                ],
                'condition' => [
                    'class' => CartAmount::class,
                    'limit' => 200,
                ],
            ],
        ];

        $productDefinitions = [
            'singleProduct' => [
                'id' => 4,
                'price' => 100,
            ],
            'cart' => [
                [
                    'id' => 4,
                    'price' => 100,
                ],
                [
                    'id' => 5,
                    'price' => 40,
                ],
            ],

        ];

        $tests = [
            'productPriceSingle' => 100,
            'productPriceTotal' => 200,
            'cartSubTotal' => 140,
            'cartGrandTotal' => 140,
            'cartSubTotalModificators' => 140,
            'cartGrandTotalModificators' => 150,
        ];

        $this->doAssertionsWithGiftItem($ruleDefinitions, $productDefinitions, $tests, false);

        $productDefinitions = [
            'singleProduct' => [
                'id' => 4,
                'price' => 100,
            ],
            'cart' => [
                [
                    'id' => 4,
                    'price' => 100,
                ],
                [
                    'id' => 5,
                    'price' => 40,
                ],
                [
                    'id' => 6,
                    'price' => 80,
                ],
            ],
        ];

        $tests = [
            'productPriceSingle' => 100,
            'productPriceTotal' => 200,
            'cartSubTotal' => 220,
            'cartGrandTotal' => 220,
            'cartSubTotalModificators' => 220,
            'cartGrandTotalModificators' => 230,
        ];

        $this->doAssertionsWithGiftItem($ruleDefinitions, $productDefinitions, $tests, true);
    }

    public function testFreeShipping()
    {
        $ruleDefinitions = [
            'testrule' => [
                'actions' => [
                    [
                        'class' => FreeShipping::class,
                    ],
                ],
                'condition' => [
                    'class' => CartAmount::class,
                    'limit' => 200,
                ],
            ],
        ];

        $productDefinitions = [
            'singleProduct' => [
                'id' => 4,
                'price' => 100,
            ],
            'cart' => [
                [
                    'id' => 4,
                    'price' => 100,
                ],
                [
                    'id' => 5,
                    'price' => 40,
                ],
            ],

        ];

        $tests = [
            'productPriceSingle' => 100,
            'productPriceTotal' => 200,
            'cartSubTotal' => 140,
            'cartGrandTotal' => 140,
            'cartSubTotalModificators' => 140,
            'cartGrandTotalModificators' => 150,
        ];

        $this->doAssertionsWithShippingCosts($ruleDefinitions, $productDefinitions, $tests, false);

        $productDefinitions = [
            'singleProduct' => [
                'id' => 4,
                'price' => 100,
            ],
            'cart' => [
                [
                    'id' => 4,
                    'price' => 100,
                ],
                [
                    'id' => 5,
                    'price' => 40,
                ],
                [
                    'id' => 6,
                    'price' => 80,
                ],
            ],
        ];

        $tests = [
            'productPriceSingle' => 100,
            'productPriceTotal' => 200,
            'cartSubTotal' => 220,
            'cartGrandTotal' => 220,
            'cartSubTotalModificators' => 220,
            'cartGrandTotalModificators' => 220,
        ];

        $this->doAssertionsWithShippingCosts($ruleDefinitions, $productDefinitions, $tests, true);
    }
}
