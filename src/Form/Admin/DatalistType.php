<?php

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Text input with a native <datalist> of suggestions (free text still allowed),
 * used to autocomplete existing series names.
 */
class DatalistType extends AbstractType
{
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['suggestions'] = $options['suggestions'];
        $view->vars['attr']['list'] = $view->vars['id'].'_suggestions';
        $view->vars['attr']['autocomplete'] = 'off';
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
