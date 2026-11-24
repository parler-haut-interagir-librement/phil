<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Application;

use Override;
use Phil\SurveyBundle\Application\Contract\QuestionnaireTemplates;

/**
 * The templates the bundle ships: `session_feedback`, an anonymous feedback on a session — a mark of the session and
 * of the speaker, what to keep and what to change —, open from 15 minutes before it to 3 days after it, its results
 * shown from 3 responses.
 */
final readonly class BuiltInTemplates implements QuestionnaireTemplates
{
    public const string SESSION_FEEDBACK = 'session_feedback';

    #[Override]
    public function names(): array
    {
        return [self::SESSION_FEEDBACK => 'Session feedback'];
    }

    #[Override]
    public function template(string $name): ?array
    {
        if (self::SESSION_FEEDBACK !== $name) {
            return null;
        }

        return [
            'title' => 'Session feedback',
            'description' => 'Your answers are anonymous: nothing links them to you.',
            'kind' => 'session_feedback',
            'anonymity' => 'anonymous',
            'pacing' => 'all',
            'sections' => [[
                'name' => 'feedback',
                'title' => 'Your feedback',
                'questions' => [
                    ['name' => 'session', 'label' => 'How was the session?', 'type' => 'scale', 'required' => true, 'min' => 1, 'max' => 5],
                    ['name' => 'speaker', 'label' => 'How was the speaker?', 'type' => 'scale', 'min' => 1, 'max' => 5],
                    ['name' => 'keep', 'label' => 'What should be kept?', 'type' => 'textarea', 'max_length' => 2000],
                    ['name' => 'change', 'label' => 'What should change?', 'type' => 'textarea', 'max_length' => 2000],
                ],
            ]],
            'endings' => [['name' => 'thanks', 'title' => 'Thank you', 'message' => "Your feedback reaches the speaker with everyone else's."]],
            'results' => ['public' => false, 'minimum' => 3],
            'feedback' => ['opens_before' => 15, 'closes_after' => 4320],
        ];
    }
}
