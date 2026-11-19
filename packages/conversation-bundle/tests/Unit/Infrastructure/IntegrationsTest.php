<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Infrastructure;

use Phil\ConversationBundle\Application\Event\MessageSent;
use Phil\ConversationBundle\Application\IdentifierDirectory;
use Phil\ConversationBundle\Domain\Conversation\Kind;
use Phil\ConversationBundle\Domain\Exception\InvalidParticipant;
use Phil\ConversationBundle\Domain\Model\Conversation;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Domain\Model\Participation;
use Phil\ConversationBundle\Domain\Reception\WhoCanWrite;
use Phil\ConversationBundle\Domain\ValueObject\ConversationId;
use Phil\ConversationBundle\Domain\ValueObject\MessageId;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalConversationRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalReceptionRepository;
use Phil\ConversationBundle\Infrastructure\Http\ConversationController;
use Phil\ConversationBundle\Infrastructure\Mercure\MercureMessagePublisher;
use Phil\ConversationBundle\Infrastructure\Notifications\ConversationSubjects;
use Phil\ConversationBundle\Infrastructure\Notifications\NotifyParticipants;
use Phil\ConversationBundle\Infrastructure\Privacy\ConversationPrivacy;
use Phil\ConversationBundle\Infrastructure\Security\SecurityCurrentParticipant;
use Phil\ConversationBundle\Infrastructure\Twig\ConversationExtension;
use Phil\ConversationBundle\Infrastructure\Twig\KitComponents;
use Phil\ConversationBundle\Tests\Unit\Fixture\FixedUrls;
use Phil\ConversationBundle\Tests\Unit\Fixture\RecordingBus;
use Phil\ConversationBundle\Tests\Unit\Fixture\RecordingHub;
use Phil\ConversationBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\ConversationBundle\Tests\Unit\Fixture\Store;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(NotifyParticipants::class)]
#[CoversClass(ConversationSubjects::class)]
#[CoversClass(MercureMessagePublisher::class)]
#[CoversClass(ConversationPrivacy::class)]
#[CoversClass(SecurityCurrentParticipant::class)]
#[CoversClass(IdentifierDirectory::class)]
#[CoversClass(ConversationExtension::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(DbalConversationRepository::class)]
#[CoversClass(DbalReceptionRepository::class)]
#[CoversClass(ConversationTables::class)]
final class IntegrationsTest extends TestCase
{
    public function testTheRecipientsAreNotifiedWithIdentifiersOnly(): void
    {
        $bus = new RecordingBus();
        $notify = new NotifyParticipants($this->notifications($bus, 'site.message'), 'site.message');

        $notify(new MessageSent('c1', 'm1', 3, 'ada', ['bob', 'cy'], ['bob']));
        $notify(new MessageSent('c1', 'm2', 4, 'ada', ['bob'], []));

        self::assertCount(1, $bus->messages);
        $message = $bus->messages[0];
        self::assertSame('site.message', $message->type);
        self::assertSame(['bob'], $message->recipients);
        self::assertSame(['phil_conversation.conversation', 'c1'], [$message->subjectType, $message->subjectId]);
        self::assertSame(['message' => 'm1'], $message->data);
        self::assertSame('ada', $message->actor);
        self::assertSame(['phil_conversation.message', 'phil_conversation.conversation'], [NotifyParticipants::TYPE, NotifyParticipants::SUBJECT]);
    }

