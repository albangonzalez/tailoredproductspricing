<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProductsPricing\Form\Type;

use PrestaShopBundle\Form\Admin\Type\DisablingSwitchType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TailoredProductsSettingsType extends AbstractType
{
    private const FIELD_CLASS = 'js-tpp-dimension-field';
    private const FIELD_SELECTOR = '.js-tpp-dimension-field';
    private const FORM_THEME = '@PrestaShop/Admin/TwigTemplateForm/prestashop_ui_kit_base.html.twig';
    private const DOMAIN = 'Modules.Tailoredproductspricing.Admin';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $m = $options['tpp_module'];

        $builder
            ->add('tpp_heading', FormType::class, [
                'label' => 'Tailored Products Pricing',
                'label_tag_name' => 'h3',
                'mapped' => false,
                'required' => false,
                'form_theme' => self::FORM_THEME,
            ])
            ->add('tpp_enabled', DisablingSwitchType::class, [
                'label' => 'Enable price-per-square-meter for this product',
                'required' => false,
                'target_selector' => self::FIELD_SELECTOR,
                'disable_on_match' => true,
                'form_theme' => self::FORM_THEME,
            ])
            ->add('tpp_unit', ChoiceType::class, [
                'label' => 'Dimension unit',
                'required' => false,
                'choices' => ['cm' => 'cm', 'mm' => 'mm'],
                'placeholder' => false,
                'row_attr' => ['class' => self::FIELD_CLASS],
                'form_theme' => self::FORM_THEME,
            ]);

        foreach ([
            'tpp_min_width' => 'Min width',
            'tpp_max_width' => 'Max width',
            'tpp_min_height' => 'Min height',
            'tpp_max_height' => 'Max height',
        ] as $name => $label) {
            $builder->add($name, NumberType::class, [
                'label' => $label,
                'required' => false,
                'scale' => 2,
                'attr' => ['min' => '0', 'step' => '0.01'],
                'row_attr' => ['class' => self::FIELD_CLASS],
                'form_theme' => self::FORM_THEME,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('tpp_module');
        $resolver->setDefaults(['label' => false]);
    }
}
