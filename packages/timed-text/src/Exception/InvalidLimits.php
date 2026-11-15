<?php

declare(strict_types=1);

namespace Phil\TimedText\Exception;

use InvalidArgumentException;

final class InvalidLimits extends InvalidArgumentException implements TimedTextException
{
}
