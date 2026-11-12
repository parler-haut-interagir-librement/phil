<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Application;

use Phil\FormsAutosave\Application\Contract\DraftSealer;
use Phil\FormsAutosave\Application\Contract\DraftStore;
use Phil\FormsAutosave\Application\Exception\DraftTooLarge;
use Phil\FormsAutosave\Application\Exception\RevisionConflict;
use Phil\FormsAutosave\Domain\Exception\InvalidDraft;
use Phil\FormsAutosave\Domain\Model\Draft;
use Phil\FormsAutosave\Domain\ValueObject\DraftContent;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Psr\Clock\ClockInterface;

/**
 * The drafts a user keeps on the server: read, saved by revision, discarded and purged once expired. A user is named
 * by their identifier, kept only as its keyed hash; each content is sealed in a context naming that hash and the key,
 * so that a sealed draft copied to another row does not open.
 */
final readonly class Drafts
{
    /** How long a draft is kept after its last save, by default: seven days. */
    public const int SECONDS = 604800;

    /** How large a draft may be, by default, once encoded: 64 KiB. */
    public const int MAX_BYTES = 65536;

    public function __construct(
        private DraftStore $store,
        private DraftSealer $sealer,
        private DraftKeys $keys,
        private ClockInterface $clock,
        private int $seconds = self::SECONDS,
        private int $maxBytes = self::MAX_BYTES,
    ) {
    }

    /**
     * The user's draft under a key, or null when none is kept, it has expired, or it no longer opens — then it is deleted.
     */
    public function read(string $user, DraftKey $key): ?DraftCopy
    {
        $owner = $this->keys->owner($user);
        $draft = $this->store->find($owner, $key);

        if (!$draft instanceof Draft || $draft->hasExpired($this->clock->now())) {
            return null;
        }

        $opened = $this->sealer->open($draft->payload(), $this->context($owner, $key));

        try {
            $content = null === $opened ? null : DraftContent::fromJson($opened);
        } catch (InvalidDraft) {
            $content = null;
        }

        if (!$content instanceof DraftContent) {
            $this->store->delete($owner, $key);

            return null;
        }

        return new DraftCopy($draft->revision(), $draft->savedAt(), $content);
    }

    /**
     * Saves a content in place of the revision the browser read — null when it read none — and tells the new revision.
     * An expired draft counts as none.
     *
     * @throws DraftTooLarge    when the encoded content is larger than the server keeps
     * @throws RevisionConflict when another revision was saved meanwhile
     */
    public function save(string $user, DraftKey $key, ?int $revision, DraftContent $content): int
    {
        $json = $content->toJson();

        if (\strlen($json) > $this->maxBytes) {
            throw DraftTooLarge::above($this->maxBytes);
        }

        $owner = $this->keys->owner($user);
        $now = $this->clock->now();
        $payload = $this->sealer->seal($json, $this->context($owner, $key));
        $read = $this->store->find($owner, $key);

        if (!$read instanceof Draft || $read->hasExpired($now)) {
            $draft = Draft::first($owner, $key, $payload, $now, $this->seconds);

            if ($read instanceof Draft) {
                $this->store->delete($owner, $key);
            }

            return $this->store->insert($draft) ? 1 : throw new RevisionConflict($this->store->find($owner, $key)?->revision());
        }

        if ($revision !== $read->revision()) {
            throw new RevisionConflict($read->revision());
        }

        $next = $read->next($payload, $now, $this->seconds);

        return $this->store->replace($read, $next) ? $next->revision() : throw new RevisionConflict($this->store->find($owner, $key)?->revision());
    }

    public function discard(string $user, DraftKey $key): void
    {
        $this->store->delete($this->keys->owner($user), $key);
    }

    /**
     * Deletes every expired draft, and tells how many.
     */
    public function purge(): int
    {
        return $this->store->purge($this->clock->now());
    }

    private function context(string $owner, DraftKey $key): string
    {
        return Draft::TABLE.':'.$owner.':'.$key->value;
    }
}
