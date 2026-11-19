<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\ConversationBundle\Application\IdentifierDirectory;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\HelpdeskBundle\Application\CannedReply;
use Phil\HelpdeskBundle\Application\Event\TicketAssigned;
use Phil\HelpdeskBundle\Application\Queue;
use Phil\HelpdeskBundle\Domain\Exception\CannedReplyNotFound;
use Phil\HelpdeskBundle\Domain\Model\Ticket;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\DbalTicketRepository;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\HelpdeskTables;
use Phil\HelpdeskBundle\Infrastructure\EasyAdmin\HelpdeskAdmin;
use Phil\HelpdeskBundle\Infrastructure\Notifications\NotifyAssignee;
use Phil\HelpdeskBundle\Infrastructure\Privacy\HelpdeskPrivacy;
use Phil\HelpdeskBundle\Infrastructure\Security\RoleQueueAccess;
use Phil\HelpdeskBundle\Infrastructure\Twig\HelpdeskExtension;
use Phil\HelpdeskBundle\Infrastructure\Twig\KitComponents;
use Phil\HelpdeskBundle\Infrastructure\Twig\TwigCannedReplies;
use Phil\HelpdeskBundle\Tests\Unit\Fixture\Desk;
use Phil\HelpdeskBundle\Tests\Unit\Fixture\RecordingBus;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(RoleQueueAccess::class)]
#[CoversClass(TwigCannedReplies::class)]
#[CoversClass(HelpdeskPrivacy::class)]
#[CoversClass(NotifyAssignee::class)]
#[CoversClass(HelpdeskExtension::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(HelpdeskAdmin::class)]
#[CoversClass(DbalTicketRepository::class)]
#[CoversClass(HelpdeskTables::class)]
final class IntegrationsTest extends TestCase
{
    public function testAnAgentWorksTheQueuesOfAnyOfTheirRoles(): void
    {
        $queue = new Queue(Desk::queue('tech'), 'queue.tech', ['ROLE_TECH', 'ROLE_ADMIN']);

        self::assertTrue(new RoleQueueAccess($this->granted(['ROLE_ADMIN']))->mayWork($queue));
        self::assertTrue(new RoleQueueAccess($this->granted(['ROLE_TECH']))->mayWork($queue));
        self::assertFalse(new RoleQueueAccess($this->granted(['ROLE_BILLING']))->mayWork($queue));
        self::assertFalse(new RoleQueueAccess(null)->mayWork($queue));
        self::assertFalse(new RoleQueueAccess($this->granted([], true))->mayWork($queue), 'nobody works a queue without a token');
    }

    public function testTheCannedRepliesAreTemplatesOfTheApplication(): void
    {
        $twig = new Environment(new ArrayLoader([
            'thanks.txt.twig' => "\n  Thanks, your request in {{ ticket.queue.value }} is noted ({{ locale ?? 'none' }}).\n",
            'refund.txt.twig' => 'Refund',
        ]));
        $replies = new TwigCannedReplies($twig, [
            'thanks' => ['label' => 'canned.thanks', 'template' => 'thanks.txt.twig', 'queues' => []],
            'refund' => ['label' => 'canned.refund', 'template' => 'refund.txt.twig', 'queues' => ['billing']],
        ]);
        $billing = new Ticket(ConversationId::generate(), Desk::queue('billing'), Desk::id('ada'), new DateTimeImmutable());
        $tech = new Ticket(ConversationId::generate(), Desk::queue('tech'), Desk::id('ada'), new DateTimeImmutable());

        self::assertSame([['thanks', 'canned.thanks'], ['refund', 'canned.refund']], array_map(static fn (CannedReply $reply): array => [$reply->id, $reply->label], $replies->for($billing)));
        self::assertSame(['thanks'], array_map(static fn (CannedReply $reply): string => $reply->id, $replies->for($tech)));
        self::assertSame('Thanks, your request in tech is noted (fr).', $replies->render('thanks', $tech, 'fr'));
        self::assertSame('Thanks, your request in billing is noted (none).', $replies->render('thanks', $billing));
        self::assertSame('Refund', $replies->render('refund', $billing));

        foreach ([['refund', $tech], ['unknown', $billing]] as [$id, $ticket]) {
            try {
                $replies->render($id, $ticket);
                self::fail('A canned reply was written where it is not offered.');
            } catch (CannedReplyNotFound $cannedReplyNotFound) {
                self::assertSame('No canned reply "'.$id.'" for this request.', $cannedReplyNotFound->getMessage());
            }
        }
    }

