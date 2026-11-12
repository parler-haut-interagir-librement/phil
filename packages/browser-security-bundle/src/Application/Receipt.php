<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

/**
 * What the reception of a report body ended in, with the HTTP status the endpoint answers.
 */
enum Receipt: int
{
    case Accepted = 204;
    case Malformed = 400;
    case TooLarge = 413;
    case UnsupportedType = 415;
    case TooManyRequests = 429;
}
