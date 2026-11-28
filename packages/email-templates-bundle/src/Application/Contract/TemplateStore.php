<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Contract;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Application\CatalogEntry;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\VersionConflict;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDraft;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateVersion;

/**
 * Where the templates of the sites are kept, per site, key and locale: a draft the editors change, and the versions it
 * was published from, the latest of which the sites send. A published version is never changed: saving the draft
 * again starts the next version.
 *
 * Every method reads or writes exactly the locale it is given; falling back to another locale is the resolver's work.
 */
interface TemplateStore
{
    /**
     * The version the sites send in the locale, `null` while none is published.
     */
    public function published(TemplateKey $key, Site $site, Locale $locale): ?TemplateVersion;

    /**
     * The draft of the latest version, published or not, `null` when the site never saved one.
     */
    public function draft(TemplateKey $key, Site $site, Locale $locale): ?TemplateDraft;

    /**
     * One published version, `null` when the locale was never published from it.
     *
     * @param int<1, max> $version
     */
    public function version(TemplateKey $key, Site $site, Locale $locale, int $version): ?TemplateVersion;

    /**
     * The versions the locale was published from, the latest first.
     *
     * @param int<1, max> $limit
     *
     * @return list<TemplateVersion>
     */
    public function history(TemplateKey $key, Site $site, Locale $locale, int $limit = 50): array;

    /**
     * Writes the draft, in a new version when the latest one is published.
     *
     * @param int<0, max> $expected the revision the definition was edited from, `0` for a template the site never saved
     *
     * @throws VersionConflict when another revision was saved meanwhile
     * @throws InvalidTemplate when the storage cannot hold the locale
     */
    public function saveDraft(TemplateKey $key, Site $site, Locale $locale, TemplateDefinition $definition, DateTimeImmutable $savedAt, ?string $author, int $expected): TemplateDraft;

    /**
     * Publishes the draft: the sites send it from now on.
     *
     * @throws UnknownTemplate when there is no draft
     * @throws InvalidTemplate when the draft is published already, without changes
     */
    public function publish(TemplateKey $key, Site $site, Locale $locale): TemplateVersion;

    /**
     * Takes the locale offline, keeping its draft and its versions.
     *
     * @return bool whether it was published
     */
    public function unpublish(TemplateKey $key, Site $site, Locale $locale): bool;

    /**
     * Abandons the changes made since the locale was published: the draft is the published version again.
     *
     * @return bool whether there were changes to abandon
     */
    public function discardDraft(TemplateKey $key, Site $site, Locale $locale): bool;

    /**
     * The draft of each template the site saved, by key then locale.
     *
     * @return list<CatalogEntry>
     */
    public function catalog(Site $site): array;
}
