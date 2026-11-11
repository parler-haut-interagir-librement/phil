<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Toggle;

use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleConflict;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleRefused;
use Phil\FeatureFlagsBundle\Domain\Exception\UnknownFlag;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRecord;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRequest;
use Psr\Clock\ClockInterface;

/**
 * Applies a toggle in a fixed order: authorize, read the current value, audit, write.
 *
 * **The audit line comes before the write**, so no toggle exists without its trace: an auditor that fails
 * stops the toggle before anything is written. The write is a compare-and-set on the value read, so two
 * concurrent toggles cannot both apply and the value before the audit line carries is the one the write
 * replaced; a lost race throws {@see ToggleConflict}.
 *
 * The audit line of a toggle whose write then fails (a conflict, a store error) stays behind unless the
 * application runs `toggle()` inside one transaction shared by the store and the auditor, which the exception
 * rolls back. Without that transaction the trail can hold a toggle that did not apply, never miss one that did.
 *
 * A refused toggle writes nothing and audits nothing. A toggle to the value the flag already has is a no-op,
 * neither written nor audited, so the audit trail only holds changes.
 *
 * **A flag the store does not hold yet** — declared in the configuration, say — is toggled from the state its
 * provider declares, when a catalog is given: the toggle stores its first state, with an atomic insert in place
 * of the compare-and-set. A toggle still never creates a flag: a flag no provider declares is unknown.
 */
final readonly class FlagToggler
{
    public function __construct(
        private ToggleAuthorizer $authorizer,
        private WritableFlagStore $store,
        private ToggleAuditor $auditor,
        private ClockInterface $clock,
        private ?FlagCatalog $catalog = null,
    ) {
    }

    /**
     * @return bool whether the flag changed
     *
     * @throws ToggleRefused
     * @throws UnknownFlag
     * @throws ToggleConflict
     */
    public function toggle(ToggleRequest $request): bool
    {
        $this->authorizer->authorize($request);

        $stored = $this->store->isActive($request->flag);
        $before = $stored ?? $this->catalog?->find($request->flag)?->declared->active;

        if (null === $before) {
            throw UnknownFlag::named($request->flag);
        }

        if ($before === $request->active) {
            return false;
        }

        $this->auditor->record(new ToggleRecord(
            $request->flag,
            $request->actor,
            $before,
            $request->active,
            $request->reason,
            $this->clock->now(),
        ));

        $written = null === $stored
            ? $this->store->insert($request->flag, $request->active)
            : $this->store->compareAndSet($request->flag, $before, $request->active);

        if (!$written) {
            throw ToggleConflict::on($request->flag, $before);
        }

        return true;
    }
}
