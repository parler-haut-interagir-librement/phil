<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

use Phil\CodeOfConduct\Contract\AcceptanceStore;
use Phil\CodeOfConduct\Contract\CodeVersionStore;
use Phil\CodeOfConduct\Event\CodeAccepted;
use Phil\CodeOfConduct\Event\CodeVersionPublished;
use Phil\CodeOfConduct\Exception\ActionRefused;
use Phil\CodeOfConduct\Exception\InvalidCodeOfConduct;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The code of conduct of each scope: its versions, published one after the other, and the acceptance of its members.
 * A major version asks every member to accept the code again; a minor one does not. The application asks
 * `status()` before a member takes part — writes, registers, posts — and never holds reading back.
 *
 * Who may publish is the application's decision. The service keeps no state between two calls, and is safe to share
 * across the requests of a long-running worker.
 */
final readonly class CodeOfConduct
{
    public function __construct(
        private CodeVersionStore $versions,
        private AcceptanceStore $acceptances,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Publishes the next version of the code of a scope. The first version is major whatever `$kind` says.
     *
     * @throws InvalidCodeOfConduct when the scope, the title or the text is out of bounds
     */
    public function publish(string $scope, string $title, string $text, ChangeKind $kind = ChangeKind::Major): CodeVersion
    {
        $current = $this->versions->current(Identifier::check($scope, 'A scope'));
        $number = $current instanceof CodeVersion ? $current->number + 1 : 1;
        $version = new CodeVersion($scope, $number, $title, $text, 1 === $number ? ChangeKind::Major : $kind, $this->clock->now());

        $this->versions->add($version);
        $this->events?->dispatch(new CodeVersionPublished($scope, $number));

        return $version;
    }

    /**
     * The version in force in the scope, `null` when it has no code.
     */
    public function current(string $scope): ?CodeVersion
    {
        return $this->versions->current(Identifier::check($scope, 'A scope'));
    }

    /**
     * A version of the scope by its number, `null` when there is none.
     */
    public function version(string $scope, int $number): ?CodeVersion
    {
        return $this->versions->version(Identifier::check($scope, 'A scope'), $number);
    }

    /**
     * Records that the member accepted the version they were shown. Only the version in force is accepted: a page
     * left open while a new version was published is refused, and shows the new one.
     *
     * @throws ActionRefused when the scope has no code, or `$version` is not the version in force
     */
    public function accept(string $scope, string $member, int $version): Acceptance
    {
        Identifier::check($member, 'A member');
        $current = $this->current($scope);

        if (!$current instanceof CodeVersion || $current->number !== $version) {
            throw ActionRefused::because('Only the version of the code in force is accepted; read it again.');
        }

        $acceptance = new Acceptance($scope, $member, $version, $current->hash, $this->clock->now());
        $this->acceptances->add($acceptance);
        $this->events?->dispatch(new CodeAccepted($scope, $member, $version));

        return $acceptance;
    }

    /**
     * Where the member stands with the code of the scope.
     */
    public function status(string $scope, string $member): AcceptanceStatus
    {
        $latestMajor = $this->versions->latestMajor(Identifier::check($scope, 'A scope'));

        if (null === $latestMajor) {
            return AcceptanceStatus::NoCode;
        }

        $acceptance = $this->acceptances->latest($scope, Identifier::check($member, 'A member'));

        if (!$acceptance instanceof Acceptance) {
            return AcceptanceStatus::NotAccepted;
        }

        return $acceptance->version < $latestMajor ? AcceptanceStatus::Outdated : AcceptanceStatus::Accepted;
    }
}
