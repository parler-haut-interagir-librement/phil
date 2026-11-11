<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\ValueObject;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use stdClass;

/**
 * A JSON Resource Descriptor (RFC 7033, section 4.4): what a server says of a resource — its subject, its aliases, its
 * properties and its links.
 *
 * Several resolvers may describe one resource: `merge()` keeps the subject and the properties of the first, adds the
 * aliases it lacks and appends the links of the other, so that a bundle adds its links to those of another.
 */
final readonly class WebFingerDescriptor
{
    /**
     * @param list<string>           $aliases
     * @param array<string, ?string> $properties keyed by URI
     * @param list<WebFingerLink>    $links
     *
     * @throws InvalidValue when the subject is empty, or a property has no key
     */
    public function __construct(
        public string $subject,
        public array $aliases = [],
        public array $properties = [],
        public array $links = [],
    ) {
        if ('' === $subject) {
            throw InvalidValue::because('A WebFinger descriptor has a subject.');
        }

        if (\array_key_exists('', $properties)) {
            throw InvalidValue::because(\sprintf('The properties of the WebFinger descriptor of "%s" are keyed by a URI.', $subject));
        }
    }

    public function merge(self $other): self
    {
        return new self(
            $this->subject,
            array_values(array_unique([...$this->aliases, ...$other->aliases])),
            $this->properties + $other->properties,
            [...$this->links, ...$other->links],
        );
    }

    /**
     * The descriptor with only the links of the relations asked for (RFC 7033, section 4.3); every link when none is.
     *
     * @param list<string> $rels
     */
    public function withRels(array $rels): self
    {
        if ([] === $rels) {
            return $this;
        }

        return new self($this->subject, $this->aliases, $this->properties, array_values(array_filter($this->links, static fn (WebFingerLink $link): bool => \in_array($link->rel, $rels, true))));
    }

    /**
     * The descriptor as its JSON members, without the empty ones but the subject.
     *
     * @return array{subject: string, aliases?: list<string>, properties?: stdClass, links?: list<array<string, string|stdClass>>}
     */
    public function toArray(): array
    {
        $jrd = ['subject' => $this->subject];

        if ([] !== $this->aliases) {
            $jrd['aliases'] = $this->aliases;
        }

        if ([] !== $this->properties) {
            $jrd['properties'] = (object) $this->properties;
        }

        if ([] !== $this->links) {
            $jrd['links'] = array_map(static fn (WebFingerLink $link): array => $link->toArray(), $this->links);
        }

        return $jrd;
    }
}
