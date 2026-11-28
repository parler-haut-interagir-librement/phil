<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\ValueObject;

use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Schema\VariableSchema;
use Phil\EmailTemplatesBundle\Domain\Status\BodyFormat;

/**
 * What a template is made of, for one site and one locale: the sandboxed Twig sources of its subject, its preheader,
 * its body — HTML with the email components, or Markdown — and, when it is written by hand, its plain-text part; the
 * schema of its variables; the segments its conditional blocks test; and the images it embeds, by content identifier.
 *
 * The shape is checked here, every reason listed at once; whether the sources compile in the sandbox and render with
 * their sample values is checked when the template is saved.
 */
final readonly class TemplateDefinition
{
    public const int MAX_SUBJECT_LENGTH = 2000;

    public const int MAX_PREHEADER_LENGTH = 2000;

    public const int MAX_BODY_BYTES = 262_144;

    public const int MAX_SEGMENTS = 32;

    public const int MAX_EMBEDS = 16;

    /** The keys a definition is declared with, in a default file or an import. */
    public const array KEYS = ['subject', 'preheader', 'format', 'body', 'text', 'segments', 'embeds', 'variables'];

    private const string SEGMENT = '/^[a-z][a-z0-9_-]{0,31}$/D';

    private const string EMBED_NAME = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D';

    private const string EMBED_PATH = '/^[A-Za-z0-9_][A-Za-z0-9_.\/-]{0,254}$/D';

    /**
     * @param list<string>          $segments the segments the conditional blocks test, `segment('vip')`
     * @param array<string, string> $embeds   the path of each embedded image, by its content identifier: `cid:logo`
     */
    private function __construct(
        public string $subject,
        public string $preheader,
        public BodyFormat $format,
        public string $body,
        public ?string $text,
        public VariableSchema $schema,
        public array $segments,
        public array $embeds,
    ) {
    }

    /**
     * @param list<string>          $segments
     * @param array<string, string> $embeds
     *
     * @throws InvalidTemplate listing every reason the definition is refused
     */
    public static function create(string $subject, string $body, BodyFormat $format = BodyFormat::Html, string $preheader = '', ?string $text = null, ?VariableSchema $schema = null, array $segments = [], array $embeds = []): self
    {
        $errors = [];
        $subject = trim($subject);
        $preheader = trim($preheader);
        $text = null === $text || '' === trim($text) ? null : $text;

        foreach (['subject' => $subject, 'preheader' => $preheader, 'body' => $body, 'text' => $text ?? ''] as $field => $source) {
            if (!mb_check_encoding($source, 'UTF-8')) {
                $errors[] = \sprintf('The %s is not valid UTF-8.', $field);
            }
        }

        if ('' === $subject || mb_strlen($subject) > self::MAX_SUBJECT_LENGTH || str_contains($subject, "\n") || str_contains($subject, "\r")) {
            $errors[] = \sprintf('The subject is one line of 1 to %d characters.', self::MAX_SUBJECT_LENGTH);
        }

        if (mb_strlen($preheader) > self::MAX_PREHEADER_LENGTH) {
            $errors[] = \sprintf('The preheader is at most %d characters.', self::MAX_PREHEADER_LENGTH);
        }

        if ('' === trim($body) || \strlen($body) > self::MAX_BODY_BYTES) {
            $errors[] = \sprintf('The body is 1 byte to %d KiB.', self::MAX_BODY_BYTES / 1024);
        }

        if (null !== $text && \strlen($text) > self::MAX_BODY_BYTES) {
            $errors[] = \sprintf('The plain-text part is at most %d KiB.', self::MAX_BODY_BYTES / 1024);
        }

        $segments = self::segments($segments, $errors);
        self::checkEmbeds($embeds, $errors);

        if ([] !== $errors) {
            throw InvalidTemplate::withErrors($errors);
        }

        return new self($subject, $preheader, $format, $body, $text, $schema ?? VariableSchema::empty(), $segments, $embeds);
    }

    /**
     * A definition as a default file, a JSON column or an import declares it.
     *
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidTemplate listing every reason the declaration is refused
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        foreach (array_keys($data) as $key) {
            if (!\in_array($key, self::KEYS, true)) {
                $errors[] = \sprintf('A template has no key "%s"; its keys are %s.', $key, implode(', ', self::KEYS));
            }
        }

        $format = $data['format'] ?? BodyFormat::Html->value;
        $format = \is_string($format) ? BodyFormat::tryFrom($format) : null;

        if (!$format instanceof BodyFormat) {
            $errors[] = 'The format is "html" or "markdown".';
        }

        foreach (['subject', 'body'] as $required) {
            if (!\is_string($data[$required] ?? null)) {
                $errors[] = \sprintf('The %s is a text.', $required);
            }
        }

        foreach (['preheader', 'text'] as $optional) {
            if (null !== ($data[$optional] ?? null) && !\is_string($data[$optional])) {
                $errors[] = \sprintf('The %s is a text.', $optional);
            }
        }

        $segments = $data['segments'] ?? [];
        $embeds = $data['embeds'] ?? [];
        $variables = $data['variables'] ?? [];

        if (!\is_array($segments) || !array_is_list($segments) || [] !== array_filter($segments, static fn (mixed $segment): bool => !\is_string($segment))) {
            $errors[] = 'The segments are a list of names.';
            $segments = [];
        }

        if (!\is_array($embeds) || [] !== array_filter($embeds, static fn (mixed $path, int|string $name): bool => !\is_string($path) || !\is_string($name), \ARRAY_FILTER_USE_BOTH)) {
            $errors[] = 'The embeds are a map of content identifiers to paths.';
            $embeds = [];
        }

        if (!\is_array($variables)) {
            $errors[] = 'The variables are a map of names to their declarations.';
            $variables = [];
        }

        $schema = null;

        try {
            $schema = VariableSchema::fromArray($variables);
        } catch (InvalidTemplate $invalidTemplate) {
            $errors = [...$errors, ...$invalidTemplate->errors];
        }

        if ([] !== $errors || !$format instanceof BodyFormat || !$schema instanceof VariableSchema) {
            throw InvalidTemplate::withErrors([] === $errors ? ['The template is not valid.'] : $errors);
        }

        $subject = $data['subject'];
        $body = $data['body'];
        $preheader = $data['preheader'] ?? '';
        $text = $data['text'] ?? null;
        \assert(\is_string($subject) && \is_string($body) && \is_string($preheader) && (null === $text || \is_string($text)));

        /** @var list<string> $segments */
        /** @var array<string, string> $embeds */
        return self::create($subject, $body, $format, $preheader, $text, $schema, $segments, $embeds);
    }

    /**
     * The declaration, as `fromArray()` reads it back.
     *
     * @return array{subject: string, preheader: string, format: string, body: string, text: ?string, segments: list<string>, embeds: array<string, string>, variables: array<string, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'subject' => $this->subject,
            'preheader' => $this->preheader,
            'format' => $this->format->value,
            'body' => $this->body,
            'text' => $this->text,
            'segments' => $this->segments,
            'embeds' => $this->embeds,
            'variables' => $this->schema->toArray(),
        ];
    }

    public function hasSegment(string $segment): bool
    {
        return \in_array($segment, $this->segments, true);
    }

    /**
     * @param list<string> $segments
     * @param list<string> $errors
     *
     * @return list<string>
     */
    private static function segments(array $segments, array &$errors): array
    {
        $unique = array_values(array_unique($segments));

        if (\count($unique) > self::MAX_SEGMENTS) {
            $errors[] = \sprintf('A template tests at most %d segments.', self::MAX_SEGMENTS);
        }

        foreach ($unique as $segment) {
            if (1 !== preg_match(self::SEGMENT, $segment)) {
                $errors[] = \sprintf('A segment is a lowercase letter, then up to 31 lowercase letters, digits, "_" or "-"; "%s" is not.', TemplateKey::shown($segment));
            }
        }

        return $unique;
    }

    /**
     * @param array<string, string> $embeds
     * @param list<string>          $errors
     */
    private static function checkEmbeds(array $embeds, array &$errors): void
    {
        if (\count($embeds) > self::MAX_EMBEDS) {
            $errors[] = \sprintf('A template embeds at most %d images.', self::MAX_EMBEDS);
        }

        foreach ($embeds as $name => $path) {
            if (1 !== preg_match(self::EMBED_NAME, $name)) {
                $errors[] = \sprintf('The content identifier of an image is 1 to 64 letters, digits, "_" or "-"; "%s" is not.', TemplateKey::shown($name));
            }

            if (1 !== preg_match(self::EMBED_PATH, $path) || \in_array('..', explode('/', $path), true) || str_contains($path, '//')) {
                $errors[] = \sprintf('The image "%s" is a relative path of letters, digits, "_", ".", "-" and "/", never "..": "%s" is not.', TemplateKey::shown($name), TemplateKey::shown($path));
            }
        }
    }
}
