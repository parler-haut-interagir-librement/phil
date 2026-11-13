<?php

declare(strict_types=1);

namespace Phil\Automations\Catalogue;

/**
 * The kind of value an option takes, which tells a no-code builder the field to show and the validator what to check.
 */
enum OptionType: string
{
    /** Any string of the options' bounds. */
    case Text = 'text';

    /** An integer. */
    case Integer = 'integer';

    /** `true` or `false`. */
    case Boolean = 'boolean';

    /** A name of the application's own records — a list key, a template key, a secret's name. */
    case Identifier = 'identifier';

    /** An `https` web address, read as a browser reads it. */
    case Url = 'url';

    /** One of the choices the option declares. */
    case Choice = 'choice';

    /** A list of identifiers. */
    case Identifiers = 'identifiers';
}