    public function testTheRequestsOfAPersonAreExportedAndErased(): void
    {
        $desk = new Desk(['billing']);
        $asked = $desk->open('ada');
        $handled = $desk->open('bob');
        $desk->helpdesk->assign($handled, Desk::id('ada'), Desk::id('lead'));
        $privacy = new HelpdeskPrivacy($desk->tickets);

        self::assertSame('helpdesk', $privacy->module()->value);
        self::assertSame(['requests' => [
            ['conversation' => $asked->value, 'role' => 'requester', 'queue' => 'billing', 'status' => 'new', 'opened_at' => '2026-11-13T10:00:00+00:00', 'updated_at' => '2026-11-13T10:00:00+00:00', 'closed_at' => null],
            ['conversation' => $handled->value, 'role' => 'assignee', 'queue' => 'billing', 'status' => 'open', 'opened_at' => '2026-11-13T10:00:00+00:00', 'updated_at' => '2026-11-13T10:00:00+00:00', 'closed_at' => null],
        ]], $privacy->export(DataSubject::fromString('ada')));
        self::assertSame([], $privacy->export(DataSubject::fromString('nobody')));
        self::assertSame([], $privacy->export(DataSubject::fromString('ada@example.org')));

        $report = $privacy->erase(DataSubject::fromString('ada'));
        self::assertSame(['helpdesk', ['phil_helpdesk_ticket' => ErasureEffect::Redacted]], [$report->module->value, $report->effects]);
        self::assertSame([], $privacy->export(DataSubject::fromString('ada')));
        self::assertNull($desk->tickets->find($asked)?->requester());
        self::assertSame('helpdesk', $privacy->erase(DataSubject::fromString('ada@example.org'))->module->value);
    }

    public function testAnAgentIsNotifiedOfARequestAssignedToThemBySomeoneElse(): void
    {
        $bus = new RecordingBus();
        $handler = new ReflectionClass(SendNotificationHandler::class)->newInstanceWithoutConstructor();
        $notify = new NotifyAssignee(new Notifications(new NotificationTypes(new NotificationType('site.assigned')), $handler, $bus), 'site.assigned');

        $notify(new TicketAssigned('c1', 'agent-1', 'lead'));
        $notify(new TicketAssigned('c2', 'agent-1', 'agent-1'));
        $notify(new TicketAssigned('c3', null, 'lead'));

        self::assertCount(1, $bus->messages);
        $message = $bus->messages[0];
        self::assertSame(['site.assigned', ['agent-1'], 'phil_conversation.conversation', 'c1', [], 'lead'], [$message->type, $message->recipients, $message->subjectType, $message->subjectId, $message->data, $message->actor]);
        self::assertSame(['phil_helpdesk.assigned', 'phil_conversation.conversation'], [NotifyAssignee::TYPE, NotifyAssignee::SUBJECT]);
    }

    public function testTheTwigFunctionsNameTheRecipesAndTheParticipants(): void
    {
        $extension = new HelpdeskExtension(new KitComponents('/ui/'), new IdentifierDirectory());

        self::assertSame('ui:Button', $extension->kit('Button'));
        self::assertSame('Badge', new KitComponents()->name('Badge'));
        self::assertSame('ui:forms:Field', new KitComponents('ui/forms')->name('Field'), 'a nested directory names its recipes with colons');
        self::assertSame('ada', $extension->name(Desk::id('ada')));
        self::assertNull($extension->name(null));
    }

    public function testTheDeskIsLinkedFromAnEasyAdminDashboard(): void
    {
        self::assertSame('admin_helpdesk_desk', HelpdeskAdmin::menuItem()->getAsDto()->getRouteName());
        self::assertSame('back_helpdesk_desk', HelpdeskAdmin::menuItem('back', 'Support', null)->getAsDto()->getRouteName());
        self::assertNull(HelpdeskAdmin::dashboardRoute(new Request()));
    }

    /**
     * @param list<string> $roles
     */
    private function granted(array $roles, bool $withoutToken = false): AuthorizationCheckerInterface
    {
        return new readonly class($roles, $withoutToken) implements AuthorizationCheckerInterface {
            /**
             * @param list<string> $roles
             */
            public function __construct(
                private array $roles,
                private bool $withoutToken,
            ) {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, mixed $accessDecision = null): bool
            {
                if ($this->withoutToken) {
                    throw new AuthenticationCredentialsNotFoundException();
                }

                return null === $subject && \in_array($attribute, $this->roles, true);
            }
        };
    }
}
