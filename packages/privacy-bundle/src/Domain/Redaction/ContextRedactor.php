<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Redaction;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Throwable;
use UnitEnum;

/**
 * Redacts a log message and its structured context before anything is written.
 *
 * - A value under a **denied key** is replaced by the placeholder, whatever its type: a denied array is not
 *   walked, because a key named `token` holding an array is still a token.
 * - Every other **string** goes through the `UrlRedactor` and the `CommandLineRedactor`, at any depth: a URL
 *   logged under `uri`, `referer` or `target` carries its query string the same way, and a command line logged
 *   under `command` carries its `--password=…` the same way. The message goes through both too.
 * - A **`Throwable`**, at any depth (PSR-3 logs it under `exception`), is replaced by an array: its class, its
 *   message through both redactors, its code, file and line, and its previous throwable the same way. An
 *   exception message is where a failed request's URL most often lands, and a formatter would otherwise write
 *   it as it is. The stack trace is dropped: its arguments are not reachable without serialisation.
 * - **Any other object, at any depth, is replaced by its class name**, and a case of an enum by its class and
 *   case, `App\\Status::Open`. A formatter would otherwise write an object's public properties, or what it
 *   serialises to, as they are: a message or a DTO of another bundle would reach the log whole, personal data
 *   included. A context that needs a value logs that value, under a key the denylist can judge.
 */
final readonly class ContextRedactor
{
    public const string PLACEHOLDER = UrlRedactor::PLACEHOLDER;

    private CommandLineRedactor $commandLines;

    /**
     * @param array<array-key, mixed> $shortOptions the short option table of the `CommandLineRedactor`: each
     *                                              letter to the long name it stands for
     *
     * @throws InvalidPrivacyValue when a letter or a long name is malformed
     */
    public function __construct(
        private Denylist $denylist,
        private UrlRedactor $urls,
        array $shortOptions = [],
    ) {
        $this->commandLines = new CommandLineRedactor($denylist, $shortOptions);
    }

    public function redactMessage(string $message): string
    {
        return $this->redactString($message);
    }

    /**
     * @param array<array-key, mixed> $context
     *
     * @return array<array-key, mixed>
     */
    public function redactContext(array $context): array
    {
        $redacted = [];

        foreach ($context as $key => $value) {
            $redacted[$key] = \is_string($key) && $this->denylist->denies($key)
                ? self::PLACEHOLDER
                : $this->redactValue($value);
        }

        return $redacted;
    }

    private function redactValue(mixed $value): mixed
    {
        return match (true) {
            \is_string($value) => $this->redactString($value),
            \is_array($value) => $this->redactContext($value),
            $value instanceof Throwable => $this->redactThrowable($value),
            $value instanceof UnitEnum => $value::class.'::'.$value->name,
            \is_object($value) => $value::class,
            default => $value,
        };
    }

    private function redactString(string $text): string
    {
        return $this->commandLines->redact($this->urls->redact($text));
    }

    /**
     * @return array{class: class-string, message: string, code: int|string, file: string, line: int, previous: array<string, mixed>|null}
     */
    private function redactThrowable(Throwable $throwable): array
    {
        $previous = $throwable->getPrevious();

        return [
            'class' => $throwable::class,
            'message' => $this->redactString($throwable->getMessage()),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'previous' => $previous instanceof Throwable ? $this->redactThrowable($previous) : null,
        ];
    }
}
