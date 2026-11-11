<?php

declare(strict_types=1);

namespace Phil\BounceParser\Tests\Unit;

use Phil\BounceParser\Auth\AuthenticationResults;
use Phil\BounceParser\Auth\TrustEveryReport;
use Phil\BounceParser\BounceAction;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\BounceReport;
use Phil\BounceParser\DeliveryAction;
use Phil\BounceParser\FeedbackReport;
use Phil\BounceParser\FeedbackType;
use Phil\BounceParser\Limits;
use Phil\BounceParser\OriginalMessage;
use Phil\BounceParser\Reader\Addresses;
use Phil\BounceParser\Reader\AutoReplyReader;
use Phil\BounceParser\Reader\DeliveryStatusReader;
use Phil\BounceParser\Reader\FeedbackReader;
use Phil\BounceParser\Reader\PlainTextReader;
use Phil\BounceParser\Reader\Texts;
use Phil\BounceParser\Recipient;
use Phil\BounceParser\ReportFormat;
use Phil\BounceParser\Rule\Field;
use Phil\BounceParser\Rule\Rule;
use Phil\BounceParser\Rule\Rules;
use Phil\BounceParser\Rule\RuleScope;
use Phil\BounceParser\Verp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BounceParser::class)]
#[CoversClass(BounceReport::class)]
#[CoversClass(Recipient::class)]
#[CoversClass(OriginalMessage::class)]
#[CoversClass(FeedbackReport::class)]
#[CoversClass(FeedbackType::class)]
#[CoversClass(BounceAction::class)]
#[CoversClass(Addresses::class)]
#[CoversClass(AutoReplyReader::class)]
#[CoversClass(DeliveryStatusReader::class)]
#[CoversClass(FeedbackReader::class)]
#[CoversClass(PlainTextReader::class)]
#[CoversClass(Texts::class)]
final class BounceParserTest extends TestCase
{
    public function testReadsAHardBounceOfPostfix(): void
    {
        $report = new BounceParser(verp: new Verp('bounces', 'mail.example.net'))->parse($this->fixture('postfix-hard'));

        self::assertSame(ReportFormat::DeliveryStatus, $report->format);
        self::assertSame(BounceKind::Hard, $report->kind);
        self::assertSame(BounceAction::CountHard, $report->action);
        self::assertTrue($report->isRecognised());
        self::assertSame(['Ada@example.org'], $report->addresses());
        self::assertSame('m-42=ada=example.org', $report->verpTag);
        self::assertNull($report->rule);
        self::assertNull($report->feedback);
        self::assertFalse($report->authentic);
        self::assertSame('m-42@example.net', $report->original?->messageId());
        self::assertSame('2026-11', $report->original->header('X-Campaign'));
        self::assertNull($report->original->feedbackId());
        self::assertSame('Undelivered Mail Returned to Sender', $report->message->headers->get('Subject'));

        $recipient = $report->recipients[0];
        self::assertCount(1, $report->recipients);
        self::assertSame(DeliveryAction::Failed, $recipient->action);
        self::assertSame('5.1.1', $recipient->status?->toString());
        self::assertSame('Ada@example.org', $recipient->originalRecipient);
        self::assertSame('smtp; 550 5.1.1 <ada@example.org>: Recipient address rejected: User unknown', $recipient->diagnostic);
        self::assertSame(550, $recipient->smtpCode);
        self::assertSame('mx.example.org', $recipient->remoteMta);
        self::assertSame('Mon,  2 Nov 2026 10:00:01 -0500 (EST)', $recipient->lastAttempt);
    }

    public function testReadsADelayAndASuccess(): void
    {
        $report = new BounceParser()->parse($this->fixture('delayed'));

        self::assertSame(BounceKind::Soft, $report->kind);
        self::assertSame(BounceAction::CountSoft, $report->action);
        self::assertSame(['grace@example.com'], $report->addresses());
        self::assertSame(BounceKind::Delivered, $report->recipients[1]->kind);
        self::assertSame(DeliveryAction::Delivered, $report->recipients[1]->action);
        self::assertSame('m-43@example.net', $report->original?->messageId());
        self::assertNull($report->verpTag);
        self::assertNull($report->recipients[0]->diagnostic);
        self::assertNull($report->recipients[0]->smtpCode);
        self::assertNull($report->recipients[0]->remoteMta);
    }

