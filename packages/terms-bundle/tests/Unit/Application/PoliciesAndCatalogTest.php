<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\TermsBundle\Application\Policies;
use Phil\TermsBundle\Application\VersionCatalog;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Domain\TextFormat;
use Phil\TermsBundle\Domain\ValueObject\Policy;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;
use Phil\TermsBundle\Tests\Unit\Fixture\InMemoryVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Policies::class)]
#[CoversClass(VersionCatalog::class)]
final class PoliciesAndCatalogTest extends TestCase
{
    public function testThePoliciesInTheirOrder(): void
    {
        $terms = new Policy('terms');
        $conduct = new Policy('conduct', false);
        $privacy = new Policy('privacy');
        $policies = new Policies($terms, $conduct, $privacy);

        self::assertSame([$terms, $conduct, $privacy], $policies->all());
        self::assertSame([$terms, $privacy], $policies->required());
        self::assertSame($conduct, $policies->get('conduct'));
        self::assertTrue($policies->has('terms'));
        self::assertFalse($policies->has('cookies'));
    }

    public function testAnUnknownPolicyIsRefused(): void
    {
        try {
            new Policies()->get('a'.str_repeat('é', 89));
            self::fail('Accepted.');
        } catch (InvalidTerms $invalidTerms) {
            self::assertSame('unknown_policy', $invalidTerms->reason);
            self::assertSame(['%key%' => 'a'.str_repeat('é', 79)], $invalidTerms->parameters);
        }
    }

    public function testTheCatalogReadsEachPolicyOncePerRequest(): void
    {
        $repository = new InMemoryVersions();
        $catalog = new VersionCatalog($repository);
        $first = $this->version(1, '2026-01-01', true);
        $minor = $this->version(2, '2026-02-01', false);
        $material = $this->version(3, '2026-03-01', true);
        $upcoming = $this->version(4, '2026-06-01', false);

        foreach ([$first, $minor, $material, $upcoming] as $version) {
            $repository->add($version);
        }

        $at = new DateTimeImmutable('2026-05-01');
        self::assertSame([$upcoming, $material, $minor, $first], $catalog->versions('terms'));
        self::assertSame($upcoming, $catalog->latest('terms'));
        self::assertSame($material, $catalog->inForce('terms', $at));
        self::assertSame($material, $catalog->required('terms', $at));
        self::assertSame($minor, $catalog->inForce('terms', new DateTimeImmutable('2026-02-15')));
        self::assertSame($first, $catalog->required('terms', new DateTimeImmutable('2026-02-15')));
        self::assertSame($upcoming, $catalog->inForce('terms', new DateTimeImmutable('2026-06-01')));
        self::assertSame($material, $catalog->latestMaterial('terms'));
        self::assertSame($minor, $catalog->find('terms', 2));
        self::assertNull($catalog->find('terms', 5));
        self::assertNull($catalog->inForce('terms', new DateTimeImmutable('2025-12-31')));
        self::assertNull($catalog->required('terms', new DateTimeImmutable('2025-12-31')));
        self::assertSame(['terms' => 1], $repository->reads);

        self::assertNull($catalog->latest('privacy'));
        self::assertNull($catalog->latestMaterial('privacy'));
        self::assertSame(['terms' => 1, 'privacy' => 1], $repository->reads);
    }

    public function testTheCatalogForgetsAPolicyOrEverything(): void
    {
        $repository = new InMemoryVersions();
        $catalog = new VersionCatalog($repository);
        $catalog->versions('terms');
        $catalog->versions('privacy');

        $catalog->forget('terms');
        $catalog->versions('terms');
        $catalog->versions('privacy');
        self::assertSame(['terms' => 2, 'privacy' => 1], $repository->reads);

        $catalog->reset();
        $catalog->versions('terms');
        $catalog->versions('privacy');
        self::assertSame(['terms' => 3, 'privacy' => 2], $repository->reads);
    }

    private function version(int $number, string $effective, bool $material): PolicyVersion
    {
        return new PolicyVersion('terms', $number, ['en' => 'v'.$number], TextFormat::Text, new DateTimeImmutable('2025-12-01'), new DateTimeImmutable($effective), $material);
    }
}
