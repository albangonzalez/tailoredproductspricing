<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Service;

use Doctrine\DBAL\Connection;

/**
 * The only place this module writes to the core-owned `customized_data`
 * table. Plain `UPDATE`s only — the row is guaranteed to already exist
 * (`Cart::addTextFieldToProduct()` inserted it one statement earlier), so no
 * INSERT / upsert branch is needed. See
 * .claude/docs/specs/add-to-cart-pricing.md §3.3.
 */
class PriceWriter
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function stamp(int $idCustomization, int $type, int $index, float $price): void
    {
        if ($idCustomization <= 0 || $index <= 0) {
            return;
        }

        $this->connection->createQueryBuilder()
            ->update(_DB_PREFIX_ . 'customized_data')
            ->set('price', ':price')
            ->where('id_customization = :idCustomization')
            ->andWhere('type = :type')
            ->andWhere('`index` = :index')
            ->setParameter('price', $price)
            ->setParameter('idCustomization', $idCustomization)
            ->setParameter('type', $type)
            ->setParameter('index', $index)
            ->executeStatement();
    }

    /**
     * Zeroes out `price` on this product's own tpp customization rows that
     * were not re-written this request (e.g. a deselected cassette on a
     * re-submit). Scoped by the known set of this product's tpp
     * `customization_field` ids rather than by `id_module` — `id_module` is
     * deliberately left at 0 (a non-zero value suppresses core's own
     * rendering of the row in cart/checkout), so it can no longer serve as
     * an ownership marker. A merchant-added field or another module's field
     * on the same $idCustomization can never be touched here, since its
     * index falls outside $moduleFieldIndexes.
     *
     * @param list<int> $moduleFieldIndexes
     * @param list<int> $keptIndexes
     */
    public function resetStaleRows(int $idCustomization, array $moduleFieldIndexes, array $keptIndexes): void
    {
        if ($moduleFieldIndexes === [] || $keptIndexes === []) {
            return;
        }

        $this->connection->createQueryBuilder()
            ->update(_DB_PREFIX_ . 'customized_data')
            ->set('price', ':zero')
            ->where('id_customization = :idCustomization')
            ->andWhere('`index` IN (:moduleFieldIndexes)')
            ->andWhere('`index` NOT IN (:keptIndexes)')
            ->setParameter('zero', 0)
            ->setParameter('idCustomization', $idCustomization)
            ->setParameter('moduleFieldIndexes', $moduleFieldIndexes, Connection::PARAM_INT_ARRAY)
            ->setParameter('keptIndexes', $keptIndexes, Connection::PARAM_INT_ARRAY)
            ->executeStatement();
    }
}