    public function testReadsAFullMailboxAsSoft(): void
    {
        $report = new BounceParser()->parse($this->fixture('mailbox-full-base64'));

        self::assertSame(BounceKind::Soft, $report->kind);
        self::assertSame('5.2.2', $report->recipients[0]->status?->toString());
        self::assertSame(552, $report->recipients[0]->smtpCode);
        self::assertNull($report->original);
    }

    public function testReadsAnInternationalNotificationWithoutStatus(): void
    {
        $report = new BounceParser()->parse($this->fixture('global-dsn'));

        self::assertSame(ReportFormat::DeliveryStatus, $report->format);
        self::assertSame(BounceKind::Hard, $report->kind);
        self::assertSame(['élise@example.com'], $report->addresses());
        self::assertNull($report->recipients[0]->status);
        self::assertSame(553, $report->recipients[0]->smtpCode);
        self::assertSame('m-46@example.net', $report->original?->messageId());
    }

    public function testClassifiesEachRecipientOfANotification(): void
    {
        $report = new BounceParser()->parse($this->report(<<<'DSN'
            Reporting-MTA: dns; mx.example.com

            Final-Recipient: rfc822; a@example.com
            Action: relayed

            Final-Recipient: rfc822; b@example.com
            Action: expanded

            Final-Recipient: rfc822; c@example.com
            Status: 2.0.0

            Final-Recipient: rfc822; d@example.com
            Action: FAILED
            Status: 4.4.1 (connection timed out)

            Final-Recipient: rfc822; e@example.com
            Action: failed
            Diagnostic-Code: smtp; 421 try later

            Final-Recipient: rfc822; f@example.com
            Action: failed
            Diagnostic-Code: X-Unix; 5.7.1 refused by policy 1.2.3

            Final-Recipient: rfc822; g@example.com
            Status: 5.0.0

            Final-Recipient: rfc822;
            Action: failed
            DSN));

        $kinds = array_map(static fn (Recipient $recipient): array => [$recipient->address, $recipient->kind, $recipient->status?->toString(), $recipient->smtpCode], $report->recipients);

        self::assertSame([
            ['a@example.com', BounceKind::Delivered, null, null],
            ['b@example.com', BounceKind::Delivered, null, null],
            ['c@example.com', BounceKind::Delivered, '2.0.0', null],
            ['d@example.com', BounceKind::Soft, '4.4.1', null],
            ['e@example.com', BounceKind::Soft, null, 421],
            ['f@example.com', BounceKind::Soft, '5.7.1', null],
            ['g@example.com', BounceKind::Hard, '5.0.0', null],
        ], $kinds);
        self::assertSame(DeliveryAction::Failed, $report->recipients[3]->action);
        self::assertSame(BounceKind::Hard, $report->kind);
        self::assertSame(['d@example.com', 'e@example.com', 'f@example.com', 'g@example.com'], $report->addresses());
    }

    public function testListsEachAddressOnce(): void
    {
        $report = new BounceParser()->parse(self::report("Final-Recipient: rfc822; a@example.com\nAction: failed\n\nFinal-Recipient: rfc822; a@example.com\nAction: failed"));

        self::assertCount(2, $report->recipients);
        self::assertSame(['a@example.com'], $report->addresses());
    }

    public function testPrefersTheFinalRecipient(): void
    {
        $report = new BounceParser()->parse(self::report("Original-Recipient: rfc822; old@example.com\nFinal-Recipient: rfc822; new@example.com\nAction: failed\nRemote-MTA: dns;mx.example.com\nDiagnostic-Code: SMTP; 500 syntax"));

        self::assertSame(['new@example.com'], $report->addresses());
        self::assertSame('old@example.com', $report->recipients[0]->originalRecipient);
        self::assertSame('mx.example.com', $report->recipients[0]->remoteMta);
        self::assertSame(500, $report->recipients[0]->smtpCode);
        self::assertSame(BounceKind::Hard, $report->kind);
    }

