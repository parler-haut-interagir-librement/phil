<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * When a sequencing rule is evaluated: `<imsss:preConditionRule>`, `<imsss:exitConditionRule>` or
 * `<imsss:postConditionRule>`.
 */
enum RuleTiming: string
{
    case Pre = 'preConditionRule';
    case Exit = 'exitConditionRule';
    case Post = 'postConditionRule';
}
