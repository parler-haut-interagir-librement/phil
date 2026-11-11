<?php

declare(strict_types=1);

namespace Phil\BounceParser\Exception;

use InvalidArgumentException;

final class InvalidVerp extends InvalidArgumentException implements BounceParserException
{
}
