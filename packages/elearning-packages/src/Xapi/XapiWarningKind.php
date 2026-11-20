<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Xapi;

/**
 * What an xAPI package declares that the reader sets aside rather than refusing the package for.
 */
enum XapiWarningKind: string
{
    /** An activity has no name: its IRI stands for it. */
    case UnnamedActivity = 'unnamed_activity';
    /** An IRI is used by a second activity, which is left out. */
    case DuplicateActivity = 'duplicate_activity';
    /** The type of an activity is not an IRI, and is left out. */
    case InvalidActivityType = 'invalid_activity_type';
    /** An interaction type is not one xAPI defines, and is left out. */
    case UnknownInteractionType = 'unknown_interaction_type';
    /** An activity names an interaction type without being of type `cmi.interaction`. */
    case InteractionTypeWithoutInteraction = 'interaction_type_without_interaction';
    /** A name or a description is written in more languages than the reader keeps. */
    case TooManyLanguages = 'too_many_languages';
}
