<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Support;

use Override;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Dns\StaticTxtResolver;

/**
 * A static resolver that remembers the names it was asked, in order.
 */
final class CountingResolver implements TxtResolver
{
    /** @var list<string> */
    public array $asked = [];

    private readonly StaticTxtResolver $resolver;

    /**
     * @param array<string, list<string>> $records
     * @param list<string>                $failing
     */
    public function __construct(array $records, array $failing = [])
    {
        $this->resolver = new StaticTxtResolver($records, $failing);
    }

    #[Override]
    public function txt(string $asciiName): array
    {
        $this->asked[] = $asciiName;

        return $this->resolver->txt($asciiName);
    }
}
