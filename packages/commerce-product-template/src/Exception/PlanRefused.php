<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Exception;

use DomainException;

/**
 * A design that cannot be planned on a template: the template is not one the design allows, two combinations would
 * share a SKU, a SKU is not one a catalogue accepts, or an artwork cannot be placed.
 */
final class PlanRefused extends DomainException implements ProductTemplateException
{
    public static function templateNotAllowed(string $design, string $template): self
    {
        return new self(\sprintf('The design %s is not made on the template %s.', $design, $template));
    }

    public static function collision(string $sku, string $first, string $second): self
    {
        return new self(\sprintf('The SKU %s would name two products: %s and %s.', $sku, $first, $second));
    }

    public static function invalidSku(string $sku): self
    {
        return new self(\sprintf('"%s" is not a SKU: 1 to 64 letters, digits, ".", "_" or "-", starting with a letter or digit.', $sku));
    }

    public static function unknownZone(string $design, string $face, string $zone, string $template): self
    {
        return new self(\sprintf('The design %s places an artwork in the zone %s of the face %s, which the template %s does not have.', $design, $zone, $face, $template));
    }

    public static function ambiguousArtwork(string $design, string $face, string $zone, string $combination): self
    {
        return new self(\sprintf('The design %s has two artworks as specific as each other for the zone %s of the face %s of %s.', $design, $zone, $face, $combination));
    }

    public static function tooMany(int $limit): self
    {
        return new self(\sprintf('A plan holds at most %d products.', $limit));
    }
}
