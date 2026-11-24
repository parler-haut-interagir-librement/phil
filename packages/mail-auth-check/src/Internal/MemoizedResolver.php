<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Internal;

use Override;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Exception\DnsLookupFailed;

/**
 * Asks the resolver once per name during one check: the checks share a domain's records, failures included. A new
 * instance serves each check, so nothing is kept between them.
 *
 * @internal
 */
final class MemoizedResolver implements TxtResolver
{
    /** @var array<string, list<string>|DnsLookupFailed> */
    private array $answers = [];

    public function __construct(
        private readonly TxtResolver $resolver,
    ) {
    }

    #[Override]
    public function txt(string $asciiName): array
    {
        if (!\array_key_exists($asciiName, $this->answers)) {
            try {
                $this->answers[$asciiName] = $this->resolver->txt($asciiName);
            } catch (DnsLookupFailed $failure) {
                $this->answers[$asciiName] = $failure;
            }
        }

        $answer = $this->answers[$asciiName];

        if ($answer instanceof DnsLookupFailed) {
            throw $answer;
        }

        return $answer;
    }
}
