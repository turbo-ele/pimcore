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

namespace Pimcore\Bundle\XliffBundle\DependencyInjection;

use Pimcore\Bundle\XliffBundle\ExportDataExtractorService\DataExtractor\DataObjectDataExtractor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PimcoreXliffExtension extends ConfigurableExtension
{
    protected function loadInternal(array $config, ContainerBuilder $container): void
    {
        // on container build the shutdown handler shouldn't be called
        // for details please see https://github.com/pimcore/pimcore/issues/4709
        \Pimcore::disableShutdown();

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );

        $loader->load('services.yaml');

        if (!empty($config['data_object']['translation_extractor']['attributes'])) {
            $definition = $container->getDefinition(DataObjectDataExtractor::class);
            $definition->setArgument('$exportAttributes', $config['data_object']['translation_extractor']['attributes']);
        }
    }
}
