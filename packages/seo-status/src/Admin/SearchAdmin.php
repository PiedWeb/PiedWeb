<?php

namespace PiedWeb\SeoStatus\Admin;

use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Entity\Search\SearchGoogleData;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * @extends AbstractAdmin<Search>
 */
final class SearchAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form->add('keyword');
        $form->add('lang', ChoiceType::class, [
            'choices' => ['fr' => 'fr'],
        ]);
        $form->add('tld', ChoiceType::class, [
            'choices' => ['fr' => 'fr'],
        ]);
        $form->add('searchGoogleData.volume');
        $form->add('searchGoogleData.intent', ChoiceType::class, ['choices' => array_flip(SearchGoogleData::INTENT)]);
        $form->add('searchGoogleData.extractionFrequency', ChoiceType::class, ['choices' => SearchGoogleData::ExtractionFrequency]);
    }

    protected function configureDatagridFilters(DatagridMapper $datagrid): void
    {
        $datagrid->add('keyword');
        $datagrid->add('lang');
        $datagrid->add('tld');
        $datagrid->add('searchGoogleData.relatedSearches');
        $datagrid->add('searchGoogleData.lastExtractionAt');
        $datagrid->add('searchGoogleData.volume');
        $datagrid->add('searchGoogleData.intent');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list->add('search', null, ['edit' => 'inline', 'template' => '/admin/search/list_field_keyword.html.twig']);
        $list->add('searchGoogleData.lastExtractionAt');
        $list->add('searchGoogleData.nextExtractionFrom');
        $list->add('searchGoogleData.serpFeatures');
        $list->add('searchGoogleData.volume');
        $list->add('searchGoogleData.lastSearchResultsFirstPixelPos');
        $list->add('searchGoogleData.lastSearchResultsFirstHost');
        $list->add('_actions', null, [
            'actions' => [
                'edit' => [],
                'show' => [],
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
