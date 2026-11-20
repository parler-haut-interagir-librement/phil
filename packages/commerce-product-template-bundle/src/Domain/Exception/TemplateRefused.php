<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;
use Throwable;

/**
 * A generation, a group or the options of a product the module refuses: an unknown template or design, a plan the
 * library refuses, a SKU the catalogue holds for something else, or a product that cannot become what the plan says.
 */
final class TemplateRefused extends DomainException implements DomainError
{
    private bool $retryable = false;

    public static function unknownTemplate(string $code): self
    {
        return new self(\sprintf('No product template has the code %s.', $code));
    }

    public static function unknownDesign(string $code): self
    {
        return new self(\sprintf('No design has the code %s.', $code));
    }

    public static function unknownGroup(string $id): self
    {
        return new self(\sprintf('No product group has the identifier %s.', $id));
    }

    public static function plan(Throwable $refusal): self
    {
        return new self($refusal->getMessage(), 0, $refusal);
    }

    public static function foreignSku(string $sku, ?string $group): self
    {
        return new self(null === $group
            ? \sprintf('The SKU %s names a product of the catalogue outside any group: it is not taken over.', $sku)
            : \sprintf('The SKU %s belongs to the group %s.', $sku, $group));
    }

    public static function kind(string $sku, string $kind): self
    {
        return new self(\sprintf('The product %s is %s: a product never changes its kind.', $sku, $kind));
    }

    public static function currency(string $what, string $expected, string $given): self
    {
        return new self(\sprintf('%s counts in %s; %s is refused.', $what, $expected, $given));
    }

    public static function unreadable(string $what, string $reason): self
    {
        return new self(\sprintf('%s cannot be read back: %s.', $what, $reason));
    }

    public static function mockup(string $sku, string $face, Throwable $refusal): self
    {
        return new self(\sprintf('The %s mockup of %s cannot be composed: %s', $face, $sku, $refusal->getMessage()), 0, $refusal);
    }

    public static function missingImage(string $path): self
    {
        return new self(\sprintf('The image %s is not in the store of images.', $path));
    }

    public static function unknownOrder(string $number): self
    {
        return new self(\sprintf('No order has the number %s.', $number));
    }

    public static function orderNotPlaced(string $number, string $state): self
    {
        return new self(\sprintf('The order %s is %s: only a placed order is produced.', $number, $state));
    }

    public static function drawsPending(string $number, string $line): self
    {
        $refused = new self(\sprintf('The line %s of the order %s still has options to draw: it is produced once they are drawn.', $line, $number));
        $refused->retryable = true;

        return $refused;
    }

    /**
     * Whether trying again later may succeed: an order whose draws are not made yet.
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public static function notPlanned(string $sku): self
    {
        return new self(\sprintf('The plan of its group no longer gives the product %s: its print files cannot be composed.', $sku));
    }

    public static function unknownFont(string $name): self
    {
        return new self(\sprintf('No print font is named %s.', $name));
    }

    public static function textOption(string $option, string $reason): self
    {
        return new self(\sprintf('The text of the option %s cannot be printed: %s.', $option, $reason));
    }

    public static function draw(string $sku, string $option, Throwable $refusal): self
    {
        return new self(\sprintf('The draw of the option %s of %s cannot be read: %s', $option, $sku, $refusal->getMessage()), 0, $refusal);
    }

    public static function printFile(string $sku, string $face, string $zone, Throwable $refusal): self
    {
        return new self(\sprintf('The print file of the %s zone %s of %s cannot be composed: %s', $face, $zone, $sku, $refusal->getMessage()), 0, $refusal);
    }

    public static function orderNumber(string $number): self
    {
        return new self(\sprintf('The order number %s cannot name a file of the production store.', $number));
    }
}
