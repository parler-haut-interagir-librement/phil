<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Domain\Model;

use DateTimeImmutable;
use Phil\NewsletterArchive\Domain\Exception\InvalidArchiveValue;
use Phil\NewsletterArchive\Domain\ValueObject\Tag;
use Phil\Slug\InvalidSlug;
use Phil\Slug\Slug;

/**
 * The web version of one sent campaign, as the archive keeps it: its subject, its preheader, the list it went to, the
 * tags it is filed under, its slug, and its HTML, rendered and purified once, when it was published, with neutral data
 * in place of the subscriber's — no unsubscription or preference link, no token, no tracking pixel. Nothing in it
 * names a subscriber, so that erasing one leaves the archive as it is.
 *
 * An issue is public in two ways, each explicit: **published**, it is listed in the archive and read at its slug;
 * **shared**, it is read by whoever holds its link, an opaque token of which only the hash is kept. Unpublishing
 * withdraws both: the issue leaves the archive and its link stops working.
 */
final class ArchivedIssue
{
    public const int SUBJECT_MAX_LENGTH = 200;

    public const int PREHEADER_MAX_LENGTH = 255;

    public const int LIST_NAME_MAX_LENGTH = 120;

    /** The length of the hash of a link: SHA-256 in lowercase hexadecimal. */
    public const int LINK_HASH_LENGTH = 64;

    public private(set) ?DateTimeImmutable $publishedAt = null;

    public private(set) ?string $linkHash = null;

    public private(set) ?DateTimeImmutable $linkIssuedAt = null;

    /** @var list<Tag> */
    public private(set) array $tags;

    public readonly string $slug;

    /**
     * @param list<Tag> $tags
     *
     * @throws InvalidArchiveValue when a value is out of shape
     */
    public function __construct(
        public readonly string $campaignId,
        public readonly string $site,
        string $slug,
        public private(set) string $listId,
        public private(set) string $listKey,
        public private(set) string $listName,
        public private(set) string $subject,
        public private(set) string $preheader,
        public private(set) string $html,
        public readonly DateTimeImmutable $sentAt,
        public private(set) DateTimeImmutable $renderedAt,
        array $tags = [],
    ) {
        if ('' === $campaignId || \strlen($campaignId) > 36 || '' === $site || \strlen($site) > 64) {
            throw InvalidArchiveValue::because('An archived issue names its campaign in at most 36 bytes and its site in at most 64.');
        }

        try {
            $this->slug = Slug::fromString($slug)->value;
        } catch (InvalidSlug $invalidSlug) {
            throw InvalidArchiveValue::because($invalidSlug->getMessage());
        }

        $this->check($listName, $subject, $preheader);
        $this->retag($tags);
    }

    /**
     * Restores an issue as a store keeps it.
     */
    public static function restore(self $issue, ?DateTimeImmutable $publishedAt, ?string $linkHash, ?DateTimeImmutable $linkIssuedAt): self
    {
        $issue->publishedAt = $publishedAt;
        $issue->linkHash = $linkHash;
        $issue->linkIssuedAt = null === $linkHash ? null : $linkIssuedAt;

        return $issue;
    }

    /**
     * Freezes the web version again, as the campaign renders now: when an issue is published once more.
     *
     * @throws InvalidArchiveValue when a value is out of shape
     */
    public function refresh(string $listId, string $listKey, string $listName, string $subject, string $preheader, string $html, DateTimeImmutable $renderedAt): void
    {
        $this->check($listName, $subject, $preheader);
        $this->listId = $listId;
        $this->listKey = $listKey;
        $this->listName = $listName;
        $this->subject = $subject;
        $this->preheader = $preheader;
        $this->html = $html;
        $this->renderedAt = $renderedAt;
    }

    /**
     * @param list<Tag> $tags
     *
     * @throws InvalidArchiveValue when more than 16 distinct tags are given
     */
    public function retag(array $tags): void
    {
        $this->tags = Tag::list(array_map(static fn (Tag $tag): string => $tag->value, $tags));
    }

    /**
     * Lists the issue in the archive, from the instant given; an issue already published keeps the instant it was
     * first published at.
     */
    public function publish(DateTimeImmutable $at): void
    {
        $this->publishedAt ??= $at;
    }

    /**
     * Takes the issue out of the archive and revokes its link.
     */
    public function unpublish(): void
    {
        $this->publishedAt = null;
        $this->revokeLink();
    }

    public function isPublished(): bool
    {
        return $this->publishedAt instanceof DateTimeImmutable;
    }

    /**
     * Shares the issue by a link, of which the hash is given: a link shared before stops working.
     *
     * @throws InvalidArchiveValue when the hash is not SHA-256 in lowercase hexadecimal
     */
    public function share(string $linkHash, DateTimeImmutable $at): void
    {
        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $linkHash)) {
            throw InvalidArchiveValue::because('The hash of a link is SHA-256 in lowercase hexadecimal.');
        }

        $this->linkHash = $linkHash;
        $this->linkIssuedAt = $at;
    }

    public function revokeLink(): void
    {
        $this->linkHash = null;
        $this->linkIssuedAt = null;
    }

    public function hasLink(): bool
    {
        return null !== $this->linkHash;
    }

    /**
     * Whether the issue is read by anyone: published, or shared by a link.
     */
    public function isPublic(): bool
    {
        return $this->isPublished() || $this->hasLink();
    }

    /**
     * @return list<string>
     */
    public function tagValues(): array
    {
        return array_map(static fn (Tag $tag): string => $tag->value, $this->tags);
    }

    private function check(string $listName, string $subject, string $preheader): void
    {
        if ('' === trim($subject) || mb_strlen($subject) > self::SUBJECT_MAX_LENGTH) {
            throw InvalidArchiveValue::because(\sprintf('The subject of an archived issue is not blank and holds at most %d characters.', self::SUBJECT_MAX_LENGTH));
        }

        if (mb_strlen($preheader) > self::PREHEADER_MAX_LENGTH || mb_strlen($listName) > self::LIST_NAME_MAX_LENGTH) {
            throw InvalidArchiveValue::because(\sprintf('The preheader of an archived issue holds at most %d characters, the name of its list at most %d.', self::PREHEADER_MAX_LENGTH, self::LIST_NAME_MAX_LENGTH));
        }
    }
}
