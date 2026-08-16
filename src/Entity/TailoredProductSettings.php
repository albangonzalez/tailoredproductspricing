<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity mirroring `tailored_product_settings` (one row per product
 * with Tailored Products Pricing enabled). Natural, application-assigned PK
 * (`id_product`) — no GeneratedValue.
 *
 * Deliberately no `@ORM\Table(name=...)`: an explicit table name bypasses
 * `DoctrineNamingStrategy`'s automatic `ps_` prefixing and causes a hard
 * "table doesn't exist" fatal in production (see commit `5f811d7`, which
 * fixed exactly this on the entities this one used to be named
 * `TpProductConfig`/`TpCombinationConfig`). Let the naming strategy derive
 * `tailored_product_settings` from the class name and apply the prefix.
 *
 * @ORM\Entity(repositoryClass="PrestaShop\Module\TailoredProducts\Repository\TailoredProductSettingsRepository")
 */
class TailoredProductSettings
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
     * the nullness of the module's provisioned `customization_field` id for
     * this slug (now recorded in `tailored_product_customization_field`, see
     * {@see \PrestaShop\Module\TailoredProducts\Service\CustomizationFieldRegistry}),
     * which is provisioning state, not merchant intent.
     *
     * @var bool
     *
     * @ORM\Column(name="roll_direction_enabled", type="boolean", options={"unsigned"=true, "default"=0})
     */
    private $rollDirectionEnabled = false;

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

    public function getCassettePricePerMeter(): ?string
    {
        return $this->cassettePricePerMeter;
    }

    public function setCassettePricePerMeter(?string $cassettePricePerMeter): self
    {
        $this->cassettePricePerMeter = $cassettePricePerMeter;

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

    public function isRollDirectionEnabled(): bool
    {
        return $this->rollDirectionEnabled;
    }

    public function setRollDirectionEnabled(bool $rollDirectionEnabled): self
    {
        $this->rollDirectionEnabled = $rollDirectionEnabled;

        return $this;
    }
}
