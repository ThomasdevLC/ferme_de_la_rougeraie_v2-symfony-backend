<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Repository\Admin\ProductRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;

class SoldOutProductCrudController extends AbstractCrudController
{
    private ProductRepository $productRepository;


    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('❌ Produit épuisé')
            ->setEntityLabelInPlural('❌ Produits épuisés')
            ->setPageTitle(Crud::PAGE_INDEX, '❌ Produits épuisés')
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            ImageField::new('image')
                ->setBasePath('/uploads/images')
                ->setLabel('Image')
                ->hideOnForm(),

            TextField::new('name', 'Nom')
                ->hideOnForm(),

        ];
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $soldOutIds = $this->productRepository->findSoldOutProductIds();

        if (empty($soldOutIds)) {
            return $qb->andWhere('1 = 0');
        }

        return $qb
            ->andWhere('entity.isDeleted = false')
            ->andWhere('entity.id IN (:ids)')
            ->setParameter('ids', $soldOutIds);
    }
}
