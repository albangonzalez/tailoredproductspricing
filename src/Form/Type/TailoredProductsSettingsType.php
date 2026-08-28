<?php

declare(strict_types=1);

namespace PrestaShop\Module\TailoredProducts\Form\Type;

use PrestaShopBundle\Form\Admin\Type\DisablingSwitchType;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
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
    private const CASSETTE_FIELD_CLASS = 'js-tpp-cassette-field';
    private const CASSETTE_FIELD_SELECTOR = '.js-tpp-cassette-field';
    private const FORM_THEME = '@PrestaShop/Admin/TwigTemplateForm/prestashop_ui_kit_base.html.twig';
    private const DOMAIN = 'Modules.Tailoredproducts.Admin';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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

        $builder
            ->add('tpp_cassette_enabled', DisablingSwitchType::class, [
                'label' => 'Offer a cassette (cenefa) option for this product',
                'required' => false,
                'target_selector' => self::CASSETTE_FIELD_SELECTOR,
                'disable_on_match' => true,
                'row_attr' => ['class' => self::FIELD_CLASS],
                'translation_domain' => self::DOMAIN,
                'form_theme' => self::FORM_THEME,
            ])
            ->add('tpp_cassette_price', NumberType::class, [
                'label' => 'Cassette price per linear meter of width',
                'help' => 'Surcharge = this value × the width entered by the customer. Not applied to prices yet.',
                'required' => false,
                'scale' => 2,
                'attr' => ['min' => '0', 'step' => '0.01', 'placeholder' => '0.00'],
                'row_attr' => ['class' => self::CASSETTE_FIELD_CLASS],
                'translation_domain' => self::DOMAIN,
                'form_theme' => self::FORM_THEME,
            ])
            ->add('tpp_mechanism_color_group', ChoiceType::class, [
                'label' => 'Mechanism & cord color options',
                'help' => 'Pick an existing color attribute group (Catalog > Attributes & Features). Leave empty to not offer this choice. This choice does not affect the price.',
                'required' => false,
                'choices' => $options['color_attribute_groups'],
                'placeholder' => 'Not offered',
                'row_attr' => ['class' => self::FIELD_CLASS],
                'translation_domain' => self::DOMAIN,
                'form_theme' => self::FORM_THEME,
            ])
            ->add('tpp_roll_direction_enabled', SwitchType::class, [
                'label' => 'Offer a roll direction choice (standard / reverse)',
                'help' => 'Lets the customer choose whether the fabric unrolls off the back or the front of the tube. Does not affect the price.',
                'required' => false,
                'show_choices' => false,
                'row_attr' => ['class' => self::FIELD_CLASS],
                'translation_domain' => self::DOMAIN,
                'form_theme' => self::FORM_THEME,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['label' => false, 'color_attribute_groups' => []]);
        $resolver->setAllowedTypes('color_attribute_groups', 'array');
    }
}
