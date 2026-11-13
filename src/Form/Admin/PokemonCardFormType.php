<?php

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PokemonCardFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'row_attr' => ['class' => 'sm:col-span-2'],
                'label' => 'Name',
                'attr' => ['placeholder' => 'Pikachu ex', 'autofocus' => true],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ])
            ->add('url', UrlType::class, [
                'row_attr' => ['class' => 'sm:col-span-2'],
                'label' => 'Image URL',
                'required' => false,
                'default_protocol' => 'https',
                'help' => 'The card image is downloaded to the server when you save.',
                'attr' => ['placeholder' => 'https://assets.tcgdex.net/…/high.png', 'data-image-preview-source' => true],
                'constraints' => [new Assert\Url(), new Assert\Length(max: 255)],
            ])
            ->add('serie', DatalistType::class, [
                'label' => 'Serie',
                'suggestions' => $options['series_suggestions'],
                'attr' => ['placeholder' => 'Surging Sparks'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 255),
                    // The serie is part of the local image filename.
                    new Assert\Regex(pattern: '#[/\\\\]#', match: false, message: 'A serie cannot contain slashes.'),
                ],
            ])
            ->add('serie_nr', IntegerType::class, [
                'label' => 'Serie nr',
                'attr' => ['placeholder' => '58', 'min' => 0],
                'constraints' => [new Assert\NotNull(), new Assert\PositiveOrZero()],
            ])
            ->add('dex_nr', IntegerType::class, [
                'label' => 'Pokédex nr',
                'attr' => ['placeholder' => '25', 'min' => 0],
                'constraints' => [new Assert\NotNull(), new Assert\PositiveOrZero()],
            ])
            ->add('gen', IntegerType::class, [
                'label' => 'Generation',
                'attr' => ['placeholder' => '1', 'min' => 0],
                'constraints' => [new Assert\NotNull(), new Assert\PositiveOrZero()],
            ])
            ->add('list', DatalistType::class, [
                'label' => 'List',
                'suggestions' => $options['list_suggestions'],
                'help' => '"F" shows the card on the Final page.',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'series_suggestions' => [],
            'list_suggestions' => [],
        ]);
    }
}
