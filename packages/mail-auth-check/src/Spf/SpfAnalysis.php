<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Spf;

/**
 * What `SpfAnalyser` found in the SPF record of a domain and in the records it includes.
 */
final readonly class SpfAnalysis
{
    /**
     * @param ?string               $reason   why the record of the domain could not be used, when it is not Found
     * @param int                   $lookups  the DNS lookups the record needs, counted up to one past the limit
     * @param list<string>          $includes the domains reached through `include` and `redirect`, nested ones too
     * @param list<string>          $loops    the domains included again inside themselves
     * @param array<string, string> $broken   why each included domain without a usable record fails, by domain
     * @param list<string>          $macros   the macro domains, which depend on each message and are not followed
     * @param ?Qualifier            $all      the result for other servers: the `all` of the record, or of the record
     *                                        it redirects to; null when there is none
     */
    public function __construct(
        public string $domain,
        public SpfState $state,
        public ?string $reason = null,
        public ?SpfRecord $record = null,
        public int $lookups = 0,
        public array $includes = [],
        public array $loops = [],
        public array $broken = [],
        public array $macros = [],
        public ?Qualifier $all = null,
    ) {
    }

    public function exceedsLookupLimit(): bool
    {
        return $this->lookups > SpfAnalyser::LOOKUP_LIMIT;
    }
}
