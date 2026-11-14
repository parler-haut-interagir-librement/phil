<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Setting;

/**
 * The type of a setting's value, which decides how it is read, checked, stored and shown in a form.
 */
enum SettingType: string
{
    /** `true` or `false`: a checkbox. */
    case Boolean = 'boolean';

    /** An integer, between a minimum and a maximum when they are given: a number field. */
    case Integer = 'integer';

    /** One line of text, of a bounded length, matching a pattern when one is given: a text field. */
    case Text = 'text';

    /** One of the declared choices: radio buttons. */
    case Choice = 'choice';

    /** Any number of the declared choices, as a list in the order of the declaration: checkboxes. */
    case Choices = 'choices';
}
