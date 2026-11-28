<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Content;

use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Exception\WriteConflict;
use Phil\ContentBundle\Application\Persistence\ContentTransaction;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\ContentBundle\Domain\Exception\TransitionRefused;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
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
use Phil\EmailTemplatesBundle\Infrastructure\Content\Entity\EmailTemplateContent;
use Phil\EmailTemplatesBundle\Infrastructure\Content\Entity\EmailTemplateDimension;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Locale as ContentLocale;
use Phil\LocaleDimensions\Version;

/**
 * The templates as contents of `phil/content-bundle`: one root per template, scope and locale
 * (`EmailTemplateContent`), and its rows in that locale by stage and version (`EmailTemplateDimension`). The draft stage
 * is the working copy, the live stage what the sites send; publishing freezes a version, and the next save forks the
 * next one, so the versions published are the history.
 *
 * A root holds one locale because the locales of a template are written and published apart: `phil/content-bundle`
 * forks every locale of a version together, and moves each published one to `draft`, so that one root for all the
 * locales would show the French template changed whenever the English one is saved.
 *
 * Writes go through `ContentManager`, each in a transaction of `ContentTransaction`: the editorial workflow, its
 * guards and its events apply to the templates as to any content. A save locks the root of the template, so that two
 * saves over the same revision cannot both pass the check.
 *
 * The locales are those `phil_content.locales` serves; one it does not serve, or a locale the content storage cannot
 * hold, is an `InvalidTemplate`.
 */
