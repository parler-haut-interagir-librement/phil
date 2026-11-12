<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

use Phil\AccessibilityCheck\Contract\HtmlRule;
use Phil\AccessibilityCheck\Contract\Rule;
use Phil\AccessibilityCheck\Contract\UjoRule;
use Phil\AccessibilityCheck\Exception\InvalidContent;
use Phil\AccessibilityCheck\Exception\InvalidPolicy;
use Phil\AccessibilityCheck\Exception\LimitExceeded;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Rule\DefaultRules;
use Phil\AccessibilityCheck\Translation\Messages;
use Phil\AccessibilityCheck\Ujo\PlainFields;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\Ujo\Document;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Checks the accessibility of a content before it is published: an HTML page or fragment, or a UJO document read
 * block by block. Each rule the policy keeps runs in the order the rules were given, and the report holds the outcome
 * of each and what it found.
 *
 * The checker holds no state between two checks: build it once, with the application's policy and rules, and keep it.
 */
final readonly class AccessibilityChecker
{
    /**
     * @var list<Rule>
     */
    private array $rules;

    private TranslatorInterface $translator;

    /**
     * @param ?iterable<Rule>      $rules      the rules, in the order they run; those of `DefaultRules::all()` when `null`
     * @param ?TranslatorInterface $translator the translator of the messages, `Messages::translator()` when `null`
     *
     * @throws InvalidPolicy when two rules have one identifier
     */
    public function __construct(
        private Policy $policy = new Policy(),
        ?iterable $rules = null,
        ?TranslatorInterface $translator = null,
        private PlainFields $plainFields = new PlainFields(),
    ) {
        $list = [];

        foreach ($rules ?? DefaultRules::all() as $rule) {
            if (isset($list[$rule->id()])) {
                throw InvalidPolicy::duplicateRule($rule->id());
            }

            $list[$rule->id()] = $rule;
        }

        $this->rules = array_values($list);
        $this->translator = $translator ?? Messages::translator();
    }

    /**
     * The same checker with another policy.
     */
    public function withPolicy(Policy $policy): self
    {
        return clone ($this, ['policy' => $policy]);
    }

    public function policy(): Policy
    {
        return $this->policy;
    }

    /**
     * The rules, in the order they run, those the policy turns off included.
     *
     * @return list<Rule>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Checks an HTML page or fragment with the rules that read HTML.
     *
     * @param ?string $language the language of the content when it does not say its own; the policy's when `null`
     *
     * @throws LimitExceeded  when the content is larger, deeper or holds more elements than the policy allows
     * @throws InvalidContent when it is not UTF-8
     */
    public function checkHtml(string $html, ?string $language = null): Report
    {
        $target = HtmlTarget::parse($html, $this->policy, $language ?? $this->policy->language);

        return $this->report(HtmlRule::class, static fn (HtmlRule $rule): iterable => $rule->checkHtml($target));
    }

    /**
     * Checks a UJO document with the rules that read UJO; a normalized document is located best.
     *
     * @param ?string $language the language of the document; the policy's when `null`
     *
     * @throws LimitExceeded  when the document holds more blocks or nests deeper than the policy allows, or one of its
     *                        fields holds more HTML than it allows
     * @throws InvalidContent when a field holds HTML that is not UTF-8
     */
    public function checkUjo(Document $document, ?string $language = null): Report
    {
        $target = UjoTarget::read($document, $this->policy, $language ?? $this->policy->language, $this->plainFields);

        return $this->report(UjoRule::class, static fn (UjoRule $rule): iterable => $rule->checkUjo($target));
    }

    /**
     * @template T of Rule
     *
     * @param class-string<T>                  $kind
     * @param callable(T): iterable<Violation> $check
     */
    private function report(string $kind, callable $check): Report
    {
        $results = [];
        $findings = [];

        foreach ($this->rules as $rule) {
            if (!$rule instanceof $kind || !$this->policy->isEnabled($rule->id())) {
                continue;
            }

            $count = 0;

            foreach ($check($rule) as $violation) {
                $findings[] = new Finding(
                    $rule->id(),
                    $violation->criterion ?? $rule->criterion(),
                    $this->policy->severities[$rule->id()] ?? $violation->severity ?? $rule->severity(),
                    $violation->message,
                    $violation->location,
                );
                ++$count;
            }

            $results[] = new RuleResult($rule->id(), $rule->criterion(), $this->policy->severities[$rule->id()] ?? $rule->severity(), $count);
        }

        return new Report($results, $findings, $this->translator, $this->policy->locale);
    }
}
