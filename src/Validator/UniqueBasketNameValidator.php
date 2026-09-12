<?php

namespace App\Validator;

use App\Entity\Product;
use App\Repository\Admin\ProductRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueBasketNameValidator extends ConstraintValidator
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueBasketName) {
            throw new UnexpectedTypeException($constraint, UniqueBasketName::class);
        }

        // No-op for regular products: only baskets carry the rule.
        if (!$value instanceof Product || !$value->isBasket()) {
            return;
        }

        // The TitleCaseListener trims the name on its way to the database, so
        // compare on the trimmed value too — otherwise " Panier" would slip
        // past as a distinct name and land as "Panier".
        $name = trim($value->getName() ?? '');

        // An empty name is another constraint's business.
        if ('' === $name) {
            return;
        }

        $conflict = $this->productRepository->findConflictingBasket($name, $value->getId());

        if (null === $conflict) {
            return;
        }

        $message = $conflict['isDisplayed'] ? $constraint->message : $constraint->hiddenMessage;

        $this->context->buildViolation($message)
            ->setParameter('{{ suggestion }}', $this->suggest($name))
            ->atPath('name')
            ->addViolation();
    }

    /**
     * Hand the admin a ready-made variant rather than just refusing: the whole
     * point of the rule is to push towards "... (petit)" / "... (grand)".
     */
    private function suggest(string $name): string
    {
        return $name . ' (petit)';
    }
}
