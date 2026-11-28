<?php

declare(strict_types=1);

namespace Phil\EmailPreview;

use Phil\EmailPreview\Contract\Rule;
use Phil\EmailPreview\Rule\DefaultRules;

/**
 * Checks an email against its rules, without sending it, and reports what each found. It keeps no state between two
 * emails: one checker serves every request of a worker.
 */
final readonly class EmailChecker
{
    /** @var array<Rule> */
    private array $rules;

    /**
     * @param iterable<Rule>|null $rules the rules to run, in the order of the report; `DefaultRules::all()` when `null`
     */
    public function __construct(?iterable $rules = null)
    {
        $this->rules = null === $rules ? DefaultRules::all() : [...$rules];
    }

    public function check(CheckedEmail $email): Report
    {
        $names = [];
        $findings = [];

        foreach ($this->rules as $rule) {
            $names[] = $rule->name();

            foreach ($rule->check($email) as $finding) {
                $findings[] = $finding;
            }
        }

        return new Report($names, $findings);
    }
}
