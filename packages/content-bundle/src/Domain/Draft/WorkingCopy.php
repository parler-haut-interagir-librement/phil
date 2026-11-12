<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Draft;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use JsonException;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;

/**
 * Submitted data kept aside from the draft of one locale, until an editor applies it.
 *
 * - **An autosave** belongs to one author: what their editing form holds, saved by itself as they type, seen by
 *   nobody else, and replaced at each save. It has no name.
 * - **A named draft** belongs to the content: an alternative an editor saved under a name — « shorter version »,
 *   « for the launch » —, which any editor may read, apply or delete. Saving it again under the same name replaces it.
 *
 * Neither touches the content's draft rows: applying one writes its data through `ContentManager::edit()`, under the
 * same validation as any edit. The data is the submitted data of the pipeline, 256 KiB of JSON at most.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_content_working_copy')]
#[ORM\Index(columns: ['resource_key', 'resource_id', 'locale'])]
final class WorkingCopy
{
    public const int MAX_BYTES = 262_144;

    public const int MAX_NAME_LENGTH = 100;

    public const int MAX_AUTHOR_LENGTH = 255;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'phil_content_working_copy_id')]
    private WorkingCopyId $id;

    #[ORM\Column(name: 'resource_key', length: 64)]
    private string $resourceKey;

    #[ORM\Column(name: 'resource_id', length: 64)]
    private string $resourceId;

    #[ORM\Column(name: 'raw_html', options: ['default' => false])]
    private bool $rawHtml = false;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(
        ContentReference $content,
        #[ORM\Column(name: 'locale', type: 'phil_content_locale')]
        private Locale $locale,
        #[ORM\Column(name: 'author', length: 255, nullable: true)]
        private ?string $author,
        #[ORM\Column(name: 'name', length: 100, nullable: true)]
        private ?string $name,
        #[ORM\Column(name: 'data', type: 'json')]
        private array $data,
        #[ORM\Column(name: 'saved_at', type: 'datetime_immutable')]
        private DateTimeImmutable $savedAt,
    ) {
        $this->id = WorkingCopyId::generate();
        $this->resourceKey = $content->resourceKey;
        $this->resourceId = $content->resourceId;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidContent when the author is empty or too long, or the data too large
     */
    public static function autosave(ContentReference $content, Locale $locale, string $author, array $data, DateTimeImmutable $savedAt): self
    {
        return new self($content, $locale, self::checkedAuthor($author), null, self::checkedData($data), $savedAt);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws InvalidContent when the name or the author is empty or too long, or the data too large
     */
    public static function named(ContentReference $content, Locale $locale, string $name, string $author, array $data, DateTimeImmutable $savedAt): self
    {
        return new self($content, $locale, self::checkedAuthor($author), self::checkedName($name), self::checkedData($data), $savedAt);
    }

    /**
     * Keeps new data, saved now: the author of a named draft becomes the editor who saved it last.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidContent when the author is empty or too long, or the data too large
     */
    public function replace(array $data, string $author, DateTimeImmutable $savedAt): void
    {
        $this->data = self::checkedData($data);
        $this->author = self::checkedAuthor($author);
        $this->savedAt = $savedAt;
        $this->rawHtml = false;
    }

    /**
     * Records that its author holds the raw HTML permission: applying it writes its body as theirs. Saving it again
     * forgets it, until the new author is known to hold it too.
     */
    public function grantRawHtml(): void
    {
        $this->rawHtml = true;
    }

    /**
     * Whether the author who saved it last held the raw HTML permission.
     */
    public function rawHtml(): bool
    {
        return $this->rawHtml;
    }

    public function id(): WorkingCopyId
    {
        return $this->id;
    }

    public function content(): ContentReference
    {
        return ContentReference::of($this->resourceKey, $this->resourceId);
    }

    public function locale(): Locale
    {
        return $this->locale;
    }

    public function author(): ?string
    {
        return $this->author;
    }

    /**
     * The name of a named draft; `null` for an autosave.
     */
    public function name(): ?string
    {
        return $this->name;
    }

    public function isAutosave(): bool
    {
        return null === $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    public function savedAt(): DateTimeImmutable
    {
        return $this->savedAt;
    }

    /**
     * @throws InvalidContent
     */
    private static function checkedAuthor(string $author): string
    {
        if ('' === trim($author) || mb_strlen($author) > self::MAX_AUTHOR_LENGTH) {
            throw InvalidContent::because(\sprintf('The author of a working copy is 1 to %d characters.', self::MAX_AUTHOR_LENGTH));
        }

        return $author;
    }

    /**
     * @throws InvalidContent
     */
    private static function checkedName(string $name): string
    {
        $name = trim($name);

        if ('' === $name || mb_strlen($name) > self::MAX_NAME_LENGTH || 1 === preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw InvalidContent::because(\sprintf('The name of a draft is 1 to %d characters, without a control character.', self::MAX_NAME_LENGTH));
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     *
     * @throws InvalidContent
     */
    private static function checkedData(array $data): array
    {
        try {
            $bytes = \strlen(json_encode($data, \JSON_THROW_ON_ERROR));
        } catch (JsonException $jsonException) {
            throw InvalidContent::because('The data of a working copy is not JSON: '.$jsonException->getMessage());
        }

        if ($bytes > self::MAX_BYTES) {
            throw InvalidContent::because(\sprintf('The data of a working copy is %d bytes of JSON at most; this one is %d.', self::MAX_BYTES, $bytes));
        }

        return $data;
    }
}
