<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Editing;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Persistence\ContentTransaction;
use Phil\ContentBundle\Domain\Contract\WorkingCopyRepository;
use Phil\ContentBundle\Domain\Draft\WorkingCopy;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\LocaleDimensions\Locale;
use Psr\Clock\ClockInterface;

/**
 * The private autosave of each author and the named drafts of each content, kept beside the draft of a locale and
 * written into it only when an editor applies them.
 *
 * - **`autosave()`** keeps what an author's form holds, replacing their previous autosave; nobody else reads it. An
 *   editing page saves it by itself as the author types, and offers to restore it when the author comes back.
 * - **`saveNamed()`** keeps data under a name, for every editor of the content; the same name replaces it.
 * - **`apply()`** writes a working copy into the draft through `ContentManager::edit()`, under the same validation:
 *   an autosave is then removed, a named draft stays for another use.
 * - **A working copy keeps the raw HTML permission of its author**: saving one records whether the user who saves it
 *   holds it (`AuthorRawHtml`, registered with a `RawHtmlPermission`), and applying it writes its body with
 *   that permission, so an editor who holds it does not lend it to the text of an author who does not.
 *
 * The author is the identifier the application gives the person at work, as Symfony's user identifier. Each method
 * runs in its own transaction; a content that does not exist is refused before anything is kept, and deleting a
 * content removes its working copies.
 */
final readonly class WorkingCopies
{
    public function __construct(
        private WorkingCopyRepository $copies,
        private ContentManager $contents,
        private ContentTransaction $transaction,
        private ClockInterface $clock,
        private ?AuthorRawHtml $rawHtml = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws DomainError when the content does not exist, the locale is not served, or the data too large
     */
    public function autosave(ContentReference $content, Locale|string $locale, string $author, array $data): WorkingCopy
    {
        $locale = $this->locale($content, $locale);

        return $this->transaction->run(function () use ($content, $locale, $author, $data): WorkingCopy {
            $now = $this->now();
            $copy = $this->copies->findAutosave($content, $locale, $author);

            if ($copy instanceof WorkingCopy) {
                $copy->replace($data, $author, $now);

                return $this->authored($copy);
            }

            $copy = WorkingCopy::autosave($content, $locale, $author, $data, $now);
            $this->copies->add($copy);

            return $this->authored($copy);
        });
    }

    public function autosaved(ContentReference $content, Locale|string $locale, string $author): ?WorkingCopy
    {
        return $this->copies->findAutosave($content, $this->contents->servedLocale($locale), $author);
    }

    public function discardAutosave(ContentReference $content, Locale|string $locale, string $author): void
    {
        $this->transaction->run(function () use ($content, $locale, $author): void {
            $copy = $this->autosaved($content, $locale, $author);

            if ($copy instanceof WorkingCopy) {
                $this->copies->remove($copy);
            }
        });
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws DomainError when the content does not exist, the locale is not served, the name or the data refused
     */
    public function saveNamed(ContentReference $content, Locale|string $locale, string $name, string $author, array $data): WorkingCopy
    {
        $locale = $this->locale($content, $locale);

        return $this->transaction->run(function () use ($content, $locale, $name, $author, $data): WorkingCopy {
            $now = $this->now();
            $copy = $this->copies->findNamed($content, $locale, trim($name));

            if ($copy instanceof WorkingCopy) {
                $copy->replace($data, $author, $now);

                return $this->authored($copy);
            }

            $copy = WorkingCopy::named($content, $locale, $name, $author, $data, $now);
            $this->copies->add($copy);

            return $this->authored($copy);
        });
    }

    /**
     * @return list<WorkingCopy>
     */
    public function named(ContentReference $content, Locale|string $locale): array
    {
        return $this->copies->findAllNamed($content, $this->contents->servedLocale($locale));
    }

    /**
     * @throws ContentNotFound when no draft has that name
     */
    public function deleteNamed(ContentReference $content, Locale|string $locale, string $name): void
    {
        $this->transaction->run(function () use ($content, $locale, $name): void {
            $this->copies->remove($this->findNamed($content, $locale, $name));
        });
    }

    /**
     * Writes the author's autosave into the draft, then removes it.
     *
     * @throws ContentNotFound when the author has no autosave
     * @throws DomainError     when the edit is refused
     */
    public function applyAutosave(ContentReference $content, Locale|string $locale, string $author): void
    {
        $this->transaction->run(function () use ($content, $locale, $author): void {
            $copy = $this->autosaved($content, $locale, $author) ?? throw ContentNotFound::autosave($content, $author);

            $this->write($content, $copy);
            $this->copies->remove($copy);
        });
    }

    /**
     * Writes a named draft into the draft; the named draft stays.
     *
     * @throws ContentNotFound when no draft has that name
     * @throws DomainError     when the edit is refused
     */
    public function applyNamed(ContentReference $content, Locale|string $locale, string $name): void
    {
        $this->transaction->run(function () use ($content, $locale, $name): void {
            $copy = $this->findNamed($content, $locale, $name);
            $this->write($content, $copy);
        });
    }

    /**
     * The working copy, recording whether the user who saves it holds the raw HTML permission.
     */
    private function authored(WorkingCopy $copy): WorkingCopy
    {
        if ($this->rawHtml?->userIsGranted() ?? false) {
            $copy->grantRawHtml();
        }

        return $copy;
    }

    /**
     * Writes the working copy into the draft as its author saved it: its body carries the raw HTML permission of
     * that author, not that of the editor applying it.
     *
     * @throws DomainError
     */
    private function write(ContentReference $content, WorkingCopy $copy): void
    {
        $edit = function () use ($content, $copy): null {
            $this->contents->edit($content, $copy->locale(), $copy->data());

            return null;
        };

        if (!$this->rawHtml instanceof AuthorRawHtml) {
            $edit();

            return;
        }

        $this->rawHtml->as($copy->rawHtml(), $edit);
    }

    /**
     * @throws ContentNotFound
     */
    private function findNamed(ContentReference $content, Locale|string $locale, string $name): WorkingCopy
    {
        return $this->copies->findNamed($content, $this->contents->servedLocale($locale), trim($name))
            ?? throw ContentNotFound::namedDraft($content, $name);
    }

    /**
     * The locale, once the content is known to exist and the locale to be served.
     *
     * @throws DomainError
     */
    private function locale(ContentReference $content, Locale|string $locale): Locale
    {
        $locale = $this->contents->servedLocale($locale);

        if (!$this->contents->exists($content)) {
            throw ContentNotFound::forReference($content);
        }

        return $locale;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }
}
