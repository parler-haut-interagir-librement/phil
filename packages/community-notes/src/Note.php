<?php

declare(strict_types=1);

namespace Phil\CommunityNotes;

use DateTimeImmutable;
use Phil\CommunityNotes\Exception\InvalidNote;
use Phil\Resource\ResourceReference;

/**
 * A note written by a member beside a resource of the application — a post, a page, a message — to give its readers
 * context: its text, its author, when it was written, its status and the decision that gave it.
 */
final readonly class Note
{
    public const int MAX_TEXT = 2000;

    private function __construct(
        public string $id,
        public ResourceReference $subject,
        public ResourceReference $author,
        public string $text,
        public DateTimeImmutable $writtenAt,
        public NoteStatus $status,
        public ?Decision $decision,
    ) {
    }

    /**
     * A new note, needing ratings.
     *
     * @throws InvalidNote when the identifier is not 1 to 64 characters, or the text not a UTF-8 text of 1 to 2000
     *                     characters
     */
    public static function write(string $id, ResourceReference $subject, ResourceReference $author, string $text, DateTimeImmutable $writtenAt): self
    {
        if (1 !== preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id)) {
            throw InvalidNote::because('A note is identified by 1 to 64 ASCII letters, digits, "_" or "-".');
        }

        if ('' === trim($text) || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > self::MAX_TEXT) {
            throw InvalidNote::because(\sprintf('The text of a note is a UTF-8 text of 1 to %d characters.', self::MAX_TEXT));
        }

        return new self($id, $subject, $author, $text, $writtenAt, NoteStatus::NeedsMoreRatings, null);
    }

    /**
     * The note as a store kept it, without checks.
     */
    public static function restore(string $id, ResourceReference $subject, ResourceReference $author, string $text, DateTimeImmutable $writtenAt, NoteStatus $status, ?Decision $decision): self
    {
        return new self($id, $subject, $author, $text, $writtenAt, $status, $decision);
    }

    /**
     * The note with the status of a decision, and the decision kept to explain it.
     */
    public function decided(Decision $decision): self
    {
        return new self($this->id, $this->subject, $this->author, $this->text, $this->writtenAt, $decision->status, $decision);
    }

    /**
     * The note with a new decision kept to explain it, its status unchanged: a withdrawn note decided again after a
     * rater's data was erased, so that no explanation names them any more.
     */
    public function explained(Decision $decision): self
    {
        return new self($this->id, $this->subject, $this->author, $this->text, $this->writtenAt, $this->status, $decision);
    }

    public function withdrawn(): self
    {
        return new self($this->id, $this->subject, $this->author, $this->text, $this->writtenAt, NoteStatus::Withdrawn, $this->decision);
    }

    public function isShown(): bool
    {
        return NoteStatus::Helpful === $this->status;
    }
}
