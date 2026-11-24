<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use EasyCorp\Bundle\EasyAdminBundle\Config\Menu\RouteMenuItem;
use LogicException;
use Phil\IpAnonymizer\Truncation;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Message\SendNotification;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\TermsBundle\Application\Message\SendTermsReminders;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\ValueObject\Acceptance;
use Phil\TermsBundle\Infrastructure\EasyAdmin\TermsAdmin;
use Phil\TermsBundle\Infrastructure\Http\TermsAdminController;
use Phil\TermsBundle\Infrastructure\IpAnonymizer\NetworkMasker;
use Phil\TermsBundle\Infrastructure\Notifications\NotificationsNotifier;
use Phil\TermsBundle\Infrastructure\Notifications\OutstandingAudience;
use Phil\TermsBundle\Infrastructure\Privacy\AcceptancesPrivacy;
use Phil\TermsBundle\Infrastructure\Scheduler\TermsSchedule;
use Phil\TermsBundle\Infrastructure\Security\TokenMemberResolver;
use Phil\TermsBundle\Tests\Unit\Fixture\InMemoryAcceptances;
use Phil\TermsBundle\Tests\Unit\Fixture\Kit;
use Phil\TermsBundle\Tests\Unit\Fixture\RecordingBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Translation\TranslatableMessage;

#[CoversClass(TokenMemberResolver::class)]
#[CoversClass(NetworkMasker::class)]
#[CoversClass(NotificationsNotifier::class)]
#[CoversClass(OutstandingAudience::class)]
#[CoversClass(AcceptancesPrivacy::class)]
#[CoversClass(TermsSchedule::class)]
#[CoversClass(TermsAdmin::class)]
final class AdaptersTest extends TestCase
{
    public function testTheMemberIsTheUserOfTheToken(): void
    {
        $tokens = new TokenStorage();
        $resolver = new TokenMemberResolver($tokens);
        self::assertNull($resolver->current());

        $tokens->setToken(new NullToken());
        self::assertNull($resolver->current());

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('42', null), 'main'));
        self::assertSame('42', $resolver->current());
    }

    public function testAnEmailAddressIsNotAMember(): void
    {
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('jo@example.com', null), 'main'));

        $this->expectExceptionObject(new LogicException('The user identifier of the security token is an email address, although phil_terms.stable_identifiers declares it never is: name a resolver that gives a stable identifier in phil_terms.member_resolver.'));

        new TokenMemberResolver($tokens)->current();
    }

    public function testTheNetworkOfAnAddressIsKept(): void
    {
        $masker = new NetworkMasker(new Truncation());

        self::assertSame('192.0.2.0', $masker->mask('192.0.2.77'));
        self::assertSame('2001:db8:1::', $masker->mask('2001:db8:1:2::7'));
        self::assertNull($masker->mask('not an address'));
    }

    public function testTheNoticesGoThroughTheNotifications(): void
    {
        $kit = new Kit();
        $bus = new RecordingBus();
        $handler = new ReflectionClass(SendNotificationHandler::class)->newInstanceWithoutConstructor();
        $notifications = new Notifications(new NotificationTypes(new NotificationType('terms.published'), new NotificationType('terms.reminder')), $handler, $bus);
        $notifier = new NotificationsNotifier($notifications, 'terms.published', 'terms.reminder');

        $notifier->published($kit->terms->publish('terms', ['en' => 'x']));
        $notifier->remind('privacy', 3, ['a', 'b']);

        self::assertEquals([
            new SendNotification('terms.published', [], 'phil_terms:terms', 'phil_terms_policy', 'terms', ['version' => 1]),
            new SendNotification('terms.reminder', ['a', 'b'], null, 'phil_terms_policy', 'privacy', ['version' => 3]),
        ], $bus->messages);
    }

    public function testTheAudienceIsTheMembersWhoHaveNotAcceptedTheLatestMaterialVersion(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'x']);
        $kit->terms->accept('a', 'terms', 1, AcceptanceChannel::Form);

        $audience = new OutstandingAudience($kit->policies, $kit->outstanding(['a', 'b']));

        self::assertTrue($audience->supports('phil_terms:terms'));
        self::assertTrue($audience->supports('phil_terms:conduct'));
        self::assertFalse($audience->supports('phil_terms:cookies'));
        self::assertFalse($audience->supports('event:terms'));
        self::assertFalse($audience->supports('x:phil_terms:terms'));
        self::assertSame(['b'], [...$audience->recipients('phil_terms:terms')]);
    }

    public function testTheAcceptancesAreExportedThenErased(): void
    {
        $acceptances = new InMemoryAcceptances();
        $acceptances->append(new Acceptance('42', 'terms', 1, new DateTimeImmutable('2026-05-01 10:00:00+00:00'), AcceptanceChannel::Signup, '192.0.2.0', 'fr', 'a3f1000000000000000000000000000000000000000000000000000000000000'));
        $acceptances->append(new Acceptance('43', 'terms', 1, new DateTimeImmutable(), AcceptanceChannel::Signup));

        $privacy = new AcceptancesPrivacy($acceptances);

        self::assertSame('terms', $privacy->module()->value);
        self::assertSame(['acceptances' => [['policy' => 'terms', 'version' => 1, 'accepted_at' => '2026-05-01T10:00:00+00:00', 'channel' => 'signup', 'network' => '192.0.2.0', 'locale' => 'fr', 'text_sha256' => 'a3f1000000000000000000000000000000000000000000000000000000000000']]], $privacy->export(DataSubject::fromString('42')));
        self::assertSame([], $privacy->export(DataSubject::fromString('44')));

        $report = $privacy->erase(DataSubject::fromString('42'));
        self::assertSame('terms', $report->module->value);
        self::assertSame(['phil_terms_acceptance' => ErasureEffect::Deleted], $report->effects);
        self::assertSame([], $acceptances->ofMember('42'));
        self::assertCount(1, $acceptances->ofMember('43'));
    }

    public function testTheScheduleSendsTheRemindersAtItsFrequency(): void
    {
        $provider = new TermsSchedule('1 day');
        $schedule = $provider->getSchedule();
        $messages = $schedule->getRecurringMessages();

        self::assertSame($schedule, $provider->getSchedule());
        self::assertCount(1, $messages);
        $trigger = $messages[0]->getTrigger();
        self::assertInstanceOf(PeriodicalTrigger::class, $trigger);
        self::assertSame('every 1 day', (string) $trigger);
        $context = new MessageContext('phil_terms', 'id', $trigger, new DateTimeImmutable());
        self::assertEquals([new SendTermsReminders()], [...$messages[0]->getMessages($context)]);
        self::assertSame('every 1 week', (string) new TermsSchedule()->getSchedule()->getRecurringMessages()[0]->getTrigger());
    }

    public function testTheMenuOfEasyAdmin(): void
    {
        $items = TermsAdmin::menuItems();

        self::assertCount(1, $items);
        self::assertInstanceOf(RouteMenuItem::class, $items[0]);
        $dto = $items[0]->getAsDto();
        self::assertEquals(new TranslatableMessage('menu.terms', [], 'phil_terms'), $dto->getLabel());
        self::assertSame('fa fa-file-contract', $dto->getIcon());
        self::assertSame(TermsAdminController::LIST_ROUTE, $dto->getRouteName());
    }
}
