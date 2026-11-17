<?php

declare(strict_types=1);

namespace Phil\Outbox;

use Phil\Outbox\Contract\EventCodec;
use Phil\Outbox\Exception\InvalidSetting;
use Phil\Outbox\Exception\UnknownEventType;

/**
 * The codecs of the outbox by the types they rebuild: the allowlist of the types it records and relays.
 */
final readonly class Codecs
{
    /** @var array<string, EventCodec> */
    private array $codecs;

    /**
     * @param iterable<EventCodec> $codecs
     *
     * @throws InvalidSetting when a type is not one, or two codecs rebuild the same type
     */
    public function __construct(iterable $codecs)
    {
        $byType = [];

        foreach ($codecs as $codec) {
            foreach ($codec->types() as $type) {
                if (1 !== preg_match(OutboxRecord::TYPE_PATTERN, $type)) {
                    throw InvalidSetting::because(\sprintf('"%s" is not an event type: 1 to 120 lowercase letters and digits, in words joined by ".", "_" or "-".', mb_strimwidth($type, 0, 130, '…')));
                }

                if (isset($byType[$type])) {
                    throw InvalidSetting::because(\sprintf('Two codecs rebuild the event type "%s": %s and %s.', $type, $byType[$type]::class, $codec::class));
                }

                $byType[$type] = $codec;
            }
        }

        $this->codecs = $byType;
    }

    public function has(string $type): bool
    {
        return isset($this->codecs[$type]);
    }

    /**
     * @throws UnknownEventType when no codec rebuilds the type
     */
    public function get(string $type): EventCodec
    {
        return $this->codecs[$type] ?? throw UnknownEventType::named($type);
    }

    /**
     * The types of the allowlist, in the order the codecs gave them.
     *
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->codecs);
    }
}
