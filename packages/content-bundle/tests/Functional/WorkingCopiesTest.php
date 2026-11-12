<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional;

use Phil\ContentBundle\Application\Editing\WorkingCopies;
use Phil\ContentBundle\Domain\Draft\WorkingCopy;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineWorkingCopyRepository;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The private autosave of each author and the named drafts of a content, stored by Doctrine beside the draft rows.
 */
#[CoversClass(WorkingCopies::class)]
#[CoversClass(DoctrineWorkingCopyRepository::class)]
final class WorkingCopiesTest extends FunctionalTestCase
{
    public function testAnAutosaveIsPrivateReplacedAndAppliedOnce(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture', 'excerptTitle' => 'Ouverture']);
        $copies = $this->copies();

        $copies->autosave($news, 'fr', 'alice', ['excerptTitle' => 'Ouv']);
        $copies->autosave($news, 'fr', 'alice', ['excerptTitle' => 'Ouverture du site']);
        $copies->autosave($news, 'fr', 'bob', ['excerptTitle' => 'Autre']);
        $this->entityManager()->clear();

        self::assertSame(['excerptTitle' => 'Ouverture du site'], $copies->autosaved($news, 'fr', 'alice')?->data());
        self::assertSame(['excerptTitle' => 'Autre'], $copies->autosaved($news, 'fr', 'bob')?->data());
        self::assertNull($copies->autosaved($news, 'en', 'alice'));
        self::assertSame('Ouverture', $this->contents()->draft($news, 'fr')['excerptTitle'], 'An autosave does not touch the draft.');
        self::assertSame([], $copies->named($news, 'fr'), 'An autosave is not a named draft.');

        $copies->applyAutosave($news, 'fr', 'alice');
        $this->entityManager()->clear();

        self::assertSame('Ouverture du site', $this->contents()->draft($news, 'fr')['excerptTitle']);
        self::assertNull($copies->autosaved($news, 'fr', 'alice'));
        self::assertNotNull($copies->autosaved($news, 'fr', 'bob'));

        $copies->discardAutosave($news, 'fr', 'bob');
        $copies->discardAutosave($news, 'fr', 'bob');
        self::assertNull($copies->autosaved($news, 'fr', 'bob'));
    }

    public function testANamedDraftIsSharedReplacedByNameAndStaysOnceApplied(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture', 'excerptTitle' => 'Ouverture']);
        $copies = $this->copies();

        $copies->saveNamed($news, 'fr', 'Version courte', 'alice', ['excerptTitle' => 'Court']);
        $copies->saveNamed($news, 'fr', '  Version courte ', 'bob', ['excerptTitle' => 'Plus court']);
        $copies->saveNamed($news, 'fr', 'Lancement', 'alice', ['excerptTitle' => 'Lancé']);
        $this->entityManager()->clear();

        $named = $copies->named($news, 'fr');
        self::assertSame(['Lancement', 'Version courte'], array_map(static fn (WorkingCopy $copy): ?string => $copy->name(), $named));
        self::assertSame('bob', $named[1]->author());
        self::assertSame(['excerptTitle' => 'Plus court'], $named[1]->data());
        self::assertFalse($named[1]->isAutosave());

        $copies->applyNamed($news, 'fr', 'Version courte');
        $this->entityManager()->clear();

        self::assertSame('Plus court', $this->contents()->draft($news, 'fr')['excerptTitle']);
        self::assertCount(2, $copies->named($news, 'fr'));

        $copies->deleteNamed($news, 'fr', 'Lancement');
        self::assertCount(1, $copies->named($news, 'fr'));

        $this->expectException(ContentNotFound::class);
        $this->expectExceptionMessageIs('"'.$news->toString().'" has no draft named "Lancement".');

        $copies->applyNamed($news, 'fr', 'Lancement');
    }

    public function testApplyingAMissingAutosaveIsNotFound(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture']);

        $this->expectException(ContentNotFound::class);
        $this->expectExceptionMessageIs('"alice" has no autosave of "'.$news->toString().'".');

        $this->copies()->applyAutosave($news, 'fr', 'alice');
    }

    public function testNothingIsKeptForAContentThatDoesNotExist(): void
    {
        $this->expectException(ContentNotFound::class);

        $this->copies()->autosave(ContentReference::of('news', 'nope'), 'fr', 'alice', []);
    }

    public function testNothingIsKeptInALocaleTheSiteDoesNotServe(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture']);

        $this->expectException(InvalidDimension::class);

        $this->copies()->saveNamed($news, 'de', 'Version', 'alice', []);
    }

    public function testDeletingTheContentRemovesItsWorkingCopies(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture']);
        $other = $this->contents()->create('news', 'fr', ['slug' => 'autre']);
        $this->copies()->autosave($news, 'fr', 'alice', ['excerptTitle' => 'A']);
        $this->copies()->saveNamed($news, 'fr', 'Version', 'alice', ['excerptTitle' => 'B']);
        $this->copies()->autosave($other, 'fr', 'alice', ['excerptTitle' => 'C']);

        $this->contents()->delete($news);
        $this->entityManager()->clear();

        self::assertSame(1, $this->entityManager()->getRepository(WorkingCopy::class)->count([]));
    }

    private function copies(): WorkingCopies
    {
        return $this->service(WorkingCopies::class);
    }
}
