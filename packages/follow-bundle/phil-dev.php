<?php

declare(strict_types=1);

use Phil\FollowBundle\Application\Handler\FanOutActivityHandler;

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // `COUNT(*)` is a number whatever the driver returns: the cast keeps the type for the drivers that give a
            // string, and the other result is a branch no query reaches.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*return is_numeric\\(\\$count\\) \\? \\(int\\) \\$count : 0;.*'],
            ],
            // `implode()` ignores the keys of the placeholders, so the first key of `array_fill()` is not in the statement.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return is_numeric\\(\\$count\\) \\? \\(int\\) \\$count : 0;.*',
                    '.*array_fill\\(0, \\\\count\\(\\$(columns|rows)\\), .*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return is_numeric\\(\\$count\\) \\? \\(int\\) \\$count : 0;.*',
                    '.*array_fill\\(0, \\\\count\\(\\$(columns|rows)\\), .*',
                ],
            ],
            // What is read here builds an activity, which makes its own list of mentions, and a statement that returns its
            // rows as a list.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_values\\(array_filter\\(\\$row->json\\(.mentions.\\), \\\\is_string\\(\\.\\.\\.\\)\\)\\),.*',
                    '.*return array_values\\(array_map\\(\\$this->hydrate\\(\\.\\.\\.\\), \\$rows\\)\\);.*',
                    // The notifications put the recipients in a list of their own.
                    '.*\\$loud = array_values\\(array_diff\\(\\$claimed, \\$quiet\\)\\);.*',
                ],
            ],
            // The UTC type of DBAL builds an instant from any text, or fails by itself: the refusal is for the type
            // checker, which cannot know it.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => ['.*\\$value instanceof DateTimeImmutable \\? \\$value : throw .*'],
            ],
            // `isset()` is true for a key whatever its value, so the value that marks a subject seen is never read.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$seen = \\[\\$subject->key\\(\\) => true\\];.*',
                    '.*\\$seen\\[\\$parent->key\\(\\)\\] = true;.*',
                ],
            ],
            // A chain of the subject alone has nothing to cut.
            'ArrayOneItem' => [
                'ignoreSourceCodeByRegex' => ['.*return \\$chain;.*'],
            ],
            // Two different members never compare equal.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['.*return strcmp\\(\\$one, \\$other\\) < 0 \\? new self.*'],
            ],
            // Without a claimed member the next lines send to no one and mark no one: the same as returning.
            'ReturnRemoval' => [
                'ignore' => [FanOutActivityHandler::class.'::deliver'],
            ],
            // The call only gives back the parent of the builder, which nothing reads.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*\\$children->end\\(\\);.*'],
            ],
        ],
    ],
];
