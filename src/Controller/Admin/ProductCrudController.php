<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Enum\ProductCategory;
use App\Enum\ProductUnit;
use App\Form\ProductVariantType;
use App\Service\Admin\ProductService;
use App\Service\Image\ProductImageProcessor;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
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
use Symfony\Component\Validator\Constraints\Image as ImageConstraint;


class ProductCrudController extends AbstractCrudController
{
    private Security $security;
    private ProductService $productService;
    private ProductImageProcessor $productImageProcessor;

    public function __construct(
        Security $security,
        ProductService $productService,
        ProductImageProcessor $productImageProcessor
    )
    {
        $this->security = $security;
        $this->productService = $productService;
        $this->productImageProcessor = $productImageProcessor;
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
        return $crud
            ->addFormTheme('admin/form_theme.html.twig')
            ->setEntityLabelInSingular('🥕 Produit')
            ->setEntityLabelInPlural('🥕 Produits')
            ->setPageTitle(Crud::PAGE_INDEX, '🥕 Produits')
            ->setPageTitle(
                Crud::PAGE_EDIT,
                fn (Product $product) => '✏️ Modifier: ' . $product->getName()
            )
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

            // ── Carte : Identité ──────────────────────────────────────────
            FormField::addFieldset()
                ->onlyOnForms()
                ->setCssClass('fdlr-card identity-card'),

            BooleanField::new('isDisplayed')->setLabel('Affiché'),

            TextField::new('name')->setLabel('Nom'),

            ImageField::new('image')
                ->setBasePath('/uploads/images')
                ->setUploadDir('public/uploads/images')
                ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
                ->setFileConstraints(
                    new ImageConstraint(
                        maxWidth: 8000,
                        maxHeight: 8000,
                        maxPixels: 40000000,
                        maxWidthMessage: 'L’image est trop large (maximum {{ max_width }} px).',
                        maxHeightMessage: 'L’image est trop haute (maximum {{ max_height }} px).',
                        maxPixelsMessage: 'L’image est trop grande pour être traitée.',
                    )
                )
                ->setFormTypeOptions([
                    'required' => Crud::PAGE_NEW === $pageName,
                    'row_attr' => ['class' => 'image-field'],
                ])
                ->addCssClass('avatar-image'),

            TextField::new('variantLabel')
                ->setLabel('')
                ->onlyOnIndex()
                ->setTemplatePath('admin/field/variant_badge.html.twig'),

            TextField::new('priceDisplay', 'Prix (€)')
                ->onlyOnIndex(),

            ChoiceField::new('category')
                ->setLabel('Catégorie')
                ->setChoices($this->categoryChoices())
                ->setRequired(true)
                ->renderExpanded()
                ->setFormTypeOption('row_attr', ['class' => 'choice-pills'])
                ->formatValue(
                    static fn ($value): ?string => $value instanceof ProductCategory ? $value->label() : null
                ),

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
                ->renderExpanded()
                ->setFormTypeOption('row_attr', ['class' => 'choice-pills'])
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

            FormField::addFieldset()
                ->onlyOnForms()
                ->setCssClass('fdlr-card'),

            BooleanField::new('hasVariants')
                ->setLabel('Variants')
                ->onlyOnForms()
                ->setFormTypeOption('row_attr', ['class' => 'has-variants-wrapper']),

            CollectionField::new('variants')
                ->setLabel(false)
                ->setEntryType(ProductVariantType::class)
                ->setEntryIsComplex(true)
                ->allowAdd()
                ->allowDelete()
                ->onlyOnForms()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', ['class' => 'variants-wrapper']),

            FormField::addFieldset()
                ->onlyOnForms()
                ->setCssClass('fdlr-card'),

            MoneyField::new('priceInEuros')
                ->setLabel('Prix (€)')
                ->setCurrency('EUR')
                ->setStoredAsCents(false)
                ->setNumDecimals(2)
                ->onlyOnForms()
                ->setFormTypeOption('required', false)
                ->setFormTypeOption('row_attr', ['class' => 'price-wrapper']),

            BooleanField::new('hasStock')
                ->hideOnIndex()
                ->setLabel('Stock')
                ->setFormTypeOption('row_attr', ['class' => 'has-stock-wrapper flex-stock-row']),

            NumberField::new('stock')
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
            ->update(
                Crud::PAGE_INDEX,
                Action::NEW,
                fn(Action $a) => $a
                    ->setLabel('➕ Ajouter un produit'))
            ->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                fn (Action $action) => $action
                    ->setLabel('✏️ Modifier')
            )
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
            )
            ->add(
                ChoiceFilter::new('category')
                    ->setLabel('Catégorie')
                    ->setChoices($this->categoryChoices())
            )
            ->add(
                ChoiceFilter::new('hasVariants')
                    ->setLabel('Variants')
                    ->setChoices([
                        'Avec variants' => true,
                        'Sans variants' => false,
                    ])
            );
    }

    /**
     * Category choices for the admin (label => enum), built from the enum so
     * the canonical declaration order and French labels stay in one place.
     *
     * @return array<string, ProductCategory>
     */
    private function categoryChoices(): array
    {
        $choices = [];
        foreach (ProductCategory::cases() as $category) {
            if ($category === ProductCategory::BASKET) {
                continue;
            }
            $choices[$category->label()] = $category;
        }

        return $choices;
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
                'Les produits suivants n\'ont pas été supprimés car ils sont liés à des commandes en cours : %s',
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

        return $qb
            ->andWhere('entity.isDeleted = false')
            ->andWhere('entity.isBasket = false');
    }


    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Product) {
            return;
        }

        $this->handleInterDependingOnUnit($entityInstance);
        $this->neutralizeSimpleFieldsForVariants($entityInstance);
        $this->optimizeImage($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }


    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Product) {
            return;
        }

        $this->handleInterDependingOnUnit($entityInstance);
        $this->neutralizeSimpleFieldsForVariants($entityInstance);

        $originalData = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        $originalImage = $originalData['image'] ?? null;

        if ($originalImage !== $entityInstance->getImage()) {
            $this->optimizeImage($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Enforce the has_variants invariant: a variant product carries no
     * product-level price/stock/discount (those live on each variant),
     * so clear them before saving to avoid stale values.
     */
    private function neutralizeSimpleFieldsForVariants(Product $product): void
    {
        if (!$product->hasVariants()) {
            return;
        }

        $product->setPrice(null);
        $product->setHasStock(false);
        $product->setStock(null);
        $product->setDiscount(false);
        $product->setDiscountText(null);
    }

    private function handleInterDependingOnUnit(Product $product): void
    {
        if ($product->getUnit() !== ProductUnit::KG) {
            $product->setInter(null);
        }
    }

    private function optimizeImage(Product $product): void
    {
        $image = $product->getImage();
        if (null === $image || '' === $image) {
            return;
        }

        $this->productImageProcessor->optimize($image);
    }
}
