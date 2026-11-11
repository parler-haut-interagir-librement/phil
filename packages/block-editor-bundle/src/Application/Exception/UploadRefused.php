<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Application\Exception;

use RuntimeException;

/**
 * An image uploader refuses a file. Its message is shown to the writer as it is, so it says what to do in their words
 * and holds nothing private.
 */
final class UploadRefused extends RuntimeException implements BlockEditorException
{
}
