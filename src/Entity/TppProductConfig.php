<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity mirroring `tpp_product_config` (one row per product with
 * Tailored Products Pricing enabled). Natural, application-assigned PK
 * (`id_product`) — no GeneratedValue.
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="PrestaShop\Module\TailoredProductsPricing\Repository\ProductConfigRepository")
 */
class TppProductConfig
{
    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(name="id_product", type="integer", options={"unsigned"=true})
     */
    private $idProduct;

    /**
     * @var string|null
     *
     * @ORM\Column(name="min_width", type="decimal", precision=10, scale=2, nullable=true)
     */
    private $minWidth;

    /**
     * @var string|null
     *
     * @ORM\Column(name="max_width", type="decimal", precision=10, scale=2, nullable=true)
     */
    private $maxWidth;

    /**
     * @var string|null
     *
     * @ORM\Column(name="min_height", type="decimal", precision=10, scale=2, nullable=true)
     */
    private $minHeight;

    /**
     * @var string|null
     *
     * @ORM\Column(name="max_height", type="decimal", precision=10, scale=2, nullable=true)
     */
    private $maxHeight;

    /**
     * @var string
     *
     * @ORM\Column(name="unit", type="string", length=8, options={"default"="cm"})
     */
    private $unit = 'cm';

    /**
     * Cassette (cenefa) price, expressed per linear meter of the customer-entered
     * width: surcharge = value × width_m. Null means the cassette choice is not
     * offered for this product — there is no separate enabled flag.
     *
     * @var string|null
     *
     * @ORM\Column(name="cassette_price_per_meter", type="decimal", precision=20, scale=2, nullable=true)
     */
    private $cassettePricePerMeter;

    /**
     * Id of the core `attribute_group` row (`group_type = 'color'`) whose
     * attributes are offered as mechanism/cord color options for this product.
     * Null means the choice is not offered — there is no separate enabled flag.
     * The module only ever reads that group; it never creates or edits core
     * attribute data, and there is deliberately no SQL FK (see the spec).
     *
     * @var int|null
     *
     * @ORM\Column(name="id_attribute_group_mechanism_color", type="integer", options={"unsigned"=true}, nullable=true)
     */
    private $idAttributeGroupMechanismColor;

    /**
     * Whether the standard/reverse roll-direction choice is offered for this
     * product. Unlike the cassette price and the mechanism-color group, this
     * choice carries no value of its own, so it gets an explicit flag column
     * rather than overloading the nullness of a settings column — and never
     * the nullness of {@see self::$idCustomizationFieldRollDirection}, which
     * is provisioning state, not merchant intent.
     *
     * @var bool
     *
     * @ORM\Column(name="roll_direction_enabled", type="boolean", options={"unsigned"=true, "default"=0})
     */
    private $rollDirectionEnabled = false;

    /**
     * Id of the module-provisioned "Width" `customization_field` row
     * ({@see \PrestaShop\Module\TailoredProductsPricing\Service\CustomizationFieldRegistry}).
     * Null when the module is enabled but provisioning has not (yet)
     * recorded an id, or after deprovisioning.
     *
     * @var int|null
     *
     * @ORM\Column(name="id_customization_field_width", type="integer", options={"unsigned"=true}, nullable=true)
     */
    private $idCustomizationFieldWidth;

    /**
     * Id of the module-provisioned "Height" `customization_field` row.
     * See {@see self::$idCustomizationFieldWidth}.
     *
     * @var int|null
     *
     * @ORM\Column(name="id_customization_field_height", type="integer", options={"unsigned"=true}, nullable=true)
     */
    private $idCustomizationFieldHeight;

    /**
     * Id of the module-provisioned "Cord side" `customization_field` row.
     * See {@see self::$idCustomizationFieldWidth}.
     *
     * @var int|null
     *
     * @ORM\Column(name="id_customization_field_cord_side", type="integer", options={"unsigned"=true}, nullable=true)
     */
    private $idCustomizationFieldCordSide;

    /**
     * Id of the module-provisioned "Cassette" `customization_field` row.
     * Unlike the width/height/cord-side ids, this one is null whenever the
     * cassette choice is off for the product (i.e. whenever
     * {@see self::$cassettePricePerMeter} is null).
     * See {@see self::$idCustomizationFieldWidth}.
     *
     * @var int|null
     *
     * @ORM\Column(name="id_customization_field_cassette", type="integer", options={"unsigned"=true}, nullable=true)
     */
    private $idCustomizationFieldCassette;

