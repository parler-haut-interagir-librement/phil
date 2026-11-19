<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Fixture;

use Phil\RedirectsBundle\Application\LocationBuilder;
use Phil\RedirectsBundle\Application\TargetPolicy;
use Phil\ShortLinkBundle\Application\Code\CodeGenerator;
use Phil\ShortLinkBundle\Application\Code\SqidCodec;
use Phil\ShortLinkBundle\Application\Contract\CreationThrottle;
use Phil\ShortLinkBundle\Application\Contract\TargetCheck;
use Phil\ShortLinkBundle\Application\ShortLinkDomain;
use Phil\ShortLinkBundle\Application\ShortLinkDomains;
use Phil\ShortLinkBundle\Application\ShortLinkResolver;
use Phil\ShortLinkBundle\Application\ShortLinks;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Clock\MockClock;

/**
 * The services of the tests on one set of domains: `go.example.com` for every locale, `lien.example.fr` for `fr`, and
 * links allowed to `example.com` and `example.fr`, with a clock at noon UTC on 2026-11-12 and draws from a seeded
 * engine.
 */
final class Links
{
    public InMemoryShortLinks $repository;

    public MockClock $clock;

    public SqidCodec $codec;

    public ShortLinkDomains $domains;

    public function __construct()
    {
        $this->repository = new InMemoryShortLinks();
        $this->clock = new MockClock('2026-11-12 12:00:00 UTC');
        $this->codec = new SqidCodec(minLength: 6, blocklist: ['fuck']);
        $this->domains = new ShortLinkDomains([new ShortLinkDomain('go.example.com'), new ShortLinkDomain('lien.example.fr', 'fr')]);
    }

    public function links(bool $anyHost = false, bool $customCodes = true, ?TargetCheck $check = null, ?CreationThrottle $throttle = null, ?CodeGenerator $generator = null): ShortLinks
    {
        return new ShortLinks(
            $this->repository,
            $this->domains,
            $this->codec,
            $generator ?? new CodeGenerator($this->codec, randomizer: new Randomizer(new Mt19937(42))),
            new TargetPolicy(['example.com', 'example.fr']),
            $this->clock,
            $anyHost,
            $customCodes,
            check: $check,
            throttle: $throttle,
        );
    }

    public function resolver(?string $homeUrl = null): ShortLinkResolver
    {
        return new ShortLinkResolver($this->repository, $this->domains, $this->codec, new LocationBuilder(), $this->clock, $homeUrl);
    }
}
