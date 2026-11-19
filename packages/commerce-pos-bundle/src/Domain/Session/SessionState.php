<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Session;

enum SessionState: string
{
    case Open = 'open';
    case Closed = 'closed';
}
