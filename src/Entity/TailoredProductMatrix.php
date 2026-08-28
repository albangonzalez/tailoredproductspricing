<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="PrestaShop\Module\TailoredProducts\Repository\TailoredProductMatrixRepository")
 */
class TailoredProductMatrix
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $idProduct;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $widthCeiling;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private $heightCeiling;

    /**
     * @var string
     *
     * @ORM\Column(type="decimal", precision=20, scale=6)
     */
    private $price;

    public function __construct(int $idProduct, int $widthCeiling, int $heightCeiling, string $price)
    {
        $this->idProduct = $idProduct;
        $this->widthCeiling = $widthCeiling;
        $this->heightCeiling = $heightCeiling;
        $this->price = $price;
    }

    public function getIdProduct(): int
    {
        return $this->idProduct;
    }

    public function getWidthCeiling(): int
    {
        return $this->widthCeiling;
    }

    public function getHeightCeiling(): int
    {
        return $this->heightCeiling;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;

        return $this;
    }
}
