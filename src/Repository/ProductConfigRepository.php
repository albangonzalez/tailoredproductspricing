<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Repository;

use Doctrine\ORM\EntityRepository;
use PrestaShop\Module\TailoredProducts\Entity\TpProductConfig;

/**
 * Doctrine repository for `tp_product_config`, built by the
 * `doctrine.orm.default_entity_manager`'s `getRepository` factory (see
 * config/services.yml). It cannot receive custom constructor dependencies —
 * business logic belongs to {@see \PrestaShop\Module\TailoredProducts\Service\ProductConfigManager}.
 */
class ProductConfigRepository extends EntityRepository
{
    public function findByProductId(int $idProduct): ?TpProductConfig
    {
        return $this->find($idProduct);
    }

    public function save(TpProductConfig $config): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($config);
        $entityManager->flush();
    }

    public function removeByProductId(int $idProduct): void
    {
        $config = $this->find($idProduct);
        if ($config === null) {
            return;
        }

        $entityManager = $this->getEntityManager();
        $entityManager->remove($config);
        $entityManager->flush();
    }
}
