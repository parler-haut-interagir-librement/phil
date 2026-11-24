<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Status;

/**
 * Whether a response names its respondent. Anonymous, the default, keeps nothing that links a response to a person:
 * neither the member's identifier nor an IP address, its identifier random and its instant the day only.
 */
enum Anonymity: string
{
    case Anonymous = 'anonymous';
    case Identified = 'identified';
}
