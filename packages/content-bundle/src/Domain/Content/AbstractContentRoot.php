<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Content;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Symfony\Component\String\UnicodeString;

/**
 * The base of a stored content root: the application's root entity extends it, declares its own identifier
 * and its own shared fields, and names its row class.
 *
 * ```php
 * #[ORM\Entity]
 * class Article extends AbstractContentRoot
 * {
 *     #[ORM\Id, ORM\Column(type: 'article_id')]
 *     private ArticleId $id;
 *
 *     public static function dimensionContentClass(): string
 *     {
 *         return ArticleDimension::class;
 *     }
 * }
 * ```
 *
 * **The identifier column must be named `id`**: the row's join column, `root_id`, references it. Its type is
 * the application's.
 *
 * The root maps no field of its own, and no collection of its rows: a row points to its root, never the
 * reverse (see `ContentRoot`).
 */
#[ORM\MappedSuperclass]
abstract class AbstractContentRoot implements ContentRoot
{
    /**
     * The row class of this root. One row class belongs to one root class, and the bundle's configuration
     * lists the root classes, from which it learns the row classes.
     *
     * @return class-string<AbstractDimensionContent>
     */
    abstract public static function dimensionContentClass(): string;

    /**
     * The kind of content this root is, as routes, links and console commands name it: by default the short
     * class name in snake case (`News` is `news`, `StaticPage` is `static_page`). A root overrides it to keep a
     * stable key when its class is renamed; the key must stay a lowercase letter followed by at most 63
     * lowercase letters, digits or underscores.
     */
    public static function resourceKey(): string
    {
        return new UnicodeString(array_last(explode('\\', static::class)))->snake()->toString();
    }

    #[Override]
    public function createDimensionContent(DimensionKey $dimension): AbstractDimensionContent
    {
        $class = static::dimensionContentClass();

        return new $class($this, $dimension);
    }
}
