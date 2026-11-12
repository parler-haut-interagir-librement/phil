<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Phil\MediaBundle\Infrastructure\Admin\MediaAdminController;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * The media library reached from an EasyAdmin back office: the file explorer keeps its own routes, access, stateless
 * CSRF tokens and layout — `phil_media.admin.layout`, which the application points to a template of its back office —,
 * goes through `MediaLibrary` for every change, and the dashboard's menu links to it:
 *
 *     public function configureMenuItems(): iterable
 *     {
 *         yield MenuItem::section('Content');
 *         yield MediaAdmin::menuItem();
 *     }
 */
final class MediaAdmin
{
    private function __construct()
    {
    }

    /**
     * The link to the file explorer, opened on a collection when one is named.
     */
    public static function menuItem(string|TranslatableInterface $label = 'Media', ?string $icon = 'fa fa-photo-film', ?string $collection = null): RouteMenuItem
    {
        return MenuItem::linkToRoute($label, $icon, MediaAdminController::INDEX_ROUTE, null === $collection ? [] : ['collection' => $collection]);
    }
}
