<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\Contract;

use Phil\ResourcePermissions\Model\TypeDefinition;

/**
 * Adds kinds, or relations to the kinds of the bundle, to the access model: a tree of folders, a role of the
 * application. Tagged `phil_access.model_contributor`; the contributors run in the order of their services.
 */
interface ModelContributor
{
    /**
     * @param array<string, TypeDefinition> $types the kinds so far, by name
     *
     * @return array<string, TypeDefinition> the kinds to build the model from
     */
    public function contribute(array $types): array;
}