    public function testReadsTheSmtpCodeOnlyAtTheStartOfTheDiagnostic(): void
    {
        $report = new BounceParser()->parse(self::report("Final-Recipient: rfc822; a@example.com\nAction: failed\nDiagnostic-Code: x-unix; error 450 later"));

        self::assertNull($report->recipients[0]->smtpCode);
        self::assertSame(BounceKind::Hard, $report->kind);
        self::assertSame(DeliveryAction::Failed, $report->recipients[0]->action);
    }

    public function testTakesTheOriginalRecipientWhenTheFinalOneIsMissing(): void
    {
        $report = new BounceParser()->parse($this->report("Original-Recipient: rfc822; a@example.com\nAction: failed\nStatus: 5.1.1"));

        self::assertSame(['a@example.com'], $report->addresses());
        self::assertSame('a@example.com', $report->recipients[0]->originalRecipient);
    }

    public function testReadsANotificationWithoutRecipientAsUnknown(): void
    {
        $report = new BounceParser()->parse($this->report('Reporting-MTA: dns; mx.example.com'));

        self::assertSame(ReportFormat::DeliveryStatus, $report->format);
        self::assertSame(BounceKind::Unknown, $report->kind);
        self::assertSame(BounceAction::Review, $report->action);
        self::assertFalse($report->isRecognised());
        self::assertSame([], $report->addresses());
    }

    public function testReadsTheWorstKindFirstWhateverTheOrder(): void
    {
        $soft = new BounceParser()->parse($this->report("Final-Recipient: rfc822; a@example.com\nAction: delivered\n\nFinal-Recipient: rfc822; b@example.com\nAction: delayed"));
        $delivered = new BounceParser()->parse($this->report("Final-Recipient: rfc822; a@example.com\nAction: delivered"));

        self::assertSame(BounceKind::Soft, $soft->kind);
        self::assertSame(BounceKind::Delivered, $delivered->kind);
        self::assertSame(BounceAction::Ignore, $delivered->action);
        self::assertSame(['a@example.com'], $delivered->addresses());
    }

    public function testSendsAnAbuseReportNobodyVouchesForToReview(): void
    {
        $report = new BounceParser()->parse($this->fixture('arf'));

        self::assertSame(ReportFormat::FeedbackReport, $report->format);
        self::assertSame(BounceKind::Complaint, $report->kind);
        self::assertSame(BounceAction::Review, $report->action);
        self::assertFalse($report->authentic);
        self::assertSame(['Grace@example.com'], $report->addresses());
        self::assertSame('m-42@example.net', $report->original?->messageId());
        self::assertSame('2026-11:news:example.net', $report->original->feedbackId());

        $feedback = $report->feedback;
        self::assertNotNull($feedback);
        self::assertSame(FeedbackType::Abuse, $feedback->type);
        self::assertSame('SomeGenerator/1.0', $feedback->userAgent);
        self::assertSame('1', $feedback->version);
        self::assertSame('bounces+m-42@example.net', $feedback->originalMailFrom);
        self::assertSame(['Grace@example.com'], $feedback->originalRcptTo);
        self::assertSame('Thu, 8 Mar 2026 14:00:00 EDT', $feedback->arrivalDate);
        self::assertSame('mail.fbl.example.com', $feedback->reportingMta);
        self::assertSame('192.0.2.1', $feedback->sourceIp);
        self::assertSame(3, $feedback->incidents);
        self::assertSame(['example.net', 'news.example.net'], $feedback->reportedDomain);
        self::assertSame(['https://example.net/unsubscribe'], $feedback->reportedUri);
        self::assertSame('mail.fbl.example.com; spf=pass smtp.mailfrom=example.net', $feedback->authenticationResults);
    }

    public function testUnsubscribesOnAnAuthenticAbuseReport(): void
    {
        $parser = new BounceParser(authenticator: new AuthenticationResults(['mail.example.net'], ['example.com']));
        $report = $parser->parse($this->fixture('arf'));

        self::assertTrue($report->authentic);
        self::assertSame(BounceAction::Unsubscribe, $report->action);
        self::assertFalse(new BounceParser(authenticator: new AuthenticationResults(['mail.example.net'], ['example.org']))->parse($this->fixture('arf'))->authentic);
    }

