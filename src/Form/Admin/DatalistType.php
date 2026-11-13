<?php

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Text input with a dropdown of suggestions that filters while typing (free
 * text still allowed), used to pick existing series names. Rendered as a
 * combobox by admin/_form_theme.html.twig.
 */
class DatalistType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['suggestions'] = $options['suggestions'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['suggestions' => []]);
        $resolver->setAllowedTypes('suggestions', 'array');
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
