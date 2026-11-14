<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\FormsBundle\Domain\Exception\InvalidEntry;
use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Tests\Unit\Fixtures\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Form::class)]
#[CoversClass(Entry::class)]
#[CoversClass(InvalidEntry::class)]
final class FormTest extends TestCase
{
    public function testAFormIsDefined(): void
    {
        $form = $this->form();

        self::assertSame([36, 'inscription', 'Inscription au tournoi', ['name', 'email', 'team', 'consent'], [], null, 30, true], [\strlen($form->id), $form->key, $form->title(), array_map(static fn (Field $field): string => $field->name, $form->fields()), $form->recipients(), $form->webhookUrl(), $form->retentionDays(), $form->isOpen()]);
        self::assertSame(365, Form::create('a', 'A', [Field::of('a', 'A', FieldType::Text)])->retentionDays());
        self::assertSame('a'.str_repeat('-', 63), Form::create('a'.str_repeat('-', 63), 'A', [Field::of('a', 'A', FieldType::Text)])->key);
        self::assertSame(str_repeat('é', 255), Form::create('0', str_repeat('é', 255), [Field::of('a', 'A', FieldType::Text)])->title());

        $form->rename(' Inscription ');
        $form->define([Field::of('email', 'Courriel', FieldType::Email, true)]);
        $form->notify(['info@example.com', 'info@example.com', 'jeux@example.com'], 'https://hooks.example.com/forms');
        $form->keepFor(3650);
        $form->close();
        self::assertSame(['Inscription', ['email'], ['info@example.com', 'jeux@example.com'], 'https://hooks.example.com/forms', 3650, false], [$form->title(), array_map(static fn (Field $field): string => $field->name, $form->fields()), $form->recipients(), $form->webhookUrl(), $form->retentionDays(), $form->isOpen()]);

        $form->open();
        $form->keepFor(1);
        $form->notify([]);
        self::assertSame([true, 1, [], null], [$form->isOpen(), $form->retentionDays(), $form->recipients(), $form->webhookUrl()]);
        $form->notify([], 'https://'.str_repeat('a', 1992));
        self::assertSame(2000, \strlen((string) $form->webhookUrl()));
        $form->notify([], 'https://'.str_repeat('é', 1992));
        self::assertSame(2000, mb_strlen((string) $form->webhookUrl()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function webhooksOnTheServersNetwork(): iterable
    {
        foreach (['https://localhost:8080/hook', 'https://admin.localhost/', 'https://127.0.0.1/x', 'https://10.0.0.5/', 'https://192.168.1.1/', 'https://172.16.0.1/', 'https://169.254.169.254/latest/meta-data', 'https://0.0.0.0/', 'https://[::1]/', 'https://[fe80::1]/', 'https://[fc00::1]/', 'https://2130706433/', 'https://user@127.0.0.1/', 'https://LOCALHOST./'] as $url) {
            yield $url => [$url];
        }
    }

    #[DataProvider('webhooksOnTheServersNetwork')]
    public function testAWebhookOnTheServersNetworkIsRefused(string $url): void
    {
        $this->expectException(InvalidForm::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" points at the server\'s own network: a webhook goes to a public host.', $url));

        Forms::registration()->notify([], $url);
    }

    public function testAWebhookOnAPublicHostIsKept(): void
    {
        $form = Forms::registration();

        foreach (['https://hooks.example.com/forms', 'https://8.8.8.8/x', 'https://[2001:4860::8888]/', 'https://example.com:8443/x?y=1#z', 'https://user:secret@hooks.example.com/', 'https://1password.example/hook', 'https://hooks.example1/', 'https://4.example/'] as $url) {
            $form->notify([], $url);
            self::assertSame($url, $form->webhookUrl());
        }
    }

    public function testADefinitionAgainstTheRulesIsRefused(): void
    {
        $form = $this->form();

        foreach ([
            [static fn (): Form => Form::create('', 'A', [Field::of('a', 'A', FieldType::Text)]), 'A form key is 1 to 64 lower-case letters, digits or "-"; "" is not.'],
            [static fn (): Form => Form::create('-a', 'A', [Field::of('a', 'A', FieldType::Text)]), 'A form key is 1 to 64 lower-case letters, digits or "-"; "-a" is not.'],
            [static fn (): Form => Form::create('A', 'A', [Field::of('a', 'A', FieldType::Text)]), 'A form key is 1 to 64 lower-case letters, digits or "-"; "A" is not.'],
            [static fn (): Form => Form::create("a\n", 'A', [Field::of('a', 'A', FieldType::Text)]), "A form key is 1 to 64 lower-case letters, digits or \"-\"; \"a\n\" is not."],
            [static fn (): Form => Form::create('a'.str_repeat('b', 64), 'A', [Field::of('a', 'A', FieldType::Text)]), 'A form key is 1 to 64 lower-case letters, digits or "-"; "a'.str_repeat('b', 64).'" is not.'],
            [static fn (): Form => Form::create('a', ' ', [Field::of('a', 'A', FieldType::Text)]), 'A form title is 1 to 255 characters.'],
            [static fn (): Form => Form::create('a', str_repeat('é', 256), [Field::of('a', 'A', FieldType::Text)]), 'A form title is 1 to 255 characters.'],
            [static fn (): Form => Form::create('a', 'A', []), 'A form has at least one field.'],
            [static fn (): Form => Form::create('a', 'A', [Field::of('a', 'A', FieldType::Text), Field::of('a', 'B', FieldType::Email)]), 'A form has one field named "a".'],
            [static fn (): Form => Form::create('a', 'A', [Field::of('a', 'A', FieldType::Text)], 0), 'Entries are kept 1 to 3650 days.'],
            [static fn (): Form => Form::create('a', 'A', [Field::of('a', 'A', FieldType::Text)], 3651), 'Entries are kept 1 to 3650 days.'],
            [static function () use ($form): Form {
                $form->notify(['info@example.com', 'info']);

                return $form;
            }, '"info" is not an email address.'],
            [static function () use ($form): Form {
                $form->notify([], 'ftp://example.com');

                return $form;
            }, '"ftp://example.com" is not an https URL.'],
            [static function () use ($form): Form {
                $form->notify([], 'https://');

                return $form;
            }, '"https://" is not an https URL.'],
            [static function () use ($form): Form {
                $form->notify([], "https://example.com\n");

                return $form;
            }, "\"https://example.com\n\" is not an https URL."],
            [static function () use ($form): Form {
                $form->notify([], 'x https://example.com');

                return $form;
            }, '"x https://example.com" is not an https URL.'],
            [static function () use ($form): Form {
                $form->notify([], 'https://'.str_repeat('a', 1993));

                return $form;
            }, '"https://'.str_repeat('a', 1993).'" is not an https URL.'],
            [static function () use ($form): Form {
                $form->notify([], 'http://hooks.example.com/forms');

                return $form;
            }, '"http://hooks.example.com/forms" is not an https URL.'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidForm $invalidForm) {
                self::assertSame($message, $invalidForm->getMessage());
            }
        }

        self::assertSame([[], null], [$form->recipients(), $form->webhookUrl()]);
    }

    public function testAnEntryIsTakenAndKeptForTheRetention(): void
    {
        $entry = $this->form()->submit(['name' => ' Ada ', 'email' => 'ada@example.com', 'consent' => true, 'other' => 'ignored'], new DateTimeImmutable('2026-11-04 08:00', new DateTimeZone('America/Toronto')), 'fr');

        self::assertSame(['name' => 'Ada', 'email' => 'ada@example.com', 'team' => null, 'consent' => true], $entry->values);
        self::assertSame([36, 'inscription', 'fr', '2026-11-04T13:00:00+00:00', '2026-12-04T13:00:00+00:00'], [\strlen($entry->id), $entry->form->key, $entry->locale, $entry->submittedAt()->format('c'), $entry->purgeAfter()->format('c')]);
        self::assertNull($this->form()->submit(['name' => 'Ada', 'email' => 'ada@example.com', 'consent' => true], new DateTimeImmutable())->locale);
    }

    public function testRefusedValuesAreAllSaid(): void
    {
        try {
            $this->form()->accept(['email' => 'ada', 'team' => 'Rouge']);
            self::fail('The values were taken.');
        } catch (InvalidEntry $invalidEntry) {
            self::assertSame(['name' => 'required', 'email' => 'email', 'team' => 'choice', 'consent' => 'required'], $invalidEntry->errors);
            self::assertSame('The entry is refused: name: required, email: email, team: choice, consent: required.', $invalidEntry->getMessage());
        }

        $closed = $this->form();
        $closed->close();
        $this->expectExceptionObject(InvalidEntry::with(['form' => 'closed']));

        $closed->accept(['name' => 'Ada', 'email' => 'ada@example.com', 'consent' => true]);
    }

    private function form(): Form
    {
        return Form::create('inscription', ' Inscription au tournoi ', [
            Field::of('name', 'Nom', FieldType::Text, true),
            Field::of('email', 'Courriel', FieldType::Email, true),
            Field::of('team', 'Équipe', FieldType::Choice, choices: ['Bleue', 'Verte']),
            Field::of('consent', 'J’accepte le règlement', FieldType::Checkbox, true),
        ], 30);
    }
}
