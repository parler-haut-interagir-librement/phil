<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Memory;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\EmailTemplatesBundle\Application\CatalogEntry;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateStore;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\VersionConflict;
use Phil\EmailTemplatesBundle\Domain\Status\PublicationState;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDraft;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateVersion;
use Psr\Clock\ClockInterface;

/**
 * The templates kept in memory, for tests and for an application without a database: they last as long as the process.
 * Drafts, publication and versions follow the rules of the content store: a published version is frozen, the next save
 * starts the next version, and abandoning the changes copies the published version back.
 */
final class InMemoryTemplateStore implements TemplateStore
{
    /**
     * @var array<string, array{key: TemplateKey, site: Site, locale: Locale, state: PublicationState, live: int<1, max>|null, versions: non-empty-array<int<1, max>, array{definition: TemplateDefinition, savedAt: DateTimeImmutable, author: ?string, revision: int<1, max>, publishedAt: ?DateTimeImmutable}>}>
     */
    private array $templates = [];

    public function __construct(
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    #[Override]
    public function published(TemplateKey $key, Site $site, Locale $locale): ?TemplateVersion
    {
        $template = $this->templates[$this->id($key, $site, $locale)] ?? null;

        return null === $template || null === $template['live'] ? null : $this->version($key, $site, $locale, $template['live']);
    }

    #[Override]
    public function draft(TemplateKey $key, Site $site, Locale $locale): ?TemplateDraft
    {
        $template = $this->templates[$this->id($key, $site, $locale)] ?? null;

        if (null === $template) {
            return null;
        }

        $version = max(array_keys($template['versions']));
        $saved = $template['versions'][$version];

        return new TemplateDraft($key, $site, $locale, $version, $saved['revision'], $saved['definition'], $template['state'], $saved['savedAt'], $saved['author'], $template['live']);
    }

    #[Override]
    public function version(TemplateKey $key, Site $site, Locale $locale, int $version): ?TemplateVersion
    {
        $saved = $this->templates[$this->id($key, $site, $locale)]['versions'][$version] ?? null;

        if (null === $saved || null === $saved['publishedAt']) {
            return null;
        }

        return new TemplateVersion($key, $site, $locale, $version, $saved['definition'], $saved['savedAt'], $saved['author'], $saved['publishedAt']);
    }

    #[Override]
    public function history(TemplateKey $key, Site $site, Locale $locale, int $limit = 50): array
    {
        $versions = array_keys($this->templates[$this->id($key, $site, $locale)]['versions'] ?? []);
        rsort($versions);
        $history = [];

        foreach ($versions as $version) {
            $found = $this->version($key, $site, $locale, $version);

            if ($found instanceof TemplateVersion) {
                $history[] = $found;
            }
        }

        return \array_slice($history, 0, $limit);
    }

    #[Override]
    public function saveDraft(TemplateKey $key, Site $site, Locale $locale, TemplateDefinition $definition, DateTimeImmutable $savedAt, ?string $author, int $expected): TemplateDraft
    {
        $id = $this->id($key, $site, $locale);
        $draft = $this->draft($key, $site, $locale);
        $revision = $draft->revision ?? 0;

        if ($revision !== $expected) {
            throw VersionConflict::expected($key->value, $locale->value, $expected, $revision);
        }

        $saved = ['definition' => $definition, 'savedAt' => $savedAt, 'author' => $author, 'revision' => $revision + 1, 'publishedAt' => null];

        if (!$draft instanceof TemplateDraft) {
            $this->templates[$id] = ['key' => $key, 'site' => $site, 'locale' => $locale, 'state' => PublicationState::Unpublished, 'live' => null, 'versions' => [1 => $saved]];

            return $this->draft($key, $site, $locale) ?? throw UnknownTemplate::draft($key->value, $locale->value);
        }

        $template = $this->templates[$id];
        $version = $draft->version;

        if (null !== $template['versions'][$version]['publishedAt']) {
            ++$version;
        }

        $template['versions'][$version] = $saved;

        if (PublicationState::Published === $template['state']) {
            $template['state'] = PublicationState::Pending;
        }

        $this->templates[$id] = $template;

        return $this->draft($key, $site, $locale) ?? throw UnknownTemplate::draft($key->value, $locale->value);
    }

    #[Override]
    public function publish(TemplateKey $key, Site $site, Locale $locale): TemplateVersion
    {
        $id = $this->id($key, $site, $locale);
        $draft = $this->draft($key, $site, $locale) ?? throw UnknownTemplate::draft($key->value, $locale->value);

        if (!$draft->isPending()) {
            throw InvalidTemplate::because(\sprintf('The email template "%s" (%s) is published already, without changes.', $key->value, $locale->value));
        }

        $template = $this->templates[$id];
        $published = $template['versions'][$draft->version];
        $published['publishedAt'] = $this->clock?->now() ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $template['versions'][$draft->version] = $published;
        $template['live'] = $draft->version;
        $template['state'] = PublicationState::Published;
        $this->templates[$id] = $template;

        return $this->version($key, $site, $locale, $draft->version) ?? throw UnknownTemplate::version($key->value, $locale->value, $draft->version);
    }

    #[Override]
    public function unpublish(TemplateKey $key, Site $site, Locale $locale): bool
    {
        $id = $this->id($key, $site, $locale);
        $template = $this->templates[$id] ?? null;

        if (null === $template || null === $template['live']) {
            return false;
        }

        $template['live'] = null;
        $template['state'] = PublicationState::Unpublished;
        $this->templates[$id] = $template;

        return true;
    }

    #[Override]
    public function discardDraft(TemplateKey $key, Site $site, Locale $locale): bool
    {
        $id = $this->id($key, $site, $locale);
        $template = $this->templates[$id] ?? null;

        if (null === $template || PublicationState::Pending !== $template['state'] || null === $template['live']) {
            return false;
        }

        $template['versions'][max(array_keys($template['versions']))] = $template['versions'][$template['live']];
        $template['state'] = PublicationState::Published;
        $this->templates[$id] = $template;

        return true;
    }

    #[Override]
    public function catalog(Site $site): array
    {
        $entries = [];

        foreach ($this->templates as $template) {
            if (!$template['site']->equals($site)) {
                continue;
            }

            $draft = $this->draft($template['key'], $template['site'], $template['locale']);

            if ($draft instanceof TemplateDraft) {
                $entries[$draft->key->value."\0".$draft->locale->value] = new CatalogEntry($draft->key, $draft->site, $draft->locale, $draft->state, $draft->version, $draft->savedAt, $draft->author, $draft->liveVersion);
            }
        }

        ksort($entries, \SORT_STRING);

        return array_values($entries);
    }

    private function id(TemplateKey $key, Site $site, Locale $locale): string
    {
        return $site->value."\0".$key->value."\0".$locale->value;
    }
}