    public function testTheNotifiedConversationIsNamedWhenShown(): void
    {
        $store = new Store();
        $group = $store->conversations->startGroup(Store::id('ada'), [Store::id('bob')], 'Club', 'Hi');
        $direct = $store->conversations->startDirect(Store::id('ada'), Store::id('cy'), 'Hi');
        $store->conversations->leave($direct->id, Store::id('cy'));
        $urls = new FixedUrls();
        $subjects = new ConversationSubjects($store->conversations, new IdentifierDirectory(), $urls);

        $resolved = $subjects->resolve(new SubjectReference(NotifyParticipants::SUBJECT, $group->id->value), 'fr');
        self::assertSame(['Club', 'https://example.com/messages'], [$resolved?->label, $resolved?->url]);
        self::assertSame([[ConversationController::THREAD_ROUTE, ['id' => $group->id->value], UrlGeneratorInterface::ABSOLUTE_URL]], $urls->asked);
        self::assertSame('ada', $subjects->resolve(new SubjectReference(NotifyParticipants::SUBJECT, $direct->id->value), null)?->label, 'the names of the active participants');

        self::assertNull(new ConversationSubjects($store->conversations, new IdentifierDirectory())->resolve(new SubjectReference(NotifyParticipants::SUBJECT, $group->id->value), null)?->url);
        self::assertNull(new ConversationSubjects($store->conversations, new IdentifierDirectory(), new FixedUrls(false))->resolve(new SubjectReference(NotifyParticipants::SUBJECT, $group->id->value), null)?->url);
        self::assertNull($subjects->resolve(new SubjectReference('comment', $group->id->value), null));
        self::assertNull($subjects->resolve(new SubjectReference(NotifyParticipants::SUBJECT, 'not-a-uuid'), null));
        self::assertNull($subjects->resolve(new SubjectReference(NotifyParticipants::SUBJECT, '0192f0c1-5c5e-7a3b-9e1d-0123456789ab'), null));
    }

    public function testTheOpenPagesAreToldAMessageCame(): void
    {
        $hub = new RecordingHub();
        $publisher = new MercureMessagePublisher($hub, 'urn:site:');

        $publisher(new MessageSent('c1', 'm1', 3, 'ada', ['bob', 'cy'], ['bob']));

        self::assertCount(1, $hub->updates);
        $update = $hub->updates[0];
        self::assertSame(['urn:site:ada', 'urn:site:bob', 'urn:site:cy'], $update->getTopics());
        self::assertSame('{"conversation":"c1","message":"m1","sequence":3}', $update->getData());
        self::assertTrue($update->isPrivate());
        self::assertSame('urn:phil:conversation:ada', new MercureMessagePublisher($hub)->topic('ada'));
    }

    public function testAHubThatCannotBeReachedIsLoggedAndDoesNotFailTheMessage(): void
    {
        $logger = new RecordingLogger();
        new MercureMessagePublisher(new RecordingHub(true), MercureMessagePublisher::PREFIX, $logger)(new MessageSent('c1', 'm1', 3, 'ada', [], []));
        new MercureMessagePublisher(new RecordingHub(true))(new MessageSent('c1', 'm1', 3, 'ada', [], []));

        self::assertCount(1, $logger->records);
        [$level, $message, $context] = $logger->records[0];
        self::assertSame(LogLevel::WARNING, $level);
        self::assertSame('The message "{message}" of the conversation "{conversation}" could not be pushed to Mercure: {reason}', $message);
        self::assertSame(['m1', 'c1', 'The hub is down.'], [$context['message'], $context['conversation'], $context['reason']]);
        self::assertInstanceOf(RuntimeException::class, $context['exception']);
    }

    public function testAMembersConversationsAreExportedOpened(): void
    {
        $store = new Store();
        $group = $store->conversations->startGroup(Store::id('ada'), [Store::id('bob')], 'Club', 'From ada');
        $store->conversations->reply($group->id, Store::id('bob'), 'From bob');
        $store->conversations->mute($group->id, Store::id('ada'));
        $store->reception->allow(Store::id('ada'), WhoCanWrite::Contacts);
        $store->reception->block(Store::id('ada'), Store::id('cy'));

        $privacy = new ConversationPrivacy($store->repository, $store->receptions);

        self::assertSame('conversations', $privacy->module()->value);
        $export = $privacy->export(DataSubject::fromString('ada'));
        self::assertSame([[
            'id' => $group->id->value,
            'kind' => 'group',
            'subject' => 'Club',
            'joined_at' => '2026-11-13T10:00:00+00:00',
            'left_at' => null,
            'muted' => true,
        ]], $export['conversations']);
        self::assertSame([['conversation' => $group->id->value, 'id' => $store->repository->messagesBy(Store::id('ada'))[0]->id->value, 'sent_at' => '2026-11-13T10:00:00+00:00', 'body' => 'From ada']], $export['messages']);
        self::assertSame(['who_can_write' => 'contacts', 'blocked' => ['cy']], $export['reception']);

        self::assertSame([], $privacy->export(DataSubject::fromString('nobody')));
        self::assertSame([], $privacy->export(DataSubject::fromString('ada@example.org')));
        $store->reception->allow(Store::id('dee'), WhoCanWrite::Nobody);
        self::assertSame(['who_can_write' => 'nobody', 'blocked' => []], $privacy->export(DataSubject::fromString('dee'))['reception'] ?? null);
        $store->reception->block(Store::id('eve'), Store::id('ada'));
        self::assertSame(['who_can_write' => 'everyone', 'blocked' => ['ada']], $privacy->export(DataSubject::fromString('eve'))['reception'] ?? null);
    }

