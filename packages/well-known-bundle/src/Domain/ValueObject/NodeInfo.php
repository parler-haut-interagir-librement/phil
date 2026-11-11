<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Domain\ValueObject;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use stdClass;

/**
 * A NodeInfo 2.1 document: the software a server runs, the protocols it speaks, the services it connects to, whether
 * it takes new users, its usage and free metadata.
 *
 * The protocols and services are those the 2.1 schema lists; at least one protocol is required.
 */
final readonly class NodeInfo
{
    public const string VERSION = '2.1';

    public const string SCHEMA = 'http://nodeinfo.diaspora.software/ns/schema/2.1';

    /** @var list<string> */
    public const array PROTOCOLS = ['activitypub', 'buddycloud', 'dfrn', 'diaspora', 'libertree', 'ostatus', 'pumpio', 'tent', 'xmpp', 'zot'];

    /** @var list<string> */
    public const array INBOUND = ['atom1.0', 'gnusocial', 'imap', 'pnut', 'pop3', 'pumpio', 'rss2.0', 'twitter'];

    /** @var list<string> */
    public const array OUTBOUND = ['atom1.0', 'blogger', 'buddycloud', 'diaspora', 'dreamwidth', 'drupal', 'facebook', 'friendica', 'gnusocial', 'google', 'insanejournal', 'libertree', 'linkedin', 'livejournal', 'mediagoblin', 'myspace', 'pinterest', 'pnut', 'posterous', 'pumpio', 'redmatrix', 'rss2.0', 'smtp', 'tent', 'tumblr', 'twitter', 'wordpress', 'xmpp'];

    /**
     * @param list<string>         $protocols
     * @param list<string>         $inbound
     * @param list<string>         $outbound
     * @param array<string, mixed> $metadata
     *
     * @throws InvalidValue when no protocol is given, or a protocol or a service is not one of the schema
     */
    public function __construct(
        public NodeInfoSoftware $software,
        public array $protocols,
        public array $inbound,
        public array $outbound,
        public bool $openRegistrations,
        public NodeInfoUsage $usage,
        public array $metadata = [],
    ) {
        if ([] === $protocols) {
            throw InvalidValue::because('A NodeInfo document names at least one protocol.');
        }

        self::check('protocol', $protocols, self::PROTOCOLS);
        self::check('inbound service', $inbound, self::INBOUND);
        self::check('outbound service', $outbound, self::OUTBOUND);
    }

    /**
     * @param list<string> $values
     * @param list<string> $known
     *
     * @throws InvalidValue when a value is not one of the schema
     */
    public static function check(string $kind, array $values, array $known): void
    {
        foreach ($values as $value) {
            if (!\in_array($value, $known, true)) {
                throw InvalidValue::because(\sprintf('"%s" is not a NodeInfo 2.1 %s: %s.', $value, $kind, implode(', ', $known)));
            }
        }
    }

    /**
     * @return array{version: string, software: array<string, string>, protocols: list<string>, services: array{inbound: list<string>, outbound: list<string>}, openRegistrations: bool, usage: array<string, int|stdClass>, metadata: stdClass}
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'software' => $this->software->toArray(),
            'protocols' => $this->protocols,
            'services' => ['inbound' => $this->inbound, 'outbound' => $this->outbound],
            'openRegistrations' => $this->openRegistrations,
            'usage' => $this->usage->toArray(),
            'metadata' => (object) $this->metadata,
        ];
    }
}
