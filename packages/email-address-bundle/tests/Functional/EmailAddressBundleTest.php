<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Functional;

use Override;
use Phil\EmailAddress\Validator\DeliverableEmailAddress;
use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Application\Message\BounceDecidedMessage;
use Phil\EmailAddressBundle\Infrastructure\Form\EmailAddressType;
use Phil\EmailAddressBundle\Infrastructure\RemoteEvent\MailerEventConsumer;
use Phil\EmailAddressBundle\Infrastructure\Validator\Attribute\AcceptedEmailAddress;
use Phil\EmailAddressBundle\PhilEmailAddressBundle;
use Phil\EmailAddressBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[CoversClass(PhilEmailAddressBundle::class)]
final class EmailAddressBundleTest extends KernelTestCase
{
    private const string FIXTURES = __DIR__.'/../Fixtures/';

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
    }

    public function testTheFieldChecksByTheListPolicyAndCarriesItsController(): void
    {
        $forms = $this->service('test.form.factory', FormFactoryInterface::class);
        $form = $forms->create(EmailAddressType::class, null, ['list' => 'staff']);
        $form->submit('ada@example.com');

        self::assertFalse($form->isValid());
        $cause = $form->getErrors()[0]->getCause();
        self::assertInstanceOf(ConstraintViolationInterface::class, $cause);
        self::assertSame(DeliverableEmailAddress::DOMAIN_NOT_ALLOWED_ERROR, $cause->getCode());
        $attr = $form->createView()->vars['attr'];
        self::assertIsArray($attr);
        self::assertSame('/email-address/domains.json', $attr['data-phil--email-address-bundle--email-address-url-value']);

        $slip = $forms->create(EmailAddressType::class);
        $slip->submit('ada@gmial.com');
        self::assertTrue($slip->isValid());
        self::assertSame('Did you mean ada@gmail.com?', $slip->createView()->vars['help']);

        $denied = $forms->create(EmailAddressType::class);
        $denied->submit('ada@spam.example');
        self::assertSame('Addresses at "spam.example" are not accepted.', $denied->getErrors()[0]->getMessage());
    }

    public function testTheConstraintsUseTheConfiguredResolver(): void
    {
        $validator = $this->service('validator', ValidatorInterface::class);

        self::assertSame(DeliverableEmailAddress::NO_MAIL_SERVER_ERROR, $validator->validate('ada@nomail.example', new AcceptedEmailAddress('staff'))->get(0)->getCode());
        self::assertCount(0, $validator->validate('ada@example.org', new AcceptedEmailAddress('staff')));
        self::assertSame(DeliverableEmailAddress::NO_MAIL_SERVER_ERROR, $validator->validate('ada@nomail.example', new DeliverableEmailAddress(checkMailServer: true))->get(0)->getCode());
    }

    public function testTheListsAreServedAsJson(): void
    {
        $response = self::$kernel?->handle(Request::create('/email-address/domains.json'), HttpKernelInterface::MAIN_REQUEST);

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        $lists = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($lists);
        self::assertIsArray($lists['domains']);
        self::assertIsArray($lists['slips']);
        self::assertIsArray($lists['roles']);
        self::assertContains('gmail.com', $lists['domains']);
        self::assertSame('com', $lists['slips']['con']);
        self::assertContains('noreply', $lists['roles']);
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    public function testAWebhookEventIsDecidedAndSentOnWithoutTheAddress(): void
    {
        $consumer = $this->service(MailerEventConsumer::class, MailerEventConsumer::class);
        $bounce = new MailerDeliveryEvent(MailerDeliveryEvent::BOUNCE, 'evt-1', []);
        $bounce->setRecipientEmail('ada.lovelace@example.org');
        $bounce->setMetadata(['recipient' => 'subscriber-42']);

        $spam = new MailerEngagementEvent(MailerEngagementEvent::SPAM, 'evt-2', []);
        $spam->setRecipientEmail('grace.hopper@example.org');

        $consumer->consume($bounce);
        $consumer->consume($bounce);
        $consumer->consume($spam);

        self::assertQueuedMessageCount(2, 'async', BounceDecidedMessage::class);
        $sent = array_values(self::getQueuedMessages('async'));
        self::assertCount(2, $sent);
        $messages = array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $sent);
        $fingerprint = $this->service(EmailAddresses::class, EmailAddresses::class)->fingerprint('ada.lovelace@example.org');
        self::assertInstanceOf(BounceDecidedMessage::class, $messages[0]);
        self::assertSame(['suspend', 'hard', $fingerprint?->value, 'subscriber-42'], [$messages[0]->decision, $messages[0]->kind, $messages[0]->fingerprint, $messages[0]->recipientId]);
        self::assertInstanceOf(BounceDecidedMessage::class, $messages[1]);
        self::assertSame(['unsubscribe', 'complaint', null], [$messages[1]->decision, $messages[1]->kind, $messages[1]->recipientId]);

        $this->assertNoAddressReachesTheQueue($sent, ['ada.lovelace', 'grace.hopper']);
    }

    public function testTheCommandReadsADeliveryStatusNotificationAndAnAbuseReport(): void
    {
        $application = new Application(self::$kernel ?? self::bootKernel());
        $tester = new CommandTester($application->find('phil:email-address:read-bounce'));

        self::assertSame(0, $tester->execute(['file' => self::FIXTURES.'postfix-hard.eml']));
        self::assertSame("suspend\n", $tester->getDisplay());
        self::assertSame(0, $tester->execute(['file' => self::FIXTURES.'arf.eml']));
        self::assertSame("unsubscribe\n", $tester->getDisplay());
        self::assertSame(0, $tester->execute(['file' => self::FIXTURES.'arf.eml']));
        self::assertSame("duplicate\n", $tester->getDisplay());

        self::assertQueuedMessageCount(2, 'async');
        $this->assertNoAddressReachesTheQueue(array_values(self::getQueuedMessages('async')), ['ada@', 'grace@', 'example.org', 'example.com']);
    }

    /**
     * @param list<Envelope> $envelopes
     * @param list<string>   $fragments
     */
    private function assertNoAddressReachesTheQueue(array $envelopes, array $fragments): void
    {
        $serializer = new PhpSerializer();

        foreach ($envelopes as $envelope) {
            $encoded = strtolower(json_encode($serializer->encode($envelope), \JSON_THROW_ON_ERROR));

            foreach ($fragments as $fragment) {
                self::assertStringNotContainsString(strtolower($fragment), $encoded);
                self::assertStringNotContainsString(strtolower($fragment), strtolower(serialize($envelope->getMessage())));
            }
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
