<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Infrastructure\Mercure;

use DateTimeImmutable;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\Ujo\Document;
use Phil\Ujo\Json\JsonEncoder;

/**
 * The messages pushed to the writers of a resource, as Turbo Streams: presence replaces the content of the resource's
 * presence element, a saved block replaces the element of that block, a removed one removes it. The collaboration
 * controller applies them to the editor; Turbo itself can apply the presence stream to a page that shows it.
 *
 *     <turbo-stream action="update" target="phil-edit-lock-presence-…"><template>
 *         <ul class="phil-edit-lock-presence"><li data-holder="7" data-part="aB3x" data-expires-in="28">Ada</li></ul>
 *     </template></turbo-stream>
 *     <turbo-stream action="replace" target="phil-edit-lock-block-aB3x" data-holder="7"><template>
 *         <div id="phil-edit-lock-block-aB3x" data-list="root" data-after="Zt0q" data-ujo="{&quot;blocks&quot;:[…]}"></div>
 *     </template></turbo-stream>
 *     <turbo-stream action="remove" target="phil-edit-lock-block-aB3x" data-holder="7"></turbo-stream>
 */
final readonly class TurboStreams
{
    public const string PRESENCE = 'phil-edit-lock-presence-';

    public const string BLOCK = 'phil-edit-lock-block-';

    /**
     * The identifier of the element that shows who edits a resource.
     */
    public static function presenceTarget(string $resource): string
    {
        return self::PRESENCE.hash('xxh128', $resource);
    }

    /**
     * @param list<Lease> $leases
     */
    public function presence(string $resource, array $leases, DateTimeImmutable $now): string
    {
        $items = '';

        foreach ($leases as $lease) {
            $items .= \sprintf(
                '<li data-holder="%s" data-part="%s" data-expires-in="%d">%s</li>',
                $this->escape($lease->holder()->id),
                $this->escape($lease->key()->part ?? ''),
                $lease->secondsLeftAt($now),
                $this->escape($lease->holder()->name),
            );
        }

        return \sprintf(
            '<turbo-stream action="update" target="%s"><template><ul class="phil-edit-lock-presence">%s</ul></template></turbo-stream>',
            self::presenceTarget($resource),
            $items,
        );
    }

    /**
     * @param Document $block a document of the one block saved, checked by `phil/ujo`
     */
    public function saved(Holder $holder, Document $block, string $list, ?string $after): string
    {
        $target = self::BLOCK.$block->blocks[0]->id;

        return \sprintf(
            '<turbo-stream action="replace" target="%s" data-holder="%s"><template><div id="%1$s" data-list="%s"%s data-ujo="%s"></div></template></turbo-stream>',
            $target,
            $this->escape($holder->id),
            $this->escape($list),
            null === $after ? '' : ' data-after="'.$this->escape($after).'"',
            $this->escape(new JsonEncoder()->encode($block)),
        );
    }

    public function removed(Holder $holder, string $blockId): string
    {
        return \sprintf('<turbo-stream action="remove" target="%s" data-holder="%s"></turbo-stream>', self::BLOCK.$this->escape($blockId), $this->escape($holder->id));
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
