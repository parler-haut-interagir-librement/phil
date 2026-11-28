<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Application\Contract\DefaultTemplates;
use Phil\EmailTemplatesBundle\Application\Contract\SiteScope;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateStore;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateDraftDiscarded;
use Phil\EmailTemplatesBundle\Domain\Event\TemplatePublished;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateReset;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateSaved;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\VersionConflict;
use Phil\EmailTemplatesBundle\Domain\Status\PublicationState;
use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDraft;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateVersion;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Keeps the templates of the sites: saves a draft once it is checked, publishes it, abandons its changes, copies a
 * published version back into it, takes a locale offline so that the shared template or the default file is sent again,
 * and lists what each site sends. A template may only be saved under a key a default file declares, so that a site
 * never sends an email the application does not.
 *
 * Every change dispatches `TemplateSaved`, `TemplatePublished`, `TemplateDraftDiscarded` or `TemplateReset`,
 * identifiers only, which the audit trail records when `phil/audit-bundle` is installed.
 */
final readonly class EmailTemplates
{
    private const string AUTHOR = '/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D';

    public function __construct(
        private TemplateStore $store,
        private TemplateResolver $resolver,
        private TemplateValidator $validator,
        private DefaultTemplates $defaults,
        private SiteScope $sites,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Saves the draft; the sites send it once it is published.
     *
     * @param int<0, max> $expected the revision the definition was edited from; `0` for a template the site never saved
     * @param string|null $author   the identifier of who saves it, never an email address
     *
     * @throws InvalidTemplate when the template is refused, every reason listed
     * @throws VersionConflict when another revision was saved meanwhile
     */
    public function save(TemplateKey|string $key, string $locale, TemplateDefinition $definition, int $expected, ?Site $site = null, ?string $author = null): TemplateDraft
    {
        [$key, $site, $locale] = $this->scope($key, $site, $locale);
        $this->validator->validate($key, $site, $locale, $definition);
        $saved = $this->store->saveDraft($key, $site, $locale, $definition, $this->clock->now(), $this->author($author), $expected);
        $this->events?->dispatch(new TemplateSaved($key->value, $site->value, $locale->value, $saved->version, $saved->revision, $saved->author));

        return $saved;
    }

    /**
     * Publishes the draft, checked again: the sites send it from now on.
     *
     * @throws UnknownTemplate when there is no draft
     * @throws InvalidTemplate when the draft no longer passes the checks, or is published already
     */
    public function publish(TemplateKey|string $key, string $locale, ?Site $site = null, ?string $author = null): TemplateVersion
    {
        [$key, $site, $locale] = $this->scope($key, $site, $locale);
        $draft = $this->store->draft($key, $site, $locale) ?? throw UnknownTemplate::draft($key->value, $locale->value);
        $this->validator->validate($key, $site, $locale, $draft->definition);
        $published = $this->store->publish($key, $site, $locale);
        $this->events?->dispatch(new TemplatePublished($key->value, $site->value, $locale->value, $published->version, $this->author($author)));

        return $published;
    }

    /**
     * Copies a published version into the draft; the sites send it once the draft is published.
     *
     * @param int<0, max> $expected
     *
     * @throws UnknownTemplate when there is no such version
     * @throws InvalidTemplate when the version no longer passes the checks
     * @throws VersionConflict when another revision was saved meanwhile
     */
    public function revert(TemplateKey|string $key, string $locale, int $version, int $expected, ?Site $site = null, ?string $author = null): TemplateDraft
    {
        [$key, $site, $locale] = $this->scope($key, $site, $locale);
        $earlier = $version < 1 ? null : $this->store->version($key, $site, $locale, $version);

        if (!$earlier instanceof TemplateVersion) {
            throw UnknownTemplate::version($key->value, $locale->value, $version);
        }

        $this->validator->validate($key, $site, $locale, $earlier->definition);
        $saved = $this->store->saveDraft($key, $site, $locale, $earlier->definition, $this->clock->now(), $this->author($author), $expected);
        $this->events?->dispatch(new TemplateSaved($key->value, $site->value, $locale->value, $saved->version, $saved->revision, $saved->author, $version));

        return $saved;
    }

    /**
     * Abandons the changes made to the draft since it was published.
     *
     * @return bool whether there were changes to abandon
     */
    public function discard(TemplateKey|string $key, string $locale, ?Site $site = null, ?string $author = null): bool
    {
        [$key, $site, $locale] = $this->scope($key, $site, $locale);

        if (!$this->store->discardDraft($key, $site, $locale)) {
            return false;
        }

        $this->events?->dispatch(new TemplateDraftDiscarded($key->value, $site->value, $locale->value, $this->store->draft($key, $site, $locale)->version ?? 1, $this->author($author)));

        return true;
    }

    /**
     * Takes the site's template offline in the locale: it sends the shared one, or the default file, again. The draft
     * and the versions are kept, to publish again.
     *
     * @return bool whether it was published
     */
    public function reset(TemplateKey|string $key, string $locale, ?Site $site = null, ?string $author = null): bool
    {
        [$key, $site, $locale] = $this->scope($key, $site, $locale);

        if (!$this->store->unpublish($key, $site, $locale)) {
            return false;
        }

        $this->events?->dispatch(new TemplateReset($key->value, $site->value, $locale->value, $this->author($author)));

        return true;
    }

    /**
     * The template the site sends in the locale, and where it comes from.
     *
     * @throws UnknownTemplate when there is none
     */
    public function current(TemplateKey|string $key, string $locale, ?Site $site = null): ResolvedTemplate
    {
        [$key, $site, $locale] = $this->scope($key, $site, $locale);

        return $this->resolver->resolve($key, $site, $locale);
    }

    /**
     * The draft the site keeps in exactly that locale, `null` when it never saved one.
     */
    public function draft(TemplateKey|string $key, string $locale, ?Site $site = null): ?TemplateDraft
    {
        [$key, $site, $locale] = $this->scope($key, $site, $locale);

        return $this->store->draft($key, $site, $locale);
    }

    /**
     * The versions the site published, the latest first.
     *
     * @param int<1, max> $limit
     *
     * @return list<TemplateVersion>
     */
    public function history(TemplateKey|string $key, string $locale, ?Site $site = null, int $limit = 50): array
    {
        [$key, $site, $locale] = $this->scope($key, $site, $locale);

        return $this->store->history($key, $site, $locale, $limit);
    }

    /**
     * Every template a site sends or keeps a draft of, in every locale a default file, the shared scope or the site has,
     * by key then locale.
     *
     * @return list<CatalogRow>
     */
    public function catalog(?Site $site = null): array
    {
        $site ??= $this->sites->current();

        /** @var array<string, array{key: string, locale: string, origin: ?TemplateOrigin, version: ?int, savedAt: ?DateTimeImmutable, state: ?PublicationState}> $rows */
        $rows = [];

        foreach ($this->defaults->catalog() as $key => $locales) {
            foreach ($locales as $locale) {
                $rows[$key.' '.$locale] = ['key' => $key, 'locale' => $locale, 'origin' => TemplateOrigin::Default, 'version' => null, 'savedAt' => null, 'state' => null];
            }
        }

        $scopes = $site->isShared() ? [[Site::shared(), TemplateOrigin::Shared]] : [[Site::shared(), TemplateOrigin::Shared], [$site, TemplateOrigin::Site]];

        foreach ($scopes as [$scope, $origin]) {
            $own = $scope->equals($site);

            foreach ($this->store->catalog($scope) as $entry) {
                if (!$this->isDeclared($entry->key)) {
                    continue;
                }

                $id = $entry->key->value.' '.$entry->locale->value;
                $row = $rows[$id] ?? ['key' => $entry->key->value, 'locale' => $entry->locale->value, 'origin' => null, 'version' => null, 'savedAt' => null, 'state' => null];

                if (null !== $entry->liveVersion) {
                    $row['origin'] = $origin;
                    $row['version'] = $entry->liveVersion;
                }

                if ($own) {
                    $row['state'] = $entry->state;
                    $row['savedAt'] = $entry->savedAt;
                }

                $rows[$id] = $row;
            }
        }

        ksort($rows, \SORT_STRING);

        return array_values(array_map(static fn (array $row): CatalogRow => new CatalogRow($row['key'], $row['locale'], $row['origin'], $row['version'], $row['savedAt'], $row['state']), $rows));
    }

    /**
     * @return array{TemplateKey, Site, Locale}
     *
     * @throws InvalidTemplate when the key is not one a default file declares
     */
    private function scope(TemplateKey|string $key, ?Site $site, string $locale): array
    {
        $key = \is_string($key) ? TemplateKey::fromString($key) : $key;

        if (!$this->isDeclared($key)) {
            throw InvalidTemplate::because(\sprintf('No default file declares the template "%s": add one before the sites write their own.', $key->value));
        }

        return [$key, $site ?? $this->sites->current(), Locale::fromString($locale)];
    }

    private function isDeclared(TemplateKey $key): bool
    {
        return isset($this->defaults->catalog()[$key->value]);
    }

    /**
     * The author kept with a draft: an identifier, or nobody — an email address is never kept.
     */
    private function author(?string $author): ?string
    {
        return null !== $author && 1 === preg_match(self::AUTHOR, $author) ? $author : null;
    }
}
