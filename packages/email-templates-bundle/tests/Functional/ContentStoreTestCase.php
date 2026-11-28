<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateStore;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\VersionConflict;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Infrastructure\Content\Entity\EmailTemplateDimension;
use Phil\EmailTemplatesBundle\Tests\Functional\App\TestKernel;
use Phil\EmailTemplatesBundle\Tests\Unit\Infrastructure\StoreTestCase;

/**
 * Every promise of a store on the contents of `phil/content-bundle`, in the test application, on the database its URL
 * names; and what only the content store does: the locales the contents serve, a row written around the bundle, a
 * second application saving over the same revision, a long body.
 */
abstract class ContentStoreTestCase extends StoreTestCase
{
    /** @var list<TestKernel> */
    private array $kernels = [];

    /**
     * The URL of the database, `null` for the SQLite file of the test application.
     */
    abstract protected function databaseUrl(): ?string;

    public function testALocaleTheContentsDoNotServeIsRefused(): void
    {
        $store = $this->store();

        try {
            $store->saveDraft(self::key(), self::site(), Locale::fromString('de'), self::definition('De'), new DateTimeImmutable('@0'), null, 0);
            self::fail('A locale phil_content.locales does not serve is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertStringStartsWith('The locale "de" is not one phil_content.locales serves: declare it there before the sites write templates in it.', $invalidTemplate->getMessage());
        }

        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('The locale "zh_Hant" is a language and, optionally, a region of two capital letters');
        $store->draft(self::key(), self::site(), Locale::fromString('zh_Hant'));
    }

    public function testALocaleNotServedPublishesNothing(): void
    {
        $store = $this->store();

        self::assertFalse($store->unpublish(self::key(), self::site(), Locale::fromString('en')));
        $store->saveDraft(self::key(), self::site(), Locale::fromString('en'), self::definition('En'), new DateTimeImmutable('@0'), null, 0);

        self::assertFalse($store->unpublish(self::key(), self::site(), Locale::fromString('de')));
        self::assertFalse($store->discardDraft(self::key(), self::site(), Locale::fromString('de')));

        $this->expectException(UnknownTemplate::class);
        $store->publish(self::key(), self::site(), Locale::fromString('de'));
    }

    public function testARowWrittenAroundTheBundleIsReadThroughTheChecks(): void
    {
        $store = $this->store();
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('One'), new DateTimeImmutable('@0'), null, 0);

        $entities = $this->entities();

        foreach ($entities->getRepository(EmailTemplateDimension::class)->findAll() as $row) {
            if ($row->dimension()->isLocalized()) {
                $row->setTemplateData(['definition' => ['subject' => 'x'], 'revision' => 0]);
            }
        }

        $entities->flush();
        $entities->clear();

        $this->expectException(InvalidTemplate::class);
        $this->expectExceptionMessageIsOrContains('The stored email template fr_CA/draft/1 holds no readable template.');
        $store->draft(self::key(), self::site(), self::locale());
    }

    public function testASecondApplicationSavingOverTheSameRevisionIsRefused(): void
    {
        $store = $this->store();
        $other = $this->boot()->getContainer()->get(TemplateStore::class);
        self::assertInstanceOf(TemplateStore::class, $other);
        $other->saveDraft(self::key(), self::site(), self::locale(), self::definition('Other'), new DateTimeImmutable('@0'), null, 0);

        try {
            $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('Mine'), new DateTimeImmutable('@0'), null, 0);
            self::fail('The second save over the same revision is refused.');
        } catch (VersionConflict) {
        }

        $other->saveDraft(self::key(), self::site(), self::locale(), self::definition('Other again'), new DateTimeImmutable('@0'), null, 1);

        $this->expectException(VersionConflict::class);
        $store->saveDraft(self::key(), self::site(), self::locale(), self::definition('Mine'), new DateTimeImmutable('@0'), null, 1);
    }

    public function testALongBodyIsKept(): void
    {
        $store = $this->store();
        $body = str_repeat('é', 120_000);
        $store->saveDraft(self::key(), self::site(), self::locale(), TemplateDefinition::create('Long', $body), new DateTimeImmutable('@0'), null, 0);
        $store->publish(self::key(), self::site(), self::locale());

        self::assertSame($body, $store->published(self::key(), self::site(), self::locale())?->definition->body);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $kernel->shutdown();
        }

        $this->kernels = [];
    }

    #[Override]
    protected function store(): TemplateStore
    {
        $kernel = $this->boot();
        $entities = $this->entities();
        $tool = new SchemaTool($entities);
        $metadata = $entities->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $store = $kernel->getContainer()->get(TemplateStore::class);
        self::assertInstanceOf(TemplateStore::class, $store);

        return $store;
    }

    private function boot(): TestKernel
    {
        $kernel = new TestKernel('test', true, $this->databaseUrl());
        $kernel->boot();
        $this->kernels[] = $kernel;

        return $kernel;
    }

    private function entities(): EntityManagerInterface
    {
        $doctrine = $this->kernels[0]->getContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $doctrine);
        $entities = $doctrine->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $entities);

        return $entities;
    }
}
