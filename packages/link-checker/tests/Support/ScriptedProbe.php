<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Support;

use Override;
use Phil\LinkChecker\Contract\LinkProbe;
use Phil\LinkChecker\LinkResult;
use Phil\LinkChecker\LinkStatus;
use Phil\LinkChecker\Probe\ProbeResult;
use Phil\Url\Url;

/**
 * A probe that answers from a script, by address, and remembers what it was asked: an address it does not know is
 * alive with the code 200.
 */
final class ScriptedProbe implements LinkProbe
{
    /** @var list<string> */
    private array $asked = [];

    /**
     * @param array<string, ProbeResult> $answers
     */
    public function __construct(
        private readonly array $answers = [],
    ) {
    }

    /**
     * The requests asked for since the last call, in order — `HEAD` and the address, or `GET` when the HTML was asked
     * for —, then forgotten.
     *
     * @return list<string>
     */
    public function asked(): array
    {
        $asked = $this->asked;
        $this->asked = [];

        return $asked;
    }

    #[Override]
    public function probe(Url $url, bool $withBody = false): ProbeResult
    {
        $this->asked[] = ($withBody ? 'GET ' : 'HEAD ').$url->toString();

        return $this->answers[$url->toString()] ?? new ProbeResult(new LinkResult(LinkStatus::Alive, 200));
    }
}
