<?php
/**
 * Created by PhpStorm.
 * User: samuel
 * Date: 7/30/16
 * Time: 11:45 AM
 */

namespace Foundation\Bundle\Form\Type;


use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AjaxImageType extends AbstractType
{

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['default_image'] = $options['empty_data'];
        $view->vars['upload_destination'] = $options['upload_destination'];
        $view->vars['upload_url'] = $options['upload_url'];
    }


    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => null,
            'upload_destination' => '/files',
            'upload_url' => null
        ));
    }

    public function getParent()
    {
        return FileType::class;
    }


    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        return 'ajax_image';
    }

}