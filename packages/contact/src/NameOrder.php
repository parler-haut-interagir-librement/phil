<?php

declare(strict_types=1);

namespace Phil\Contact;

/**
 * Which name of a person is written first: the given name, `Ada Lovelace`, or the family name, `Bartók Béla`.
 */
enum NameOrder: string
{
    case GivenFirst = 'given-first';
    case FamilyFirst = 'family-first';
}
