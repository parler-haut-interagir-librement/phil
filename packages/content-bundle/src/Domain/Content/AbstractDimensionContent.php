<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Content;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;

/**
 * The base of a stored dimension row: the application's row entity extends it and composes its capabilities.
 *
 * ```php
 * #[ORM\Entity]
 * class ArticleDimension extends AbstractDimensionContent implements TemplateCapable, WorkflowCapable
 * {
 *     use TemplateTrait;
 *     use WorkflowTrait;
 * }
 * ```
 *
 * This class maps the identifier and the dimension. The rest is mapped on the concrete class when Doctrine
 * loads it: the `root` association, towards the root class that names this row class, the unique constraint
 * on the dimension, and the columns of each capability the class implements. None of that is written here,
 * since only the concrete class knows its root and its capabilities.
 *
 * **The dimension never changes once the row is built.** A row of another locale, stage or version is
 * another row.
 *
 * **The unlocalized row has two locale columns.** `locale` is `NULL` for it, which is what a reader of the
 * table expects. But no database counts two `NULL` as a duplicate in a unique index — neither MySQL nor
 * PostgreSQL by default, nor SQLite — so a unique index over `locale` would let two unlocalized rows of the
 * same stage and version coexist. `locale_key` carries the same value with a sentinel, `*`, in place of
 * `NULL`, and the unique constraint covers it instead. A sentinel cannot collide with a locale, since a
 * `Locale` never contains `*`; and both columns are written by this constructor alone, from one key, so
 * they cannot drift apart. A generated column would avoid the second write, at the cost of a raw column
 * definition that differs by platform and that schema comparison reads back as a change on every run.
 *
 * The constructor is final: `AbstractContentRoot::createDimensionContent()` builds rows of the application's
 * class through it, with these two arguments only. A row receives its values through its capabilities.
 */
#[ORM\MappedSuperclass]
abstract class AbstractDimensionContent implements DimensionContent
{
    /**
     * What `locale_key` holds for the unlocalized row.
     */
    public const string UNLOCALIZED_KEY = '*';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'phil_content_dimension_content_id')]
    private DimensionContentId $id;

    #[ORM\Column(name: 'locale', type: 'phil_content_locale', length: 12, nullable: true)]
    private ?Locale $locale;

    #[ORM\Column(name: 'locale_key', length: 12)]
    private string $localeKey;

    #[ORM\Column(name: 'stage', type: 'phil_content_stage', length: 8)]
    private Stage $stage;

    #[ORM\Column(name: 'version', type: 'phil_content_version')]
    private Version $version;

    final public function __construct(
        protected ContentRoot $root,
        DimensionKey $dimension,
    ) {
        $this->id = DimensionContentId::generate();
        $this->locale = $dimension->locale;
        $this->localeKey = $dimension->locale->value ?? self::UNLOCALIZED_KEY;
        $this->stage = $dimension->stage;
        $this->version = $dimension->version;
    }

    public function id(): DimensionContentId
    {
        return $this->id;
    }

    /**
     * What `locale_key` holds: the locale, or `UNLOCALIZED_KEY` for the unlocalized row. The column the unique
     * constraint and the lookups of the repository read, so that the unlocalized row is matched by equality
     * like any other.
     */
    public function localeKey(): string
    {
        return $this->localeKey;
    }

    #[Override]
    public function root(): ContentRoot
    {
        return $this->root;
    }

    #[Override]
    public function dimension(): DimensionKey
    {
        return $this->locale instanceof Locale
            ? DimensionKey::localized($this->locale, $this->stage, $this->version)
            : DimensionKey::unlocalized($this->stage, $this->version);
    }
}
