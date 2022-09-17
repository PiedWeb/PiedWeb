<?php

namespace PiedWeb\SeoStatus\Admin;

use PiedWeb\SeoStatus\Entity\Proxy;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractAdmin<Proxy>
 */
final class ProxyAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form->add('proxy');
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list->add('proxy');
        $list->add('lastUsedAt');
        $list->add('googleBlacklist');
        $list->add('_actions', null, [
            'actions' => [
                'edit' => [],
                'delete' => [],
            ],
            'row_align' => 'right',
            'header_class' => 'text-right',
        ]);
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show->add('keyword');
    }
}
