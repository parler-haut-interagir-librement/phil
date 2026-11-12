<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

use Countable;
use JsonSerializable;
use Override;
use Phil\AccessibilityCheck\Translation\Messages;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What a check found: the outcome of each rule that ran, in the order of the rules, and the findings, rule after rule
 * and in the order of the content within a rule. Its messages are written in its locale, by its translator.
 */
final readonly class Report implements Countable, JsonSerializable
{
    public TranslatorInterface $translator;

    /**
     * @param list<RuleResult>     $results
     * @param list<Finding>        $findings
     * @param ?TranslatorInterface $translator the translator of the messages; `Messages::translator()` when `null`
     */
    public function __construct(
        public array $results = [],
        public array $findings = [],
        ?TranslatorInterface $translator = null,
        public string $locale = 'en',
    ) {
        $this->translator = $translator ?? Messages::translator();
    }

    /**
     * Whether the content may be published: no finding is `$minimum` or graver.
     */
    public function passes(Severity $minimum = Severity::Error): bool
    {
        return [] === $this->findings($minimum);
    }

    /**
     * The findings that are `$minimum` or graver; every finding by default.
     *
     * @return list<Finding>
     */
    public function findings(Severity $minimum = Severity::Notice): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (Finding $finding): bool => $finding->severity->isAtLeast($minimum),
        ));
    }

    /**
     * The findings of exactly one severity.
     *
     * @return list<Finding>
     */
    public function withSeverity(Severity $severity): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (Finding $finding): bool => $severity === $finding->severity,
        ));
    }

    /**
     * The findings of one rule.
     *
     * @return list<Finding>
     */
    public function ofRule(string $rule): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn (Finding $finding): bool => $rule === $finding->rule,
        ));
    }

    /**
     * The outcome of one rule, or `null` when it did not run.
     */
    public function result(string $rule): ?RuleResult
    {
        foreach ($this->results as $result) {
            if ($rule === $result->rule) {
                return $result;
            }
        }

        return null;
    }

    /**
     * What a finding says, in the report's locale.
     */
    public function message(Finding $finding): string
    {
        return $this->translate($finding->message);
    }

    /**
     * A message in the report's locale, its placeholders replaced by their values.
     */
    public function translate(Message $message): string
    {
        return Messages::translate($this->translator, $message, $this->locale);
    }

    /**
     * The same report, its messages written in another locale.
     */
    public function inLocale(string $locale): self
    {
        return new self($this->results, $this->findings, $this->translator, $locale);
    }

    #[Override]
    public function count(): int
    {
        return \count($this->findings);
    }

    /**
     * @return array{passed: bool, locale: string, counts: array{error: int, warning: int, notice: int}, rules: list<array{rule: string, criterion: string, level: string, severity: string, passed: bool, findings: int}>, findings: list<array{rule: string, criterion: string, level: string, severity: string, message: string, messageKey: string, parameters: array<string, int|float|string>, location: array<string, int|string>}>}
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passes(),
            'locale' => $this->locale,
            'counts' => [
                'error' => \count($this->withSeverity(Severity::Error)),
                'warning' => \count($this->withSeverity(Severity::Warning)),
                'notice' => \count($this->withSeverity(Severity::Notice)),
            ],
            'rules' => array_map(static fn (RuleResult $result): array => $result->toArray(), $this->results),
            'findings' => array_map(fn (Finding $finding): array => $finding->toArray($this->message($finding)), $this->findings),
        ];
    }

    /**
     * @return array{passed: bool, locale: string, counts: array{error: int, warning: int, notice: int}, rules: list<array{rule: string, criterion: string, level: string, severity: string, passed: bool, findings: int}>, findings: list<array{rule: string, criterion: string, level: string, severity: string, message: string, messageKey: string, parameters: array<string, int|float|string>, location: array<string, int|string>}>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
