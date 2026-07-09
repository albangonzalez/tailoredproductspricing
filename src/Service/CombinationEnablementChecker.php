<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Service;

use Doctrine\DBAL\Connection;
use PrestaShop\Module\TailoredProductsPricing\Repository\ProductConfigRepository;

/**
 * Resolves whether Tailored Products Pricing is enabled for the product
 * behind a given combination.
 *
 * This is the module's one sanctioned non-ORM data access: `product_attribute`
 * is a core-owned table the module must not define a competing Doctrine
 * entity for, so the id_product_attribute -> id_product lookup goes through
 * a parameterized DBAL `Connection` query rather than `Db::getInstance()`.
 */
final class CombinationEnablementChecker
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ProductConfigRepository $productConfigRepository
    ) {
    }

    public function isModuleEnabledForCombination(int $idProductAttribute): bool
    {
        if ($idProductAttribute <= 0) {
            return false;
        }

        $idProduct = (int) $this->connection->createQueryBuilder()
            ->select('id_product')
            ->from(_DB_PREFIX_ . 'product_attribute')
            ->where('id_product_attribute = :idProductAttribute')
            ->setParameter('idProductAttribute', $idProductAttribute)
            ->executeQuery()
            ->fetchOne();

        if ($idProduct <= 0) {
            return false;
        }

        return $this->productConfigRepository->findByProductId($idProduct) !== null;
    }
}
