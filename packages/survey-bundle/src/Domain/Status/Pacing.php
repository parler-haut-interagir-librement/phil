<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Domain\Status;

/**
 * How the questions are shown: one at a time, a section at a time, or all at once. Without JavaScript, every question
 * is on one page; the Stimulus controller then shows them at this pace, with the progress.
 */
enum Pacing: string
{
    case Question = 'question';
    case Section = 'section';
    case All = 'all';
}
