<?php

declare(strict_types=1);

namespace Phil\ErrorTracking;

use DateTimeImmutable;
use Exception;
use Phil\ErrorTracking\Exception\InvalidEvent;
use Phil\ErrorTracking\Internal\Text;

/**
 * One error as it happened: its identifier, its instant, where it was caught and how grave it is, its chain of
 * exceptions — the one thrown first, then each previous one —, the release and environment that ran it, the reduced
 * request and the tags of the code that caught it.
 *
 * Every text is bounded when the event is built; `toArray()` and `fromArray()` give and read the plain values a store
 * keeps.
 *
 * @phpstan-type FrameArray array{file: string, line: int, function: ?string, in_app: bool}
 * @phpstan-type ExceptionArray array{type: string, message: string, frames: list<FrameArray>}
 * @phpstan-type RequestArray array{method: string, url: string, route: ?string, headers: array<string, string>, client_address: ?string}
 * @phpstan-type EventArray array{id: string, occurred_at: string, source: string, severity: string, release: ?string, environment: ?string, exceptions: list<ExceptionArray>, request: ?RequestArray, context: array<string, scalar|null>}
 */
final readonly class ErrorEvent
{
    public const int MAX_CONTEXT = 50;

    public const int MAX_CONTEXT_LENGTH = 1024;

    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    /**
     * @var non-empty-list<ExceptionRecord>
     */
    public array $exceptions;

    public ?string $release;

    public ?string $environment;

    /**
     * @var array<string, scalar|null>
     */
    public array $context;

    /**
     * @param list<ExceptionRecord>         $exceptions the exception thrown, then each previous one
     * @param array<array-key, scalar|null> $context
     *
     * @throws InvalidEvent when the identifier is not 32 lower-case hexadecimal digits, or the chain is empty
     */
    public function __construct(
        public string $id,
        public DateTimeImmutable $occurredAt,
        public Source $source,
        public Severity $severity,
        array $exceptions,
        ?string $release = null,
        ?string $environment = null,
        public ?RequestSummary $request = null,
        array $context = [],
    ) {
        if (1 !== preg_match('/^[0-9a-f]{32}$/D', $id)) {
            throw InvalidEvent::because('An event identifier is 32 lower-case hexadecimal digits.');
        }

        if ([] === $exceptions) {
            throw InvalidEvent::because('An event holds at least one exception.');
        }

        $this->exceptions = $exceptions;
        $this->release = null === $release ? null : Text::clip($release, 128);
        $this->environment = null === $environment ? null : Text::clip($environment, 64);
        $kept = [];

        foreach (\array_slice($context, 0, self::MAX_CONTEXT, true) as $key => $value) {
            $kept[Text::clip((string) $key, 64)] = \is_string($value) ? Text::clip($value, self::MAX_CONTEXT_LENGTH) : $value;
        }

        $this->context = $kept;
    }

    /**
     * `RuntimeException: The export failed`, from the exception thrown.
     */
    public function title(): string
    {
        $thrown = $this->exceptions[0];

        return '' === $thrown->message ? $thrown->type : $thrown->type.': '.$thrown->message;
    }

    /**
     * The innermost frame of the application in the chain, from the exception thrown to its causes.
     */
    public function culprit(): ?Frame
    {
        foreach ($this->exceptions as $exception) {
            $culprit = $exception->culprit();

            if ($culprit instanceof Frame) {
                return $culprit;
            }
        }

        return null;
    }

    /**
     * The same event with other texts: what the scrubber returns.
     *
     * @param list<ExceptionRecord>         $exceptions
     * @param array<array-key, scalar|null> $context
     */
    public function with(array $exceptions, ?RequestSummary $request, array $context): self
    {
        return new self($this->id, $this->occurredAt, $this->source, $this->severity, $exceptions, $this->release, $this->environment, $request, $context);
    }

    /**
     * @return EventArray
     */
    public function toArray(): array
    {
        $request = $this->request;

        return [
            'id' => $this->id,
            'occurred_at' => $this->occurredAt->format(self::DATE_FORMAT),
            'source' => $this->source->value,
            'severity' => $this->severity->value,
            'release' => $this->release,
            'environment' => $this->environment,
            'exceptions' => array_map(static fn (ExceptionRecord $exception): array => [
                'type' => $exception->type,
                'message' => $exception->message,
                'frames' => array_map(static fn (Frame $frame): array => [
                    'file' => $frame->file,
                    'line' => $frame->line,
                    'function' => $frame->function,
                    'in_app' => $frame->inApp,
                ], $exception->frames),
            ], $this->exceptions),
            'request' => $request instanceof RequestSummary ? [
                'method' => $request->method,
                'url' => $request->url,
                'route' => $request->route,
                'headers' => $request->headers,
                'client_address' => $request->clientAddress,
            ] : null,
            'context' => $this->context,
        ];
    }

    /**
     * The event `toArray()` gave, read back.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidEvent when a value is missing or of another type
     */
    public static function fromArray(array $data): self
    {
        $instant = self::string($data, 'occurred_at');

        try {
            $occurredAt = new DateTimeImmutable($instant);
        } catch (Exception $exception) {
            throw InvalidEvent::because('The instant of the event is not a date.', $exception);
        }

        $exceptions = [];

        foreach (self::list($data, 'exceptions') as $exception) {
            $frames = [];

            foreach (self::list($exception, 'frames') as $frame) {
                $frames[] = new Frame(self::string($frame, 'file'), self::integer($frame, 'line'), self::optional($frame, 'function'), true === ($frame['in_app'] ?? null));
            }

            $exceptions[] = new ExceptionRecord(self::string($exception, 'type'), self::string($exception, 'message'), $frames);
        }

        $request = $data['request'] ?? null;

        return new self(
            self::string($data, 'id'),
            $occurredAt,
            Source::tryFrom(self::string($data, 'source')) ?? throw InvalidEvent::because('An unknown source.'),
            Severity::tryFrom(self::string($data, 'severity')) ?? throw InvalidEvent::because('An unknown severity.'),
            $exceptions,
            self::optional($data, 'release'),
            self::optional($data, 'environment'),
            \is_array($request) ? new RequestSummary(
                self::string($request, 'method'),
                self::string($request, 'url'),
                self::optional($request, 'route'),
                array_filter(self::array($request, 'headers'), \is_string(...)),
                self::optional($request, 'client_address'),
            ) : null,
            array_filter(self::array($data, 'context'), static fn (mixed $value): bool => null === $value || \is_scalar($value)),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        return \is_string($data[$key] ?? null) ? $data[$key] : throw InvalidEvent::because(\sprintf('"%s" is not a text.', $key));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function optional(array $data, string $key): ?string
    {
        return null === ($data[$key] ?? null) ? null : self::string($data, $key);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function integer(array $data, string $key): int
    {
        return \is_int($data[$key] ?? null) ? $data[$key] : throw InvalidEvent::because(\sprintf('"%s" is not an integer.', $key));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function array(array $data, string $key): array
    {
        return \is_array($data[$key] ?? null) ? $data[$key] : throw InvalidEvent::because(\sprintf('"%s" is not a list.', $key));
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, array<array-key, mixed>>
     */
    private static function list(array $data, string $key): array
    {
        return array_filter(self::array($data, $key), \is_array(...));
    }
}