    public function testReadsTheFeedbackThatIsNoComplaint(): void
    {
        $trusted = new BounceParser(authenticator: new TrustEveryReport());
        $notSpam = $trusted->parse($this->feedback("Feedback-Type: not-spam\nIncidents: 0", "To: Ada <ada@example.com>\n"));
        $auth = $trusted->parse($this->feedback("Feedback-Type: auth-failure\nIncidents: 99999999999\nReceived-Date: yesterday", ''));

        self::assertSame(BounceKind::Feedback, $notSpam->kind);
        self::assertSame(BounceAction::Ignore, $notSpam->action);
        self::assertSame(['ada@example.com'], $notSpam->addresses());
        self::assertSame(1, $notSpam->feedback?->incidents);
        self::assertSame(BounceKind::Feedback, $auth->kind);
        self::assertSame(BounceAction::Review, $auth->action);
        self::assertSame([], $auth->addresses());
        self::assertSame(1, $auth->feedback?->incidents);
        self::assertSame('yesterday', $auth->feedback->arrivalDate);
        self::assertSame(FeedbackType::Other, $trusted->parse($this->feedback('Feedback-Type: opt-out', ''))->feedback?->type);
        self::assertSame(BounceAction::Unsubscribe, $trusted->parse($this->feedback('Feedback-Type: fraud', ''))->action);
        self::assertSame(12, $trusted->parse($this->feedback('Incidents: 12', ''))->feedback?->incidents);
        self::assertSame(1, $trusted->parse($this->feedback('Incidents: x2', ''))->feedback?->incidents);
        self::assertNull($trusted->parse($this->feedback('Feedback-Type: abuse', ''))->feedback?->reportingMta);
        self::assertSame('mx', $trusted->parse($this->feedback('Reporting-MTA: mx', ''))->feedback?->reportingMta);
    }

    public function testReadsAnAbuseReportWithoutTheMessageItReports(): void
    {
        $report = new BounceParser()->parse("From: fbl@example.com\nContent-Type: multipart/report; report-type=feedback-report; boundary=b\n\n--b\nContent-Type: message/feedback-report\n\nFeedback-Type: abuse\nOriginal-Rcpt-To: nobody\nOriginal-Rcpt-To: <b@Example.com>\nArrival-Date: first\nReceived-Date: second\nIncidents: 1234567890\nReporting-MTA: dns;mx.example.com\n--b--\n");

        self::assertNull($report->original);
        self::assertSame(['b@example.com'], $report->feedback?->originalRcptTo);
        self::assertSame(['b@example.com'], $report->addresses());
        self::assertSame('first', $report->feedback->arrivalDate);
        self::assertSame(1, $report->feedback->incidents);
        self::assertSame('mx.example.com', $report->feedback->reportingMta);
        self::assertSame([], new BounceParser()->parse("Content-Type: multipart/report; boundary=b\n\n--b\nContent-Type: message/feedback-report\n\nFeedback-Type: abuse\n--b--")->addresses());
    }

    public function testGivesTheDefaultsOfAReport(): void
    {
        $feedback = new FeedbackReport(FeedbackType::Abuse);

        self::assertSame(1, $feedback->incidents);
        self::assertSame(FeedbackType::Abuse, FeedbackType::read(' ABUSE '));
        self::assertSame(FeedbackType::Other, FeedbackType::read(null));
    }

    public function testReadsABounceWrittenAsTextByExim(): void
    {
        $report = new BounceParser(verp: new Verp('bounces', 'example.net'))->parse($this->fixture('exim-plain'));

        self::assertSame(ReportFormat::PlainText, $report->format);
        self::assertSame(BounceKind::Hard, $report->kind);
        self::assertSame(['linus@example.org'], $report->addresses());
        self::assertSame(550, $report->recipients[0]->smtpCode);
        self::assertNull($report->recipients[0]->status);
        self::assertSame(DeliveryAction::Failed, $report->recipients[0]->action);
        self::assertSame('t9', $report->verpTag);
    }

