<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\FormsBundle\Application\NotificationFailed;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Infrastructure\Mailer\EmailNotifier;
use Phil\FormsBundle\Infrastructure\Webhook\WebhookNotifier;
use Phil\FormsBundle\Tests\Unit\Fixtures\Forms;
use Phil\FormsBundle\Tests\Unit\Fixtures\MutableClock;
use Phil\FormsBundle\Tests\Unit\Fixtures\RecordingMailer;
use Phil\StandardWebhooks\HmacSecret;
use Phil\StandardWebhooks\Tolerance;
use Phil\StandardWebhooks\Verifier;
use Phil\StandardWebhooks\WebhookHeaders;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Mime\Address;

#[CoversClass(EmailNotifier::class)]
#[CoversClass(WebhookNotifier::class)]
final class NotifiersTest extends TestCase
{
    private const string SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    public function testAnEntryIsEmailedToTheFormsRecipients(): void
    {
        $form = Forms::registration();
        $form->notify(['info@example.com', 'jeux@example.com']);

        $entry = $form->submit(Forms::values(), new DateTimeImmutable(), 'fr');
        $mailer = new RecordingMailer();

        new EmailNotifier($mailer, 'site@example.com', '[Site] ')->notify($entry);

        $email = $mailer->sent[0] ?? null;
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame(['site@example.com', ['info@example.com', 'jeux@example.com'], ['ada@example.com'], '[Site] Inscription au tournoi', 'fr'], [$email->getFrom()[0]->getAddress(), $this->addresses($email->getTo()), $this->addresses($email->getReplyTo()), $email->getSubject(), $email->getLocale()]);
        self::assertSame([EmailNotifier::HTML_TEMPLATE, EmailNotifier::TEXT_TEMPLATE, '@PhilForms/email/entry.html.twig', '@PhilForms/email/entry.txt.twig'], [$email->getHtmlTemplate(), $email->getTextTemplate(), EmailNotifier::HTML_TEMPLATE, EmailNotifier::TEXT_TEMPLATE]);
        self::assertSame($entry, $email->getContext()['entry']);
        self::assertEquals($form->fields(), $email->getContext()['fields']);
        self::assertSame('OOF, AutoReply', $email->getHeaders()->get('X-Auto-Response-Suppress')?->getBodyAsString());
        self::assertSame('Inscription au tournoi', $this->mailed($form)->getSubject());
    }

    public function testTheFirstEmailFieldAnswers(): void
    {
        $form = Form::create('a', 'A', [Field::of('other', 'Autre', FieldType::Text), Field::of('first', 'Premier', FieldType::Email), Field::of('second', 'Second', FieldType::Email)]);
        $form->notify(['info@example.com']);

        self::assertSame(['first@example.com'], $this->addresses($this->mailed($form, ['other' => 'x@example.com', 'first' => 'first@example.com', 'second' => 'second@example.com'])->getReplyTo()));
        self::assertSame(['second@example.com'], $this->addresses($this->mailed($form, ['second' => 'second@example.com'])->getReplyTo()));
        self::assertSame([], $this->mailed($form, [])->getReplyTo());
    }

    public function testNoRecipientNoEmailAndATransportDownFails(): void
    {
        $mailer = new RecordingMailer();
        new EmailNotifier($mailer, 'site@example.com')->notify(Forms::registration()->submit(Forms::values(), new DateTimeImmutable()));
        self::assertSame([], $mailer->sent);

        $form = Forms::registration();
        $form->notify(['info@example.com']);
        $this->expectException(NotificationFailed::class);
        $this->expectExceptionMessageIsOrContains('The email notification failed: Connection refused.');

        new EmailNotifier(new RecordingMailer(down: true), 'site@example.com')->notify($form->submit(Forms::values(), new DateTimeImmutable()));
    }

