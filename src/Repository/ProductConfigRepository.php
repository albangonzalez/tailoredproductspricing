<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Repository;

use Doctrine\ORM\EntityRepository;
use PrestaShop\Module\TailoredProductsPricing\Entity\TppProductConfig;

/**
 * Doctrine repository for `tpp_product_config`, built by the
 * `doctrine.orm.default_entity_manager`'s `getRepository` factory (see
 * config/services.yml). It cannot receive custom constructor dependencies —
 * business logic belongs to {@see \PrestaShop\Module\TailoredProductsPricing\Service\ProductConfigManager}.
 */
class ProductConfigRepository extends EntityRepository
{
    public function findByProductId(int $idProduct): ?TppProductConfig
    {
        return $this->find($idProduct);
    }

    public function save(TppProductConfig $config): void
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
