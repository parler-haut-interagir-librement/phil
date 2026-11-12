<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional\App;

/**
 * Codes of the IPTC Digital Source Type vocabulary in an enum, as an application or phil/creation-disclosure holds them.
 */
enum SourceType: string
{
    case Captured = 'digitalCapture';
    case Generated = 'trainedAlgorithmicMedia';
}
