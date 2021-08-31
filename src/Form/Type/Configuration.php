<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ChampsLibres\WopiTestBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class Configuration extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('server', TextType::class, ['label' => 'WOPI client'])
            ->add('access_token', TextType::class, ['label' => 'WOPI access token'])
            ->add('access_token_ttl', IntegerType::class, ['label' => 'WOPI access token ttl'])
            ->add(
                'submit',
                SubmitType::class,
                [
                    'label' => 'Save configuration',
                ]
            )
            ->add(
                'reset',
                SubmitType::class,
                [
                    'label' => 'Reset configuration',
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