    /**
     * Id of the module-provisioned "Mechanism color" `customization_field`
     * row. Null whenever the mechanism-color choice is off for the product
     * (i.e. whenever {@see self::$idAttributeGroupMechanismColor} is null).
     * See {@see self::$idCustomizationFieldWidth}.
     *
     * @var int|null
     *
     * @ORM\Column(name="id_customization_field_mechanism_color", type="integer", options={"unsigned"=true}, nullable=true)
     */
    private $idCustomizationFieldMechanismColor;

    /**
     * Id of the module-provisioned "Roll direction" `customization_field` row.
     * Null whenever the roll-direction choice is off for the product (i.e.
     * whenever {@see self::$rollDirectionEnabled} is false).
     * See {@see self::$idCustomizationFieldWidth}.
     *
     * @var int|null
     *
     * @ORM\Column(name="id_customization_field_roll_direction", type="integer", options={"unsigned"=true}, nullable=true)
     */
    private $idCustomizationFieldRollDirection;

    public function __construct(int $idProduct)
    {
        $this->idProduct = $idProduct;
    }

    public function getIdProduct(): int
    {
        return $this->idProduct;
    }

    public function getMinWidth(): ?string
    {
        return $this->minWidth;
    }

    public function setMinWidth(?string $minWidth): self
    {
        $this->minWidth = $minWidth;

        return $this;
    }

    public function getMaxWidth(): ?string
    {
        return $this->maxWidth;
    }

    public function setMaxWidth(?string $maxWidth): self
    {
        $this->maxWidth = $maxWidth;

        return $this;
    }

    public function getMinHeight(): ?string
    {
        return $this->minHeight;
    }

    public function setMinHeight(?string $minHeight): self
    {
        $this->minHeight = $minHeight;

        return $this;
    }

    public function getMaxHeight(): ?string
    {
        return $this->maxHeight;
    }

    public function setMaxHeight(?string $maxHeight): self
    {
        $this->maxHeight = $maxHeight;

        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    public function getIdCustomizationFieldWidth(): ?int
    {
        return $this->idCustomizationFieldWidth;
    }

    public function setIdCustomizationFieldWidth(?int $idCustomizationFieldWidth): self
    {
        $this->idCustomizationFieldWidth = $idCustomizationFieldWidth;

        return $this;
    }

    public function getIdCustomizationFieldHeight(): ?int
    {
        return $this->idCustomizationFieldHeight;
    }

    public function setIdCustomizationFieldHeight(?int $idCustomizationFieldHeight): self
    {
        $this->idCustomizationFieldHeight = $idCustomizationFieldHeight;

        return $this;
    }

    public function getIdCustomizationFieldCordSide(): ?int
    {
        return $this->idCustomizationFieldCordSide;
    }

    public function setIdCustomizationFieldCordSide(?int $idCustomizationFieldCordSide): self
    {
        $this->idCustomizationFieldCordSide = $idCustomizationFieldCordSide;

        return $this;
    }

    public function getCassettePricePerMeter(): ?string
    {
        return $this->cassettePricePerMeter;
    }

    public function setCassettePricePerMeter(?string $cassettePricePerMeter): self
    {
        $this->cassettePricePerMeter = $cassettePricePerMeter;

        return $this;
    }

    public function getIdCustomizationFieldCassette(): ?int
    {
        return $this->idCustomizationFieldCassette;
    }

    public function setIdCustomizationFieldCassette(?int $idCustomizationFieldCassette): self
    {
        $this->idCustomizationFieldCassette = $idCustomizationFieldCassette;

        return $this;
    }

    public function getIdAttributeGroupMechanismColor(): ?int
    {
        return $this->idAttributeGroupMechanismColor;
    }

    public function setIdAttributeGroupMechanismColor(?int $idAttributeGroupMechanismColor): self
    {
        $this->idAttributeGroupMechanismColor = $idAttributeGroupMechanismColor;

        return $this;
    }

    public function getIdCustomizationFieldMechanismColor(): ?int
    {
        return $this->idCustomizationFieldMechanismColor;
    }

    public function setIdCustomizationFieldMechanismColor(?int $idCustomizationFieldMechanismColor): self
    {
        $this->idCustomizationFieldMechanismColor = $idCustomizationFieldMechanismColor;

        return $this;
    }

    public function isRollDirectionEnabled(): bool
    {
        return $this->rollDirectionEnabled;
    }

    public function setRollDirectionEnabled(bool $rollDirectionEnabled): self
    {
        $this->rollDirectionEnabled = $rollDirectionEnabled;

        return $this;
    }

    public function getIdCustomizationFieldRollDirection(): ?int
    {
        return $this->idCustomizationFieldRollDirection;
    }

    public function setIdCustomizationFieldRollDirection(?int $idCustomizationFieldRollDirection): self
    {
        $this->idCustomizationFieldRollDirection = $idCustomizationFieldRollDirection;

        return $this;
    }
}
