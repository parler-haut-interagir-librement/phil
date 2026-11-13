<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Twig;

use Phil\AccessBundle\Application\AccessSubject;
use Phil\AccessBundle\Application\Contract\AccessibleItem;
use Phil\AccessBundle\Application\ItemAccess;
use Phil\AccessBundle\Application\Items;
use Phil\AccessBundle\Application\ViewAs;
use Phil\Resource\ResourceReference;
use Twig\Attribute\AsTwigFunction;

/**
 * The access of the request in templates: `access_can('edit', item)`, `access_visibility(item)`, `access_viewing_as()`.
 * An item is a `ResourceReference`, an `AccessibleItem` or its text, `page:42`.
 */
final readonly class AccessExtension
{
    public function __construct(
        private ItemAccess $access,
        private Items $items,
        private AccessSubject $subject,
        private ?ViewAs $viewAs = null,
    ) {
    }

    #[AsTwigFunction(name: 'access_can')]
    public function can(string $level, AccessibleItem|ResourceReference|string $item): bool
    {
        return $this->access->can($level, $this->reference($item));
    }

    /**
     * The visibility of an item, `everyone`, `members`, `admins` or `owner`, or null before it is registered.
     */
    #[AsTwigFunction(name: 'access_visibility')]
    public function visibility(AccessibleItem|ResourceReference|string $item): ?string
    {
        return $this->items->visibility($this->reference($item))?->value;
    }

    /**
     * The identifier of the member the admin views the site as, or null.
     */
    #[AsTwigFunction(name: 'access_viewing_as')]
    public function viewingAs(): ?string
    {
        return $this->subject->isViewingAs() ? $this->viewAs?->member()?->value : null;
    }

    private function reference(AccessibleItem|ResourceReference|string $item): AccessibleItem|ResourceReference
    {
        return \is_string($item) ? ResourceReference::fromString($item) : $item;
    }
}