    public function testWhatIsKeptOfAMemberIsExportedWhateverIsMissing(): void
    {
        $store = new Store();
        $privacy = new ConversationPrivacy($store->repository, $store->receptions);

        // A message whose author belongs to no conversation of the participations.
        $thread = ConversationId::generate();
        $store->repository->save(new Conversation($thread, Kind::Group, null, $store->clock->now()));
        $store->repository->add(new Message(MessageId::generate(), $thread, Store::id('ada'), 'Alone', $store->clock->now()));

        $export = $privacy->export(DataSubject::fromString('ada'));
        self::assertIsArray($export['messages'] ?? null);
        self::assertSame(['Alone'], array_column($export['messages'], 'body'));

        // A conversation of the participations that holds no message.
        $empty = ConversationId::generate();
        $store->repository->save(new Conversation($empty, Kind::Group, 'Quiet', $store->clock->now()));
        $store->repository->saveParticipation(new Participation($empty, Store::id('bob'), $store->clock->now()));

        $export = $privacy->export(DataSubject::fromString('bob'));
        self::assertIsArray($export['conversations'] ?? null);
        self::assertSame(['Quiet'], array_column($export['conversations'], 'subject'));
    }

    public function testAMembersConversationsAreErased(): void
    {
        $store = new Store();
        $group = $store->conversations->startGroup(Store::id('ada'), [Store::id('bob')], 'Club', 'From ada');
        $store->reception->block(Store::id('ada'), Store::id('cy'));
        $store->reception->block(Store::id('bob'), Store::id('ada'));

        $privacy = new ConversationPrivacy($store->repository, $store->receptions);

        $report = $privacy->erase(DataSubject::fromString('ada'));

        self::assertSame('conversations', $report->module->value);
        self::assertSame([
            'phil_conversation_message' => ErasureEffect::Redacted,
            'phil_conversation_participant' => ErasureEffect::Deleted,
            'phil_conversation_reception' => ErasureEffect::Deleted,
            'phil_conversation_block' => ErasureEffect::Deleted,
        ], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('ada')));
        self::assertSame([], $store->receptions->of(Store::id('bob'))->blocked());
        self::assertTrue($store->repository->messages($group->id, 5)[0]->isErased());
        self::assertSame('conversations', $privacy->erase(DataSubject::fromString('ada@example.org'))->module->value);
    }

    public function testTheCurrentParticipantIsTheSignedInUser(): void
    {
        $tokens = new TokenStorage();
        $current = new SecurityCurrentParticipant($tokens);

        self::assertNull($current->current());
        self::assertNull(new SecurityCurrentParticipant(null)->current());

        $signedIn = new TokenStorage();
        $signedIn->setToken(new UsernamePasswordToken(new InMemoryUser('member-7', null), 'main'));
        self::assertSame('member-7', new SecurityCurrentParticipant($signedIn)->current()?->value);

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada@example.org', null), 'main'));

        $this->expectException(InvalidParticipant::class);

        $current->current();
    }

    public function testTheTwigFunctionsNameTheRecipesAndTheParticipants(): void
    {
        $extension = new ConversationExtension(new KitComponents('ui/kit/'), new IdentifierDirectory());

        self::assertSame('ui:kit:Button', $extension->kit('Button'));
        self::assertSame('Button', new KitComponents()->name('Button'));
        self::assertSame('Badge', new KitComponents('/')->name('Badge'));
        self::assertSame('member-7', $extension->name(Store::id('member-7')));
        self::assertNull($extension->name(null));
    }

    private function notifications(RecordingBus $bus, string $type): Notifications
    {
        // The bus delivers: the handler, which needs a whole store, is never called.
        $handler = new ReflectionClass(SendNotificationHandler::class)->newInstanceWithoutConstructor();

        return new Notifications(new NotificationTypes(new NotificationType($type)), $handler, $bus);
    }
}