    public function testAnEntryIsPostedSignedToTheWebhook(): void
    {
        $form = Forms::registration();
        $form->notify([], 'https://hooks.example.com/forms');

        $entry = $form->submit(Forms::values(), new DateTimeImmutable('2026-11-04 12:00 UTC'), 'fr');
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse('', ['http_code' => 204]);
        });
        $now = new DateTimeImmutable('2026-11-04 12:00:05.123 UTC');

        new WebhookNotifier($client, self::SECRET, new MutableClock($now), 3.5)->notify($entry);

        self::assertCount(1, $requests);
        [$method, $url, $options] = $requests[0];
        self::assertSame(['POST', 'https://hooks.example.com/forms', 0, 3.5], [$method, $url, $options['max_redirects'], $options['timeout']]);
        $body = $options['body'];
        self::assertIsString($body);
        self::assertSame(\sprintf('{"type":"form.entry.submitted","timestamp":"2026-11-04T12:00:05.123Z","data":{"form":"inscription","entry":"%s","submitted_at":"2026-11-04T12:00:00Z","locale":"fr","values":{"name":"Ada","email":"ada@example.com","site":"https://ada.example.com","born":null,"team":"Bleue","message":"Bonjour,\nà bientôt.","consent":true}}}', $entry->id), $body);
        $headers = $this->headers($options['headers']);
        self::assertSame(['application/json', 'msg_'.$entry->id, '1793793605'], [$headers['content-type'], $headers['webhook-id'], $headers['webhook-timestamp']]);
        $verified = new Verifier()->verify(WebhookHeaders::fromStrings($headers['webhook-id'], $headers['webhook-timestamp'], $headers['webhook-signature']), $body, [HmacSecret::fromString(self::SECRET)], Tolerance::ofSeconds(60), $now);
        self::assertSame($body, $verified->rawBody);
        self::assertSame(WebhookNotifier::TYPE, 'form.entry.submitted');
    }

    public function testNoWebhookNoPostAndARefusalFails(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => self::fail('Something was posted.'));
        new WebhookNotifier($client, self::SECRET, new NativeClock())->notify(Forms::registration()->submit(Forms::values(), new DateTimeImmutable()));

        $form = Forms::registration();
        $form->notify([], 'https://hooks.example.com/forms');

        foreach ([
            [new MockResponse('', ['http_code' => 200]), null],
            [new MockResponse('', ['http_code' => 299]), null],
            [new MockResponse('', ['http_code' => 199]), 'The webhook notification failed: https://hooks.example.com/forms answered 199.'],
            [new MockResponse('', ['http_code' => 300]), 'The webhook notification failed: https://hooks.example.com/forms answered 300.'],
            [new MockResponse('', ['http_code' => 500]), 'The webhook notification failed: https://hooks.example.com/forms answered 500.'],
            [new MockResponse('', ['error' => 'Connection refused.']), 'The webhook notification failed: Connection refused.'],
        ] as [$response, $message]) {
            try {
                new WebhookNotifier(new MockHttpClient($response), self::SECRET, new NativeClock())->notify($form->submit(Forms::values(), new DateTimeImmutable()));
                self::assertNull($message);
            } catch (NotificationFailed $notificationFailed) {
                self::assertSame($message, $notificationFailed->getMessage());
            }
        }

        $options = [];
        new WebhookNotifier(new MockHttpClient(static function (string $method, string $url, array $given) use (&$options): MockResponse {
            $options = $given;

            return new MockResponse();
        }), self::SECRET, new NativeClock())->notify($form->submit(Forms::values(), new DateTimeImmutable()));
        self::assertSame(10.0, $options['timeout'] ?? null);
        self::assertEqualsWithDelta(time(), (int) $this->headers($options['headers'] ?? [])['webhook-timestamp'], 5);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function privateAddresses(): iterable
    {
        yield 'a private network' => ['10.0.0.5'];
        yield 'a private network in an IPv4-mapped IPv6 address' => ['::ffff:10.0.0.5'];
        yield 'a private network in a 6to4 IPv6 address' => ['2002:a00:5::1'];
        yield 'a private network in a Teredo IPv6 address' => ['2001:0:4136:e378:8000:63bf:f5ff:fffa'];
        yield 'a private network in a NAT64 IPv6 address' => ['64:ff9b::a00:5'];
    }

    /**
     * The client the bundle gives the notifier: a host whose name resolves to a private network is refused, here pinned
     * to the address so that no lookup decides it.
     */
    #[DataProvider('privateAddresses')]
    public function testAWebhookHostThatResolvesToAPrivateNetworkIsNotPostedTo(string $ip): void
    {
        $form = Forms::registration();
        $form->notify([], 'https://hooks.example.com/forms');

        $guarded = new NoPrivateNetworkHttpClient(new MockHttpClient(static fn (): MockResponse => self::fail('Something was posted.')));
        $client = ScopingHttpClient::forBaseUri($guarded, 'https://hooks.example.com/', ['resolve' => ['hooks.example.com' => $ip]]);

        $this->expectException(NotificationFailed::class);
        new WebhookNotifier($client, self::SECRET, new NativeClock())->notify($form->submit(Forms::values(), new DateTimeImmutable()));
    }

    /**
     * @param array<string, string|bool|null> $values
     */
    private function mailed(Form $form, ?array $values = null): TemplatedEmail
    {
        $mailer = new RecordingMailer();
        new EmailNotifier($mailer, 'site@example.com')->notify($form->submit($values ?? Forms::values(), new DateTimeImmutable()));
        $email = $mailer->sent[0] ?? null;
        self::assertInstanceOf(TemplatedEmail::class, $email);

        return $email;
    }

    /**
     * @param array<Address> $addresses
     *
     * @return array<string>
     */
    private function addresses(array $addresses): array
    {
        return array_map(static fn (Address $address): string => $address->getAddress(), $addresses);
    }

    /**
     * @return array<string, string>
     */
    private function headers(mixed $given): array
    {
        $headers = [];
        self::assertIsArray($given);

        foreach ($given as $line) {
            self::assertIsString($line);
            [$name, $value] = explode(': ', $line, 2);
            $headers[strtolower($name)] = $value;
        }

        return $headers;
    }
}
