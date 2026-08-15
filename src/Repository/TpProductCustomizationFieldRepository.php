<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Repository;

use Doctrine\ORM\EntityRepository;
use PrestaShop\Module\TailoredProducts\Entity\TpProductCustomizationField;

/**
 * Doctrine repository for `tp_product_customization_field`, built by the
 * `doctrine.orm.default_entity_manager`'s `getRepository` factory (see
 * config/services.yml / config/front/services.yml). It cannot receive
 * custom constructor dependencies — business logic belongs to
 * {@see \PrestaShop\Module\TailoredProducts\Service\CustomizationFieldRegistry}
 * (the sole writer) and {@see \PrestaShop\Module\TailoredProducts\Service\AddToCartCustomizer}
 * (a read-only consumer).
 */
class TpProductCustomizationFieldRepository extends EntityRepository
{
    /**
     * @return list<TpProductCustomizationField>
     */
    public function findByProductId(int $idProduct): array
    {
        return $this->findBy(['idProduct' => $idProduct]);
    }

    /**
     * Bulk-deletes every row for $idProduct via a DQL DELETE — delete-then-insert
     * semantics, not upsert, so "no row" reliably means "not provisioned".
     */
    public function deleteByProductId(int $idProduct): void
    {
        $this->createQueryBuilder('f')
            ->delete()
            ->where('f.idProduct = :idProduct')
            ->setParameter('idProduct', $idProduct)
            ->getQuery()
            ->execute();
    }
}