    public function testReadsABounceWrittenAsTextByQmail(): void
    {
        $report = new BounceParser()->parse($this->fixture('qmail'));

        self::assertSame(ReportFormat::PlainText, $report->format);
        self::assertSame(BounceKind::Hard, $report->kind);
        self::assertSame(['hedy@example.com'], $report->addresses());
        self::assertSame('5.1.1', $report->recipients[0]->status?->toString());
        self::assertNull($report->recipients[0]->smtpCode);
    }

    public function testKnowsABounceByItsSubject(): void
    {
        $report = new BounceParser()->parse("From: robot@mx.example.com\nSubject: Undeliverable: Hello\n\nDelivery to ada@example.com failed: 452 4.2.2 over quota\n");

        self::assertSame(ReportFormat::PlainText, $report->format);
        self::assertSame(BounceKind::Soft, $report->kind);
        self::assertSame(452, $report->recipients[0]->smtpCode);
        self::assertSame(ReportFormat::PlainText, new BounceParser()->parse("Return-Path: <>\n\nfor ada@example.com\n")->format);
        self::assertSame(ReportFormat::PlainText, new BounceParser()->parse("From: a@example.com\nSubject: =?utf-8?q?=C3=89CHEC_de_la_remise?=\n\nb@example.com\n")->format);
        self::assertSame(ReportFormat::Unrecognised, new BounceParser()->parse("From: notpostmaster@example.com\nSubject: Hello\n\nb@example.com\n")->format);

        $line = new BounceParser()->parse("From: MAILER-DAEMON@mx.example.com\n\nDelivery to ada@example.com failed\n451 try again later\n");
        self::assertSame(451, $line->recipients[0]->smtpCode);
        self::assertSame(BounceKind::Soft, $line->kind);

        $skipped = new BounceParser()->parse("From: MAILER-DAEMON@mx.example.com\n\nFrom postmaster@mx.example.com about @example.com, x@ and ada@Example.COM: 550\n");
        self::assertSame(['ada@example.com'], $skipped->addresses());
        self::assertSame(ReportFormat::Unrecognised, new BounceParser()->parse("Return-Path: <x@example.com>\nSubject: About undeliverable mail\n\nada@example.com 550 5.1.1\n")->format);
    }

    public function testLeavesABounceWithoutCodeToTheRules(): void
    {
        $unknown = new BounceParser()->parse($this->fixture('unknown-daemon'));
        $noAddress = new BounceParser()->parse("From: MAILER-DAEMON@mx.example.com\n\n550 5.1.1 unknown\n");

        self::assertSame(ReportFormat::PlainText, $unknown->format);
        self::assertSame(BounceKind::Unknown, $unknown->kind);
        self::assertSame(BounceAction::Review, $unknown->action);
        self::assertSame([], $unknown->addresses());
        self::assertSame(ReportFormat::PlainText, $noAddress->format);
        self::assertSame(BounceKind::Unknown, $noAddress->kind);
        self::assertSame([], $noAddress->recipients);
    }

    public function testReadsAnAutomaticReply(): void
    {
        $report = new BounceParser()->parse($this->fixture('out-of-office'));

        self::assertSame(ReportFormat::AutoReply, $report->format);
        self::assertSame(BounceKind::AutoReply, $report->kind);
        self::assertSame(BounceAction::Ignore, $report->action);
        self::assertSame(['katherine@example.com'], $report->addresses());
        self::assertSame('m-45@example.net', $report->original?->messageId());
    }

    public function testPrefersTheMessageAReplyReturnsToTheOneItAnswers(): void
    {
        $report = new BounceParser()->parse("From: k@example.com\nAuto-Submitted: auto-replied\nIn-Reply-To: <m-1@example.net>\nContent-Type: multipart/mixed; boundary=b\n\n--b\n\nAway.\n--b\nContent-Type: message/rfc822\n\nMessage-ID: <m-2@example.net>\n\nx\n--b--");

        self::assertSame('m-2@example.net', $report->original?->messageId());
    }

