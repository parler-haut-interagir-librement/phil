<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Benchmark;

use Phil\BrowserSecurityBundle\Application\HeaderBuilder;
use Phil\BrowserSecurityBundle\Application\PolicyOverride;
use Phil\BrowserSecurityBundle\Application\PolicyRegistry;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Phil\BrowserSecurityBundle\PhilBrowserSecurityBundle;

/**
 * The headers of one response — the hot path, paid by every page —, from a policy of a dozen directives with a
 * report-only policy beside it: without nonce, with the two nonces of a page and a hash, and with an override of its
 * route.
 */
final readonly class HeaderBuilderBench
{
    private const string REPORT_URI = 'https://www.example.com/_csp/report';

    private HeaderBuilder $builder;

    private RequestState $nonces;

    private RequestState $overridden;

    public function __construct()
    {
        $policies = PolicyRegistry::fromConfig([
            'default' => ['directives' => PhilBrowserSecurityBundle::DEFAULT_POLICY + ['media-src' => ["'self'", 'https://media.example.com']], 'strict_dynamic' => true],
            'strict' => ['extends' => 'default', 'directives' => ['style-src' => ["'self'"], 'img-src' => ["'self'"]]],
        ]);
        $this->builder = new HeaderBuilder($policies, 'default', 'strict');
        $this->nonces = new RequestState();
        $this->nonces->nonce(InlineKind::Script);
        $this->nonces->nonce(InlineKind::Style);
        $this->nonces->addHash(InlineKind::Script, "console.log('inline')");

        $this->overridden = new RequestState();
        $this->overridden->addOverride(new PolicyOverride(add: ['img-src' => ['https://images.example.com']]));
    }

    public function benchWithoutNonce(): void
    {
        $this->builder->build(new RequestState(), self::REPORT_URI);
    }

    public function benchWithNoncesAndAHash(): void
    {
        $this->builder->build($this->nonces, self::REPORT_URI);
    }

    public function benchWithAnOverride(): void
    {
        $this->builder->build($this->overridden, self::REPORT_URI);
    }

    public function benchANewNonce(): void
    {
        new RequestState()->nonce(InlineKind::Script);
    }
}
