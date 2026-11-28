<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Tests\Functional;

use Override;
use Phil\ShiftsBundle\Application\Absences;
use Phil\ShiftsBundle\Application\Minors;
use Phil\ShiftsBundle\Application\Rosters;
use Phil\ShiftsBundle\Infrastructure\DependencyInjection\ShiftsServices;
use Phil\ShiftsBundle\Infrastructure\Doctrine\ShiftsSchemaListener;
use Phil\ShiftsBundle\Infrastructure\Http\Access;
use Phil\ShiftsBundle\Infrastructure\Http\CoordinatorController;
use Phil\ShiftsBundle\Infrastructure\Http\Pages;
use Phil\ShiftsBundle\Infrastructure\Http\ShiftsGuard;
use Phil\ShiftsBundle\Infrastructure\Http\SwapController;
use Phil\ShiftsBundle\Infrastructure\Http\VolunteerController;
use Phil\ShiftsBundle\Infrastructure\Security\ShiftsVoter;
use Phil\ShiftsBundle\Infrastructure\Security\TokenCurrentPerson;
use Phil\ShiftsBundle\Infrastructure\Twig\Components\MyShiftsComponent;
use Phil\ShiftsBundle\Infrastructure\Twig\Components\ShiftCardComponent;
use Phil\ShiftsBundle\Tests\Fixtures\Stage;
use Phil\ShiftsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The bundle in an application: its tables from the ORM's schema, its templates, its form, its components, its voters,
 * the allocation through Messenger, and its buttons posted as a browser without JavaScript posts them.
 */
