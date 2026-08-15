<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Repository;

use Doctrine\ORM\EntityRepository;
use PrestaShop\Module\TailoredProducts\Entity\TppCombinationConfig;

/**
 * Doctrine repository for `tpp_combination_config`, built by the
 * `doctrine.orm.default_entity_manager`'s `getRepository` factory (see
 * config/services.yml). Supersedes the previous raw-SQL
 * `Adapter\CombinationConfigRepository`.
 */
class CombinationConfigRepository extends EntityRepository
{
    public function findByPk(int $idProductAttribute): ?TppCombinationConfig
    {
        return $this->find($idProductAttribute);
    }

    public function getPricePerSqm(int $idProductAttribute): float
    {
        $config = $this->findByPk($idProductAttribute);

        return $config ? (float) $config->getPricePerSqm() : 0.0;
    }

    public function save(int $idProductAttribute, float $pricePerSqm): void
    {
        if ($idProductAttribute <= 0) {
            return;
        }

        $entityManager = $this->getEntityManager();
        $config = $this->findByPk($idProductAttribute);

        if ($config === null) {
            $config = new TppCombinationConfig($idProductAttribute);
        }

        $config->setPricePerSqm((string) $pricePerSqm);

        $entityManager->persist($config);
        $entityManager->flush();
    }
}
