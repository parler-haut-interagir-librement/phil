<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Infrastructure;

use Phil\LicensingBundle\Application\ExportGuard;
use Phil\LicensingBundle\Application\LicenseCompliance;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Contract\Licensable;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Infrastructure\Security\ExportVoter;
use Phil\LicensingBundle\Tests\Unit\Support\Declarations;
use Phil\LicensingBundle\Tests\Unit\Support\InMemoryDeclarations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[CoversClass(ExportVoter::class)]
final class ExportVoterTest extends TestCase
{
    public function testTheVote(): void
    {
        $declarations = new InMemoryDeclarations();
        $declarations->add(Declarations::complete());

        $incomplete = Declarations::of(LicenseCatalog::OGL, '43');
        $declarations->add($incomplete);
        $voter = new ExportVoter(new ExportGuard($declarations, new LicenseCompliance(new LicenseCatalog())));
        $token = new NullToken();
        $licensable = new class implements Licensable {
            public function licenseSubject(): SubjectReference
            {
                return SubjectReference::of('book', '42');
            }
        };

        self::assertTrue($voter->supportsAttribute('PHIL_LICENSING_EXPORT'));
        self::assertFalse($voter->supportsAttribute('EDIT'));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, SubjectReference::of('book', '42'), [ExportVoter::EXPORT]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $licensable, [ExportVoter::EXPORT]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, new stdClass(), [ExportVoter::EXPORT]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, SubjectReference::of('book', '42'), ['EDIT']));

        $vote = new Vote();
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, SubjectReference::of('book', '43'), [ExportVoter::EXPORT], $vote));
        self::assertSame(['missing_license_text', 'missing_product_identity', 'missing_designated_content', 'missing_notice'], $vote->reasons);
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, SubjectReference::of('book', '44'), [ExportVoter::EXPORT]));
    }
}
