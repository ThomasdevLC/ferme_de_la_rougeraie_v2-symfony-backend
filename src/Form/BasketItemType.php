<?php

namespace App\Form;

use App\Entity\BasketItem;
use App\Entity\Product;
use App\Repository\Admin\ProductRepository;
use App\Utils\Translator\UnitTranslator;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;

class BasketItemType extends AbstractType
{
    public function __construct(
        private UnitTranslator $unitTranslator,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'label' => 'Produit',
                'class' => Product::class,
                'choice_label' => 'name',
                // Filtered pool: never offer deleted products, nor baskets
                // themselves (a basket cannot contain a basket).
                'query_builder' => static fn (ProductRepository $repo) => $repo
                    ->createQueryBuilder('p')
                    ->where('p.isDeleted = false')
                    ->andWhere('p.isBasket = false')
                    ->orderBy('p.name', 'ASC'),
                // Group the dropdown by product category (Légumes, Fruits...);
                // uncategorised products land in a trailing "Sans catégorie".
                'group_by' => static fn (Product $product): string => $product->getCategory()?->label() ?? 'Sans catégorie',
                // Expose each product's unit on its <option> so the JS can show
                // it next to the name in the collapsed row title.
                'choice_attr' => fn (Product $product): array => [
                    'data-unit' => $product->getUnit() !== null
                        ? $this->unitTranslator->translate($product->getUnit())
                        : '',
                ],
                'placeholder' => '— Choisir un produit —',
                // Turn the native <select> into EasyAdmin's tom-select
                // autocomplete (client-side search, no server round-trip).
                'attr' => ['data-ea-widget' => 'ea-autocomplete'],
                'constraints' => [
                    new NotNull(['message' => 'Sélectionnez un produit.']),
                ],
            ])
            ->add('quantity', NumberType::class, [
                'label' => 'Qté',
                'constraints' => [
                    new NotNull(['message' => 'La quantité est requise.']),
                    new Positive(['message' => 'La quantité doit être supérieure à zéro.']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BasketItem::class,
        ]);
    }
}