#[CoversClass(VolunteerController::class)]
#[CoversClass(SwapController::class)]
#[CoversClass(CoordinatorController::class)]
#[CoversClass(Pages::class)]
#[CoversClass(ShiftsGuard::class)]
#[CoversClass(Access::class)]
#[CoversClass(ShiftsVoter::class)]
#[CoversClass(TokenCurrentPerson::class)]
#[CoversClass(ShiftsSchemaListener::class)]
#[CoversClass(ShiftsServices::class)]
#[CoversClass(ShiftCardComponent::class)]
#[CoversClass(MyShiftsComponent::class)]
final class ShiftsPagesTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->kernel()->resetDatabase();
        $this->kernel()->clock()->modify('2026-11-27 10:00:00 UTC');
    }

    public function testTheSchemaOfTheOrmHoldsTheTables(): void
    {
        self::assertTrue($this->kernel()->connection()->createSchemaManager()->tablesExist(['phil_shifts_shifts', 'phil_shifts_places', 'phil_shifts_swaps', 'phil_shifts_guardians', 'phil_shifts_absences']));
    }

    public function testAVisitorIsSentToSignInAndAVolunteerIsNoCoordinator(): void
    {
        $this->client->request('GET', '/shifts/');
        self::assertResponseStatusCodeSame(401);

        $this->login('ada');
        $this->client->request('GET', '/shifts/manage');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/shifts/shift/missing');
        self::assertResponseStatusCodeSame(404);
    }

    public function testACoordinatorPlansAShiftThroughTheForm(): void
    {
        $this->login('grace');
        $crawler = $this->client->request('GET', '/shifts/manage/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Plan a shift');

        $this->client->submit($crawler->selectButton('Save')->form([
            'shift[id]' => 'desk-am',
            'shift[title]' => 'Info desk',
            'shift[kind]' => 'fixed',
            'shift[starts_at]' => '2026-11-28T09:00',
            'shift[ends_at]' => '2026-11-28T12:00',
            'shift[capacity]' => '1',
            'shift[reserve]' => '1',
            'shift[mode]' => 'self_sign_up',
            'shift[weight]' => '100',
            'shift[location]' => 'Hall A',
        ]));
        self::assertResponseRedirects('/shifts/manage/desk-am', 303);
        $shift = $this->rosters()->find('desk-am')?->shift;
        self::assertSame('2026-11-28 14:00:00', $shift?->startsAt->format('Y-m-d H:i:s'));

        $crawler = $this->client->request('GET', '/shifts/manage/new');
        $this->client->submit($crawler->selectButton('Save')->form([
            'shift[id]' => 'desk-am',
            'shift[title]' => 'Again',
            'shift[starts_at]' => '2026-11-28T09:00',
            'shift[ends_at]' => '2026-11-28T12:00',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', 'A shift has this identifier already.');

        $crawler = $this->client->request('GET', '/shifts/manage/desk-am/edit');
        self::assertResponseIsSuccessful();
        self::assertSame('2026-11-28T09:00', $crawler->filter('#shift_starts_at')->attr('value'));
        $this->client->submit($crawler->selectButton('Save')->form(['shift[capacity]' => '3', 'shift[ends_at]' => '2026-11-28T08:00']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', 'The window of a shift ends 5 minutes to 31 days after it starts.');
        $this->client->submit($crawler->selectButton('Save')->form(['shift[capacity]' => '3']));
        self::assertResponseRedirects('/shifts/manage/desk-am', 303);
        self::assertSame(3, $this->rosters()->find('desk-am')?->shift->capacity);
    }

    public function testVolunteersSignUpWaitAndWithdraw(): void
    {
        $this->rosters()->plan(Stage::desk(capacity: 1, reserve: 1));
        $this->rosters()->plan(Stage::desk('noon', '11:50:00', '13:00:00'));

        $this->login('ada');
        $crawler = $this->client->request('GET', '/shifts/shift/desk-am');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.phil-shifts__when', '2026-11-28 04:00');
        self::assertSelectorTextContains('.phil-shifts__places', 'One place left of 1.');
        $this->client->submit($crawler->selectButton('Sign up')->form());
        self::assertResponseRedirects('/shifts/shift/desk-am', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('section', 'On the shift');

        $crawler = $this->client->request('GET', '/shifts/shift/noon');
        $this->client->submit($crawler->selectButton('Sign up')->form());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', 'This shift overlaps another one this volunteer holds.');

        $this->client->request('POST', '/shifts/shift/noon/sign-up', ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('[role=alert]', 'This form has expired.');

        $this->login('bob');
        $crawler = $this->client->request('GET', '/shifts/shift/desk-am');
        $this->client->submit($crawler->selectButton('Join the reserve')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('section', 'In the reserve');

        $this->login('ada');
        $crawler = $this->client->request('GET', '/shifts/shift/desk-am');
        $this->client->submit($crawler->selectButton('Withdraw')->form());
        self::assertSame('confirmed', $this->rosters()->find('desk-am')?->statusOf('bob')?->value);

        $crawler = $this->client->request('GET', '/shifts/');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.phil-shifts__list li'));

        $this->kernel()->clock()->modify('2026-11-28 13:00:00 UTC');
        $this->login('bob');
        $this->client->request('GET', '/shifts/shift/desk-am');
        self::assertSelectorTextContains('section', 'This shift is closed');
        $this->client->request('GET', '/shifts/mine');
        self::assertSelectorTextContains('section', '3 hours served on one shift, 3 weighted.');
    }

    public function testTheSwapBoardWithAnApproval(): void
    {
        $this->rosters()->plan(Stage::desk(capacity: 1, approval: true));
        $this->rosters()->signUp('desk-am', 'ada', []);

        $this->login('ada');
        $crawler = $this->client->request('GET', '/shifts/shift/desk-am');
        $this->client->submit($crawler->selectButton('Offer my place')->form(['note' => 'Sick, sorry']));
        self::assertResponseRedirects('/shifts/swaps', 303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('section', 'Sick, sorry');
        self::assertCount(1, $crawler->filter('button:contains("Cancel my offer")'));

        $this->login('bob');
        $crawler = $this->client->request('GET', '/shifts/swaps');
        $this->client->submit($crawler->selectButton('Take this place')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('section', 'Taken by bob, waiting for a coordinator');

        $this->login('grace');
        $crawler = $this->client->request('GET', '/shifts/manage');
        self::assertSelectorTextContains('section', 'bob takes the place of ada on Info desk');
        $this->client->submit($crawler->selectButton('Approve')->form());
        self::assertResponseRedirects('/shifts/manage', 303);
        self::assertSame('confirmed', $this->rosters()->find('desk-am')?->statusOf('bob')?->value);

        $this->client->request('POST', '/shifts/manage/swaps/missing/approve', ['_token' => $this->token('/shifts/manage/desk-am')]);
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('[role=alert]', 'No offer has this identifier.');

        $this->login('ada');
        $this->client->request('POST', '/shifts/swaps/missing/take', ['_token' => $this->token('/shifts/shift/desk-am')]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testACoordinatorAssignsAllocatesAndDeletes(): void
    {
        $this->rosters()->plan(Stage::cloakroom(capacity: 1));
        $this->rosters()->expressInterest('cloakroom', 'ada', []);
        $this->rosters()->expressInterest('cloakroom', 'bob', []);

        $this->login('grace');
        $this->client->request('GET', '/shifts/manage');
        self::assertSelectorTextContains('section', 'Lacks volunteers');
        self::assertSelectorTextContains('section', '2 interested');
        $crawler = $this->client->request('GET', '/shifts/manage/cloakroom');
        $this->client->submit($crawler->selectButton('Allocate the places')->form());
        self::assertResponseRedirects('/shifts/manage/cloakroom', 303);
        self::assertSame('confirmed', $this->rosters()->find('cloakroom')?->statusOf('ada')?->value);

        $crawler = $this->client->request('GET', '/shifts/manage/cloakroom');
        $this->client->submit($crawler->filter('form')->reduce(static fn (Crawler $form): bool => str_contains((string) $form->attr('action'), '/assign') && 1 === $form->filter('input[name=volunteer][required]')->count())->form(['volunteer' => 'cy']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', 'This shift is full, and so is its reserve.');

        $this->client->request('POST', '/shifts/manage/cloakroom/assign', ['_token' => $this->token('/shifts/manage/cloakroom'), 'volunteer' => ' ']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', 'This is not the identifier of a volunteer.');

        $crawler = $this->client->request('GET', '/shifts/manage/cloakroom');
        $this->client->submit($crawler->selectButton('Take off the shift')->form());
        self::assertNull($this->rosters()->find('cloakroom')?->statusOf('ada'));
        $this->client->request('POST', '/shifts/manage/cloakroom/assign', ['_token' => $this->token('/shifts/manage/cloakroom'), 'volunteer' => 'bob']);
        self::assertSame('grace', $this->rosters()->find('cloakroom')?->confirmed()[0]->assignedBy);

        $this->client->request('POST', '/shifts/manage/cloakroom/delete', ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/shifts/manage/cloakroom');
        $this->client->submit($crawler->selectButton('Delete the shift')->form());
        self::assertResponseRedirects('/shifts/manage', 303);
        self::assertNull($this->rosters()->find('cloakroom'));
    }

    public function testACoordinatorRecordsAnAbsenceThatBlocksTheSignUpUntilLifted(): void
    {
        $this->rosters()->plan(Stage::desk());
        $this->rosters()->plan(Stage::desk('evening', '20:00:00', '21:00:00'));
        $this->rosters()->signUp('desk-am', 'ada', []);
        $this->rosters()->signUp('desk-am', 'bob', []);

        $this->login('grace');
        $crawler = $this->client->request('GET', '/shifts/manage/desk-am');
        self::assertCount(0, $crawler->selectButton('Absent without notice'));
        $this->client->request('POST', '/shifts/manage/desk-am/absent', ['_token' => $this->token('/shifts/manage/desk-am'), 'volunteer' => 'ada']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', 'An absence is recorded once the shift has started.');

        $this->kernel()->clock()->modify('2026-11-28 09:30:00 UTC');
        $crawler = $this->client->request('GET', '/shifts/manage/desk-am');
        self::assertCount(2, $crawler->selectButton('Absent without notice'));
        $this->client->submit($crawler->selectButton('Absent without notice')->form());
        self::assertResponseRedirects('/shifts/manage/desk-am', 303);
        $crawler = $this->client->followRedirect();
        self::assertSame(['ada'], $this->absences()->absentFrom('desk-am'));
        self::assertSame('Absent without notice', $crawler->filter('li')->first()->filter('.isBadge')->first()->text());
        self::assertSame('Blocked by absences', $crawler->filter('li')->first()->filter('.isBadge')->eq(1)->text());
        self::assertCount(1, $crawler->selectButton('Not absent after all'));
        self::assertCount(1, $crawler->selectButton('Lift the block'));

        $this->login('ada');
        $crawler = $this->client->request('GET', '/shifts/shift/evening');
        $this->client->submit($crawler->selectButton('Sign up')->form());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', 'Your unannounced absences block signing up by yourself');

        $this->login('grace');
        $crawler = $this->client->request('GET', '/shifts/manage/desk-am');
        $this->client->submit($crawler->selectButton('Not absent after all')->form());
        self::assertSame([], $this->absences()->absentFrom('desk-am'));
        $this->client->request('POST', '/shifts/manage/desk-am/absent', ['_token' => $this->token('/shifts/manage/desk-am'), 'volunteer' => 'ada']);
        self::assertTrue($this->absences()->isBlocked('ada'));
        $crawler = $this->client->request('GET', '/shifts/manage/desk-am');
        $this->client->submit($crawler->selectButton('Lift the block')->form());
        self::assertResponseRedirects('/shifts/manage/desk-am', 303);
        self::assertFalse($this->absences()->isBlocked('ada'));
        self::assertSame('grace', $this->absences()->ofVolunteer('ada')[0]->liftedBy);

        $this->client->request('POST', '/shifts/manage/desk-am/absent', ['_token' => $this->token('/shifts/manage/desk-am'), 'volunteer' => 'cy']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', 'This volunteer is not on this shift.');
        $this->client->request('POST', '/shifts/manage/desk-am/lift', ['_token' => 'forged', 'volunteer' => 'ada']);
        self::assertResponseStatusCodeSame(403);

        $this->login('ada');
        $this->client->request('POST', '/shifts/manage/desk-am/absent', ['_token' => $this->token('/shifts/shift/desk-am'), 'volunteer' => 'bob']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAMinorSignsUpOnceTheConsentOfAGuardianIsRecorded(): void
    {
        $this->rosters()->plan(Stage::desk());

        $this->login('kid');
        $crawler = $this->client->request('GET', '/shifts/shift/desk-am');
        $this->client->submit($crawler->selectButton('Sign up')->form());
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role=alert]', 'A volunteer who is a minor joins a shift once the consent of a parent or guardian is recorded.');

        $minors = self::getContainer()->get(Minors::class);
        self::assertInstanceOf(Minors::class, $minors);
        $minors->record('kid', 'Marie Curie', '514-555-0100', null, 'grace');
        $minors->consent('kid', 'grace');

        $crawler = $this->client->request('GET', '/shifts/shift/desk-am');
        $this->client->submit($crawler->selectButton('Sign up')->form());
        self::assertResponseRedirects('/shifts/shift/desk-am', 303);
        self::assertSame('confirmed', $this->rosters()->find('desk-am')?->statusOf('kid')?->value);
    }

    public function testTheComponents(): void
    {
        $this->rosters()->plan(Stage::desk());
        $this->rosters()->signUp('desk-am', 'ada', []);
        $this->login('ada');
        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('article.phil-shifts-card'));
        self::assertSame('Info desk', $crawler->filter('article h3')->text());
        self::assertSame('Withdraw', $crawler->filter('article button')->text());
        self::assertSame('My shifts', $crawler->filter('.phil-shifts-mine h2')->text());
        self::assertCount(1, $crawler->filter('.phil-shifts-mine li'));
        self::assertStringContainsString('No shift served yet.', $crawler->filter('.phil-shifts-mine')->text());
        $this->client->submit($crawler->selectButton('Withdraw')->form());
        self::assertResponseRedirects('/shifts/shift/desk-am', 303);
        self::assertNull($this->rosters()->find('desk-am')?->statusOf('ada'));
    }

    public function testTheComponentsForNobody(): void
    {
        $this->rosters()->plan(Stage::desk());

        $crawler = $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('article.phil-shifts-card'));
        self::assertSame('Sign up', $crawler->filter('article button')->text());
        self::assertCount(0, $crawler->filter('.phil-shifts-mine'));

        $this->kernel()->clock()->modify('2026-11-28 09:00:00 UTC');
        $crawler = $this->client->request('GET', '/dashboard');
        self::assertCount(0, $crawler->filter('article button'));
    }

    private function login(string $user): void
    {
        $roles = ['ada' => ['ROLE_USER'], 'bob' => ['ROLE_USER', 'FIRST_AID'], 'grace' => ['ROLE_USER', 'ROLE_SHIFTS_COORDINATOR'], 'kid' => ['ROLE_USER']][$user];
        $this->client->loginUser(new InMemoryUser($user, 'x', $roles));
    }

    private function token(string $page): string
    {
        $crawler = $this->client->request('GET', $page);

        return (string) $crawler->filter('input[name=_token]')->attr('value', '');
    }

    private function rosters(): Rosters
    {
        $rosters = self::getContainer()->get(Rosters::class);
        self::assertInstanceOf(Rosters::class, $rosters);

        return $rosters;
    }

    private function absences(): Absences
    {
        $absences = self::getContainer()->get(Absences::class);
        self::assertInstanceOf(Absences::class, $absences);

        return $absences;
    }

    private function kernel(): TestKernel
    {
        $kernel = $this->client->getKernel();
        self::assertInstanceOf(TestKernel::class, $kernel);

        return $kernel;
    }
}
