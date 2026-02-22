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
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
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
            ->setPageTitle(
                Crud::PAGE_EDIT,
                fn (Product $product) => '📦 Modifier quantité stock : ' . $product->getName()
            )
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureActions(Actions $actions): Actions
    {
        $returnAction = Action::new('Retour')
            ->linkToUrl('/admin/sold-out-product');

        return $actions
            ->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                fn (Action $action) => $action
                    ->setLabel('Remettre en stock')
                    ->setIcon('fa fa-box-open')
            )
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE,)
            ->add(Crud::PAGE_EDIT, $returnAction)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);

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

            BooleanField::new('hasStock')
                ->hideOnIndex()
                ->setLabel('Stock')
                ->setFormTypeOption('row_attr', ['class' => 'has-stock-wrapper flex-stock-row']),

            IntegerField::new('stock')
                ->onlyOnForms()
                ->setFormTypeOption('attr', [
                    'min' => 0,
                ])
                ->setFormTypeOption('row_attr', ['class' => 'stock-wrapper']),

            BooleanField::new('limited')->setLabel('Qté Limitée')
                ->hideOnIndex()

            ,


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