    public function testKnowsWhenAnAutomaticReplySaysNo(): void
    {
        $parser = new BounceParser();

        self::assertSame(ReportFormat::Unrecognised, $parser->parse("Auto-Submitted: NO\n\nx")->format);
        self::assertSame(ReportFormat::Unrecognised, $parser->parse("Auto-Submitted: no (a person)\n\nx")->format);
        self::assertSame(ReportFormat::AutoReply, $parser->parse("Auto-Submitted: auto-replied (out)\n\nx")->format);
    }

    public function testKnowsTheFieldsOfAutomaticReplies(): void
    {
        $parser = new BounceParser();

        foreach (['X-Autoreply: yes', 'X-Autorespond: on', 'Precedence: Auto_Reply', 'Auto-Submitted: auto-generated'] as $field) {
            $report = $parser->parse("From: x\n".$field."\n\nAway.");

            self::assertSame(ReportFormat::AutoReply, $report->format, $field);
            self::assertSame([], $report->recipients);
            self::assertNull($report->original);
        }

        self::assertSame(ReportFormat::Unrecognised, $parser->parse("Precedence: bulk\n\nNews.")->format);
    }

    public function testLeavesAMessageOfAPersonUnrecognised(): void
    {
        $report = new BounceParser()->parse($this->fixture('person'));

        self::assertSame(ReportFormat::Unrecognised, $report->format);
        self::assertSame(BounceKind::Unknown, $report->kind);
        self::assertSame(BounceAction::Review, $report->action);
        self::assertSame([], $report->recipients);
        self::assertNull($report->original);
    }

    public function testDecidesByTheFirstRuleThatMatches(): void
    {
        $rules = new Rules(
            Rule::containing('full', Field::Body, 'MAILBOX IS FULL', BounceKind::Soft),
            Rule::containing('full again', Field::Body, 'mailbox', BounceKind::Hard, BounceAction::Suppress),
        );
        $report = new BounceParser($rules)->parse("From: a@example.com\nSubject: Re\n\nYour message: the mailbox is full.\n");

        self::assertSame(ReportFormat::Unrecognised, $report->format);
        self::assertSame(BounceKind::Soft, $report->kind);
        self::assertSame(BounceAction::CountSoft, $report->action);
        self::assertSame('full', $report->rule);
        self::assertTrue($report->isRecognised());
    }

    public function testTriesRulesOnRecognisedMessagesOnlyWhenTheyAlwaysApply(): void
    {
        $unrecognisedOnly = new BounceParser(new Rules(Rule::containing('user unknown', Field::Diagnostic, 'User unknown', BounceKind::Soft)));
        $always = new BounceParser(new Rules(Rule::containing('user unknown', Field::Diagnostic, 'User unknown', BounceKind::Soft, scope: RuleScope::Always)));

        self::assertSame(BounceKind::Hard, $unrecognisedOnly->parse($this->fixture('postfix-hard'))->kind);
        self::assertSame(BounceKind::Soft, $always->parse($this->fixture('postfix-hard'))->kind);
        self::assertSame('user unknown', $always->parse($this->fixture('postfix-hard'))->rule);
        self::assertSame(['Ada@example.org'], $always->parse($this->fixture('postfix-hard'))->addresses());
    }

    public function testReadsTheFieldOfEachRule(): void
    {
        $message = "From: Robot <robot@example.com>\nX-Loop: one\nX-Loop: two\nSubject: =?utf-8?q?R=C3=A9ponse?=\n\nBody text\n";

        foreach ([
            [Field::Subject, '/^Réponse$/u', null],
            [Field::From, '/^Robot <robot@example\.com>$/', null],
            [Field::Body, "/^Body text\n\n$/", null],
            [Field::Header, "/^one\ntwo$/", 'X-Loop'],
        ] as [$field, $pattern, $header]) {
            $report = new BounceParser(new Rules(Rule::matching('r', $field, $pattern, BounceKind::AutoReply, header: $header)))->parse($message);

            self::assertSame('r', $report->rule, $field->value);
        }

        $diagnostic = new BounceParser(new Rules(Rule::matching('r', Field::Diagnostic, "/^smtp; 1\nsmtp; 3$/", BounceKind::Soft, scope: RuleScope::Always)))
            ->parse($this->report("Final-Recipient: rfc822; a@example.com\nDiagnostic-Code: smtp; 1\n\nFinal-Recipient: rfc822; b@example.com\n\nFinal-Recipient: rfc822; c@example.com\nDiagnostic-Code: smtp; 3"));

        self::assertSame('r', $diagnostic->rule);
        self::assertNull(new BounceParser(new Rules(Rule::matching('r', Field::Subject, '/./', BounceKind::Soft)))->parse("From: a\n\nx")->rule);
        self::assertNull(new BounceParser(new Rules(Rule::matching('r', Field::From, '/./', BounceKind::Soft)))->parse("Subject: a\n\nx")->rule);
    }

