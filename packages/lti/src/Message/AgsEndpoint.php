<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * The claim of the Assignment and Grade Services in a launch: the scopes the tool may ask for, the line items of the
 * context and, when the resource link has one, its own line item.
 */
final readonly class AgsEndpoint
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public array $scopes,
        public ?Url $lineItems = null,
        public ?Url $lineItem = null,
    ) {
    }

    /**
     * @internal
     */
    public static function read(Fields $claim): self
    {
        return new self($claim->stringList('scope', 16), $claim->optionalUrl('lineitems'), $claim->optionalUrl('lineitem'));
    }

    public function allows(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }

    /**
     * @return array<string, string|list<string>>
     */
    public function toClaim(): array
    {
        return array_filter(['scope' => $this->scopes, 'lineitems' => $this->lineItems?->toString(), 'lineitem' => $this->lineItem?->toString()], static fn (mixed $value): bool => null !== $value);
    }
}
