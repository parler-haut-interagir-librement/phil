<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

/**
 * How the content was evaluated.
 */
enum EvaluationMethod: string
{
    /** By the organisation itself, people testing the content. */
    case SelfAssessment = 'self-assessment';

    /** By an independent party. */
    case ThirdParty = 'third-party';

    /** By an automated check, such as the reports of phil/accessibility-check. */
    case Automated = 'automated';
}
