<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\PickupDay;
use EasyCorp\Bundle\EasyAdminBundle\Config\{Action, Actions, Crud, Filters};
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{BooleanField,
    ChoiceField,
    CollectionField,
    DateTimeField,
    IdField,
    MoneyField,
    TextField};
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class OrderCrudController extends AbstractCrudController

{
        public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('📦 Commande')
            ->setEntityLabelInPlural('📦 Commandes');
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [
            IdField::new('id')->hideOnDetail(),
            DateTimeField::new('createdAt', 'Date de commande')->hideOnDetail(),
            TextField::new('user.firstName', 'Prénom')->hideOnDetail(),
            TextField::new('user.lastName', 'Nom')->hideOnDetail(),
            MoneyField::new('total', 'Prix total')->setCurrency('EUR')->hideOnDetail(),

            ChoiceField::new('pickupDay')
                ->setLabel('Jour de retrait')
                ->renderAsBadges()
                ->setChoices(
                    array_combine(
                        array_map(fn(PickupDay $d) => $d->label(), PickupDay::cases()),
                        array_map(fn(PickupDay $d) => $d->value,   PickupDay::cases())
                    )
                )
                ->formatValue(fn($value) => PickupDay::fromWeekday((int) $value)->label())->hideOnDetail(),

            BooleanField::new('done', 'Traitée')->renderAsSwitch(true)->hideOnDetail(),
        ];

        if (Crud::PAGE_DETAIL === $pageName) {
            $fields[] = CollectionField::new('productOrders')
                ->setLabel(false)
                ->setTemplatePath('admin/user_order.html.twig')
                ->onlyOnDetail();
        }

        return $fields;
    }


    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::DELETE, Action::BATCH_DELETE, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->remove(Crud::PAGE_DETAIL, Action::EDIT)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
        return $action->setLabel('Détails')->setIcon('fa fa-eye');
    });
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(
                BooleanFilter::new('done')
                    ->setLabel('Traitée')
            )
            ->add(
                ChoiceFilter::new('pickupDay')
                    ->setLabel('Jour de retrait')
                    ->setChoices([
                        'Mardi'    => PickupDay::TUESDAY->value,
                        'Vendredi' => PickupDay::FRIDAY->value,
                    ])
            );
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        return $qb->andWhere('entity.isDeleted = false');
    }

}
