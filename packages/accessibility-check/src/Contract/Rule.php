<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Contract;

use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;

/**
 * A check of one aspect of accessibility: its identifier, the success criterion of WCAG it tests and the severity of
 * what it finds. A rule reads HTML (`HtmlRule`), UJO documents (`UjoRule`), or both; it holds no state between two
 * contents.
 */
interface Rule
{
    /**
     * The identifier of the rule, in kebab case, such as `image-alt`: it names the rule in the policy, in the report
     * and in the keys of its messages.
     */
    public function id(): string;

    public function criterion(): SuccessCriterion;

    /**
     * The severity of what the rule finds, unless a violation or the policy says otherwise.
     */
    public function severity(): Severity;
}
