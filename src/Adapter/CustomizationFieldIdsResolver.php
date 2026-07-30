<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Adapter;

use Doctrine\DBAL\Connection;

/**
 * Resolves the core `customization_field` ids provisioned for a tpp-enabled
 * product's width/height dimensions.
 *
 * `customization_field` is a core-owned table the module must not define a
 * competing Doctrine entity for, so this goes through a parameterized DBAL
 * `Connection` query rather than `Db::getInstance()`.
 */
final class CustomizationFieldIdsResolver
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @return array{0: int, 1: int}|null [$widthFieldId, $heightFieldId], or null unless
     *                                     exactly 2 module-owned text fields exist for the product.
     */
    public function resolve(int $idProduct): ?array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('id_customization_field')
            ->from(_DB_PREFIX_ . 'customization_field')
            ->where('id_product = :idProduct')
            ->andWhere('type = :type')
            ->andWhere('is_deleted = 0')
            ->andWhere('is_module = 1')
            ->orderBy('id_customization_field', 'ASC')
            ->setParameter('idProduct', $idProduct)
            ->setParameter('type', 1)
            ->executeQuery()
            ->fetchFirstColumn();

        if (count($rows) !== 2) {
            return null;
        }

        return [(int) $rows[0], (int) $rows[1]];
    }
}
