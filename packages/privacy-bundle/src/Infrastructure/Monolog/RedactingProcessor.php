<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Monolog;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Override;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Redaction\ContextRedactor;
use Phil\PrivacyBundle\Domain\Redaction\Denylist;
use Phil\PrivacyBundle\Domain\Redaction\UrlRedactor;

/**
 * Redacts every record before a handler writes it: the message, the context and the extra fields.
 *
 * `extra` is redacted too because other processors fill it — a web processor adds the request URI, with its
 * query string. Register this processor so that it runs **after** them; with the bundle's tag it is added to
 * every handler.
 */
final readonly class RedactingProcessor implements ProcessorInterface
{
    public function __construct(
        private ContextRedactor $redactor,
    ) {
    }

    /**
     * The processor as the bundle configuration describes it: the denied keys, the token path prefixes and
     * the short option table.
     *
     * @param list<string>             $denylist
     * @param list<string>             $tokenPathPrefixes
     * @param array<array-key, string> $shortOptions      each letter to the long option name it stands for
     *
     * @throws InvalidPrivacyValue when a key, a prefix, a letter or a long name is malformed
     */
    public static function fromConfiguration(array $denylist, array $tokenPathPrefixes, array $shortOptions = []): self
    {
        return new self(new ContextRedactor(Denylist::fromList($denylist), new UrlRedactor($tokenPathPrefixes), $shortOptions));
    }

    #[Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactor->redactMessage($record->message),
            context: $this->redactor->redactContext($record->context),
            extra: $this->redactor->redactContext($record->extra),
        );
    }
}
