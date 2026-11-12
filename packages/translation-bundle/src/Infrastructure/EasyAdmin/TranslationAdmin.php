<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Phil\TranslationBundle\Infrastructure\Http\Routes;
use Phil\TranslationMemory\LanguageTag;

/**
 * The glossary pages reached from an EasyAdmin back office: they keep their own routes, forms and layout — the
 * bundle's `layout`, which the application points to a template of its back office —, every change goes through the
 * bundle's `Glossaries`, and the dashboard's menu links to them:
 *
 *     public function configureMenuItems(): iterable
 *     {
 *         yield from TranslationAdmin::menuItems([['en', 'fr'], ['en', 'fr-CA']]);
 *     }
 */
final class TranslationAdmin
{
    private function __construct()
    {
    }

    /**
     * A link to the glossary of each pair of languages, labelled “Glossary en → fr” unless a label is given.
     *
     * @param list<array{string, string}> $pairs
     *
     * @return list<RouteMenuItem>
     */
    public static function menuItems(array $pairs, string $label = 'Glossary %s → %s', string $icon = 'fa fa-language'): array
    {
        return array_map(static function (array $pair) use ($label, $icon): RouteMenuItem {
            $source = LanguageTag::of($pair[0])->value;
            $target = LanguageTag::of($pair[1])->value;

            return MenuItem::linkToRoute(\sprintf($label, $source, $target), $icon, Routes::GLOSSARY, ['source' => $source, 'target' => $target]);
        }, $pairs);
    }
}
