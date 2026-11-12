<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Application;

use Phil\BrowserSecurityBundle\Application\Exception\UnknownPolicy;
use Phil\BrowserSecurityBundle\Application\HeaderBuilder;
use Phil\BrowserSecurityBundle\Application\PolicyOverride;
use Phil\BrowserSecurityBundle\Application\PolicyRegistry;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Phil\BrowserSecurityBundle\Domain\Syntax\SourceSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeaderBuilder::class)]
final class HeaderBuilderTest extends TestCase
{
    private PolicyRegistry $policies;

    protected function setUp(): void
    {
        $this->policies = PolicyRegistry::fromConfig([
            'default' => ['directives' => ['default-src' => ["'self'"], 'script-src' => ["'self'"]], 'strict_dynamic' => true],
            'strict' => ['directives' => ['default-src' => ["'none'"], 'script-src-elem' => ["'self'"], 'style-src' => ["'self'"], 'style-src-elem' => ["'self'"]]],
            'quiet' => ['directives' => ['default-src' => ["'self'"]], 'report' => false],
            'empty' => [],
        ]);
    }

    public function testTheEnforcedPolicyAlone(): void
    {
        $state = new RequestState();

        self::assertSame([HeaderBuilder::ENFORCE => "default-src 'self'; script-src 'self'"], new HeaderBuilder($this->policies)->build($state));
        self::assertSame('default', $state->policyName);
    }

    public function testTheReportingDirectivesAndEndpoint(): void
    {
        $headers = new HeaderBuilder($this->policies, 'default', 'strict', 'violations')->build(new RequestState(), 'https://example.com/_csp/report');

        self::assertSame([
            HeaderBuilder::ENFORCE => "default-src 'self'; script-src 'self'; report-uri https://example.com/_csp/report; report-to violations",
            HeaderBuilder::REPORT_ONLY => "default-src 'none'; script-src-elem 'self'; style-src 'self'; style-src-elem 'self'; report-uri https://example.com/_csp/report; report-to violations",
            HeaderBuilder::REPORTING_ENDPOINTS => 'violations="https://example.com/_csp/report"',
        ], $headers);
    }

    public function testAPolicyWithoutReporting(): void
    {
        self::assertSame([HeaderBuilder::ENFORCE => "default-src 'self'"], new HeaderBuilder($this->policies, 'quiet')->build(new RequestState(), 'https://example.com/r'));
        self::assertSame(
            [HeaderBuilder::ENFORCE => "default-src 'self'", HeaderBuilder::REPORT_ONLY => "default-src 'self'; script-src 'self'; report-uri /r; report-to csp", HeaderBuilder::REPORTING_ENDPOINTS => 'csp="/r"'],
            new HeaderBuilder($this->policies, 'quiet', 'default')->build(new RequestState(), '/r'),
        );
    }

    public function testOnlyAReportOnlyPolicy(): void
    {
        $state = new RequestState();

        self::assertSame([HeaderBuilder::REPORT_ONLY => "default-src 'self'; script-src 'self'"], new HeaderBuilder($this->policies, null, 'default')->build($state));
        self::assertNull($state->policyName);
        self::assertSame([], new HeaderBuilder($this->policies, null)->build(new RequestState(), '/r'));
    }

    public function testAnEmptyPolicyWritesNoHeader(): void
    {
        self::assertSame([], new HeaderBuilder($this->policies, 'empty')->build(new RequestState()));
    }

    public function testTheNoncesAndHashesOfTheRequest(): void
    {
        $state = new RequestState();
        $script = $state->nonce(InlineKind::Script);
        $state->addHash(InlineKind::Script, 'a()');
        $style = $state->nonce(InlineKind::Style);

        self::assertSame(
            [HeaderBuilder::ENFORCE => "default-src 'self'; script-src 'self' 'nonce-".$script."' ".SourceSyntax::hashOf('a()')." 'strict-dynamic'; style-src 'self' 'nonce-".$style."'"],
            new HeaderBuilder($this->policies)->build($state),
        );
    }

    public function testTheElementDirectivesGetTheTokensToo(): void
    {
        $state = new RequestState();
        $script = $state->nonce(InlineKind::Script);
        $style = $state->nonce(InlineKind::Style);

        self::assertSame(
            [HeaderBuilder::ENFORCE => "default-src 'none'; script-src-elem 'self' 'nonce-".$script."'; style-src 'self' 'nonce-".$style."'; style-src-elem 'self' 'nonce-".$style."'"],
            new HeaderBuilder($this->policies, 'strict')->build($state),
        );
    }

    public function testStrictDynamicNeedsANonceOrAHash(): void
    {
        $state = new RequestState();
        $state->nonce(InlineKind::Style);

        self::assertSame(
            [HeaderBuilder::ENFORCE => "default-src 'self'; script-src 'self'; style-src 'self' 'nonce-".$state->nonce(InlineKind::Style)."'"],
            new HeaderBuilder($this->policies)->build($state),
        );
    }

    public function testTheOverridesInOrder(): void
    {
        $state = new RequestState();
        $state->addOverride(new PolicyOverride('quiet', add: ['img-src' => ['data:']]));
        $state->addOverride(new PolicyOverride(add: ['img-src' => ['https://cdn.example.com']]));

        self::assertSame(
            [HeaderBuilder::ENFORCE => "default-src 'self'; img-src 'self' data: https://cdn.example.com", HeaderBuilder::REPORT_ONLY => "default-src 'self'; script-src 'self'; img-src 'self' data: https://cdn.example.com"],
            new HeaderBuilder($this->policies, 'default', 'default')->build($state),
        );
        self::assertSame('quiet', $state->policyName);
    }

    public function testAnOverrideSendsTheEnforcedPolicyAsReportOnly(): void
    {
        $state = new RequestState();
        $state->addOverride(new PolicyOverride(reportOnly: true));
        $state->addOverride(new PolicyOverride('quiet'));

        self::assertSame([HeaderBuilder::REPORT_ONLY => "default-src 'self'"], new HeaderBuilder($this->policies, 'default', 'strict')->build($state));

        $state->addOverride(new PolicyOverride(reportOnly: false));
        self::assertSame(
            [HeaderBuilder::ENFORCE => "default-src 'self'", HeaderBuilder::REPORT_ONLY => "default-src 'none'; script-src-elem 'self'; style-src 'self'; style-src-elem 'self'"],
            new HeaderBuilder($this->policies, 'default', 'strict')->build($state),
        );
    }

    public function testAnOverrideNamingAnUnknownPolicy(): void
    {
        $state = new RequestState();
        $state->addOverride(new PolicyOverride('other'));

        $this->expectException(UnknownPolicy::class);

        new HeaderBuilder($this->policies)->build($state);
    }

    public function testAnOverrideWithAnInvalidSource(): void
    {
        $state = new RequestState();
        $state->addOverride(new PolicyOverride(add: ['img-src' => ['bad source']]));

        $this->expectException(InvalidPolicy::class);

        new HeaderBuilder($this->policies)->build($state);
    }

    public function testAnOverrideCanDisableThePolicy(): void
    {
        $state = new RequestState();
        $state->addOverride(new PolicyOverride(disabled: true));
        $state->addOverride(new PolicyOverride(add: ['img-src' => ['data:']]));

        self::assertSame([], new HeaderBuilder($this->policies, 'default', 'strict')->build($state));
        self::assertNull($state->policyName);
    }
}
