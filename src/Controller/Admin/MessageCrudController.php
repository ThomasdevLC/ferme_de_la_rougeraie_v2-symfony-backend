<?php

namespace App\Controller\Admin;

use App\Entity\Message;
use App\Enum\MessageType;
use App\Service\Admin\MessageService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{BooleanField, ChoiceField, TextareaField, TextField,};
use Symfony\Bundle\SecurityBundle\Security;


class MessageCrudController extends AbstractCrudController
{
    public function __construct(
        private Security $security,
        private MessageService $messageService
    ) {}

    public static function getEntityFqcn(): string
    {
        return Message::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addCssFile('css/admin/message-editor.css')
            ->addJsFile('js/admin/message-editor.js');
    }


    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('💬 Message')
            ->setEntityLabelInPlural('💬 Messages')
            ->setDefaultSort(['type' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        $fields = [];

        $fields[] = ChoiceField::new('type')
            ->setLabel('Type de message')
            ->setChoices([
                'Bannière' => MessageType::MARQUEE,
                'Fermeture boutique' => MessageType::CLOSEDSHOP,
            ])
            ->formatValue(function ($value, $entity) {
                if ($entity && null !== $entity->getType()) {
                    return match ($entity->getType()->value) {
                        MessageType::MARQUEE->value => 'Bannière',
                        MessageType::CLOSEDSHOP->value => 'Fermeture boutique',
                        default => $entity->getType()->value,
                    };
                }
                return $value;
            });

        $fields[] = TextareaField::new('content')
            ->setLabel('Contenu')
            ->setFormTypeOption('attr', ['class' => 'message-editor-input']);

        if ($pageName === 'index') {
            $fields[] = TextField::new('isActiveLabel')
                ->setLabel('Affiché')
                ->onlyOnIndex();
        } else {
            $fields[] = BooleanField::new('isActive')
                ->setLabel('Afficher le message')
                ->renderAsSwitch(true);
        }

        return $fields;
    }


    public function configureActions(Actions $actions): Actions
    {
        $returnAction = Action::new('Retour')
            ->linkToUrl('/admin/message');

        return $actions
            ->add(Crud::PAGE_NEW, $returnAction)
            ->add(Crud::PAGE_EDIT, $returnAction)
            ->remove(Crud::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->remove(Crud::PAGE_EDIT, Action::SAVE_AND_CONTINUE);
    }

    public function createEntity(string $entityFqcn): Message
    {
        $message = new Message();
        $message->setUser($this->security->getUser());
        return $message;
    }

    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof Message && $entityInstance->isActive()) {
            $this->messageService->disableOtherMessages($entityInstance);
        }
        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof Message && $entityInstance->isActive()) {
            $this->messageService->disableOtherMessages($entityInstance);
        }
        parent::updateEntity($em, $entityInstance);
    }

}
