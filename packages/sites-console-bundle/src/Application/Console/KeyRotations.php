<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Application\Console;

use Phil\SitesConsoleBundle\Application\Contract\AgentClient;
use Phil\SitesConsoleBundle\Application\Exception\AgentFailure;
use Phil\SitesConsoleBundle\Application\Exception\NotFound;
use Phil\SitesConsoleBundle\Application\Exception\RotationRefused;
use Phil\SitesConsoleBundle\Domain\Contract\KeyRotationRepository;
use Phil\SitesConsoleBundle\Domain\Model\KeyRotation;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Status\JournalAction;
use Phil\SitesConsoleBundle\Domain\Status\KeyOperation;
use Phil\SitesConsoleBundle\Domain\Status\RotationPhase;
use Phil\SitesConsoleBundle\Domain\Status\RotationStep;
use Phil\SitesConsoleBundle\Domain\ValueObject\KeyRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Psr\Clock\ClockInterface;

/**
 * Rotates the console key without cutting any site off: the next public key is staged on every site by a request
 * signed with the current key, the console then signs with the next key, and the previous key is retired from every
 * site by a request signed with the next one. Each step on each site carries the same idempotency key on every
 * attempt; the run stops at the first site that fails, every step is journaled, and running it again resumes where
 * it stopped. Nothing goes through a queue, and only public keys leave the console.
 */
final readonly class KeyRotations
{
    public function __construct(
        private SiteRegistry $registry,
        private KeyRotationRepository $rotations,
        private AgentClient $client,
        private ConsoleSigning $signing,
        private Journal $journal,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The rotation not finished yet, if any.
     */
    public function unfinished(): ?KeyRotation
    {
        return $this->rotations->unfinished();
    }

    /**
     * Starts the rotation to the configured next key, or resumes the one under way, and takes it as far as it goes.
     *
     * @throws RotationRefused when the console has no next key, the next key is the current one, or another rotation
     *                         is under way
     */
    public function run(?string $actor): KeyRotation
    {
        $rotation = $this->begin($actor);

        if (RotationPhase::Staging === $rotation->phase()) {
            if (!$this->steps($rotation, KeyOperation::Stage, $actor)) {
                return $rotation;
            }

            $rotation->switchSigning($this->clock->now());
            $this->rotations->save($rotation);
            $this->journal->record(JournalAction::KeySwitched, $actor, null, $rotation->id(), true, 'The console signs with '.$rotation->nextKey()->toBase64());
        }

        if (RotationPhase::Retiring === $rotation->phase() && $this->steps($rotation, KeyOperation::Retire, $actor)) {
            $rotation->finish($this->clock->now());
            $this->rotations->save($rotation);
            $this->journal->record(JournalAction::KeyRotationFinished, $actor, null, $rotation->id(), true, 'No site accepts '.$rotation->previousKey()->toBase64().' any more');
        }

        return $rotation;
    }

    private function begin(?string $actor): KeyRotation
    {
        $current = $this->signing->current()->verifyingKey();
        $rotation = $this->rotations->unfinished();

        // The keys of the configuration were moved before the last sites were reached: the next key already signs.
        if ($rotation instanceof KeyRotation && RotationPhase::Retiring === $rotation->phase() && $rotation->nextKey()->equals($current)) {
            return $rotation;
        }

        $next = ($this->signing->next() ?? throw RotationRefused::noNextKey())->verifyingKey();

        if ($current->equals($next)) {
            throw RotationRefused::sameKey();
        }

        if ($rotation instanceof KeyRotation) {
            if (!$rotation->nextKey()->equals($next)) {
                throw RotationRefused::otherRotation($rotation->id());
            }

            if (!$rotation->previousKey()->equals($current)) {
                throw RotationRefused::otherPrevious($rotation->id());
            }

            return $rotation;
        }

        $sites = array_map(static fn (Site $site): string => $site->id(), $this->registry->all());
        $rotation = KeyRotation::start($current, $next, $sites, $actor, $this->clock->now());
        $this->rotations->save($rotation);
        $this->journal->record(JournalAction::KeyRotationStarted, $actor, null, $rotation->id(), true, \sprintf('From %s to %s on %d sites', $current->toBase64(), $next->toBase64(), \count($sites)));

        return $rotation;
    }

    /**
     * Takes one operation to every site not settled yet, one after the other.
     *
     * @return bool whether every site is settled; false at the first failure
     */
    private function steps(KeyRotation $rotation, KeyOperation $operation, ?string $actor): bool
    {
        $key = KeyOperation::Stage === $operation ? $rotation->nextKey() : $rotation->previousKey();
        $action = KeyOperation::Stage === $operation ? JournalAction::KeyStaged : JournalAction::KeyRetired;

        foreach ($rotation->unsettled($operation) as $siteId) {
            try {
                $site = $this->registry->get($siteId);
            } catch (NotFound) {
                $rotation->skip($siteId, $this->clock->now());
                $this->rotations->save($rotation);
                $this->journal->record($action, $actor, $siteId, $rotation->id(), true, 'skipped: the site left the console');

                continue;
            }

            try {
                $outcome = $this->client->keys($site, new KeyRequest($rotation->requestId($operation, $siteId), $operation, $key));
            } catch (AgentFailure $agentFailure) {
                $outcome = Outcome::failed($agentFailure->getMessage());
            }

            $step = $rotation->record($operation, $siteId, $outcome, $this->clock->now());
            $this->rotations->save($rotation);
            $this->journal->record($action, $actor, $siteId, $rotation->id(), RotationStep::Done === $step, '' === $outcome->message ? $step->value : $step->value.': '.$outcome->message);

            if (RotationStep::Done !== $step) {
                return false;
            }
        }

        return true;
    }
}
