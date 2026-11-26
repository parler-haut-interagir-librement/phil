<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\Reporting;

use Override;
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Unit;
use Phil\OrganizationsBundle\Domain\ValueObject\UnitId;
use Phil\ReportingBundle\Application\Contract\UnitDirectory;
use Phil\ReportingBundle\Domain\Training\UnitNode;

/**
 * The tree of units as phil/reporting-bundle reads it for its training reports: the path of a unit, from its root to
 * the unit, archived units included so that the training of a unit archived since keeps its place. A unit the tree
 * does not know, or a text that is no unit identifier, has no path.
 */
final readonly class ReportingUnitDirectory implements UnitDirectory
{
    public function __construct(
        private UnitTree $tree,
    ) {
    }

    #[Override]
    public function path(string $unit): array
    {
        try {
            $found = $this->tree->unit(UnitId::fromString($unit));
        } catch (InvalidOrganization) {
            return [];
        }

        if (!$found instanceof Unit) {
            return [];
        }

        return array_map(
            static fn (Unit $node): UnitNode => new UnitNode($node->id->value, $node->name->value),
            [...$this->tree->ancestors($found->id), $found],
        );
    }
}
