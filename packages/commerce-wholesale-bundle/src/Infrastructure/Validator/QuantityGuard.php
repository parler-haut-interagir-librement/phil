<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Infrastructure\Validator;

use Override;
use Phil\CommerceBundle\Application\Port\UnitPriceResolver;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceWholesaleBundle\Domain\Exception\QuantityRefused;
use Phil\Money\Money;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Checks the quantity rules where the core prices a line: the core asks its unit price resolvers for the line's whole
 * quantity, for the order's customer group, before it changes the order, whenever a line is added, its quantity
 * changed, or the order's group changed. Asked before every other resolver, the guard gives no price: it lets an
 * allowed quantity through to them, and refuses another with `QuantityRefused`, the order left as it was.
 *
 * It reads products only, whose reference names the SKU; a configured line is checked on its own quantity.
 */
final readonly class QuantityGuard implements UnitPriceResolver
{
    private const string PRODUCT = 'product';

    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    #[Override]
    public function priceFor(Sellable $sellable, PricingContext $context): ?Money
    {
        if (self::PRODUCT !== $sellable->reference->type) {
            return null;
        }

        $requested = new RequestedQuantity($sellable->reference->id, $context->quantity, $context->customerGroup);
        $violations = $this->validator->validate($requested, new AllowedQuantity());

        if (0 === \count($violations)) {
            return null;
        }

        $messages = [];

        foreach ($violations as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        throw QuantityRefused::of($requested->sku, $requested->quantity, $messages);
    }
}
