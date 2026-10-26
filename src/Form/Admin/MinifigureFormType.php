<?php

namespace App\Form\Admin;

use App\Entity\Minifigure;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class MinifigureFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'row_attr' => ['class' => 'sm:col-span-2'],
                'label' => 'Name',
                'attr' => ['placeholder' => 'Venom', 'autofocus' => true],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ])
            ->add('url', UrlType::class, [
                'row_attr' => ['class' => 'sm:col-span-2'],
                'label' => 'Image URL',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'The image is downloaded to the server when you save.',
                'attr' => ['placeholder' => 'https://img.bricklink.com/…/sh0912.png', 'data-image-preview-source' => true],
                'constraints' => [new Assert\Url(), new Assert\Length(max: 255)],
            ])
            ->add('series', DatalistType::class, [
                'label' => 'Series',
                'suggestions' => $options['series_suggestions'],
                'attr' => ['placeholder' => 'Super Heroes'],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ])
            ->add('identifier', TextType::class, [
                'label' => 'Identifier',
                'help' => 'BrickLink ID, e.g. sh0912. Not on BrickLink yet? Use a temporary ID like "'.Minifigure::TEMPORARY_PREFIX.'venom".',
                'attr' => ['placeholder' => 'sh0912', 'data-temp-id-target' => true, 'spellcheck' => 'false'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 255),
                    // The identifier is used as the local image filename.
                    new Assert\Regex(
                        pattern: '/^[A-Za-z0-9][A-Za-z0-9._-]*$/',
                        message: 'Use only letters, numbers, dots, dashes and underscores.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['series_suggestions' => []]);
    }
}
