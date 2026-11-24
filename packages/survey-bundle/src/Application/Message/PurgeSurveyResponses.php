<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application\Message;

/**
 * Deletes the responses each questionnaire keeps past its retention. It carries nothing: the handler reads the
 * retentions and the clock, so that running it twice deletes nothing more.
 */
final readonly class PurgeSurveyResponses
{
}
