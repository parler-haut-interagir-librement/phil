<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Twig\Components;

use Phil\AccessBundle\Application\Contract\AccessibleItem;
use Phil\AccessBundle\Application\ItemAccess;
use Phil\AccessBundle\Application\Items;
use Phil\AccessBundle\Application\Sharing;
use Phil\AccessBundle\Application\View\Share;
use Phil\AccessBundle\Domain\ValueObject\SharingLevels;
use Phil\AccessBundle\Infrastructure\Twig\KitComponents;
use Phil\Resource\ResourceReference;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Who has access to an item, `<twig:PhilAccess:AccessSummary item="page:42" />`: its visibility as a badge, and its
 * shares in a table — level, member or group, end. Only those holding the widest level on the item see it; nothing is
 * drawn for anyone else. The parts are recipes of the kit.
 */
#[AsTwigComponent(name: 'PhilAccess:AccessSummary', template: '@PhilAccess/components/AccessSummary.html.twig', exposePublicProps: false)]
final class AccessSummaryComponent
{
    private ?ResourceReference $item = null;

    public function __construct(
        private readonly ItemAccess $access,
        private readonly Items $items,
        private readonly Sharing $sharing,
        private readonly SharingLevels $levels,
        private readonly KitComponents $kit,
    ) {
    }

    public function mount(AccessibleItem|ResourceReference|string $item): void
    {
        $this->item = match (true) {
            $item instanceof AccessibleItem => $item->accessReference(),
            $item instanceof ResourceReference => $item,
            default => ResourceReference::fromString($item),
        };
    }

    public function isVisible(): bool
    {
        return $this->item instanceof ResourceReference && $this->access->can($this->levels->names[0], $this->item);
    }

    public function visibility(): ?string
    {
        return $this->item instanceof ResourceReference ? $this->items->visibility($this->item)?->value : null;
    }

    /**
     * @return list<Share>
     */
    public function shares(): array
    {
        return $this->item instanceof ResourceReference ? $this->sharing->shares($this->item) : [];
    }

    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }
}
