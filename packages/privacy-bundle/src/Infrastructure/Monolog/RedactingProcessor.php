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
use Phil\PrivacyBundle\Domain\Subject\DataCategory;

/**
 * Redacts every record before a handler writes it: the message, the context and the extra fields.
 *
 * `extra` is redacted too because other processors fill it — a web processor adds the request URI, with its
 * query string. Register this processor so that it runs **after** them; with the bundle's tag it is added to
 * every handler.
 */
final readonly class RedactingProcessor implements ProcessorInterface
{
    /** The categories of `#[PersonalData]` the PHIL packages declare, denied by default. */
    public const array PERSONAL_DATA_CATEGORIES = ['email', 'person_name', 'nickname', 'phone', 'ip', 'contact'];

    public function __construct(
        private ContextRedactor $redactor,
    ) {
    }

    /**
     * The processor as the bundle configuration describes it: the denied keys, the token path prefixes, the short
     * option table, and the categories of `#[PersonalData]` whose names are denied keys too.
     *
     * @param list<string>             $denylist
     * @param list<string>             $tokenPathPrefixes
     * @param array<array-key, string> $shortOptions           each letter to the long option name it stands for
     * @param list<string>             $personalDataCategories categories such as `email`, each a denied key
     *
     * @throws InvalidPrivacyValue when a key, a prefix, a letter, a long name or a category is malformed
     */
    public static function fromConfiguration(array $denylist, array $tokenPathPrefixes, array $shortOptions = [], array $personalDataCategories = []): self
    {
        $categories = array_map(static fn (string $category): string => DataCategory::fromString($category)->value, $personalDataCategories);

        return new self(new ContextRedactor(Denylist::fromList([...$denylist, ...$categories]), new UrlRedactor($tokenPathPrefixes), $shortOptions));
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
