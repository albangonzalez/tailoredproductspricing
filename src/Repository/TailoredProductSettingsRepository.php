<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Repository;

use Doctrine\ORM\EntityRepository;
use PrestaShop\Module\TailoredProducts\Entity\TailoredProductSettings;

/**
 * Doctrine repository for `tailored_product_settings`, built by the
 * `doctrine.orm.default_entity_manager`'s `getRepository` factory (see
 * config/services.yml). It cannot receive custom constructor dependencies —
 * business logic belongs to {@see \PrestaShop\Module\TailoredProducts\Service\ProductConfigManager}.
 */
class TailoredProductSettingsRepository extends EntityRepository
{
    public function findByProductId(int $idProduct): ?TailoredProductSettings
    {
        return $this->find($idProduct);
    }

    public function save(TailoredProductSettings $config): void
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
