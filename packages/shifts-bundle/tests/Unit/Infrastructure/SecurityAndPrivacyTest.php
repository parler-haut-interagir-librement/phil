<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Shifts\Roster\PlaceStatus;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Phil\ShiftsBundle\Domain\Model\Absence;
use Phil\ShiftsBundle\Domain\Model\GuardianContact;
use Phil\ShiftsBundle\Infrastructure\Http\Access;
use Phil\ShiftsBundle\Infrastructure\Http\ShiftsGuard;
use Phil\ShiftsBundle\Infrastructure\Privacy\ShiftsPrivacy;
use Phil\ShiftsBundle\Infrastructure\Security\ShiftsVoter;
use Phil\ShiftsBundle\Infrastructure\Security\TokenCurrentPerson;
use Phil\ShiftsBundle\Tests\Fixtures\FixedPerson;
use Phil\ShiftsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\RoleVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\TokenStorage\NativeSessionTokenStorage;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface as CsrfStorage;

#[CoversClass(ShiftsVoter::class)]
#[CoversClass(TokenCurrentPerson::class)]
#[CoversClass(Access::class)]
#[CoversClass(ShiftsGuard::class)]
#[CoversClass(ShiftsPrivacy::class)]
final class SecurityAndPrivacyTest extends TestCase
{
    public function testWhoVolunteersAndWhoCoordinates(): void
    {
        $decisions = new AccessDecisionManager([new RoleVoter()]);
        $open = new ShiftsVoter($decisions, null, 'ROLE_SHIFTS_COORDINATOR');
        $restricted = new ShiftsVoter($decisions, 'ROLE_VOLUNTEER', 'ROLE_COORD');
        $ada = $this->token('ada', ['ROLE_USER']);
        $grace = $this->token('grace', ['ROLE_SHIFTS_COORDINATOR', 'ROLE_COORD']);
        $vic = $this->token('vic', ['ROLE_VOLUNTEER']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $open->vote($ada, null, [ShiftsVoter::VOLUNTEER]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $open->vote($ada, null, [ShiftsVoter::COORDINATE]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $open->vote($grace, null, [ShiftsVoter::COORDINATE]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $open->vote(new NullToken(), null, [ShiftsVoter::VOLUNTEER]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $open->vote($ada, null, ['phil_shifts.other']));
        self::assertSame(VoterInterface::ACCESS_DENIED, $restricted->vote($ada, null, [ShiftsVoter::VOLUNTEER]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $restricted->vote($vic, null, [ShiftsVoter::VOLUNTEER]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $restricted->vote($vic, null, [ShiftsVoter::COORDINATE]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $restricted->vote($grace, null, [ShiftsVoter::COORDINATE]));
    }

    public function testThePersonOfTheToken(): void
    {
        $tokens = new TokenStorage();
        $person = new TokenCurrentPerson($tokens);
        self::assertNull($person->current());
        self::assertSame([], $person->roles());

        $tokens->setToken(new NullToken());
        self::assertNull($person->current());

        $tokens->setToken($this->token('ada', ['ROLE_USER', 'FIRST_AID']));
        self::assertSame('ada', $person->current());
        self::assertSame(['ROLE_USER', 'FIRST_AID'], $person->roles());

        $tokens->setToken($this->token('ada', [3 => 'ROLE_USER']));
        self::assertSame(['ROLE_USER'], $person->roles());
    }

    public function testTheAccessOfThePages(): void
    {
        $tokens = new TokenStorage();
        $tokens->setToken($this->token('ada', ['ROLE_USER']));

        $checker = new AuthorizationChecker($tokens, new AccessDecisionManager([new ShiftsVoter(new AccessDecisionManager([new RoleVoter()]), null, 'ROLE_SHIFTS_COORDINATOR')]));
        $access = new Access($checker, new FixedPerson('ada', ['FIRST_AID']));

        self::assertTrue($access->isGranted(ShiftsVoter::VOLUNTEER));
        self::assertFalse($access->isGranted(ShiftsVoter::COORDINATE));
        self::assertSame('ada', $access->person());
        self::assertSame(['FIRST_AID'], $access->roles());
        $access->deny(ShiftsVoter::VOLUNTEER);

        try {
            $access->deny(ShiftsVoter::COORDINATE, 'subject');
            self::fail('The access is refused.');
        } catch (AccessDeniedException $accessDeniedException) {
            self::assertSame([ShiftsVoter::COORDINATE], $accessDeniedException->getAttributes());
            self::assertSame('subject', $accessDeniedException->getSubject());
        }

        $this->expectException(AccessDeniedException::class);
        new Access($checker, new FixedPerson(null))->person();
    }

    public function testTheTokenOfTheButtons(): void
    {
        $storage = new class implements CsrfStorage {
            /** @var array<string, string> */
            public array $tokens = [];

            public function getToken(string $tokenId): string
            {
                return $this->tokens[$tokenId] ?? throw new TokenNotFoundException();
            }

            public function setToken(string $tokenId, #[SensitiveParameter] string $token): void
            {
                $this->tokens[$tokenId] = $token;
            }

            public function removeToken(string $tokenId): ?string
            {
                return null;
            }

            public function hasToken(string $tokenId): bool
            {
                return isset($this->tokens[$tokenId]);
            }
        };
        $guard = new ShiftsGuard(new CsrfTokenManager(null, $storage));
        $token = $guard->token();

        self::assertArrayHasKey(ShiftsGuard::TOKEN_ID, $storage->tokens);
        $guard->assertTrusted($token);

        try {
            $guard->assertTrusted('forged');
            self::fail('The token is refused.');
        } catch (RequestRefused $requestRefused) {
            self::assertSame(RequestRefused::UNTRUSTED_REQUEST, $requestRefused->reason);
        }

        self::assertTrue(class_exists(NativeSessionTokenStorage::class));
    }

    public function testTheExportAndTheErasureOfAVolunteer(): void
    {
        $stage = new Stage();
        $stage->rosters->plan(Stage::desk(capacity: 1));
        $stage->rosters->plan(Stage::cloakroom());
        $stage->rosters->signUp('desk-am', 'ada', []);
        $stage->rosters->signUp('desk-am', 'bob', []);
        $stage->rosters->assign('cloakroom', 'ada', 'grace');

        $offer = $stage->swaps->offer('desk-am', 'ada', 'Sick');
        $stage->guardianRepository->save(new GuardianContact('ada', 'Marie Curie', '514-555-0100', null, 'grace', new DateTimeImmutable('2026-11-27T09:00:00Z'), 'olga'));
        $stage->guardianRepository->save(new GuardianContact('bob', 'Pierre', null, 'pierre@example.org', 'grace'));
        $stage->absenceRepository->add(new Absence('early', 'ada', new DateTimeImmutable('2026-11-20T09:00:00Z'), 'grace', new DateTimeImmutable('2026-11-21T09:00:00Z'), 'olga'));
        $stage->absenceRepository->add(new Absence('late', 'ada', new DateTimeImmutable('2026-11-22T09:00:00Z'), 'grace'));
        $stage->absenceRepository->add(new Absence('late', 'bob', new DateTimeImmutable('2026-11-22T09:00:00Z'), 'grace'));

        $privacy = new ShiftsPrivacy($stage->rosterRepository, $stage->swapRepository, $stage->guardianRepository, $stage->absenceRepository);

        self::assertSame('shifts', $privacy->module()->value);
        $export = $privacy->export(DataSubject::fromString('ada'));
        self::assertSame([
            ['shift' => 'cloakroom', 'title' => 'Cloakroom', 'starts_at' => '2026-11-28T08:00:00Z', 'ends_at' => '2026-11-28T20:00:00Z', 'minutes' => 120, 'status' => 'confirmed', 'since' => '2026-11-27T10:00:00Z', 'assigned_by' => 'grace'],
            ['shift' => 'desk-am', 'title' => 'Info desk', 'starts_at' => '2026-11-28T09:00:00Z', 'ends_at' => '2026-11-28T12:00:00Z', 'minutes' => 180, 'status' => 'confirmed', 'since' => '2026-11-27T10:00:00Z', 'assigned_by' => null],
        ], $export['shifts'] ?? null);
        self::assertSame([['id' => $offer->id, 'shift_id' => 'desk-am', 'offered_by' => 'ada', 'created_at' => '2026-11-27T10:00:00Z', 'note' => 'Sick', 'status' => 'open']], $export['swaps'] ?? null);
        self::assertSame(['name' => 'Marie Curie', 'phone' => '514-555-0100', 'email' => null, 'recorded_by' => 'grace', 'consented_at' => '2026-11-27T09:00:00Z', 'consent_recorded_by' => 'olga'], $export['guardian'] ?? null);
        self::assertSame([
            ['shift' => 'late', 'recorded_at' => '2026-11-22T09:00:00Z', 'recorded_by' => 'grace', 'lifted_at' => null, 'lifted_by' => null],
            ['shift' => 'early', 'recorded_at' => '2026-11-20T09:00:00Z', 'recorded_by' => 'grace', 'lifted_at' => '2026-11-21T09:00:00Z', 'lifted_by' => 'olga'],
        ], $export['absences'] ?? null);
        self::assertSame(['name' => 'Pierre', 'phone' => null, 'email' => 'pierre@example.org', 'recorded_by' => 'grace', 'consented_at' => null, 'consent_recorded_by' => null], $privacy->export(DataSubject::fromString('bob'))['guardian'] ?? null);
        self::assertSame([], $privacy->export(DataSubject::fromString('nobody')));

        $report = $privacy->erase(DataSubject::fromString('ada'));
        self::assertSame('shifts', $report->module->value);
        self::assertSame([
            'phil_shifts_places' => ErasureEffect::Deleted,
            'phil_shifts_swaps' => ErasureEffect::Deleted,
            'phil_shifts_guardians' => ErasureEffect::Deleted,
            'phil_shifts_absences' => ErasureEffect::Deleted,
        ], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('ada')));
        self::assertNull($stage->guardianRepository->find('ada'));
        self::assertNotNull($stage->guardianRepository->find('bob'));
        self::assertSame(['bob'], $stage->absenceRepository->absentFrom('late'));
        self::assertSame(PlaceStatus::Reserve, $stage->rosters->find('desk-am')?->statusOf('bob'));
    }

    /**
     * @param array<int, string> $roles
     */
    private function token(string $user, array $roles): UsernamePasswordToken
    {
        return new UsernamePasswordToken(new InMemoryUser($user, null, $roles), 'main', $roles);
    }
}
