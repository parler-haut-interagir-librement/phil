<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Status;

/**
 * What a request to `keys` asks of an agent: stage a key as the next key of the console, or retire a key.
 */
enum KeyOperation: string
{
    case Stage = 'stage';
    case Retire = 'retire';
}
