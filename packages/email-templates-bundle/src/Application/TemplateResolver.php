<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application;

use Phil\EmailTemplatesBundle\Application\Contract\DefaultTemplates;
use Phil\EmailTemplatesBundle\Application\Contract\TemplateStore;
use Phil\EmailTemplatesBundle\Domain\Exception\UnknownTemplate;
use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDraft;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateVersion;

/**
 * Finds the template a site sends in a locale. The locales are tried in turn — the one asked for, its language, the
 * default locale —, and for each, the version the site published, then the shared one, then the default file: `fr_CA`
 * shared wins over `fr` of the site, and a site that published `fr` sends it for `fr_CA` when nothing is published for
 * `fr_CA`. A draft is never sent: until it is published, the next step of the chain answers.
 */
final readonly class TemplateResolver
{
    public function __construct(
        private TemplateStore $store,
        private DefaultTemplates $defaults,
        private Locale $defaultLocale,
    ) {
    }

    /**
     * @throws UnknownTemplate when no locale of the chain has the template
     */
    public function resolve(TemplateKey $key, Site $site, Locale $locale): ResolvedTemplate
    {
        foreach ($this->chain($locale) as $candidate) {
            $found = $this->store->published($key, $site, $candidate);

            if ($found instanceof TemplateVersion) {
                return $this->fromVersion($found, $site->isShared() ? TemplateOrigin::Shared : TemplateOrigin::Site);
            }

            if (!$site->isShared()) {
                $shared = $this->store->published($key, Site::shared(), $candidate);

                if ($shared instanceof TemplateVersion) {
                    return $this->fromVersion($shared, TemplateOrigin::Shared);
                }
            }

            $default = $this->defaults->find($key, $candidate);

            if ($default instanceof TemplateDefinition) {
                return new ResolvedTemplate($key, Site::shared(), $candidate, $default, TemplateOrigin::Default);
            }
        }

        throw UnknownTemplate::named($key->value, $locale->value);
    }

    /**
     * The draft the site — or the shared scope — keeps in exactly that locale, for a preview; what the site sends when it
     * keeps none.
     *
     * @throws UnknownTemplate when there is neither
     */
    public function resolveDraft(TemplateKey $key, Site $site, Locale $locale): ResolvedTemplate
    {
        $draft = $this->store->draft($key, $site, $locale);

        if (!$draft instanceof TemplateDraft) {
            return $this->resolve($key, $site, $locale);
        }

        return new ResolvedTemplate($key, $site, $locale, $draft->definition, $site->isShared() ? TemplateOrigin::Shared : TemplateOrigin::Site, $draft->version, $draft->isPending());
    }

    /**
     * One version the site — or the shared scope — published in exactly that locale.
     *
     * @throws UnknownTemplate when there is no such version
     */
    public function resolveVersion(TemplateKey $key, Site $site, Locale $locale, int $version): ResolvedTemplate
    {
        $found = $version < 1 ? null : $this->store->version($key, $site, $locale, $version);

        if (!$found instanceof TemplateVersion) {
            throw UnknownTemplate::version($key->value, $locale->value, $version);
        }

        return $this->fromVersion($found, $site->isShared() ? TemplateOrigin::Shared : TemplateOrigin::Site);
    }

    /**
     * The locales tried, in order, each once.
     *
     * @return list<Locale>
     */
    public function chain(Locale $locale): array
    {
        $chain = [];

        foreach ([$locale, $locale->language(), $this->defaultLocale, $this->defaultLocale->language()] as $candidate) {
            $chain[$candidate->value] ??= $candidate;
        }

        return array_values($chain);
    }

    public function defaultLocale(): Locale
    {
        return $this->defaultLocale;
    }

    private function fromVersion(TemplateVersion $version, TemplateOrigin $origin): ResolvedTemplate
    {
        return new ResolvedTemplate($version->key, $version->site, $version->locale, $version->definition, $origin, $version->version);
    }
}
