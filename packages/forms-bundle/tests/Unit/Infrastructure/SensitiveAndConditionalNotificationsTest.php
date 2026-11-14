<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\FormsBundle\Application\HmacEmailDigester;
use Phil\FormsBundle\Application\NotificationFailed;
use Phil\FormsBundle\Application\SealedValues;
use Phil\FormsBundle\Domain\Contract\FieldSealer;
use Phil\FormsBundle\Domain\Exception\UnreadableValue;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Domain\ValueObject\Notification;
use Phil\FormsBundle\Infrastructure\Doctrine\DoctrineFormRepository;
use Phil\FormsBundle\Infrastructure\Mailer\EmailNotifier;
use Phil\FormsBundle\Infrastructure\Privacy\FormEntriesPrivacy;
use Phil\FormsBundle\Infrastructure\Webhook\WebhookNotifier;
use Phil\FormsBundle\Tests\Unit\Fixtures\Database;
use Phil\FormsBundle\Tests\Unit\Fixtures\MutableClock;
use Phil\FormsBundle\Tests\Unit\Fixtures\RecordingMailer;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * The emails a form sends under conditions, and what leaves of a sensitive value: nothing by email or webhook, the
 * value opened for an access request.
 */
#[CoversClass(EmailNotifier::class)]
#[CoversClass(WebhookNotifier::class)]
#[CoversClass(FormEntriesPrivacy::class)]
#[CoversClass(SealedValues::class)]
#[CoversClass(UnreadableValue::class)]
final class SensitiveAndConditionalNotificationsTest extends TestCase
{
    private function form(): Form
    {
        $form = Form::create('contact', 'Contact', [
            Field::of('name', 'Name', FieldType::Text),
            Field::of('private', 'Private email', FieldType::Email, sensitive: true),
            Field::of('email', 'Email', FieldType::Email),
            Field::of('department', 'Department', FieldType::Choice, choices: ['Sales', 'Support']),
            Field::of('detail', 'Detail', FieldType::Text, shownWhen: ['==' => [['var' => 'department'], 'Support']]),
        ]);
        $form->notify(['all@example.com']);
        $form->notifyAlso([
            Notification::to(['sales@example.com'], 'Sales: {name}', ['==' => [['var' => 'department'], 'Sales']]),
            Notification::to(['support@example.com'], 'Support: {detail}', ['==' => [['var' => 'department'], 'Support']]),
            Notification::to(['details@example.com'], when: ['!=' => [['var' => 'detail'], 'none']]),
        ]);

        return $form;
    }

    public function testEachNotificationWhoseConditionHoldsIsEmailedWithItsSubject(): void
    {
        $mailer = new RecordingMailer();
        $entry = $this->form()->submit(['name' => 'Ada', 'private' => 'ada@home.example', 'email' => 'ada@example.com', 'department' => 'Sales', 'detail' => 'ignored'], new DateTimeImmutable(), allowUnsealed: true);

        new EmailNotifier($mailer, 'site@example.com', '[Site] ')->notify($entry);

        self::assertSame([
            [['all@example.com'], '[Site] Contact', ['ada@example.com']],
            [['sales@example.com'], '[Site] Sales: Ada', ['ada@example.com']],
        ], array_map($this->summary(...), $mailer->sent));
        $email = $mailer->sent[0];
        self::assertInstanceOf(TemplatedEmail::class, $email);
        $fields = $email->getContext()['fields'] ?? null;
        self::assertIsArray($fields);
        self::assertSame(['name', 'private', 'email', 'department'], array_map(static fn (mixed $field): string => $field instanceof Field ? $field->name : '', $fields), 'the fields asked');

        $support = new RecordingMailer();
        $form = $this->form();
        $form->notify([]);
        new EmailNotifier($support, 'site@example.com')->notify($form->submit(['private' => 'ada@home.example', 'department' => 'Support', 'detail' => 'Login'], new DateTimeImmutable(), allowUnsealed: true));
        self::assertSame([[['support@example.com'], 'Support: Login', []], [['details@example.com'], 'Contact', []]], array_map($this->summary(...), $support->sent), 'never the sensitive address to answer');
    }

    public function testEveryEmailIsTriedAndTheFirstFailureThrown(): void
    {
        $mailer = new class implements MailerInterface {
            /** @var list<string> */
            public array $tried = [];

            #[Override]
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                Assert::assertInstanceOf(TemplatedEmail::class, $message);
                $this->tried[] = $message->getTo()[0]->getAddress();

                if ('sales@example.com' !== $message->getTo()[0]->getAddress()) {
                    throw new TransportException('Refused for '.$message->getTo()[0]->getAddress().'.');
                }
            }
        };
        $form = $this->form();
        $form->notifyAlso([Notification::to(['sales@example.com']), Notification::to(['third@example.com'])]);

        try {
            new EmailNotifier($mailer, 'site@example.com')->notify($form->submit(['department' => 'Sales'], new DateTimeImmutable(), allowUnsealed: true));
            self::fail('failed');
        } catch (NotificationFailed $notificationFailed) {
            self::assertSame('The email notification failed: Refused for all@example.com.', $notificationFailed->getMessage());
        }

