<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Repository;

use Doctrine\ORM\EntityRepository;
use PrestaShop\Module\TailoredProducts\Entity\TailoredProductAttribute;

/**
 * Doctrine repository for `tailored_product_attribute`, built by
 * the `doctrine.orm.default_entity_manager`'s `getRepository` factory (see
 * config/services.yml). Supersedes the previous raw-SQL
 * `Adapter\CombinationConfigRepository`.
 */
class TailoredProductAttributeRepository extends EntityRepository
{
    public function findByPk(int $idProductAttribute): ?TailoredProductAttribute
    {
        return $this->find($idProductAttribute);
    }

    public function getUnitPrice(int $idProductAttribute): float
    {
        $config = $this->findByPk($idProductAttribute);

        return $config ? (float) $config->getUnitPrice() : 0.0;
    }

    public function save(int $idProductAttribute, float $unitPrice): void
    {
        if ($idProductAttribute <= 0) {
            return;
        }

        $entityManager = $this->getEntityManager();
        $config = $this->findByPk($idProductAttribute);

        if ($config === null) {
            $config = new TailoredProductAttribute($idProductAttribute);
        }

        $config->setUnitPrice((string) $unitPrice);

        $entityManager->persist($config);
        $entityManager->flush();
    }
}
