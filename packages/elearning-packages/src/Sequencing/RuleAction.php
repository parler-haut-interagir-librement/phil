<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * What a sequencing rule does when its conditions hold: before an activity is entered (skip, disabled, hidden from
 * choice, stop forward traversal), when an attempt on a descendant ends (exit), or after an attempt ends (exit parent,
 * exit all, retry, retry all, continue, previous).
 */
enum RuleAction: string
{
    case Skip = 'skip';
    case Disabled = 'disabled';
    case HiddenFromChoice = 'hiddenFromChoice';
    case StopForwardTraversal = 'stopForwardTraversal';
    case Exit = 'exit';
    case ExitParent = 'exitParent';
    case ExitAll = 'exitAll';
    case Retry = 'retry';
    case RetryAll = 'retryAll';
    case Continue = 'continue';
    case Previous = 'previous';

    public function timing(): RuleTiming
    {
        return match ($this) {
            self::Skip, self::Disabled, self::HiddenFromChoice, self::StopForwardTraversal => RuleTiming::Pre,
            self::Exit => RuleTiming::Exit,
            default => RuleTiming::Post,
        };
    }
}
