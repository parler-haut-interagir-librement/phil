<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Fixtures;

use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;

/**
 * Questionnaires the tests share, as data, each overridden member by member.
 */
final class Samples
{
    private function __construct()
    {
    }

    /**
     * A questionnaire of every type: an age band that shows a section for adults, a score computed from two marks, a
     * profile chosen by the score, a matrix, a ranking.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function survey(array $overrides = []): array
    {
        return [...[
            'key' => 'games',
            'title' => 'Games',
            'sections' => [
                [
                    'name' => 'about',
                    'title' => 'About you',
                    'questions' => [
                        ['name' => 'age', 'label' => 'Age band', 'type' => 'single_choice', 'required' => true, 'choices' => [['value' => 'minor', 'label' => 'Under 18'], ['value' => 'adult', 'label' => '18 or over']]],
                        ['name' => 'region', 'label' => 'Region', 'type' => 'single_choice', 'choices' => [['value' => 'north', 'label' => 'North'], ['value' => 'south', 'label' => 'South']]],
                        ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                    ],
                ],
                [
                    'name' => 'adults',
                    'title' => 'For adults',
                    'shown_when' => ['==' => [['var' => 'age'], 'adult']],
                    'questions' => [
                        ['name' => 'fun', 'label' => 'Fun', 'type' => 'scale', 'required' => true, 'min' => 1, 'max' => 5],
                        ['name' => 'rules', 'label' => 'Rules', 'type' => 'scale', 'min' => 1, 'max' => 5],
                        ['name' => 'comment', 'label' => 'Comment', 'type' => 'textarea', 'shown_when' => ['<' => [['var' => 'fun'], 3]]],
                    ],
                ],
                [
                    'name' => 'more',
                    'title' => 'More',
                    'questions' => [
                        ['name' => 'genres', 'label' => 'Genres', 'type' => 'multiple_choice', 'max' => 2, 'choices' => [['value' => 'horror', 'label' => 'Horror'], ['value' => 'fantasy', 'label' => 'Fantasy'], ['value' => 'scifi', 'label' => 'Science fiction']]],
                        ['name' => 'order', 'label' => 'Order', 'type' => 'ranking', 'choices' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B'], ['value' => 'c', 'label' => 'C']]],
                        ['name' => 'grid', 'label' => 'Grid', 'type' => 'matrix', 'rows' => [['value' => 'food', 'label' => 'Food'], ['value' => 'room', 'label' => 'Room']], 'choices' => [['value' => 'bad', 'label' => 'Bad'], ['value' => 'good', 'label' => 'Good']]],
                        ['name' => 'again', 'label' => 'Again?', 'type' => 'yes_no'],
                        ['name' => 'players', 'label' => 'Players', 'type' => 'number', 'min' => 1, 'max' => 12],
                        ['name' => 'played', 'label' => 'Played on', 'type' => 'date'],
                        ['name' => 'recommend', 'label' => 'Recommend', 'type' => 'net_promoter'],
                        ['name' => 'nickname', 'label' => 'Nickname', 'type' => 'text', 'max_length' => 10],
                    ],
                ],
            ],
            'computed' => [['compute' => 'score', 'type' => 'number', 'value' => ['+' => [['var' => 'fun'], ['var' => 'rules']]]]],
            'endings' => [
                ['name' => 'fan', 'title' => 'A fan', 'profile' => 'Enthusiast', 'when' => ['>=' => [['var' => 'score'], 8]]],
                ['name' => 'critic', 'title' => 'A critic', 'profile' => 'Critic', 'when' => ['==' => [['var' => 'grid.food'], 'bad']]],
                ['name' => 'thanks', 'title' => 'Thanks', 'message' => 'Thank you.'],
            ],
        ], ...$overrides];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function questionnaire(array $overrides = []): Questionnaire
    {
        return Questionnaire::fromArray(self::survey($overrides));
    }

    /**
     * A one-question questionnaire: a single choice between red and blue.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    public static function colour(array $overrides = []): array
    {
        return [...[
            'key' => 'colour',
            'title' => 'Colour',
            'sections' => [['name' => 'main', 'title' => 'Main', 'questions' => [
                ['name' => 'colour', 'label' => 'Colour', 'type' => 'single_choice', 'required' => true, 'choices' => [['value' => 'red', 'label' => 'Red'], ['value' => 'blue', 'label' => 'Blue']]],
            ]]],
            'endings' => [['name' => 'thanks', 'title' => 'Thanks']],
        ], ...$overrides];
    }
}
