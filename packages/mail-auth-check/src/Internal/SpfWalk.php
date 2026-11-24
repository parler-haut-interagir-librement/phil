<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Internal;

use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Exception\DnsLookupFailed;
use Phil\MailAuthCheck\Exception\InvalidRecord;
use Phil\MailAuthCheck\Spf\Qualifier;
use Phil\MailAuthCheck\Spf\SpfMechanism;
use Phil\MailAuthCheck\Spf\SpfParser;
use Phil\MailAuthCheck\Spf\SpfRecord;
use Phil\MailAuthCheck\Spf\SpfState;

/**
 * The state of one walk through an SPF record and the records it includes; a new walk serves each analysis.
 *
 * @internal
 */
final class SpfWalk
{
    public int $lookups = 0;

    /** @var list<string> */
    public array $includes = [];

    /** @var list<string> */
    public array $loops = [];

    /** @var array<string, string> */
    public array $broken = [];

    /** @var list<string> */
    public array $macros = [];

    public function __construct(
        private readonly TxtResolver $dns,
        private readonly SpfParser $parser,
        private readonly int $limit,
    ) {
    }

    /**
     * @return array{SpfState, ?SpfRecord, ?string} the state, the record when Found, the reason otherwise
     */
    public function fetch(string $domain): array
    {
        try {
            $texts = $this->dns->txt($domain);
        } catch (DnsLookupFailed) {
            return [SpfState::LookupFailed, null, 'its DNS lookup failed'];
        }

        $records = array_values(array_filter($texts, SpfParser::isSpf(...)));

        if ([] === $records) {
            return [SpfState::Missing, null, 'it has no SPF record'];
        }

        if (\count($records) > 1) {
            return [SpfState::Multiple, null, 'it has more than one SPF record'];
        }

        try {
            return [SpfState::Found, $this->parser->parse($records[0]), null];
        } catch (InvalidRecord $invalidRecord) {
            return [SpfState::Invalid, null, 'its record cannot be read: '.$invalidRecord->getMessage()];
        }
    }

    /**
     * Counts the lookups of a record and follows its includes, then its redirect when it has no `all`.
     *
     * @param array<string, true> $stack the domains being walked, to tell a loop
     *
     * @return ?Qualifier the result the record gives other servers
     */
    public function walk(SpfRecord $record, array $stack): ?Qualifier
    {
        foreach ($record->mechanisms as $mechanism) {
            if (!$mechanism->costsLookup()) {
                continue;
            }

            if (!$this->spend()) {
                break;
            }

            if ('include' === $mechanism->name && null !== $mechanism->target) {
                $this->follow($mechanism->target, $stack);
            }
        }

        $all = $record->all();

        if ($all instanceof SpfMechanism) {
            return $all->qualifier;
        }

        if (null === $record->redirect || !$this->spend()) {
            return null;
        }

        return $this->follow($record->redirect, $stack);
    }

    /**
     * Counts one lookup, unless the limit is already passed; false once it is.
     */
    private function spend(): bool
    {
        if ($this->lookups > $this->limit) {
            return false;
        }

        ++$this->lookups;

        return $this->lookups <= $this->limit;
    }

    /**
     * @param array<string, true> $stack
     */
    private function follow(string $target, array $stack): ?Qualifier
    {
        if (str_contains($target, '%')) {
            $this->macros[] = $target;

            return null;
        }

        if (isset($stack[$target])) {
            $this->loops[] = $target;

            return null;
        }

        if (!\in_array($target, $this->includes, true)) {
            $this->includes[] = $target;
        }

        [, $record, $reason] = $this->fetch($target);

        if (null === $record) {
            $this->broken[$target] = (string) $reason;

            return null;
        }

        return $this->walk($record, $stack + [$target => true]);
    }
}
