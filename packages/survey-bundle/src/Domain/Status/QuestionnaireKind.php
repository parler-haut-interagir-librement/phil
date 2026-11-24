<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Status;

/**
 * What a questionnaire is for: a survey, the feedback of a session, open in a window around it, a letter written
 * after a game, or a routing form, whose endings lead each respondent somewhere.
 */
enum QuestionnaireKind: string
{
    case Survey = 'survey';
    case SessionFeedback = 'session_feedback';
    case PostGameLetter = 'post_game_letter';
    case Routing = 'routing';

    /**
     * Whether a response is about a subject the application names, such as a session.
     */
    public function needsSubject(): bool
    {
        return self::SessionFeedback === $this;
    }
}
