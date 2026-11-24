<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Dns;

use Override;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Exception\DnsLookupFailed;

/**
 * Answers from a map of names: for tests, for a preview of records not yet published, or for records an application
 * already holds. A name of neither list has no TXT record.
 */
final readonly class StaticTxtResolver implements TxtResolver
{
    /**
     * @param array<string, list<string>> $records the TXT records by name, in lowercase ASCII without a final dot
     * @param list<string>                $failing the names whose lookup fails
     */
    public function __construct(
        private array $records = [],
        private array $failing = [],
    ) {
    }

    #[Override]
    public function txt(string $asciiName): array
    {
        if (\in_array($asciiName, $this->failing, true)) {
            throw DnsLookupFailed::for($asciiName);
        }

        return $this->records[$asciiName] ?? [];
    }
}
