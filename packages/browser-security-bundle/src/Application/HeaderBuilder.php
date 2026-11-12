<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Domain\Enum\InlineKind;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;

/**
 * The headers of a response from the named policies, the overrides of its request, its nonces and hashes, and the
 * address its reports go to.
 *
 * The enforced policy goes in `Content-Security-Policy` and the report-only policy, when the configuration names one,
 * in `Content-Security-Policy-Report-Only`, so that a stricter policy can be tried on real traffic while the current
 * one keeps protecting. An override sending the enforced policy as report-only takes that header's place, and one
 * disabling the policy sends neither. A request without nonce, hash nor override pays only for reading the strings built with the policies.
 */
final readonly class HeaderBuilder
{
    public const string ENFORCE = 'Content-Security-Policy';

    public const string REPORT_ONLY = 'Content-Security-Policy-Report-Only';

    public const string REPORTING_ENDPOINTS = 'Reporting-Endpoints';

    public const string DEFAULT_GROUP = 'csp';

    public function __construct(
        private PolicyRegistry $policies,
        private ?string $enforced = 'default',
        private ?string $reportOnly = null,
        private string $group = self::DEFAULT_GROUP,
    ) {
    }

    /**
     * @param string|null $reportUri the absolute address of the reports, or none to send no reporting directive
     *
     * @return array<string, string> the values by header name, without the empty ones
     *
     * @throws InvalidPolicy when an override names an unknown policy or an invalid source
     */
    public function build(RequestState $state, ?string $reportUri = null): array
    {
        $enforced = $this->enforced;
        $asReportOnly = false;
        $disabled = false;

        foreach ($state->overrides as $override) {
            $enforced = $override->policy ?? $enforced;
            $asReportOnly = $override->reportOnly ?? $asReportOnly;
            $disabled = $disabled || $override->disabled;
        }

        if ($disabled) {
            return [];
        }

        $state->policyName = $enforced;
        $headers = [];
        $reporting = false;

        if (null !== $enforced) {
            $headers[$asReportOnly ? self::REPORT_ONLY : self::ENFORCE] = $this->render($enforced, $state, $reportUri, $reporting);
        }

        if (null !== $this->reportOnly && !isset($headers[self::REPORT_ONLY])) {
            $headers[self::REPORT_ONLY] = $this->render($this->reportOnly, $state, $reportUri, $reporting);
        }

        if ($reporting) {
            $headers[self::REPORTING_ENDPOINTS] = $this->group.'="'.$reportUri.'"';
        }

        return array_filter($headers, static fn (string $value): bool => '' !== $value);
    }

    private function render(string $name, RequestState $state, ?string $reportUri, bool &$reporting): string
    {
        $definition = $this->policies->get($name);
        $policy = $definition->policy;

        foreach ($state->overrides as $override) {
            $policy = $override->apply($policy);
        }

        $tokens = [];

        foreach (InlineKind::cases() as $kind) {
            $sources = $state->sources($kind);

            if ([] === $sources) {
                continue;
            }

            if (InlineKind::Script === $kind && $definition->strictDynamic) {
                $sources[] = "'strict-dynamic'";
            }

            $tokens[$kind->directive()->value] = $sources;

            if (null !== $policy->sources($kind->elementDirective())) {
                $tokens[$kind->elementDirective()->value] = $sources;
            }
        }

        $report = $definition->report && null !== $reportUri;
        $reporting = $reporting || $report;

        return $policy->header($tokens, $report ? 'report-uri '.$reportUri.'; report-to '.$this->group : '');
    }
}
