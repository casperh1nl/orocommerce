<?php

namespace Oro\Bundle\PaymentTermBundle\Form\Type;

use Oro\Bundle\UIBundle\Form\DataTransformer\StripTagsTransformer;
use Oro\Bundle\UIBundle\Tools\HtmlTagHelper;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentTermType extends AbstractType
{
    const NAME = 'oro_payment_term';

    /** @var string */
    private $dataClass;

    /**
     * @var HtmlTagHelper $htmlTagHelper
     */
    private $htmlTagHelper;

    /**
     * @param string $dataClass
     */
    public function __construct($dataClass, HtmlTagHelper $htmlTagHelper)
    {
        $this->dataClass = $dataClass;
        $this->htmlTagHelper = $htmlTagHelper;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('label', TextType::class, ['required' => true, 'label' => 'oro.paymentterm.label.label']);

        $builder->get('label')->addModelTransformer(new StripTagsTransformer($this->htmlTagHelper));
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => $this->dataClass,
                'csrf_token_id' => 'payment_term',
            ]
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return self::NAME;
    }
}
