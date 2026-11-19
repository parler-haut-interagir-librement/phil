<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Application;

/**
 * What the reception of a browser's report ended in, with the HTTP status the endpoint answers.
 */
enum Receipt: int
{
    case Accepted = 204;
    case Malformed = 400;
    case Forbidden = 403;
    case TooLarge = 413;
    case UnsupportedType = 415;
    case TooManyRequests = 429;
}