final readonly class ContentTemplateStore implements TemplateStore
{
    public function __construct(
        private EntityManagerInterface $entities,
        private ContentManager $contents,
        private ContentTransaction $transaction,
        private ContentRootRepository $roots,
        private DimensionContentRepository $rows,
    ) {
    }

    #[Override]
    public function published(TemplateKey $key, Site $site, Locale $locale): ?TemplateVersion
    {
        $at = $this->locale($locale);
        $root = $this->root($key, $site, $locale);

        if (!$root instanceof EmailTemplateContent) {
            return null;
        }

        $version = $this->rows->latestLocalizedVersion($root, Stage::Live, $at);
        $row = $version instanceof Version ? $this->rows->find($root, DimensionKey::localized($at, Stage::Live, $version)) : null;

        return $row instanceof EmailTemplateDimension ? $this->frozen($row, $key, $site, $locale) : null;
    }

    #[Override]
    public function draft(TemplateKey $key, Site $site, Locale $locale): ?TemplateDraft
    {
        $this->locale($locale);
        $root = $this->root($key, $site, $locale);

        return $root instanceof EmailTemplateContent ? $this->draftOf($root, $key, $site, $locale) : null;
    }

    #[Override]
    public function version(TemplateKey $key, Site $site, Locale $locale, int $version): ?TemplateVersion
    {
        $root = $this->root($key, $site, $locale);
        $row = $root instanceof EmailTemplateContent ? $this->rows->find($root, DimensionKey::localized($this->locale($locale), Stage::Draft, Version::of($version))) : null;

        return $row instanceof EmailTemplateDimension ? $this->frozen($row, $key, $site, $locale) : null;
    }

    #[Override]
    public function history(TemplateKey $key, Site $site, Locale $locale, int $limit = 50): array
    {
        $root = $this->root($key, $site, $locale);

        if (!$root instanceof EmailTemplateContent) {
            return [];
        }

        $history = [];

        foreach ($this->rows->findVersions($root, Stage::Draft, $this->locale($locale)) as $row) {
            $version = $row instanceof EmailTemplateDimension ? $this->frozen($row, $key, $site, $locale) : null;

            if ($version instanceof TemplateVersion) {
                $history[] = $version;
            }

            if (\count($history) >= $limit) {
                break;
            }
        }

        return $history;
    }

    #[Override]
    public function saveDraft(TemplateKey $key, Site $site, Locale $locale, TemplateDefinition $definition, DateTimeImmutable $savedAt, ?string $author, int $expected): TemplateDraft
    {
        $at = $this->locale($locale);

        try {
            return $this->transaction->run(function () use ($key, $site, $locale, $at, $definition, $savedAt, $author, $expected): TemplateDraft {
                $root = $this->root($key, $site, $locale);

                if ($root instanceof EmailTemplateContent) {
                    $this->entities->lock($root, LockMode::PESSIMISTIC_WRITE);
                } else {
                    $root = new EmailTemplateContent($key->value, $site->value, $locale->value);
                    $this->roots->add($root);
                    $this->transaction->flush();
                }

                $revision = $this->draftOf($root, $key, $site, $locale)->revision ?? 0;

                if ($revision !== $expected) {
                    throw VersionConflict::expected($key->value, $locale->value, $expected, $revision);
                }

                $payload = new TemplatePayload($definition, $revision + 1, $savedAt, $author);
                $this->contents->edit($this->reference($root), $at, ['templateData' => $payload->toData()]);

                return $this->draftOf($root, $key, $site, $locale) ?? throw UnknownTemplate::draft($key->value, $locale->value);
            });
        } catch (WriteConflict) {
            // Another request created the root of the template meanwhile: its draft is a revision this one has not seen.
            throw VersionConflict::expected($key->value, $locale->value, $expected, $expected + 1);
        } catch (InvalidDimension $invalidDimension) {
            throw $this->unserved($locale, $invalidDimension);
        }
    }

    #[Override]
    public function publish(TemplateKey $key, Site $site, Locale $locale): TemplateVersion
    {
        $root = $this->root($key, $site, $locale) ?? throw UnknownTemplate::draft($key->value, $locale->value);

        try {
            $this->contents->publish($this->reference($root), $this->locale($locale));
        } catch (ContentNotFound) {
            throw UnknownTemplate::draft($key->value, $locale->value);
        } catch (TransitionRefused $transitionRefused) {
            throw InvalidTemplate::because(\sprintf('The email template "%s" (%s) cannot be published: %s', $key->value, $locale->value, $transitionRefused->getMessage()));
        } catch (InvalidDimension $invalidDimension) {
            throw $this->unserved($locale, $invalidDimension);
        }

        return $this->published($key, $site, $locale) ?? throw UnknownTemplate::draft($key->value, $locale->value);
    }

    #[Override]
    public function unpublish(TemplateKey $key, Site $site, Locale $locale): bool
    {
        $root = $this->root($key, $site, $locale);

        if (!$root instanceof EmailTemplateContent) {
            return false;
        }

        try {
            $this->contents->unpublish($this->reference($root), $this->locale($locale));
        } catch (ContentNotFound|TransitionRefused|InvalidDimension) {
            return false;
        }

        return true;
    }

    #[Override]
    public function discardDraft(TemplateKey $key, Site $site, Locale $locale): bool
    {
        $root = $this->root($key, $site, $locale);

        if (!$root instanceof EmailTemplateContent) {
            return false;
        }

        try {
            $this->contents->removeDraft($this->reference($root), $this->locale($locale));
        } catch (ContentNotFound|TransitionRefused|InvalidDimension) {
            return false;
        }

        return true;
    }

    #[Override]
    public function catalog(Site $site): array
    {
        $entries = [];

        foreach ($this->entities->getRepository(EmailTemplateContent::class)->findBy(['site' => $site->value]) as $root) {
            $key = TemplateKey::fromString($root->templateKey());
            $draft = $this->draftOf($root, $key, $site, Locale::fromString($root->locale()));

            if ($draft instanceof TemplateDraft) {
                $entries[$key->value."\0".$draft->locale->value] = new CatalogEntry($key, $site, $draft->locale, $draft->state, $draft->version, $draft->savedAt, $draft->author, $draft->liveVersion);
            }
        }

        ksort($entries, \SORT_STRING);

        return array_values($entries);
    }

    private function root(TemplateKey $key, Site $site, Locale $locale): ?EmailTemplateContent
    {
        return $this->entities->getRepository(EmailTemplateContent::class)->findOneBy(['site' => $site->value, 'templateKey' => $key->value, 'locale' => $locale->value]);
    }

    private function reference(EmailTemplateContent $root): ContentReference
    {
        return ContentReference::of(EmailTemplateContent::RESOURCE_KEY, $root->id());
    }

    private function draftOf(EmailTemplateContent $root, TemplateKey $key, Site $site, Locale $locale): ?TemplateDraft
    {
        $at = $this->locale($locale);
        $version = $this->rows->latestLocalizedVersion($root, Stage::Draft, $at);
        $row = $version instanceof Version ? $this->rows->find($root, DimensionKey::localized($at, Stage::Draft, $version)) : null;

        if (!$row instanceof EmailTemplateDimension) {
            return null;
        }

        $payload = $this->payload($row);
        $live = $this->rows->latestLocalizedVersion($root, Stage::Live, $at);

        return new TemplateDraft($key, $site, $locale, $this->number($row), $payload->revision, $payload->definition, PublicationState::tryFrom($row->getWorkflowPlace() ?? '') ?? PublicationState::Unpublished, $payload->savedAt, $payload->author, $live instanceof Version ? max(1, $live->number) : null);
    }

    /**
     * The version a frozen draft row, or a live row, holds; `null` for a row never published.
     */
    private function frozen(EmailTemplateDimension $row, TemplateKey $key, Site $site, Locale $locale): ?TemplateVersion
    {
        $publishedAt = $row->getWorkflowPublished();

        if (!$publishedAt instanceof DateTimeImmutable) {
            return null;
        }

        $payload = $this->payload($row);

        return new TemplateVersion($key, $site, $locale, $this->number($row), $payload->definition, $payload->savedAt, $payload->author, $publishedAt);
    }

    private function payload(EmailTemplateDimension $row): TemplatePayload
    {
        return TemplatePayload::fromData($row->getTemplateData(), $row->dimension()->toString());
    }

    /**
     * @return int<1, max>
     */
    private function number(EmailTemplateDimension $row): int
    {
        return max(1, $row->dimension()->version->number);
    }

    private function locale(Locale $locale): ContentLocale
    {
        try {
            return ContentLocale::fromString($locale->value);
        } catch (InvalidLocaleDimension $invalidLocaleDimension) {
            throw InvalidTemplate::because(\sprintf('The locale "%s" is a language and, optionally, a region of two capital letters, as "fr_CA", for the templates kept as contents: %s', $locale->value, $invalidLocaleDimension->getMessage()));
        }
    }

    private function unserved(Locale $locale, InvalidDimension $previous): InvalidTemplate
    {
        return InvalidTemplate::because(\sprintf('The locale "%s" is not one phil_content.locales serves: declare it there before the sites write templates in it. %s', $locale->value, $previous->getMessage()));
    }
}
