<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\ValueObject;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use stdClass;

/**
 * A link of a JSON Resource Descriptor (RFC 7033, section 4.4.4): its relation, and what it points to.
 *
 * `rel` is a registered relation type or a URI. `href` points to the target; `template` is the URI template some
 * protocols put in its place, such as the remote follow of ActivityPub servers. `titles` are keyed by language tag, or
 * `und`; `properties` by URI, each value a string or `null`.
 */
final readonly class WebFingerLink
{
    /**
     * @param array<string, string>  $titles
     * @param array<string, ?string> $properties
     *
     * @throws InvalidValue when the relation is empty, or a title or a property has no key
     */
    public function __construct(
        public string $rel,
        public ?string $type = null,
        public ?string $href = null,
        public ?string $template = null,
        public array $titles = [],
        public array $properties = [],
    ) {
        if ('' === $rel) {
            throw InvalidValue::because('A WebFinger link has a relation.');
        }

        if (\array_key_exists('', $titles) || \array_key_exists('', $properties)) {
            throw InvalidValue::because(\sprintf('The titles and properties of the WebFinger link "%s" are keyed by a language tag or a URI.', $rel));
        }
    }

    /**
     * The link as its JSON members, in the order of the RFC, without the members it does not have.
     *
     * @return array<string, string|stdClass>
     */
    public function toArray(): array
    {
        return array_filter([
            'rel' => $this->rel,
            'type' => $this->type,
            'href' => $this->href,
            'template' => $this->template,
            'titles' => [] === $this->titles ? null : (object) $this->titles,
            'properties' => [] === $this->properties ? null : (object) $this->properties,
        ], static fn (string|stdClass|null $member): bool => null !== $member);
    }
}
