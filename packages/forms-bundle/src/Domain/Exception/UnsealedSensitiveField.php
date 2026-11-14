<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Domain\Exception;

use LogicException;

/**
 * A form with a sensitive field, built or submitted where no `FieldSealer` is configured and the application has not
 * chosen to keep sensitive values as sent. The message says what to configure.
 */
final class UnsealedSensitiveField extends LogicException implements FormsException
{
    public static function in(string $form, string $field): self
    {
        return new self(\sprintf('The field "%s" of the form "%s" is sensitive and no sealer is configured: set "phil_forms.sealing.key" to a master key of Symfony\'s KeyManagement component, alias Phil\FormsBundle\Domain\Contract\FieldSealer to a sealer of the application, or set "phil_forms.allow_unsealed_sensitive: true" to keep sensitive values as sent.', $field, $form));
    }
}
