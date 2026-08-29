<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Repository;

use Doctrine\ORM\EntityRepository;
use PrestaShop\Module\TailoredProducts\Entity\TailoredProductMatrix;

class TailoredProductMatrixRepository extends EntityRepository
{
    public function findCell(int $idProduct, int $widthCeiling, int $heightCeiling): ?TailoredProductMatrix
    {
        return $this->find([
            'idProduct' => $idProduct,
            'widthCeiling' => $widthCeiling,
            'heightCeiling' => $heightCeiling,
        ]);
    }

    public function resolveCeilings(int $idProduct, int $width, int $height): array
    {
        // DQL's general CASE expression requires an ELSE clause, but a bare
        // NULL literal isn't a valid DQL scalar expression — bind it as a
        // parameter instead, which is accepted regardless of its value.
        $result = $this->createQueryBuilder('m')
            ->select(
                'MIN(CASE WHEN m.widthCeiling >= :width THEN m.widthCeiling ELSE :null END) AS width_ceiling',
                'MIN(CASE WHEN m.heightCeiling >= :height THEN m.heightCeiling ELSE :null END) AS height_ceiling'
            )
            ->where('m.idProduct = :idProduct')
            ->setParameter('idProduct', $idProduct)
            ->setParameter('width', $width)
            ->setParameter('height', $height)
            ->setParameter('null', null)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'width_ceiling' => $result !== null && $result['width_ceiling'] !== null ? (int) $result['width_ceiling'] : null,
            'height_ceiling' => $result !== null && $result['height_ceiling'] !== null ? (int) $result['height_ceiling'] : null,
        ];
    }
}
