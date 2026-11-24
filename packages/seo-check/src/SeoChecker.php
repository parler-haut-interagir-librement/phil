<?php

declare(strict_types=1);

namespace Phil\SeoCheck;

use Phil\SeoCheck\Contract\SeoRule;
use Phil\SeoCheck\Rule\DefaultRules;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Runs the rules on a published page and lists what to correct. It never stops a publication: the page is already
 * online when it is checked, and the report is read by whoever published it. A rule that fails — a port that cannot
 * answer — gives a warning that says so, and the other rules still run.
 *
 * It keeps nothing from one page to the next: one instance serves every request of a worker.
 */
final readonly class SeoChecker
{
    /** @var list<SeoRule> */
    private array $rules;

    /**
     * @param iterable<SeoRule>|null $rules      the rules, in the order of the report; `DefaultRules::create()` when null
     * @param ?TranslatorInterface   $translator the translator of the messages; `Messages::translator()` when null
     */
    public function __construct(
        ?iterable $rules = null,
        private ?TranslatorInterface $translator = null,
    ) {
        $this->rules = null === $rules ? DefaultRules::create() : iterator_to_array($rules, false);
    }

    /**
     * @param string $locale the language the report's messages are written in
     */
    public function check(CheckedPage $page, string $locale = 'en'): Report
    {
        $findings = [];

        foreach ($this->rules as $rule) {
            try {
                foreach ($rule->check($page) as $finding) {
                    $findings[] = $finding;
                }
            } catch (Throwable $throwable) {
                $findings[] = new Finding($rule->name(), Severity::Warning, new Message('check.rule-failed', ['rule' => $rule->name(), 'error' => $throwable::class]));
            }
        }

        return new Report($findings, $this->translator, $locale);
    }
}
