<?php

declare(strict_types=1);

namespace Phil\CommunityNotes\Tests\Unit\Security;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\CommunityNotes\Note;
use Phil\CommunityNotes\Security\AuthorizationQualification;
use Phil\CommunityNotes\Security\QualificationSubject;
use Phil\CommunityNotes\Tests\Unit\Fixture\RecordingChecker;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthorizationQualification::class)]
#[CoversClass(QualificationSubject::class)]
final class AuthorizationQualificationTest extends TestCase
{
    public function testTheAttributesAreAskedOnWhatTheMemberActsOn(): void
    {
        $checker = new RecordingChecker(['COMMUNITY_NOTE_WRITE']);
        $qualification = new AuthorizationQualification($checker);
        $member = ResourceReference::of('user', 'ann');
        $note = Note::write('n', ResourceReference::of('post', '1'), ResourceReference::of('user', 'zoe'), 'T', new DateTimeImmutable('2026-11-12 10:00:00 UTC'));

        self::assertTrue($qualification->mayWrite($member, ResourceReference::of('post', '2')));
        self::assertFalse($qualification->mayRate($member, $note));
        self::assertEquals([
            ['COMMUNITY_NOTE_WRITE', new QualificationSubject($member, ResourceReference::of('post', '2'), null)],
            ['COMMUNITY_NOTE_RATE', new QualificationSubject($member, ResourceReference::of('post', '1'), $note)],
        ], $checker->asked);
        self::assertSame(['COMMUNITY_NOTE_WRITE', 'COMMUNITY_NOTE_RATE'], [AuthorizationQualification::WRITE, AuthorizationQualification::RATE]);
    }

    public function testRolesMayStandForTheAttributes(): void
    {
        $qualification = new AuthorizationQualification(new RecordingChecker(['ROLE_NOTE_RATER']), 'ROLE_NOTE_WRITER', 'ROLE_NOTE_RATER');
        $note = Note::write('n', ResourceReference::of('post', '1'), ResourceReference::of('user', 'zoe'), 'T', new DateTimeImmutable('2026-11-12 10:00:00 UTC'));

        self::assertFalse($qualification->mayWrite(ResourceReference::of('user', 'ann'), ResourceReference::of('post', '1')));
        self::assertTrue($qualification->mayRate(ResourceReference::of('user', 'ann'), $note));
    }

    public function testTheAttributesAreNamed(): void
    {
        foreach ([['', 'R'], ['W', '']] as [$write, $rate]) {
            try {
                new AuthorizationQualification(new RecordingChecker([]), $write, $rate);
                self::fail('Accepted.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame('The attributes asked of Symfony Security to write and to rate are named.', $invalidArgumentException->getMessage());
            }
        }
    }
}
