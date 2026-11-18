<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application;

use Phil\EditLockBundle\Application\Exception\EditRefused;
use Phil\EditLockBundle\Application\Exception\InvalidBlock;
use Phil\EditLockBundle\Application\Exception\LeaseHeld;
use Phil\EditLockBundle\Application\Exception\LeaseLost;
use Phil\EditLockBundle\Domain\Exception\InvalidLease;
use Phil\EditLockBundle\Domain\ValueObject\Fence;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Ujo;

/**
 * The blocks a holder saves while they edit: each is checked by `phil/ujo`, written only by the holder of its lease
 * or of the whole resource whom the policy still lets edit it, handed to the application's block store when there is one, and told to the other writers.
 *
 * The lease is checked and the block store called within one claim on the resource, by `EditLocks::whileHolding()`:
 * a lease that expires or is taken over can no longer let a save or a removal through between the check and the
 * write. The block store receives the fence of that claim.
 */
final readonly class BlockSaves
{
    /** A list of the document: `root`, a block holding blocks, or a column of a block, `id:0`. */
    public const string LIST = '/^(?:root|[A-Za-z0-9_-]{1,64}(?::[0-9]{1,3})?)$/D';

    private Ujo $ujo;

    public function __construct(
        private EditLocks $locks,
        private Broadcaster $broadcaster,
        ?Ujo $ujo = null,
        private ?BlockStore $store = null,
    ) {
        $this->ujo = $ujo ?? new Ujo();
    }

    /**
     * Saves a block, read from its JSON form, at its place in the document.
     *
     * @throws InvalidBlock when `phil/ujo` refuses the block, or the list or the block before it is not one
     * @throws EditRefused  when the policy no longer lets the holder edit the resource
     * @throws LeaseLost    when the holder does not hold the block or the whole resource
     * @throws LeaseHeld    when another claim on the resource kept the store waiting; nothing was saved
     */
    public function save(string $resource, Holder $holder, mixed $block, string $list = 'root', ?string $after = null): Block
    {
        if (1 !== preg_match(self::LIST, $list)) {
            throw InvalidBlock::because('The list of a block is "root", the identifier of a block, or that of a column.');
        }

        if (null !== $after && 1 !== preg_match(LeaseKey::PART, $after)) {
            throw InvalidBlock::because('The block before a block is named by its identifier.');
        }

        try {
            $document = $this->ujo->normalize(Document::fromArray(['blocks' => [$block]]));
        } catch (UjoException $ujoException) {
            throw InvalidBlock::because('The block is refused: '.$ujoException->getMessage(), $ujoException);
        }

        $saved = $document->blocks[0];
        $this->locks->whileHolding(LeaseKey::of($resource, $saved->id), $holder, function (Fence $fence) use ($resource, $saved, $list, $after, $holder): void {
            $this->store?->save($resource, $saved, $list, $after, $holder, $fence);
        });
        $this->broadcaster->saved($resource, $holder, $document, $list, $after);

        return $saved;
    }

    /**
     * Removes a block from the document.
     *
     * @throws InvalidLease when the identifier is not that of a block
     * @throws EditRefused  when the policy no longer lets the holder edit the resource
     * @throws LeaseLost    when the holder does not hold the block or the whole resource
     * @throws LeaseHeld    when another claim on the resource kept the store waiting; nothing was removed
     */
    public function remove(string $resource, Holder $holder, string $blockId): void
    {
        $this->locks->whileHolding(LeaseKey::of($resource, $blockId), $holder, function (Fence $fence) use ($resource, $blockId, $holder): void {
            $this->store?->remove($resource, $blockId, $holder, $fence);
        });
        $this->broadcaster->removed($resource, $holder, $blockId);
    }
}
