<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application\Contract;

/**
 * The people who translate, as the application knows them: who is at the screen now, and the name each one is thanked
 * under in public. The bundle keeps identifiers only; names stay with the application.
 */
interface Translators
{
    /**
     * The identifier of the current user, kept with the targets, reviews and comments they write; `null` when unknown.
     */
    public function current(): ?string;

    /**
     * The name a person is credited under in public; `null` when they asked not to be credited, or are unknown.
     */
    public function credit(string $translator): ?string;
}
