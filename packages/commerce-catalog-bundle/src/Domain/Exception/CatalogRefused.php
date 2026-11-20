<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A change the catalogue refuses.
 */
final class CatalogRefused extends DomainException implements DomainError
{
    public static function currency(string $what, string $expected, string $given): self
    {
        return new self(\sprintf('%s counts in %s; %s is refused.', $what, $expected, $given));
    }

    public static function noChosenPrice(string $sku): self
    {
        return new self(\sprintf('The buyer no longer chooses the price of %s: its line is removed, or the product sold at its price.', $sku));
    }

    public static function taken(string $sku): self
    {
        return new self(\sprintf('The SKU %s already names a product.', $sku));
    }

    /**
     * @param array<string, string> $options
     */
    public static function sameOptions(string $family, array $options): self
    {
        return new self(\sprintf('A variant of %s already has the values %s.', $family, implode(' / ', $options)));
    }

    public static function unknownPart(string $product, string $part): self
    {
        return new self(\sprintf('The composite product %s names %s, which the catalogue does not hold.', $product, $part));
    }

    public static function nestedPart(string $product, string $part): self
    {
        return new self(\sprintf('The composite product %s names %s, a family or a composite: its parts are products of their own.', $product, $part));
    }

    public static function unknownCategory(string $key): self
    {
        return new self(\sprintf('No category has the key %s.', $key));
    }

    public static function noCommunityCopies(string $sku): self
    {
        return new self(\sprintf('The product %s offers no community copies.', $sku));
    }

    public static function noCommunityCopyLeft(string $sku): self
    {
        return new self(\sprintf('No community copy of %s is left to claim.', $sku));
    }

    public static function alreadyClaimed(string $sku): self
    {
        return new self(\sprintf('A community copy of %s was already claimed by the same person.', $sku));
    }
}
