<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Edit;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;
use Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuditor;
use Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuthorizer;
use Phil\FeatureFlagsBundle\Application\Toggle\WritableFlagStore;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRecord;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRequest;
use Phil\FeatureFlagsBundle\Domain\Exception\EditConflict;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleRefused;
use Phil\FeatureFlagsBundle\Domain\Exception\UnknownFlag;
use Psr\Clock\ClockInterface;

/**
 * Creates flags and edits their rules, experiment and metadata, in the order of a toggle: authorize, read,
 * audit, write. **The audit line comes before the write**, and the write is conditional on what was read.
 *
 * - **A created flag starts inactive**, whatever the request says, and its state is stored with it: turning it
 *   on is a toggle, on its own audit line. A name another provider already declares cannot be created, since
 *   that provider would shadow it.
 * - **An edit keeps the active state** of the stored definition and applies only against the revision it was
 *   made from.
 */
final readonly class FlagEditor
{
    public function __construct(
        private ToggleAuthorizer $authorizer,
        private WritableFlagDefinitions $definitions,
        private WritableFlagStore $states,
        private ToggleAuditor $auditor,
        private ClockInterface $clock,
        private FlagCatalog $catalog,
    ) {
    }

    /**
     * @throws ToggleRefused
     * @throws EditConflict
     */
    public function create(EditRequest $request): void
    {
        $this->authorizer->authorizeEdit($request);

        if (!$request->isCreation()) {
            throw InvalidValue::because('A creation names no revision: there is nothing yet to have read.');
        }

        // A flag a provider holds but cannot read is declared too: its name is taken.
        if (null !== $this->catalog->lookup($request->flag())) {
            throw EditConflict::alreadyDeclared($request->flag());
        }

        $definition = $request->definition->withActive(false);
        $this->auditor->recordEdit(new EditRecord($request->flag(), $request->actor, null, $definition, $request->reason, $this->clock->now()));

        if (!$this->definitions->create($definition)) {
            throw EditConflict::alreadyDeclared($request->flag());
        }

        // A state left by an earlier flag of the same name is kept: it is the state an operator last set.
        $this->states->insert($request->flag(), false);
    }

    /**
     * @throws ToggleRefused
     * @throws UnknownFlag
     * @throws EditConflict
     */
    public function edit(EditRequest $request): void
    {
        $this->authorizer->authorizeEdit($request);

        $revision = $request->revision ?? throw InvalidValue::because('An edit names the revision it was made against.');
        $current = $this->definitions->findRevised($request->flag()) ?? throw UnknownFlag::named($request->flag());

        if ($current->revision !== $revision) {
            throw EditConflict::staleRevision($request->flag(), $revision);
        }

        $definition = $request->definition->withActive($current->definition->active);
        $this->auditor->recordEdit(new EditRecord($request->flag(), $request->actor, $current->definition, $definition, $request->reason, $this->clock->now()));

        if (!$this->definitions->replace($definition, $revision)) {
            throw EditConflict::staleRevision($request->flag(), $revision);
        }
    }
}
