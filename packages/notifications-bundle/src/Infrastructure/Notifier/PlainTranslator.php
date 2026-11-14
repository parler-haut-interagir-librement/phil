<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Notifier;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

/**
 * The templates written as they are, their parameters replaced, when the application has no translator.
 */
final class PlainTranslator implements TranslatorInterface
{
    use TranslatorTrait;
}