    public function testBoundsTheTextTheRulesRead(): void
    {
        $parser = new BounceParser(new Rules(Rule::containing('end', Field::Body, 'END', BounceKind::Soft)), limits: new Limits(maxText: 100));

        self::assertNull($parser->parse("From: a\n\n".str_repeat('x', 100).'END')->rule);
        self::assertSame('end', $parser->parse("From: a\n\n".str_repeat('x', 96).'END')->rule);
        self::assertSame('end', $parser->parse("Content-Type: multipart/mixed; boundary=b\n\n--b\n\nEN\n--b\n\nD\n--b\n\nEND\n--b--")->rule);
        self::assertSame('two', new BounceParser(new Rules(Rule::matching('two', Field::Body, "/^one\ntwo\n$/", BounceKind::Soft)))->parse("Content-Type: multipart/mixed; boundary=b\n\n--b\n\none\n--b\n\ntwo\n--b\nContent-Type: message/delivery-status\n\nAction: failed\n--b\nContent-Type: image/png\n\nPNG\n--b--")->rule);
    }

    public function testFindsTheTagOfTheReturnPathInTheEnvelopeFields(): void
    {
        $parser = new BounceParser(verp: new Verp('bounces', 'example.net'));

        self::assertSame('x1', $parser->parse("X-Original-To: bounces+x1@example.net\nTo: bounces+x2@example.net\n\n")->verpTag);
        self::assertSame('x2', $parser->parse("Envelope-To: Ada <ada@example.com>, <bounces+x2@example.net>\n\n")->verpTag);
        self::assertSame('x3', $parser->parse("Delivered-To: someone@example.com\nDelivered-To: bounces+x3@example.net\n\n")->verpTag);
        self::assertNull($parser->parse("Delivered-To: ada@example.com\n\n")->verpTag);
    }

    public function testReadsTheTagOnlyFromTheFieldsTheMailServerAdds(): void
    {
        self::assertSame(['Delivered-To', 'X-Original-To', 'Envelope-To'], BounceParser::ENVELOPE_FIELDS);
        self::assertNull(new BounceParser(verp: new Verp('bounces', 'example.net'))->parse("To: <bounces+alice=example.org@example.net>\n\n")->verpTag);

        $parser = new BounceParser(verp: new Verp('bounces', 'example.net'), envelopeFields: ['X-Envelope-To', 'To']);

        self::assertSame('y1', $parser->parse("Delivered-To: bounces+y0@example.net\nTo: bounces+y1@example.net\n\n")->verpTag);
        self::assertSame('y2', $parser->parse("To: bounces+y1@example.net\nX-Envelope-To: bounces+y2@example.net\n\n")->verpTag);
        self::assertNull(new BounceParser(verp: new Verp('bounces', 'example.net'), envelopeFields: [])->parse("Delivered-To: bounces+y0@example.net\n\n")->verpTag);
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(\dirname(__DIR__).'/Fixtures/'.$name.'.eml');
    }

    private function report(string $status): string
    {
        return "From: MAILER-DAEMON@mx.example.com\nContent-Type: multipart/report; report-type=delivery-status; boundary=b\n\n--b\nContent-Type: message/delivery-status\n\n".$status."\n--b--\n";
    }

    private function feedback(string $fields, string $headers): string
    {
        return "From: fbl@example.com\nContent-Type: multipart/report; report-type=feedback-report; boundary=b\n\n--b\nContent-Type: message/feedback-report\n\n".$fields."\n--b\nContent-Type: text/rfc822-headers\n\n".$headers."Message-ID: <x@example.net>\n--b--\n";
    }
}