        self::assertSame(['all@example.com', 'sales@example.com', 'third@example.com'], $mailer->tried);
    }

    public function testASensitiveValueIsWithheldFromTheWebhook(): void
    {
        $form = $this->form();
        $form->notify([], 'https://hooks.example.com/forms');

        $bodies = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$bodies): MockResponse {
            $bodies[] = json_decode(\is_string($options['body'] ?? null) ? $options['body'] : '', true);

            return new MockResponse('', ['http_code' => 200]);
        });
        $notifier = new WebhookNotifier($client, 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw', new MutableClock(new DateTimeImmutable('2026-11-11 12:00 UTC')));

        $notifier->notify($form->submit(['name' => 'Ada', 'private' => 'ada@home.example', 'department' => 'Sales'], new DateTimeImmutable(), allowUnsealed: true));
        $notifier->notify(Form::create('plain', 'Plain', [Field::of('name', 'Name', FieldType::Text)])->submit(['name' => 'Ada'], new DateTimeImmutable()));

        $hooked = Form::create('hooked', 'Hooked', [Field::of('name', 'Name', FieldType::Text), Field::of('secret', 'Secret', FieldType::Text, shownWhen: ['==' => [['var' => 'name'], 'x']], sensitive: true)]);
        $hooked->notify([], 'https://hooks.example.com/forms');

        $notifier->notify($hooked->submit(['name' => 'Ada', 'secret' => 's'], new DateTimeImmutable(), allowUnsealed: true));

        self::assertCount(2, $bodies, 'a form without a webhook posts nothing');
        $first = $this->data($bodies[0]);
        $second = $this->data($bodies[1]);
        self::assertSame([['name' => 'Ada', 'email' => null, 'department' => 'Sales'], ['private']], [$first['values'] ?? null, $first['withheld'] ?? null]);
        self::assertSame(['form', 'entry', 'submitted_at', 'locale', 'values'], array_keys($second), 'nothing withheld, nothing listed');
        self::assertSame(['name' => 'Ada'], $second['values']);
    }

    public function testAnAccessRequestOpensTheSealedValuesOrSaysWhichDoNot(): void
    {
        $repository = new DoctrineFormRepository(Database::entityManager());
        $form = $this->form();
        $form->identifyBy('email');

        $repository->save($form);
        $sealer = new class implements FieldSealer {
            #[Override]
            public function seal(#[SensitiveParameter] string $value, string $context): string
            {
                return strrev($value).'@'.$context;
            }

            #[Override]
            public function open(string $sealed, string $context): string
            {
                if (!str_ends_with($sealed, '@'.$context)) {
                    throw UnreadableValue::of($context);
                }

                return strrev(substr($sealed, 0, -\strlen('@'.$context)));
            }
        };
        $digester = new HmacEmailDigester('key');
        $entry = $form->submit(['name' => 'Ada', 'private' => 'ada@home.example', 'email' => 'ada@example.com'], new DateTimeImmutable('2026-11-11 12:00 UTC'), digester: $digester, sealer: $sealer);
        $repository->save($entry);

        self::assertNotSame('ada@home.example', $entry->values['private']);
        $opened = $this->first(new FormEntriesPrivacy($repository, $digester, new SealedValues($sealer))->export(DataSubject::fromString('ada@example.com')));
        self::assertSame(['name' => 'Ada', 'private' => 'ada@home.example', 'email' => 'ada@example.com', 'department' => null], $opened['values'] ?? null);
        self::assertArrayNotHasKey('unreadable', $opened);

        $unopened = $this->first(new FormEntriesPrivacy($repository, $digester)->export(DataSubject::fromString('ada@example.com')));
        self::assertSame(['name' => 'Ada', 'email' => 'ada@example.com', 'department' => null], $unopened['values'] ?? null);
        self::assertSame(['private'], $unopened['unreadable'] ?? null);

        $this->expectException(UnreadableValue::class);
        $this->expectExceptionMessageIsOrContains('The sealed value of phil_forms.entry.'.$entry->id.'.private does not open.');
        new SealedValues()->reveal($entry);
    }

    public function testAValueThatIsNotTextDoesNotOpen(): void
    {
        $form = Form::create('a', 'A', [Field::of('a', 'A', FieldType::Text, sensitive: true)]);
        $entry = Entry::of($form, ['a' => null], new DateTimeImmutable(), new DateTimeImmutable(), null, sealed: ['a']);
        $sealer = new class implements FieldSealer {
            #[Override]
            public function seal(#[SensitiveParameter] string $value, string $context): string
            {
                return $value;
            }

            #[Override]
            public function open(string $sealed, string $context): string
            {
                return $sealed;
            }
        };

        self::assertSame(['a' => 'x'], new SealedValues($sealer)->reveal(Entry::of($form, ['a' => 'x'], new DateTimeImmutable(), new DateTimeImmutable(), null, sealed: ['a'])));
        $this->expectException(UnreadableValue::class);
        new SealedValues($sealer)->reveal($entry);
    }

    /**
     * @return array{list<string>, string, list<string>}
     */
    private function summary(RawMessage $email): array
    {
        $this->assertInstanceOf(TemplatedEmail::class, $email);

        return [
            array_values(array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo())),
            (string) $email->getSubject(),
            array_values(array_map(static fn (Address $address): string => $address->getAddress(), $email->getReplyTo())),
        ];
    }

    /**
     * @return array<array-key, mixed>
     */
    private function data(mixed $body): array
    {
        self::assertIsArray($body);
        self::assertIsArray($body['data'] ?? null);

        return $body['data'];
    }

    /**
     * @param array<array-key, mixed> $export
     *
     * @return array<array-key, mixed>
     */
    private function first(array $export): array
    {
        self::assertIsArray($export['entries'] ?? null);
        self::assertIsArray($export['entries'][0] ?? null);

        return $export['entries'][0];
    }
}
