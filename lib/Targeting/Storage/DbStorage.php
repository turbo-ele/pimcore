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

namespace Pimcore\Targeting\Storage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Pimcore\Targeting\Model\VisitorInfo;
use Pimcore\Targeting\Storage\Traits\TimestampsTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DbStorage implements TargetingStorageInterface, MaintenanceStorageInterface
{
    use TimestampsTrait;

    private Connection $db;

    private string $tableName = 'targeting_storage';

    public function __construct(Connection $db, array $options = [])
    {
        $this->db = $db;

        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);

        $this->handleOptions($resolver->resolve($options));
    }

    protected function handleOptions(array $options): void
    {
        $this->tableName = $options['tableName'];
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'tableName' => 'targeting_storage',
        ]);

        $resolver->setRequired(['tableName']);
        $resolver->setAllowedTypes('tableName', 'string');
    }

    public function all(VisitorInfo $visitorInfo, string $scope): array
    {
        if (!$visitorInfo->hasVisitorId()) {
            return [];
        }

        $qb = $this->db->createQueryBuilder();
        $qb
            ->select('name', 'value')
            ->from($this->tableName)
            ->where('visitorId = :visitorId')
            ->andWhere('scope = :scope')
            ->andWhere('name != :metaKey');

        $qb->setParameters([
            'visitorId' => $visitorInfo->getVisitorId(),
            'scope' => $scope,
            'metaKey' => self::STORAGE_KEY_META_ENTRY,
        ]);

        $this->addExpiryParam($qb, $scope);

        $stmt = $qb->executeQuery();
        $data = [];

        if ($stmt instanceof Result) {
            while ($row = $stmt->fetchAssociative()) {
                $data[$row['name']] = json_decode($row['value'], true);
            }
        }

        return $data;
    }

    public function has(VisitorInfo $visitorInfo, string $scope, string $name): bool
    {
        if (!$visitorInfo->hasVisitorId()) {
            return false;
        }

        $qb = $this->db->createQueryBuilder();
        $qb
            ->select('COUNT(name) as count')
            ->from($this->tableName)
            ->where('visitorId = :visitorId')
            ->andWhere('scope = :scope')
            ->andWhere('name = :name');

        $qb->setParameters([
            'visitorId' => $visitorInfo->getVisitorId(),
            'scope' => $scope,
            'name' => $name,
        ]);

        $this->addExpiryParam($qb, $scope);

        $stmt = $qb->executeQuery();
        $result = 0;

        if ($stmt instanceof Result) {
            $result = (int)$stmt->fetchOne();
        }

        return 1 === $result;
    }

    public function set(VisitorInfo $visitorInfo, string $scope, string $name, mixed $value): void
    {
        if (!$visitorInfo->hasVisitorId()) {
            return;
        }

        $json = json_encode($value);

        $query = <<<EOF
INSERT INTO {$this->tableName}
    (visitorId, scope, name, value, creationDate, modificationDate)
VALUES
    (:visitorId, :scope, :name, :value, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    value = :value, modificationDate = NOW();
EOF;

        $this->db->executeQuery(
            $query,
            [
                'visitorId' => $visitorInfo->getVisitorId(),
                'scope' => $scope,
                'name' => $name,
                'value' => $json,
            ]
        );

        $this->cleanup($scope);
    }

    /**
     * {@inheritdoc}
     */
    public function get(VisitorInfo $visitorInfo, string $scope, string $name, mixed $default = null): mixed
    {
        if (!$visitorInfo->hasVisitorId()) {
            return $default;
        }

        $qb = $this->db->createQueryBuilder();
        $qb
            ->select('value')
            ->from($this->tableName)
            ->where('visitorId = :visitorId')
            ->andWhere('scope = :scope')
            ->andWhere('name = :name');

        $qb->setParameters([
            'visitorId' => $visitorInfo->getVisitorId(),
            'scope' => $scope,
            'name' => $name,
        ]);

        $this->addExpiryParam($qb, $scope);

        $stmt = $qb->executeQuery();
        $result = false;

        if ($stmt instanceof Result) {
            $result = $stmt->fetchOne();
        }

        if (!$result) {
            return $default;
        }

        $decoded = json_decode($result, true);
        if (!$decoded) {
            return $default;
        }

        return $decoded;
    }

    /**
     * {@inheritdoc }
     */
    public function clear(VisitorInfo $visitorInfo, string $scope = null): void
    {
        if (!$visitorInfo->hasVisitorId()) {
            return;
        }

        if (null === $scope) {
            $this->db->executeQuery(
                'DELETE FROM ' . $this->tableName . ' WHERE visitorId = :visitorId',
                [
                    'visitorId' => $visitorInfo->getVisitorId(),
                ]
            );
        } else {
            $this->db->executeQuery(
                'DELETE FROM ' . $this->tableName . ' WHERE visitorId = :visitorId AND scope = :scope',
                [
                    'visitorId' => $visitorInfo->getVisitorId(),
                    'scope' => $scope,
                ]
            );
        }
    }

    public function migrateFromStorage(TargetingStorageInterface $storage, VisitorInfo $visitorInfo, string $scope): void
    {
        // only allow migration if a visitor ID is available as otherwise the fallback
        // would clear the original storage although data was not stored
        if (!$visitorInfo->hasVisitorId()) {
            throw new \LogicException('Can\'t migrate to DB storage as no visitor ID is set');
        }

        $values = $storage->all($visitorInfo, $scope);

        $this->db->beginTransaction();

        try {
            foreach ($values as $name => $value) {
                $this->set($visitorInfo, $scope, $name, $value);
            }

            $this->updateTimestamps(
                $visitorInfo,
                $scope,
                $storage->getCreatedAt($visitorInfo, $scope),
                $storage->getUpdatedAt($visitorInfo, $scope)
            );

            $this->cleanup($scope);

            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();

            throw $e;
        }
    }

    public function getCreatedAt(VisitorInfo $visitorInfo, string $scope): ?\DateTimeImmutable
    {
        return $this->loadDate($visitorInfo, $scope, 'MIN(creationDate)');
    }

    public function getUpdatedAt(VisitorInfo $visitorInfo, string $scope): ?\DateTimeImmutable
    {
        return $this->loadDate($visitorInfo, $scope, 'MAX(modificationDate)');
    }

    /**
     * {@inheritdoc }
     */
    public function maintenance(): void
    {
        // clean up expired keys scopes with an expiration
        foreach (self::VALID_SCOPES as $scope) {
            $expiry = $this->expiryFor($scope);

            if ($expiry > 0) {
                $this->cleanup($scope);
            }
        }
    }

    private function loadDate(VisitorInfo $visitorInfo, string $scope, string $select): ?\DateTimeImmutable
    {
        if (!$visitorInfo->hasVisitorId()) {
            return null;
        }

        $qb = $this->db->createQueryBuilder();
        $qb
            ->select($select)
            ->from($this->tableName)
            ->where('visitorId = :visitorId')
            ->andWhere('scope = :scope');

        $qb->setParameters([
            'visitorId' => $visitorInfo->getVisitorId(),
            'scope' => $scope,
        ]);

        $this->addExpiryParam($qb, $scope);

        $stmt = $qb->executeQuery();

        if ($stmt instanceof Result) {
            return $this->convertToDateTime($stmt->fetchOne());
        }

        return null;
    }

    private function convertToDateTime(mixed $result = null): ?\DateTimeImmutable
    {
        if (!$result) {
            return null;
        }

        $dateTime = $this->db->convertToPHPValue($result, Types::DATETIME_MUTABLE);

        return \DateTimeImmutable::createFromMutable($dateTime);
    }

    private function updateTimestamps(
        VisitorInfo $visitorInfo,
        string $scope,
        \DateTimeInterface $createdAt = null,
        \DateTimeInterface $updatedAt = null
    ): void {
        $timestamps = $this->normalizeTimestamps($createdAt, $updatedAt);

        $query = <<<EOF
INSERT INTO {$this->tableName}
    (visitorId, scope, name, value, creationDate, modificationDate)
VALUES
    (:visitorId, :scope, :name, :value, :creationDate, :modificationDate)
ON DUPLICATE KEY UPDATE
    value = :value, creationDate = :creationDate, modificationDate = :modificationDate;
EOF;

        $this->db->executeQuery(
            $query,
            [
                'visitorId' => $visitorInfo->getVisitorId(),
                'scope' => $scope,
                'name' => self::STORAGE_KEY_META_ENTRY,
                'value' => 1,
                'creationDate' => $timestamps['createdAt'],
                'modificationDate' => $timestamps['updatedAt'],
            ],
            [
                'creationDate' => Types::DATETIME_MUTABLE,
                'modificationDate' => Types::DATETIME_MUTABLE,
            ]
        );
    }

    protected function expiryFor(string $scope): int
    {
        $expiry = 0;
        if (self::SCOPE_SESSION === $scope) {
            $expiry = 30 * 60; // 30 minutes
        }

        return $expiry;
    }

    private function addExpiryParam(QueryBuilder $qb, string $scope): void
    {
        $expiry = $this->expiryFor($scope);
        if (0 === $expiry) {
            return;
        }

        $qb->andWhere('modificationDate >= (NOW() - INTERVAL :expiry SECOND)');
        $qb->setParameter('expiry', $expiry);
    }

    private function cleanup(string $scope): void
    {
        $expiry = $this->expiryFor($scope);
        if (0 === $expiry) {
            return;
        }

        $this->db->executeQuery(
            'DELETE FROM ' . $this->tableName . ' WHERE scope = :scope AND modificationDate < (NOW() - INTERVAL :expiry SECOND)',
            [
                'scope' => $scope,
                'expiry' => $expiry,
            ]
        );
    }
}
