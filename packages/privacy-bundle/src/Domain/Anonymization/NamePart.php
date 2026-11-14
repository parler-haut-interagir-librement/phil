<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization;

/**
 * Which part of a person's name a column holds.
 */
enum NamePart: string
{
    case Given = 'firstname';
    case Family = 'lastname';
    case Full = 'name';
}
