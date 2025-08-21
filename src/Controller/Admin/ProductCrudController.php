<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Enum\ProductUnit;
use App\Service\Admin\ProductService;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;


class ProductCrudController extends AbstractCrudController
{
    private Security $security;
    private ProductService $productService;

    public function __construct(Security $security, ProductService $productService)
    {
        $this->security = $security;
        $this->productService = $productService;
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }


    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addJsFile('js/admin/product-form.js')
            ->addCssFile('css/admin/custom.css');
    }



    public function configureCrud(Crud $crud): Crud
    {

        $soldOutNames = $this->productService->getSoldOutProducts();

        if (!empty($soldOutNames)) {
            $this->addFlash(
                'warning',
                sprintf(' Produits épuisés : %s', implode(', ', $soldOutNames))
            );
        }


        return $crud
            ->setEntityLabelInSingular('🥕 Produit')
            ->setEntityLabelInPlural('🥕 Produits')
            ->setPageTitle(Crud::PAGE_INDEX, '🥕 Produits')
            ->setPaginatorPageSize(10)
            ->setDefaultSort([
                'isDisplayed' => 'DESC',
                'updatedAt'   => 'DESC',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->hideOnForm()
                ->hideOnIndex(),

            ImageField::new('image')
                ->setBasePath('/uploads/images')
                ->setUploadDir('public/uploads/images')
                ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
                ->setFormTypeOptions([
                    'required' => Crud::PAGE_NEW === $pageName,
                ])
                ->addCssClass('avatar-image'),

            TextField::new('name')->setLabel('Nom'),

            MoneyField::new('priceInEuros')
                ->setLabel('Prix (€)')
                ->setCurrency('EUR')
                ->setStoredAsCents(false)
                ->setNumDecimals(2)
                ->setFormTypeOption('required', true),

            ChoiceField::new('unit')
                ->setLabel('Unité')
                ->setChoices([
                    'Pièce' => ProductUnit::PIECE,
                    'Botte' => ProductUnit::BUNDLE,
                    'Bouquet' => ProductUnit::BUNCH,
                    'Litre' => ProductUnit::LITER,
                    'Kilo' => ProductUnit::KG,
                ])
                ->renderAsBadges([
                    ProductUnit::PIECE->value => 'primary',
                    ProductUnit::BUNDLE->value => 'success',
                    ProductUnit::BUNCH->value => 'warning',
                    ProductUnit::LITER->value => 'info',
                    ProductUnit::KG->value => 'secondary',
                ])
                ->formatValue(function ($value, $entity) {
                    return match ($value?->value ?? null) {
                        'PIECE' => 'Pièce',
                        'BUNDLE' => 'Botte',
                        'BUNCH' => 'Bouquet',
                        'LITER' => 'Litre',
                        'KG' => 'Kilo',
                        default => $value?->value ?? '',
                    };
                }),


            NumberField::new('inter')
                ->onlyOnForms()
                ->setLabel('Intervalle (en kg)')
                ->setFormTypeOption('attr', [
                    'step' => 0.01,
                    'min' => 0,
                ])
                ->setFormTypeOption('html5', true)
                ->setFormTypeOption('row_attr', ['class' => 'inter-wrapper']),

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

            BooleanField::new('limited')->setLabel('Qté Limitée'),

            BooleanField::new('discount')
                ->hideOnIndex()
                ->setLabel('Promo')
                ->setFormTypeOption('row_attr', [
                    'class' => 'discount-wrapper',
                ]),

            TextField::new('discountText')
                ->onlyOnForms()
                ->setLabel('Texte Promo')
                ->setFormTypeOption('row_attr', ['class' => 'discountText-wrapper']),

            BooleanField::new('isDisplayed')->setLabel('Affiché'),

            TextField::new('soldOutLabel', 'État')
                ->onlyOnIndex(),


        AssociationField::new('user')
                ->hideOnForm()
                ->hideOnIndex(),
        ];
    }


    public function configureActions(Actions $actions): Actions
    {
        $returnAction = Action::new('Retour')
            ->linkToUrl('/admin/product');

        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, fn(Action $a) => $a->setLabel('Ajouter un produit'))
            ->disable(Action::DELETE)
            ->addBatchAction(
                Action::new('markDeleted', 'Supprimer produit(s)')
                    ->linkToCrudAction('markAsDeleted')
                    ->addCssClass('btn-danger')
            )
            ->add(Crud::PAGE_NEW, $returnAction)
            ->add(Crud::PAGE_EDIT, $returnAction)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);

    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(
                ChoiceFilter::new('isDisplayed')
                    ->setLabel('Affiché')
                    ->setChoices([
                        'Affiché' => true,
                        'Non affiché' => false,
                    ])
            );
    }

    public function createEntity(string $entityFqcn): Product
    {
        $product = new Product();
        $product->setUser($this->security->getUser());

        return $product;
    }

    private function redirectBackToIndex(AdminContext $context, AdminUrlGenerator $adminUrlGenerator): RedirectResponse
    {
        return $this->redirect($context->getReferrer() ?? $adminUrlGenerator
            ->setController(self::class)
            ->setAction('index')
            ->generateUrl());
    }

    public function markAsDeleted(
        Request           $request,
        AdminContext      $context,
        AdminUrlGenerator $adminUrlGenerator
    ): RedirectResponse
    {
        $entityIds = $request->request->all('batchActionEntityIds', []);

        if (empty($entityIds)) {
            $this->addFlash('warning', 'Aucun produit sélectionné.');
            return $this->redirectBackToIndex($context, $adminUrlGenerator);
        }

        $nonDeletableNames = $this->productService->markProductsAsDeletedByIds($entityIds);

        if (!empty($nonDeletableNames)) {
            $this->addFlash('warning', sprintf(
                'Les produits suivants n\'ont pas été supprimés car ils sont liés à des commandes : %s',
                implode(', ', $nonDeletableNames)
            ));
        } else {
            $this->addFlash('success', 'Produit(s) supprimé(s) !');
        }


        return $this->redirectBackToIndex($context, $adminUrlGenerator);
    }

    public function createIndexQueryBuilder(
        SearchDto        $searchDto,
        EntityDto        $entityDto,
        FieldCollection  $fields,
        FilterCollection $filters
    ): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        return $qb->andWhere('entity.isDeleted = false');
    }


    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Product) {
            return;
        }

        $this->handleInterDependingOnUnit($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }


    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Product) {
            return;
        }

        $this->handleInterDependingOnUnit($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function handleInterDependingOnUnit(Product $product): void
    {
        if ($product->getUnit() !== ProductUnit::KG) {
            $product->setInter(null);
        }
    }
}