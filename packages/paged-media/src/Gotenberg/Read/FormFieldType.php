<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Read;

/**
 * The kind of a form field.
 */
enum FormFieldType: string
{
    case Text = 'text';
    case Checkbox = 'checkbox';
    case Radio = 'radio';
    case Button = 'button';
    case Combo = 'combo';
    case List = 'list';
    case Signature = 'signature';
}
