<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Tests\Unit;

use DateTimeImmutable;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\Address;
use Phil\AccessibilityStatement\Enforcement;
use Phil\AccessibilityStatement\Evaluation;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\Exception\InvalidStatement;
use Phil\AccessibilityStatement\FeedbackContact;
use Phil\AccessibilityStatement\LocalizedText;
use Phil\AccessibilityStatement\NonConformity;
use Phil\AccessibilityStatement\NonConformityReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Address::class)]
#[CoversClass(FeedbackContact::class)]
#[CoversClass(Enforcement::class)]
#[CoversClass(Evaluation::class)]
#[CoversClass(NonConformity::class)]
#[CoversClass(InvalidStatement::class)]
final class PartsTest extends TestCase
{
    #[DataProvider('validAddresses')]
    public function testAnHttpsAddressOrAPathOfTheSiteIsKept(string $address): void
    {
        self::assertSame($address, Address::check($address, 'page'));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function validAddresses(): iterable
    {
        yield ['https://example.com/accessibility'];
        yield ['HTTPS://example.com/'];
        yield ['/contact'];
        yield ['/'];
        yield ['https://example.com/'.str_repeat('a', Address::MAX_LENGTH - 20)];
        yield ['https://bücher.example/barrierefreiheit'];
        yield ['https://example.com/a/../accessibility'];
    }

    #[DataProvider('invalidAddresses')]
    public function testAnyOtherAddressIsRefused(string $address, bool $path = true): void
    {
        try {
            Address::check($address, 'page', $path);
            self::fail('The address was accepted.');
        } catch (InvalidStatement $invalidStatement) {
            self::assertSame('address', $invalidStatement->reason);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1?: bool}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'script' => ['javascript:alert(1)'];
        yield 'http' => ['http://example.com/'];
        yield 'another host' => ['//evil.example/'];
        yield 'a backslash' => ['/\\evil.example/'];
        yield 'relative' => ['contact'];
        yield 'a space' => ['/con tact'];
        yield 'a control' => ["/contact\n"];
        yield 'delete' => ["/contact\x7F"];
        yield 'not a URL' => ['https://'];
        yield 'too long' => ['https://example.com/'.str_repeat('a', Address::MAX_LENGTH - 19)];
        yield 'a path where none is allowed' => ['/contact', false];
        yield 'user information' => ['https://example.com@evil.example/'];
        yield 'a password' => ['https://user:secret@example.com/'];
        yield 'a backslash after the scheme' => ['https://\\evil.example/'];
        yield 'an IP address' => ['https://192.168.0.1/'];
        yield 'localhost' => ['https://localhost/'];
        yield 'a single name' => ['https://intranet/'];
        yield 'an empty host' => ['https:///contact'];
    }

    public function testTheMessageOfARefusedAddressSaysWhatIsAllowed(): void
    {
        try {
            Address::check('ftp://x', 'report page');
        } catch (InvalidStatement $invalidStatement) {
            self::assertSame('The report page is an https:// address or a path of the site of at most 2048 bytes.', $invalidStatement->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The page is an https:// address of at most 2048 bytes.');
        Address::check('/x', 'page', false);
    }

    public function testAFeedbackContactGivesAtLeastOneChannel(): void
    {
        $contact = new FeedbackContact('a11y@example.com', '+1 (514) 555-0100', '/contact', LocalizedText::of('1 Main St.'), 10);

        self::assertSame('tel:+15145550100', $contact->phoneUri());
        self::assertNull(new FeedbackContact(email: 'a11y@example.com')->phoneUri());
        self::assertSame('/contact', new FeedbackContact(url: '/contact')->url);
        self::assertSame('5555', new FeedbackContact(phone: '5555')->phone);
        self::assertSame('1 Main St.', new FeedbackContact(postalAddress: LocalizedText::of('1 Main St.'))->postalAddress?->in('en'));
        self::assertSame(1, new FeedbackContact(email: 'a@example.com', responseDays: 1)->responseDays);
        self::assertSame(365, new FeedbackContact(email: 'a@example.com', responseDays: 365)->responseDays);
        $long = 'a@'.str_repeat('a', 63).'.'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 57).'.ca';
        self::assertSame($long, new FeedbackContact(email: $long)->email);
    }

    /**
     * @param array{email?: string, phone?: string, url?: string, responseDays?: int} $arguments
     */
    #[DataProvider('invalidContacts')]
    public function testAnInvalidFeedbackContactIsRefused(array $arguments, string $reason): void
    {
        try {
            new FeedbackContact(...$arguments);
            self::fail('The contact was accepted.');
        } catch (InvalidStatement $invalidStatement) {
            self::assertSame($reason, $invalidStatement->reason);
        }
    }

    /**
     * @return iterable<string, array{array{email?: string, phone?: string, url?: string, responseDays?: int}, string}>
     */
    public static function invalidContacts(): iterable
    {
        yield 'nothing' => [[], 'feedback_channel'];
        yield 'not an email' => [['email' => 'nobody'], 'feedback_email'];
        yield 'too long an email' => [['email' => 'a@'.str_repeat('a', 63).'.'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 58).'.ca'], 'feedback_email'];
        yield 'not a phone' => [['phone' => 'call us'], 'feedback_phone'];
        yield 'a short phone' => [['phone' => '55'], 'feedback_phone'];
        yield 'a long phone' => [['phone' => '+'.str_repeat('5', 33)], 'feedback_phone'];
        yield 'a phone with a line' => [['phone' => "5555\n"], 'feedback_phone'];
        yield 'a phone starting with a sign' => [['phone' => '-5555'], 'feedback_phone'];
        yield 'a bad page' => [['url' => 'javascript:x'], 'address'];
        yield 'no days' => [['email' => 'a@example.com', 'responseDays' => 0], 'feedback_response_days'];
        yield 'too many days' => [['email' => 'a@example.com', 'responseDays' => 366], 'feedback_response_days'];
    }

    public function testTheMessagesOfAContactNameTheValue(): void
    {
        $messages = [];

        foreach ([['email' => 'nobody'], ['phone' => 'call'], ['email' => 'a@example.com', 'responseDays' => 400]] as $arguments) {
            try {
                new FeedbackContact(...$arguments);
            } catch (InvalidStatement $invalidStatement) {
                $messages[] = $invalidStatement->getMessage();
            }
        }

        self::assertSame(['The feedback email is not an email address.', 'The feedback telephone is not a telephone number.', 'An answer comes within 1 to 365 days; got 400.'], $messages);
    }

    public function testAnEnforcementPageIsAnHttpsAddress(): void
    {
        $enforcement = new Enforcement(LocalizedText::of('Complain.'), LocalizedText::of('The commissioner'), 'https://example.org/complaints');
        self::assertSame('https://example.org/complaints', $enforcement->url);

        $this->expectException(InvalidStatement::class);
        new Enforcement(LocalizedText::of('Complain.'), url: '/complaints');
    }

    public function testAnEvaluationCountsItsPagesAndLinksItsReport(): void
    {
        $evaluation = new Evaluation(new DateTimeImmutable('2026-10-01'), EvaluationMethod::ThirdParty, 0, '/report.pdf');
        self::assertSame(0, $evaluation->pages);
        self::assertSame(EvaluationMethod::SelfAssessment, new Evaluation(new DateTimeImmutable())->method);
        self::assertSame(0, new Evaluation(new DateTimeImmutable())->pages);

        try {
            new Evaluation(new DateTimeImmutable(), pages: -1);
            self::fail('The pages were accepted.');
        } catch (InvalidStatement $invalidStatement) {
            self::assertSame('An evaluation covers 0 or more pages; got -1.', $invalidStatement->getMessage());
        }

        $this->expectException(InvalidStatement::class);
        new Evaluation(new DateTimeImmutable(), reportUrl: 'file:///etc/passwd');
    }

    public function testANonConformityCountsItsOccurrencesFromZero(): void
    {
        $nonConformity = new NonConformity(SuccessCriterion::ContrastMinimum);
        self::assertSame(NonConformityReason::NonCompliance, $nonConformity->reason);
        self::assertSame(0, $nonConformity->occurrences);
        self::assertFalse($nonConformity->fromAudit);

        $this->expectExceptionMessageIsOrContains('The occurrences of a non-conformity are 0 or more; got -1.');
        new NonConformity(SuccessCriterion::ContrastMinimum, occurrences: -1);
    }

    public function testTheFailedCriteriaAreUniqueInTheOrderOfTheirNumbers(): void
    {
        self::assertSame([SuccessCriterion::NonTextContent, SuccessCriterion::ContrastMinimum], NonConformity::failedCriteria([
            new NonConformity(SuccessCriterion::ContrastMinimum),
            new NonConformity(SuccessCriterion::Keyboard, NonConformityReason::OutOfScope),
            new NonConformity(SuccessCriterion::NonTextContent, NonConformityReason::DisproportionateBurden),
            new NonConformity(SuccessCriterion::ContrastMinimum),
        ]));
    }
}
