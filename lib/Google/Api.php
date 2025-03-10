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

namespace Pimcore\Google;

use Google\Client;
use Pimcore\Config;
use Pimcore\Model\Tool\TmpStore;
use Psr\Cache\CacheItemPoolInterface;

class Api
{
    const ANALYTICS_API_URL = 'https://www.googleapis.com/analytics/v3/';

    public static function getPrivateKeyPath(): string
    {
        return \Pimcore\Config::locateConfigFile('google-api-private-key.json');
    }

    public static function getConfig(): array
    {
        return Config::getSystemConfiguration('services')['google'] ?? [];
    }

    public static function isConfigured(string $type = 'service'): bool
    {
        if ($type == 'simple') {
            return self::isSimpleConfigured();
        }

        return self::isServiceConfigured();
    }

    public static function isServiceConfigured(): bool
    {
        $config = self::getConfig();

        if (!empty($config['client_id']) && !empty($config['email']) && file_exists(self::getPrivateKeyPath())) {
            return true;
        }

        return false;
    }

    public static function isSimpleConfigured(): bool
    {
        $config = self::getConfig();

        if (!empty($config['simple_api_key'])) {
            return true;
        }

        return false;
    }

    /**
     * @param string $type
     *
     * @return Client|false returns false, if client not configured
     */
    public static function getClient(string $type = 'service'): Client|bool
    {
        if ($type == 'simple') {
            return self::getSimpleClient();
        }

        return self::getServiceClient();
    }

    /**
     * @param array|null $scope
     *
     * @return Client|false
     */
    public static function getServiceClient(array $scope = null): Client|bool
    {
        if (!self::isServiceConfigured()) {
            return false;
        }

        $config = self::getConfig();

        if (!$scope) {
            // default scope
            $scope = ['https://www.googleapis.com/auth/analytics.readonly'];
        }

        $client = new Client();

        /** @var CacheItemPoolInterface $cache */
        $cache = \Pimcore::getContainer()->get('pimcore.cache.pool');
        $client->setCache($cache);

        $client->setApplicationName('pimcore CMF');
        $json = self::getPrivateKeyPath();
        $client->setAuthConfig($json);

        $client->setScopes($scope);

        $client->setClientId($config['client_id'] ?? '');

        // token cache
        $hash = crc32(serialize([$scope]));
        $tokenId = 'google-api.token.' . $hash;
        $token = null;
        if ($tokenData = TmpStore::get($tokenId)) {
            $tokenInfo = json_decode($tokenData->getData(), true);
            if (((int)$tokenInfo['created'] + (int)$tokenInfo['expires_in']) > (time() - 900)) {
                $token = $tokenData->getData();
            }
        }

        if (!$token) {
            $client->fetchAccessTokenWithAssertion();
            $token = json_encode($client->getAccessToken());

            // 1 hour (3600s) is the default expiry time
            TmpStore::add($tokenId, $token, null, 3600);
        }

        $client->setAccessToken($token);

        return $client;
    }

    /**
     * @return Client|false
     */
    public static function getSimpleClient(): Client|bool
    {
        if (!self::isSimpleConfigured()) {
            return false;
        }

        $client = new Client();

        /** @var CacheItemPoolInterface $cache */
        $cache = \Pimcore::getContainer()->get('pimcore.cache.pool');
        $client->setCache($cache);

        $client->setApplicationName('pimcore CMF');
        $client->setDeveloperKey(Config::getSystemConfiguration('services')['google']['simple_api_key']);

        return $client;
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    public static function getAnalyticsDimensions(): array
    {
        return self::getAnalyticsMetadataByType('DIMENSION');
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    public static function getAnalyticsMetrics(): array
    {
        return self::getAnalyticsMetadataByType('METRIC');
    }

    /**
     * @return mixed
     *
     * @throws \Exception
     * @throws \Exception
     */
    public static function getAnalyticsMetadata(): mixed
    {
        $client = \Pimcore::getContainer()->get('pimcore.http_client');
        $result = $client->get(self::ANALYTICS_API_URL.'metadata/ga/columns');

        return json_decode((string)$result->getBody(), true);
    }

    /**
     * @param string $type
     *
     * @return array
     *
     * @throws \Exception
     */
    protected static function getAnalyticsMetadataByType(string $type): array
    {
        $data = self::getAnalyticsMetadata();
        $translator = \Pimcore::getContainer()->get('translator');

        $result = [];
        foreach ($data['items'] as $item) {
            if ($item['attributes']['type'] == $type) {
                if (strpos($item['id'], 'XX') !== false) {
                    for ($i = 1; $i <= 5; $i++) {
                        $replace = (string) $i;
                        $name = str_replace('1', $replace, str_replace('01', $replace, $translator->trans($item['attributes']['uiName'], [], 'admin')));

                        if (in_array($item['id'], ['ga:dimensionXX', 'ga:metricXX'])) {
                            $name .= ' '.$replace;
                        }
                        $result[] = [
                            'id' => str_replace('XX', $replace, $item['id']),
                            'name' => $name,
                        ];
                    }
                } else {
                    $result[] = [
                        'id' => $item['id'],
                        'name' => $translator->trans($item['attributes']['uiName'], [], 'admin'),
                    ];
                }
            }
        }

        return $result;
    }
}
