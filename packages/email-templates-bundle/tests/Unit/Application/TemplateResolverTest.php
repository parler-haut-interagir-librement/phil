<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Application\ResolvedTemplate;
use Phil\EmailTemplatesBundle\Application\TemplateResolver;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Infrastructure\Memory\InMemoryTemplateStore;
use Phil\EmailTemplatesBundle\Infrastructure\Yaml\YamlDefaultTemplates;
use Phil\EmailTemplatesBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemplateResolver::class)]
#[CoversClass(ResolvedTemplate::class)]
final class TemplateResolverTest extends TestCase
{
    private InMemoryTemplateStore $store;

    private TemplateResolver $resolver;

    protected function setUp(): void
    {
        $this->store = new InMemoryTemplateStore();
        $this->resolver = new TemplateResolver($this->store, new YamlDefaultTemplates([Stage::DEFAULTS]), Locale::fromString('en_GB'));
    }

    public function testTheLocalesAreTriedInTurn(): void
    {
        self::assertSame(['fr_CA', 'fr', 'en_GB', 'en'], array_map(static fn (Locale $locale): string => $locale->value, $this->resolver->chain(Locale::fromString('fr_CA'))));
        self::assertSame(['en_GB', 'en'], array_map(static fn (Locale $locale): string => $locale->value, $this->resolver->chain(Locale::fromString('en_GB'))));
        self::assertSame(['en', 'en_GB'], array_map(static fn (Locale $locale): string => $locale->value, $this->resolver->chain(Locale::fromString('en'))));
        self::assertSame('en_GB', $this->resolver->defaultLocale()->value);
    }

    public function testForEachLocaleTheSiteThenTheSharedScopeThenTheDefaultFile(): void
    {
        $key = TemplateKey::fromString('welcome');
        $site = Site::fromString('main');

        $default = $this->resolver->resolve($key, $site, Locale::fromString('fr_CA'));
        self::assertSame(TemplateOrigin::Default, $default->origin);
        self::assertSame('fr', $default->locale->value);
        self::assertTrue($default->site->isShared());
        self::assertNull($default->version);

        $this->append('welcome', '', 'fr_CA', 'Shared fr_CA');
        $this->append('welcome', 'main', 'fr', 'Site fr');
        $shared = $this->resolver->resolve($key, $site, Locale::fromString('fr_CA'));
        self::assertSame(TemplateOrigin::Shared, $shared->origin);
        self::assertSame('Shared fr_CA', $shared->definition->subject);
        self::assertSame(1, $shared->version);

        self::assertSame('Site fr', $this->resolver->resolve($key, $site, Locale::fromString('fr_BE'))->definition->subject);
        self::assertSame(TemplateOrigin::Site, $this->resolver->resolve($key, $site, Locale::fromString('fr'))->origin);
        self::assertSame('Bienvenue, {{ name }}', $this->resolver->resolve($key, Site::fromString('other'), Locale::fromString('fr_BE'))->definition->subject);

        $this->append('welcome', 'main', 'fr_CA', 'Site fr_CA');
        self::assertSame('Site fr_CA', $this->resolver->resolve($key, $site, Locale::fromString('fr_CA'))->definition->subject);
        $ownShared = $this->resolver->resolve($key, Site::shared(), Locale::fromString('fr_CA'));
        self::assertSame(TemplateOrigin::Shared, $ownShared->origin);
        self::assertSame('Shared fr_CA', $ownShared->definition->subject);
    }

    public function testTheDefaultLocaleIsTheLastResort(): void
    {
        $resolved = $this->resolver->resolve(TemplateKey::fromString('plain'), Site::shared(), Locale::fromString('de'));

        self::assertSame('en', $resolved->locale->value);
    }

    public function testAnUnknownTemplate(): void
    {
        $this->expectException(UnknownTemplate::class);
        $this->expectExceptionMessageIsOrContains('No email template "nothing" exists for the locale "de", nor a default for it.');

        $this->resolver->resolve(TemplateKey::fromString('nothing'), Site::shared(), Locale::fromString('de'));
    }

    public function testAVersionInExactlyThatLocale(): void
    {
        $this->append('welcome', 'main', 'fr', 'One');
        $this->append('welcome', 'main', 'fr', 'Two');
        $this->append('welcome', '', 'fr', 'Shared');

        $first = $this->resolver->resolveVersion(TemplateKey::fromString('welcome'), Site::fromString('main'), Locale::fromString('fr'), 1);
        self::assertSame('One', $first->definition->subject);
        self::assertSame(TemplateOrigin::Site, $first->origin);
        self::assertSame(TemplateOrigin::Shared, $this->resolver->resolveVersion(TemplateKey::fromString('welcome'), Site::shared(), Locale::fromString('fr'), 1)->origin);

        $this->expectException(UnknownTemplate::class);
        $this->expectExceptionMessageIsOrContains('The email template "welcome" has no published version 1 for the locale "fr_CA".');
        $this->resolver->resolveVersion(TemplateKey::fromString('welcome'), Site::fromString('main'), Locale::fromString('fr_CA'), 1);
    }

    public function testADraftIsNotSentButIsPreviewed(): void
    {
        $key = TemplateKey::fromString('welcome');
        $site = Site::fromString('main');
        $fr = Locale::fromString('fr');
        $this->store->saveDraft($key, $site, $fr, TemplateDefinition::create('Draft', 'b'), new DateTimeImmutable('@0'), null, 0);

        self::assertSame(TemplateOrigin::Default, $this->resolver->resolve($key, $site, $fr)->origin);
        $draft = $this->resolver->resolveDraft($key, $site, $fr);
        self::assertSame('Draft', $draft->definition->subject);
        self::assertSame(TemplateOrigin::Site, $draft->origin);
        self::assertSame(1, $draft->version);
        self::assertTrue($draft->draft);
        self::assertSame($site, $draft->site);
        self::assertSame($fr, $draft->locale);

        $this->store->publish($key, $site, $fr);
        $published = $this->resolver->resolveDraft($key, $site, $fr);
        self::assertFalse($published->draft);
        self::assertSame(TemplateOrigin::Site, $this->resolver->resolve($key, $site, $fr)->origin);

        $this->store->saveDraft($key, Site::shared(), $fr, TemplateDefinition::create('Shared draft', 'b'), new DateTimeImmutable('@0'), null, 0);
        self::assertSame(TemplateOrigin::Shared, $this->resolver->resolveDraft($key, Site::shared(), $fr)->origin);

        $none = $this->resolver->resolveDraft($key, Site::fromString('other'), Locale::fromString('fr_CA'));
        self::assertSame(TemplateOrigin::Default, $none->origin);
        self::assertFalse($none->draft);
    }

    public function testAVersionBelowOneIsUnknown(): void
    {
        $this->append('welcome', 'main', 'fr', 'One');

        $this->expectException(UnknownTemplate::class);
        $this->expectExceptionMessageIsOrContains('The email template "welcome" has no published version 0 for the locale "fr".');
        $this->resolver->resolveVersion(TemplateKey::fromString('welcome'), Site::fromString('main'), Locale::fromString('fr'), 0);
    }

    /**
     * Saves the draft over its latest revision and publishes it.
     */
    private function append(string $key, string $site, string $locale, string $subject): void
    {
        $key = TemplateKey::fromString($key);
        $site = Site::fromString($site);
        $locale = Locale::fromString($locale);
        $this->store->saveDraft($key, $site, $locale, TemplateDefinition::create($subject, 'b'), new DateTimeImmutable('@0'), null, $this->store->draft($key, $site, $locale)->revision ?? 0);
        $this->store->publish($key, $site, $locale);
    }
}
