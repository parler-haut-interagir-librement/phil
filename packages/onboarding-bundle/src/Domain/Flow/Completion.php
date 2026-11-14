<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Flow;

/**
 * How a step gets done: ticked by the person, when a Symfony event is dispatched, when a checker of the application
 * says so, or when the person answers its question.
 */
enum Completion: string
{
    case Manual = 'manual';
    case Event = 'event';
    case Checker = 'checker';
    case Question = 'question';
}
