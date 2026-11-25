<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\FormsBundle\Application\HmacEmailDigester;
use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\Model\Form;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Phil\FormsBundle\Tests\Unit\Fixtures\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What an entry keeps of the person it is about: their identifier when known, the digest of the designated address.
 */
#[CoversClass(Form::class)]
#[CoversClass(Entry::class)]
#[CoversClass(HmacEmailDigester::class)]
final class SubjectTest extends TestCase
{
    public function testAnEntryKeepsWhoSentIt(): void
    {
        $form = Forms::registration();
        $digester = new HmacEmailDigester('key');

        $anonymous = $form->submit(Forms::values(), new DateTimeImmutable(), 'fr', null, $digester);
        self::assertSame([null, null], [$anonymous->subject, $anonymous->emailDigest], 'without a designated field, no digest');

        $form->identifyBy('email');
        self::assertSame('email', $form->subjectField());
        $known = $form->submit(['email' => '  Ada@Example.COM '] + Forms::values(), new DateTimeImmutable(), 'fr', 'member-7', $digester);
        self::assertSame(['member-7', hash_hmac('sha256', 'ada@example.com', hash_hkdf('sha256', 'key', 32, 'phil_forms.email_digest'))], [$known->subject, $known->emailDigest]);
        self::assertNull($form->submit(Forms::values(), new DateTimeImmutable())->emailDigest, 'without a digester, no digest');

        $optional = Form::create('lettre', 'Lettre', [Field::of('email', 'Courriel', FieldType::Email)]);
        $optional->identifyBy('email');
        self::assertNull($optional->submit([], new DateTimeImmutable(), null, null, $digester)->emailDigest, 'no address, no digest');

        $form->identifyBy(null);
        self::assertNull($form->subjectField());
    }

    public function testTheDesignatedFieldIsAnEmailFieldOfTheForm(): void
    {
        $form = Forms::registration();

        foreach (['name', 'courriel'] as $field) {
            try {
                $form->identifyBy($field);
                self::fail($field.' is not an email field of the form.');
            } catch (InvalidForm $invalidForm) {
                self::assertSame(\sprintf('The field "%s" that names the person must be an email field of the form.', $field), $invalidForm->getMessage());
            }
        }

        $form->identifyBy('email');
        $form->define([Field::of('email', 'Adresse', FieldType::Email), Field::of('name', 'Nom', FieldType::Text)]);
        self::assertSame('email', $form->subjectField());

        $this->expectException(InvalidForm::class);
        $form->define([Field::of('name', 'Nom', FieldType::Text)]);
    }

    public function testTheAddressIsNormalizedBeforeItsDigest(): void
    {
        $digester = new HmacEmailDigester('key');

        self::assertSame($digester->digest('àda@example.com'), $digester->digest(" ÀDA@Example.com\n"));
        self::assertSame(hash_hmac('sha256', 'àda@example.com', hash_hkdf('sha256', 'key', 32, 'phil_forms.email_digest')), $digester->digest(" ÀDA@Example.com\n"));
        self::assertNotSame($digester->digest('ada@example.com'), new HmacEmailDigester('other')->digest('ada@example.com'));
        self::assertNotSame(hash_hmac('sha256', 'ada@example.com', 'key'), $digester->digest('ada@example.com'));
        self::assertSame('phil_forms.email_digest', HmacEmailDigester::USAGE);

        $this->expectException(InvalidArgumentException::class);
        new HmacEmailDigester('');
    }

    #[DataProvider('identifiersKeptByTheirDigest')]
    public function testAnIdentifierThatIsNoKeyIsKeptByItsDigest(string $subject): void
    {
        $entry = Forms::registration()->submit(Forms::values(), new DateTimeImmutable(), null, $subject);

        self::assertSame('sha256:'.hash('sha256', $subject), $entry->subject);
        self::assertSame($entry->subject, Entry::subjectKey($subject));
        self::assertSame(71, \strlen($entry->subject));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function identifiersKeptByTheirDigest(): iterable
    {
        yield 'too long' => [str_repeat('a', 256)];
        yield 'a space' => ['Jean Dupont'];
        yield 'a line break' => ["member-7\n"];
        yield 'a control character' => ["member\x7F"];
    }

    public function testAnEmptyIdentifierIsNobody(): void
    {
        self::assertNull(Forms::registration()->submit(Forms::values(), new DateTimeImmutable(), null, '')->subject);
        self::assertSame('member-7', Entry::subjectKey('member-7'));
    }

    public function testASubjectOf255BytesIsKept(): void
    {
        self::assertSame(255, \strlen((string) Forms::registration()->submit(Forms::values(), new DateTimeImmutable(), null, str_repeat('a', 255))->subject));
    }
}
