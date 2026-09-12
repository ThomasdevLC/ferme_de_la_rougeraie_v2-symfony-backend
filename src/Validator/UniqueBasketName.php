<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Two baskets may not share the same name.
 *
 * Several baskets can be displayed at once, so the name is the only thing
 * telling them apart on the storefront ("... (petit)" / "... (grand)").
 *
 * Scoped to baskets on purpose: regular products are allowed to share a name
 * and some already do.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class UniqueBasketName extends Constraint
{
    public string $message = 'Un panier porte déjà ce nom. Donnez-lui un nom distinct, par exemple « {{ suggestion }} ».';

    /**
     * The clashing basket is hidden, so it is nowhere to be seen in the list:
     * say so, otherwise the admin hunts for a basket that appears not to exist.
     */
    public string $hiddenMessage = 'Un panier masqué porte déjà ce nom. Affichez-le pour le renommer, ou donnez à celui-ci un nom distinct, par exemple « {{ suggestion }} ».';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
