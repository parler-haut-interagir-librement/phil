<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Content;

use Phil\ContentBundle\Domain\Dimension\DimensionKey;

/**
 * One dimension row of a content root: its values for one locale (or none, for the shared row), one stage and
 * one version.
 *
 * What a row holds beyond its dimension is declared by the capabilities it implements — `TemplateCapable`,
 * `WorkflowCapable`, and those of the application. Each capability is an interface paired with a trait, so a
 * row class composes the ones it needs and the pipeline recognises them by `instanceof`.
 */
interface DimensionContent
{
    public function root(): ContentRoot;

    public function dimension(): DimensionKey;
}
