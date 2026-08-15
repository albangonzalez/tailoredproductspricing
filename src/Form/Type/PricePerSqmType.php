<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The per-combination "price per m²" field, rendered in the Combination
 * modal. Kept as a root scalar (getParent() = NumberType) so the mapped
 * data key stays `tpp_price_per_sqm` at the form root, unchanged from the
 * previous inline field definition.
 */
final class PricePerSqmType extends AbstractType
{
    public function getParent(): string
    {
        return NumberType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'Price per m²',
            'translation_domain' => 'Modules.Tailoredproducts.Admin',
            'scale' => 2,
            'required' => false,
            'attr' => [
                'placeholder' => '0.00',
                'min' => '0',
                'step' => 'any',
            ],
            'form_theme' => '@PrestaShop/Admin/TwigTemplateForm/prestashop_ui_kit_base.html.twig',
        ]);
    }
}
