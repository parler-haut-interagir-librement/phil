<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Support;

use Override;
use Phil\LinkChecker\Contract\LinkProbe;
use Phil\LinkChecker\LinkResult;
use Phil\LinkChecker\LinkStatus;
use Phil\LinkChecker\Probe\ProbeResult;
use Phil\Url\Url;

/**
 * A probe of phil/link-checker that answers from a script, by address, and remembers what it was asked: an address it
 * does not know is alive with the code 200.
 */
final class ScriptedLinkProbe implements LinkProbe
{
    /** @var list<string> */
    public array $asked = [];

    /**
     * @param array<string, LinkResult> $answers
     */
    public function __construct(
        private readonly array $answers = [],
    ) {
    }

    #[Override]
    public function probe(Url $url, bool $withBody = false): ProbeResult
    {
        $this->asked[] = $url->toString();

        return new ProbeResult($this->answers[$url->toString()] ?? new LinkResult(LinkStatus::Alive, 200));
    }
}
