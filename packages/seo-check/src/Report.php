<?php

declare(strict_types=1);

namespace Phil\SeoCheck;

use Countable;
use Override;
use Phil\SeoCheck\Translation\Messages;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * What the check found in a page: the points to correct, rule after rule and in the order of the page within a rule.
 * There is no overall score: a list of what to correct says more than a number. Its messages are written in its locale,
 * by its translator.
 */
final readonly class Report implements Countable
{
    public TranslatorInterface $translator;

    /**
     * @param list<Finding>        $findings
     * @param ?TranslatorInterface $translator the translator of the messages; `Messages::translator()` when null
     */
    public function __construct(
        public array $findings = [],
        ?TranslatorInterface $translator = null,
        public string $locale = 'en',
    ) {
        $this->translator = $translator ?? Messages::translator();
    }

    /**
     * Whether the check found nothing to correct, information included.
     */
    public function isEmpty(): bool
    {
        return [] === $this->findings;
    }

    /**
     * The findings that are `$minimum` or graver; every finding by default.
     *
     * @return list<Finding>
     */
    public function findings(Severity $minimum = Severity::Info): array
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
        return new self($this->findings, $this->translator, $locale);
    }

    #[Override]
    public function count(): int
    {
        return \count($this->findings);
    }

    /**
     * @return array{locale: string, counts: array{error: int, warning: int, info: int}, findings: list<array{rule: string, severity: string, message: string, messageKey: string, parameters: array<string, int|string>, subject: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'counts' => [
                'error' => \count($this->withSeverity(Severity::Error)),
                'warning' => \count($this->withSeverity(Severity::Warning)),
                'info' => \count($this->withSeverity(Severity::Info)),
            ],
            'findings' => array_map(fn (Finding $finding): array => $finding->toArray($this->message($finding)), $this->findings),
        ];
    }
}
