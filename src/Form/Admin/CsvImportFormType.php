<?php

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Upload a CSV and import it (cards or minifigures, see ToolsController).
 */
class CsvImportFormType extends AbstractType
{
    public const MODE_ADD = 'add';
    public const MODE_REPLACE = 'replace';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'CSV file',
                'row_attr' => ['class' => 'sm:col-span-2'],
                'attr' => ['accept' => '.csv,text/csv'],
                'constraints' => [
                    new Assert\NotNull(message: 'Choose a CSV file.'),
                    new Assert\File(
                        maxSize: '10M',
                        mimeTypes: [
                            'text/csv',
                            'text/plain',
                            'application/csv',
                            'application/x-csv',
                            'text/x-csv',
                            'text/comma-separated-values',
                            'text/x-comma-separated-values',
                            'application/vnd.ms-excel',
                        ],
                        mimeTypesMessage: 'Please upload a valid CSV file.',
                    ),
                ],
            ])
            ->add('mode', ChoiceType::class, [
                'label' => 'Mode',
                'choices' => [
                    'Add to collection' => self::MODE_ADD,
                    $options['replace_label'] => self::MODE_REPLACE,
                ],
                'data' => self::MODE_ADD,
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('delimiter', ChoiceType::class, [
                'label' => 'Delimiter',
                'choices' => [
                    'Semicolon ( ; )' => ';',
                    'Comma ( , )' => ',',
                    'Tab' => "\t",
                ],
                'data' => $options['default_delimiter'],
                'constraints' => [new Assert\NotBlank()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'replace_label' => 'Replace everything',
            'default_delimiter' => ';',
        ]);
    }
}
