<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Spf;

use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Internal\SpfWalk;

/**
 * Reads the SPF record of a domain and follows its `include` and `redirect` domains, counting the DNS lookups the
 * record needs against the limit of ten (RFC 7208, section 4.6.4).
 *
 * The walk stops at the eleventh lookup, so a record never costs more than eleven TXT queries; a domain included again
 * inside itself is reported as a loop and not followed twice. The `a`, `mx`, `ptr` and `exists` mechanisms are
 * counted, not resolved.
 */
final readonly class SpfAnalyser
{
    public const int LOOKUP_LIMIT = 10;

    public function __construct(
        private SpfParser $parser = new SpfParser(),
    ) {
    }

    public function analyse(string $domain, TxtResolver $dns): SpfAnalysis
    {
        $walk = new SpfWalk($dns, $this->parser, self::LOOKUP_LIMIT);
        [$state, $record, $reason] = $walk->fetch($domain);

        if (null === $record) {
            return new SpfAnalysis($domain, $state, $reason);
        }

        $all = $walk->walk($record, [$domain => true]);

        return new SpfAnalysis(
            $domain,
            SpfState::Found,
            null,
            $record,
            $walk->lookups,
            $walk->includes,
            $walk->loops,
            $walk->broken,
            $walk->macros,
            $all,
        );
    }
}
