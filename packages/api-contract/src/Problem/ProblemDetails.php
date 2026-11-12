<?php

declare(strict_types=1);

namespace Phil\ApiContract\Problem;

use Phil\ApiContract\Exception\InvalidContractValue;

/**
 * An error response in the RFC 9457 format, `application/problem+json`.
 *
 * **The `type` is the contract; the `detail` is text for a human.** A client branches on `type` and, for a
 * `type` it does not know, falls back on `status`. `detail` is written by the error handler for the reader of
 * the response and is never copied from an exception: it carries no stack trace, no query and no value the
 * caller does not already hold. The library cannot enforce that rule; it can only keep `detail` a plain
 * string that the application fills in on purpose.
 *
 * The URIs of the `type` values are the application's: the library declares none.
 *
 * Two ways in:
 * - `create()` for the server, strict: an absolute `type` URI, an error status, extension members named as
 *   the RFC recommends and never shadowing a standard member;
 * - `fromArray()` for the client, tolerant: a member of the wrong JSON type is ignored, as the RFC requires,
 *   and an unusable `type` becomes `about:blank`, which leaves the status to decide.
 */
final readonly class ProblemDetails
{
    public const string MEDIA_TYPE = 'application/problem+json';

    public const string BLANK_TYPE = 'about:blank';

    private const array STANDARD_MEMBERS = ['type', 'status', 'title', 'detail', 'instance'];

    /**
     * RFC 9457 §3.2: extension member names SHOULD start with a letter, consist of letters, digits and
     * underscores, and be at least three characters long.
     */
    private const string EXTENSION_NAME = '/^[A-Za-z][A-Za-z0-9_]{2,}$/D';

    /**
     * @param array<string, mixed> $extensions
     */
    private function __construct(
        public string $type,
        public int $status,
        public ?string $title,
        public ?string $detail,
        public ?string $instance,
        public array $extensions,
    ) {
    }

    /**
     * @param array<string, mixed> $extensions
     */
    public static function create(
        int $status,
        string $type = self::BLANK_TYPE,
        ?string $title = null,
        ?string $detail = null,
        ?string $instance = null,
        array $extensions = [],
    ): self {
        if (!self::isErrorStatus($status)) {
            throw InvalidContractValue::because(\sprintf('A problem carries an error status (400 to 599); %d is not one.', $status));
        }

        if (!self::isUsableType($type)) {
            throw InvalidContractValue::because(\sprintf('A problem type is "%s" or an absolute URI; "%s" is neither.', self::BLANK_TYPE, $type));
        }

        foreach (array_keys($extensions) as $name) {
            if (\in_array($name, self::STANDARD_MEMBERS, true)) {
                throw InvalidContractValue::because(\sprintf('The extension member "%s" would shadow a standard member.', $name));
            }

            if (1 !== preg_match(self::EXTENSION_NAME, $name)) {
                throw InvalidContractValue::because(\sprintf('The extension member name "%s" is not a letter followed by at least two letters, digits or underscores.', $name));
            }
        }

        return new self($type, $status, $title, $detail, $instance, $extensions);
    }

    /**
     * Reads a decoded problem document.
     *
     * The HTTP status of the response is the fallback for the `status` member: an intermediary may have
     * changed the former, and the member then tells what the origin sent. Neither being an error status
     * means the document is not a problem.
     *
     * @param array<array-key, mixed> $members
     */
    public static function fromArray(array $members, int $responseStatus): self
    {
        $status = $members['status'] ?? null;

        if (!\is_int($status) || !self::isErrorStatus($status)) {
            if (!self::isErrorStatus($responseStatus)) {
                throw InvalidContractValue::because(\sprintf('A problem document arrived with the non-error status %d.', $responseStatus));
            }

            $status = $responseStatus;
        }

        $type = $members['type'] ?? null;
        $extensions = [];

        foreach ($members as $name => $value) {
            if (\is_string($name) && !\in_array($name, self::STANDARD_MEMBERS, true)) {
                $extensions[$name] = $value;
            }
        }

        return new self(
            \is_string($type) && self::isUsableType($type) ? $type : self::BLANK_TYPE,
            $status,
            self::stringOrNull($members['title'] ?? null),
            self::stringOrNull($members['detail'] ?? null),
            self::stringOrNull($members['instance'] ?? null),
            $extensions,
        );
    }

    public function hasType(string $type): bool
    {
        return $this->type === $type;
    }

    /**
     * Whether a client has anything more specific than the status to go on.
     */
    public function isBlank(): bool
    {
        return self::BLANK_TYPE === $this->type;
    }

    /**
     * The JSON members, ready to encode. An absent member is left out rather than written as `null`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $members = ['type' => $this->type, 'status' => $this->status];

        if (null !== $this->title) {
            $members['title'] = $this->title;
        }

        if (null !== $this->detail) {
            $members['detail'] = $this->detail;
        }

        if (null !== $this->instance) {
            $members['instance'] = $this->instance;
        }

        return $members + $this->extensions;
    }

    private static function isErrorStatus(int $status): bool
    {
        return $status >= 400 && $status <= 599;
    }

    /**
     * RFC 9457 allows a relative reference, resolved against the document's base. A stable contract does not
     * depend on where the document was fetched from, so only an absolute URI is usable.
     */
    private static function isUsableType(string $type): bool
    {
        // `about:blank` is itself an absolute URI.
        return 1 === preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:[^\s]+$/D', $type);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
