<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar;

use DateTimeImmutable;
use Phil\EditorialCalendar\Exception\InvalidItem;
use Phil\EditorialCalendar\Exception\InvalidTransition;
use Phil\Resource\ResourceReference;

/**
 * A publication the calendar plans: the content it publishes, by reference, in one language, by one author, in the
 * section of the site it belongs to, if any, in a state of its workflow, with the moment it is planned for, in UTC. Immutable: each change gives a new item.
 */
final readonly class PlannedItem
{
    /**
     * @throws InvalidItem when the identifier, the title, the language or the section is refused
     */
    private function __construct(
        public string $id,
        public ResourceReference $content,
        public string $title,
        public string $locale,
        public AuthorId $author,
        public ItemState $state,
        public ?DateTimeImmutable $publishAt,
        public ?string $section,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $id)) {
            throw InvalidItem::because(\sprintf('An item identifier is 1 to 128 letters, digits, ".", "_", ":" or "-"; "%s" is not.', $id));
        }

        if ('' === trim($title) || mb_strlen($title) > 500) {
            throw InvalidItem::because('An item title is 1 to 500 characters, not only spaces.');
        }

        if (1 !== preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8}){0,3}$/D', $locale)) {
            throw InvalidItem::because(\sprintf('An item language is a language tag such as "fr" or "fr-CA"; "%s" is not.', $locale));
        }

        if (null !== $section && 1 !== preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $section)) {
            throw InvalidItem::because(\sprintf('An item section is 1 to 128 letters, digits, ".", "_", ":" or "-"; "%s" is not.', $section));
        }
    }

    /**
     * A new item, as an idea, with or without a moment planned and a section.
     *
     * @throws InvalidItem
     */
    public static function plan(string $id, ResourceReference $content, string $title, string $locale, AuthorId $author, ?DateTimeImmutable $publishAt = null, ?string $section = null): self
    {
        return new self($id, $content, $title, $locale, $author, ItemState::Idea, $publishAt instanceof DateTimeImmutable ? Moment::utc($publishAt) : null, $section);
    }

    /**
     * An item as it is stored, in any state.
     *
     * @throws InvalidItem
     * @throws InvalidTransition when it is scheduled without a moment
     */
    public static function restore(string $id, ResourceReference $content, string $title, string $locale, AuthorId $author, ItemState $state, ?DateTimeImmutable $publishAt, ?string $section = null): self
    {
        if (ItemState::Scheduled === $state && !$publishAt instanceof DateTimeImmutable) {
            throw InvalidTransition::withoutMoment($id);
        }

        return new self($id, $content, $title, $locale, $author, $state, $publishAt instanceof DateTimeImmutable ? Moment::utc($publishAt) : null, $section);
    }

    /**
     * The item in another state. A moment given replaces the planned one; a scheduled item needs one, and a published
     * item keeps the moment it was published at.
     *
     * @throws InvalidTransition when the workflow does not allow it, or the item would be scheduled without a moment
     */
    public function transitionTo(ItemState $state, ?DateTimeImmutable $at = null): self
    {
        if (!$this->state->canBecome($state)) {
            throw InvalidTransition::between($this->id, $this->state, $state);
        }

        $moment = $at instanceof DateTimeImmutable ? Moment::utc($at) : $this->publishAt;

        if (ItemState::Scheduled === $state && !$moment instanceof DateTimeImmutable) {
            throw InvalidTransition::withoutMoment($this->id);
        }

        return new self($this->id, $this->content, $this->title, $this->locale, $this->author, $state, $moment, $this->section);
    }

    /**
     * The item planned for another moment, or for none, its state unchanged.
     *
     * @throws InvalidTransition when it is published or withdrawn, or scheduled and left without a moment
     */
    public function reschedule(?DateTimeImmutable $publishAt): self
    {
        if (!$this->state->isPending()) {
            throw InvalidTransition::between($this->id, $this->state, $this->state);
        }

        if (ItemState::Scheduled === $this->state && !$publishAt instanceof DateTimeImmutable) {
            throw InvalidTransition::withoutMoment($this->id);
        }

        return new self($this->id, $this->content, $this->title, $this->locale, $this->author, $this->state, $publishAt instanceof DateTimeImmutable ? Moment::utc($publishAt) : null, $this->section);
    }

    /**
     * The item with another title, language, author or section. A value left `null` is kept.
     *
     * @throws InvalidItem
     */
    public function edit(?string $title = null, ?string $locale = null, ?AuthorId $author = null, ?string $section = null): self
    {
        return new self($this->id, $this->content, $title ?? $this->title, $locale ?? $this->locale, $author ?? $this->author, $this->state, $this->publishAt, $section ?? $this->section);
    }

    /**
     * The item out of any section.
     */
    public function withoutSection(): self
    {
        return new self($this->id, $this->content, $this->title, $this->locale, $this->author, $this->state, $this->publishAt, null);
    }
}
