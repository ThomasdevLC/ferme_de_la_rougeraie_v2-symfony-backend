<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Enum\ProductCategory;
use App\Enum\ProductUnit;
use App\Form\BasketItemType;
use App\Service\Image\ProductImageProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraints\Image as ImageConstraint;

class BasketCrudController extends AbstractCrudController
{
    public function __construct(
        private Security $security,
        private ProductImageProcessor $productImageProcessor,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addCssFile('css/admin/custom.css');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->addFormTheme('admin/form_theme.html.twig')
            ->setEntityLabelInSingular('🧺 Panier')
            ->setEntityLabelInPlural('🧺 Paniers')
            ->setPageTitle(Crud::PAGE_INDEX, '🧺 Paniers')
            ->setPageTitle(
                Crud::PAGE_EDIT,
                fn (Product $basket) => '✏️ Modifier: ' . $basket->getName()
            )
            ->setPaginatorPageSize(10)
            ->setDefaultSort([
                'isDisplayed' => 'DESC',
                'updatedAt'   => 'DESC',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        $returnAction = Action::new('Retour')
            ->linkToUrl('/admin/basket');

        return $actions
            ->update(
                Crud::PAGE_INDEX,
                Action::NEW,
                fn (Action $a) => $a->setLabel('➕ Ajouter un panier')
            )
            ->update(
                Crud::PAGE_INDEX,
                Action::EDIT,
                fn (Action $a) => $a->setLabel('✏️ Modifier')
            )
            ->disable(Action::DELETE)
            ->add(Crud::PAGE_NEW, $returnAction)
            ->add(Crud::PAGE_EDIT, $returnAction)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm()->hideOnIndex(),

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

            TextField::new('priceDisplay', 'Prix (€)')->onlyOnIndex(),

            ChoiceField::new('category')
                ->setLabel('Catégorie')
                ->setChoices($this->categoryChoices())
                ->setRequired(false)
                ->renderExpanded()
                ->setFormTypeOption('row_attr', ['class' => 'choice-pills'])
                ->formatValue(
                    static fn ($value): ?string => $value instanceof ProductCategory ? $value->label() : null
                ),

            FormField::addFieldset()
                ->onlyOnForms()
                ->setCssClass('fdlr-card'),

            CollectionField::new('basketItems')
                ->setLabel('Composition')
                ->setEntryType(BasketItemType::class)
                ->setEntryIsComplex(true)
                ->allowAdd()
                ->allowDelete()
                ->onlyOnForms()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('row_attr', ['class' => 'basket-items-wrapper']),

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
                ->setFormTypeOption('attr', ['min' => 0])
                ->setFormTypeOption('row_attr', ['class' => 'stock-wrapper']),

            TextField::new('soldOutLabel', 'État')->onlyOnIndex(),
        ];
    }

    /**
     * @return array<string, ProductCategory>
     */
    private function categoryChoices(): array
    {
        $choices = [];
        foreach (ProductCategory::cases() as $category) {
            $choices[$category->label()] = $category;
        }

        return $choices;
    }

    public function createEntity(string $entityFqcn): Product
    {
        $basket = new Product();
        $basket->setUser($this->security->getUser());
        $basket->setIsBasket(true);
        $basket->setUnit(ProductUnit::PIECE);

        return $basket;
    }

    public function createIndexQueryBuilder(
        SearchDto        $searchDto,
        EntityDto        $entityDto,
        FieldCollection  $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        return $qb
            ->andWhere('entity.isDeleted = false')
            ->andWhere('entity.isBasket = true');
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Product) {
            return;
        }

        $entityInstance->setIsBasket(true);
        $this->optimizeImage($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof Product) {
            return;
        }

        $originalData = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        $originalImage = $originalData['image'] ?? null;

        if ($originalImage !== $entityInstance->getImage()) {
            $this->optimizeImage($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
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
