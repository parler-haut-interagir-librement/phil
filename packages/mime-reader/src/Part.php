<?php

declare(strict_types=1);

namespace Phil\MimeReader;

use Phil\MimeReader\Exception\InvalidStream;
use Phil\MimeReader\Internal\Charset;
use Phil\MimeReader\Internal\TransferDecoder;

/**
 * A message or one of its MIME parts: its header fields, its media type and parameters, its body still encoded and
 * decoded only when asked, its child parts when it is a multipart, and the message it holds when it is a
 * `message/rfc822` or `message/global`. The parts of one message share the octets read: a part holds a range of them,
 * not a copy.
 */
final readonly class Part
{
    /**
     * @param string                $type       the media type in lowercase, the default of its place when none is valid
     * @param array<string, string> $parameters the parameters of its type, by name in lowercase
     * @param string                $encoding   its transfer encoding in lowercase, empty when none is given
     * @param list<Part>            $parts
     * @param bool                  $incomplete whether a limit cut this part: octets, depth or parts left unread
     */
    private function __construct(
        public Headers $headers,
        public string $type,
        public array $parameters,
        public string $encoding,
        private string $source,
        private int $offset,
        private int $length,
        public array $parts,
        public ?self $message,
        public bool $incomplete,
    ) {
    }

    /**
     * A part built from values already decoded, for an application or a test that has no raw message.
     *
     * @param array<string, string> $parameters
     * @param list<Part>            $parts
     */
    public static function create(Headers $headers, string $type = 'text/plain', array $parameters = [], string $body = '', array $parts = [], ?self $message = null): self
    {
        return new self($headers, strtolower($type), array_change_key_case($parameters), '', $body, 0, \strlen($body), $parts, $message, false);
    }

    /**
     * @internal the reader's constructor
     *
     * @param array<string, string> $parameters
     * @param list<Part>            $parts
     */
    public static function read(Headers $headers, string $type, array $parameters, string $encoding, string $source, int $offset, int $length, array $parts, ?self $message, bool $incomplete): self
    {
        return new self($headers, $type, $parameters, $encoding, $source, $offset, $length, $parts, $message, $incomplete);
    }

    /**
     * The body with its transfer encoding undone, as octets.
     */
    public function content(): string
    {
        $content = '';
        TransferDecoder::decode($this->source, $this->offset, $this->length, $this->encoding, static function (string $chunk) use (&$content): void {
            $content .= $chunk;
        });

        return $content;
    }

    /**
     * The body decoded and converted from its `charset` to UTF-8; without a known set, read as UTF-8 with its invalid
     * sequences replaced. Always valid UTF-8.
     */
    public function text(): string
    {
        return Charset::toUtf8($this->content(), $this->charset());
    }

    /**
     * Writes the decoded body to an open stream chunk by chunk, never holding it whole.
     *
     * @param mixed $stream an open stream
     *
     * @return int the octets written
     *
     * @throws InvalidStream when `$stream` is not an open stream
     */
    public function writeTo(mixed $stream): int
    {
        if (!\is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw new InvalidStream('Write a body to an open stream.');
        }

        $written = 0;
        TransferDecoder::decode($this->source, $this->offset, $this->length, $this->encoding, static function (string $chunk) use ($stream, &$written): void {
            $written += (int) fwrite($stream, $chunk);
        });

        return $written;
    }

    /**
     * The decoded body in a temporary stream, rewound: kept in memory up to 2 MiB, then on disk.
     *
     * @return resource
     */
    public function stream(): mixed
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+');

        if (false === $stream) {
            throw new InvalidStream('No temporary stream could be opened.');
        }

        $this->writeTo($stream);
        rewind($stream);

        return $stream;
    }

    public function charset(): ?string
    {
        return $this->parameters['charset'] ?? null;
    }

    /**
     * The disposition in lowercase, `inline` or `attachment` most often, or `null` when none is given.
     */
    public function disposition(): ?string
    {
        $disposition = $this->headers->parameterized('Content-Disposition');

        return !$disposition instanceof HeaderValue || '' === $disposition->value ? null : strtolower($disposition->value);
    }

    /**
     * The file name the part proposes, from its disposition or else its type, decoded, without any directory, control
     * character or leading dot, and cut to 255 octets: never a path to write to as it is. `null` when none is given.
     */
    public function filename(): ?string
    {
        $name = $this->headers->parameterized('Content-Disposition')?->parameter('filename') ?? $this->parameters['name'] ?? null;

        if (null === $name) {
            return null;
        }

        $name = (string) preg_replace(['/[\x00-\x1F\x7F]+/', '~^.*[/\\\\]~s'], '', $name);
        $name = mb_strcut(trim($name, " .\t"), 0, 255, 'UTF-8');

        return '' === $name ? null : $name;
    }

    public function isMultipart(): bool
    {
        return str_starts_with($this->type, 'multipart/');
    }

    /**
     * Whether the part is an attachment: so disposed, or named and not disposed inline.
     */
    public function isAttachment(): bool
    {
        $disposition = $this->disposition();

        return 'attachment' === $disposition || ('inline' !== $disposition && !$this->isMultipart() && null !== $this->filename());
    }

    /**
     * This part and every part under it, depth first, the parts of the messages it holds included only when asked.
     *
     * @return iterable<Part>
     */
    public function walk(bool $intoMessages = false): iterable
    {
        yield $this;

        foreach ($this->parts as $part) {
            yield from $part->walk($intoMessages);
        }

        if ($intoMessages && $this->message instanceof self) {
            yield from $this->message->walk(true);
        }
    }

    /**
     * The first part of that media type, this one included, without entering the messages it holds.
     */
    public function find(string $type): ?self
    {
        $type = strtolower($type);

        foreach ($this->walk() as $part) {
            if ($part->type === $type) {
                return $part;
            }
        }

        return null;
    }

    /**
     * The text of the first `text/plain` part that is not an attachment, outside held messages, or `null`.
     */
    public function textBody(): ?string
    {
        return $this->body('text/plain');
    }

    /**
     * The text of the first `text/html` part that is not an attachment, outside held messages, or `null`. It is
     * returned as text: nothing in it is run, fetched or cleaned.
     */
    public function htmlBody(): ?string
    {
        return $this->body('text/html');
    }

    /**
     * The attachments of the message, outside held messages, in their order.
     *
     * @return list<Part>
     */
    public function attachments(): array
    {
        $attachments = [];

        foreach ($this->walk() as $part) {
            if ($part->isAttachment()) {
                $attachments[] = $part;
            }
        }

        return $attachments;
    }

    /**
     * The body read as groups of header fields separated by empty lines, empty groups skipped, as `message/delivery-status`
     * (RFC 3464), `message/disposition-notification` (RFC 8098), `message/feedback-report` (RFC 5965) and
     * `text/rfc822-headers` write them: at most `$groups` groups of at most `$fields` fields.
     *
     * @return list<Headers>
     */
    public function fieldGroups(int $groups = 1000, int $fields = 500): array
    {
        $read = [];

        $texts = preg_split('/\r?\n[ \t]*\r?\n/', $this->content());

        foreach (false === $texts ? [] : $texts as $text) {
            $headers = Headers::parse($text, $fields);

            if ([] !== $headers->fields) {
                $read[] = $headers;
            }

            if (\count($read) >= $groups) {
                break;
            }
        }

        return $read;
    }

    private function body(string $type): ?string
    {
        foreach ($this->walk() as $part) {
            if ($type === $part->type && !$part->isAttachment()) {
                return $part->text();
            }
        }

        return null;
    }
}
