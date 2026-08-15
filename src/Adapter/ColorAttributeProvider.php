<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Adapter;

use Doctrine\DBAL\Connection;

/**
 * Read-only DBAL access to core's `attribute_group*` / `attribute*` tables.
 * Never writes — the module reads merchant-managed color attribute groups,
 * it never creates/edits/deletes core attribute data (module layering rule).
 */
final class ColorAttributeProvider
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @return list<array{id:int, name:string}> ordered by ag.position ASC
     */
    public function findColorGroups(int $idLang, int $idShop): array
    {
        if ($idShop <= 0) {
            return [];
        }

        $rows = $this->connection->createQueryBuilder()
            ->select('DISTINCT ag.id_attribute_group AS id', 'COALESCE(agl.name, \'\') AS name')
            ->from(_DB_PREFIX_ . 'attribute_group', 'ag')
            ->innerJoin('ag', _DB_PREFIX_ . 'attribute_group_shop', 'ags', 'ags.id_attribute_group = ag.id_attribute_group AND ags.id_shop = :idShop')
            ->leftJoin('ag', _DB_PREFIX_ . 'attribute_group_lang', 'agl', 'agl.id_attribute_group = ag.id_attribute_group AND agl.id_lang = :idLang')
            ->where('ag.group_type = :groupType')
            ->orderBy('ag.position', 'ASC')
            ->setParameter('idShop', $idShop)
            ->setParameter('idLang', $idLang)
            ->setParameter('groupType', 'color')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => ['id' => (int) $row['id'], 'name' => (string) $row['name']],
            $rows
        );
    }

    /**
     * @return list<array{id:int, name:string, color:?string}> ordered by a.position ASC
     */
    public function findGroupAttributes(int $idAttributeGroup, int $idLang, int $idShop): array
    {
        if ($idAttributeGroup <= 0 || $idShop <= 0) {
            return [];
        }

        $rows = $this->connection->createQueryBuilder()
            ->select('DISTINCT a.id_attribute AS id', 'COALESCE(al.name, \'\') AS name', 'a.color AS color')
            ->from(_DB_PREFIX_ . 'attribute', 'a')
            ->innerJoin('a', _DB_PREFIX_ . 'attribute_shop', 'as_', 'as_.id_attribute = a.id_attribute AND as_.id_shop = :idShop')
            ->leftJoin('a', _DB_PREFIX_ . 'attribute_lang', 'al', 'al.id_attribute = a.id_attribute AND al.id_lang = :idLang')
            ->where('a.id_attribute_group = :idAttributeGroup')
            ->orderBy('a.position', 'ASC')
            ->setParameter('idShop', $idShop)
            ->setParameter('idLang', $idLang)
            ->setParameter('idAttributeGroup', $idAttributeGroup)
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'color' => $row['color'] !== null ? (string) $row['color'] : null,
            ],
            $rows
        );
    }

    public function isColorGroup(int $idAttributeGroup, int $idShop): bool
    {
        if ($idAttributeGroup <= 0 || $idShop <= 0) {
            return false;
        }

        $id = $this->connection->createQueryBuilder()
            ->select('ag.id_attribute_group')
            ->from(_DB_PREFIX_ . 'attribute_group', 'ag')
            ->innerJoin('ag', _DB_PREFIX_ . 'attribute_group_shop', 'ags', 'ags.id_attribute_group = ag.id_attribute_group AND ags.id_shop = :idShop')
            ->where('ag.id_attribute_group = :idAttributeGroup')
            ->andWhere('ag.group_type = :groupType')
            ->setParameter('idShop', $idShop)
            ->setParameter('idAttributeGroup', $idAttributeGroup)
            ->setParameter('groupType', 'color')
            ->executeQuery()
            ->fetchOne();

        return $id !== false;
    }
}
