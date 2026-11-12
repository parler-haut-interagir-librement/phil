<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\Publication;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ContentBundle\Application\Contract\ConversionLosses;
use Phil\ContentBundle\Application\Exception\PublicationLosesContent;
use Phil\ContentBundle\Application\Persistence\ContentTransaction;
use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Application\Routing\RouteUpdater;
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Exception\SlugTaken;
use Phil\ContentBundle\Domain\Exception\TransitionRefused;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Slug\Slug;
use Psr\Clock\ClockInterface;

/**
 * Publishes, unpublishes, and discards the draft of one locale of a root.
 *
 * - **Publishing copies the two draft rows a locale is read from** — the shared row and the localized row of
 *   the latest version — onto their live twins, after removing the live rows the locale had, so the live
 *   stage holds, for each locale, the rows of exactly one version. The draft rows are stamped with the
 *   publication date, which freezes their version, and the slug of the published row becomes the locale's
 *   current route.
 * - **The published row stores its body in UJO.** The draft keeps the format its author writes in; the live copy
 *   holds the body the `BodyConverter` makes of it — the UJO as its source, the format `ujo`, the HTML compiled from
 *   that UJO, dated with the publication. The conversion runs before anything changes, so a body that cannot be read
 *   leaves the locale as it was. **Raw HTML is kept only when the author of the draft's body held the permission**
 *   (`Body::$rawHtml`); who publishes it does not matter.
 * - **Unpublishing removes the live row of the locale.** The route stays, so the address is not given to
 *   another root, and answers 404 until the locale is published again.
 * - **Removing the draft abandons the changes made since the last publication**: the frozen draft rows of the
 *   live version — the localized row and the shared row — are copied back into the latest draft version, so the
 *   draft holds again the text its author published, in the format it was written in. A frozen row that no longer
 *   exists is replaced by its live twin, whose body is the published UJO. The shared draft row is also read by
 *   every other locale of the version: when one has a row there, the shared row is left as it is, and the
 *   removal is refused if its values differ from those it would be given back, since putting them back would
 *   discard changes pending for the other locales.
 * - A live shared row that no live localized row reads any more is removed.
 *
 * Each operation runs inside the caller's `ContentTransaction` (`ContentManager` opens it): a live row is
 * removed and its replacement added in two flushes, so the unique dimension is never held twice.
 */
final readonly class ContentPublisher
{
    public function __construct(
        private DimensionContentRepository $rows,
        private ContentRootRepository $roots,
        private ContentVersions $versions,
        private DimensionContentCopier $copier,
        private ContentWorkflow $workflow,
        private RouteUpdater $routes,
        private ContentTransaction $transaction,
        private ContentNormalizer $normalizer,
        private BodyConverter $bodies,
        private ClockInterface $clock,
        private ?ConversionLosses $losses = null,
    ) {
    }

    /**
     * @throws ContentNotFound         when the locale has no draft row
     * @throws TransitionRefused       when the locale is already published without changes
     * @throws SlugTaken               when its slug became another root's current address meanwhile
     * @throws InvalidContent          when the body of the draft cannot be converted into UJO
     * @throws PublicationLosesContent when the conversion into UJO loses part of the body and `$acceptLosses` is false
     */
    public function publish(ContentRoot $root, Locale $locale, bool $acceptLosses = false): void
    {
        $draft = $this->latestDraft($root, $locale);
        $key = $draft->dimension();

        if (!$acceptLosses) {
            $this->refuseLosses($draft);
        }

        $shared = $this->rows->find($root, $key->unlocalizedCounterpart());
        $now = $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
        $body = $this->publishedBody($draft, $now);

        $this->workflow->apply($draft, ContentWorkflow::PUBLISH);

        foreach ([$shared, $draft] as $row) {
            if ($row instanceof WorkflowCapable) {
                $row->setWorkflowPublished($now);
            }
        }

        $previous = $this->removeLive($root, $locale);
        $staleShared = $this->rows->find($root, $key->unlocalizedCounterpart()->inStage(Stage::Live));

        if ($staleShared instanceof DimensionContent) {
            $this->rows->remove($staleShared);
        }

        $this->transaction->flush();

        if ($shared instanceof DimensionContent) {
            $this->rows->add($this->copier->copy($shared, $shared->dimension()->inStage(Stage::Live)));
        }

        $live = $this->copier->copy($draft, $key->inStage(Stage::Live));

        if ($live instanceof BodyCapable) {
            $live->setBody($body);
        }

        $this->rows->add($live);

        if ($previous instanceof Version && !$previous->equals($key->version)) {
            $this->removeOrphanShared($root, $previous);
        }

        $slug = $live instanceof RoutableCapable ? $live->getSlug() : null;

        if ($slug instanceof Slug) {
            $this->routes->assign($this->roots->referenceOf($root), $locale, $slug);
        }
    }

    /**
     * @throws ContentNotFound   when the locale has no draft row
     * @throws TransitionRefused when the locale is not published
     */
    public function unpublish(ContentRoot $root, Locale $locale): void
    {
        $draft = $this->latestDraft($root, $locale);
        $this->workflow->apply($draft, ContentWorkflow::UNPUBLISH);

        $previous = $this->removeLive($root, $locale);
        $this->transaction->flush();

        if ($previous instanceof Version) {
            $this->removeOrphanShared($root, $previous);
        }
    }

    /**
     * Discards the changes made to a published locale since it was published: the frozen draft rows of the live
     * version are copied back into the latest draft version, the shared row with them, and the locale is
     * `published` again. Each frozen row that no longer exists is replaced by its live twin.
     *
     * When another locale has a row in the latest draft version, the shared draft row is theirs as well: it is
     * left as it is, and the removal is refused unless its values are already those it would be given back.
     *
     * @throws ContentNotFound   when the locale has no draft row or is not live
     * @throws TransitionRefused when the locale has no pending draft, or its shared values changed and another
     *                           locale of the version reads them
     */
    public function removeDraft(ContentRoot $root, Locale $locale): void
    {
        $draft = $this->latestDraft($root, $locale);
        $key = $draft->dimension();
        $liveVersion = $this->rows->latestLocalizedVersion($root, Stage::Live, $locale)
            ?? throw ContentNotFound::inStage(Stage::Live, $locale);
        $live = $this->rows->find($root, DimensionKey::localized($locale, Stage::Live, $liveVersion))
            ?? throw ContentNotFound::inStage(Stage::Live, $locale);
        $published = $this->rows->find($root, DimensionKey::localized($locale, Stage::Draft, $liveVersion)) ?? $live;
        $publishedShared = $this->rows->find($root, DimensionKey::unlocalized(Stage::Draft, $liveVersion))
            ?? $this->rows->find($root, DimensionKey::unlocalized(Stage::Live, $liveVersion));
        $draftShared = $this->rows->find($root, $key->unlocalizedCounterpart());
        $others = $this->otherLocales($root, $key->version, $locale);

        // Refused before the transition is applied, so a refusal leaves the row's place as it was.
        if ([] !== $others && $this->workflow->can($draft, ContentWorkflow::REMOVE_DRAFT) && !$this->sameSharedValues($root, $key->unlocalizedCounterpart(), $draftShared, $publishedShared)) {
            throw TransitionRefused::sharedValuesPending($key->toString(), $others);
        }

        $this->workflow->apply($draft, ContentWorkflow::REMOVE_DRAFT);

        $this->rows->remove($draft);
        $restoreShared = [] === $others;

        if ($restoreShared && $draftShared instanceof DimensionContent) {
            $this->rows->remove($draftShared);
        }

        $this->transaction->flush();

        if ($restoreShared && $publishedShared instanceof DimensionContent) {
            $this->rows->add($this->copier->copy($publishedShared, $key->unlocalizedCounterpart()));
        }

        $this->rows->add($this->copier->copy($published, $key));
    }

    /**
     * The body the live row of `$draft` stores: the draft's body converted into UJO dated `$publishedAt`, or `null`
     * without one.
     *
     * @throws InvalidContent
     */
    private function publishedBody(DimensionContent $draft, DateTimeImmutable $publishedAt): ?Body
    {
        $body = $draft instanceof BodyCapable ? $draft->getBody() : null;

        return $body instanceof Body ? $this->bodies->toUjo($body, $publishedAt) : null;
    }

    /**
     * Refuses a draft whose body the conversion into UJO does not keep whole, before anything changes.
     *
     * @throws PublicationLosesContent
     * @throws InvalidContent
     */
    private function refuseLosses(DimensionContent $draft): void
    {
        $body = $draft instanceof BodyCapable ? $draft->getBody() : null;
        $losses = $body instanceof Body && $this->losses instanceof ConversionLosses ? $this->losses->of($body->source, $body->format, $body->rawHtml) : [];

        if ([] !== $losses) {
            throw PublicationLosesContent::of($draft->dimension()->toString(), $losses);
        }
    }

    /**
     * The localized draft row of the latest version.
     *
     * @throws ContentNotFound
     */
    private function latestDraft(ContentRoot $root, Locale $locale): DimensionContent
    {
        $version = $this->versions->latestDraft($root) ?? throw ContentNotFound::inStage(Stage::Draft, $locale);
        $key = DimensionKey::localized($locale, Stage::Draft, $version);

        return $this->rows->find($root, $key) ?? throw ContentNotFound::forDimension($key);
    }

    /**
     * The locales other than `$locale` that have a row in the draft version `$version`.
     *
     * @return list<string>
     */
    private function otherLocales(ContentRoot $root, Version $version, Locale $locale): array
    {
        $locales = [];

        foreach ($this->rows->findAll($root, Stage::Draft, $version) as $row) {
            $other = $row->dimension()->locale;

            if ($other instanceof Locale && !$other->equals($locale)) {
                $locales[] = $other->value;
            }
        }

        return $locales;
    }

    /**
     * Whether two shared rows hold the same values, compared in their normalized form; a missing row reads as
     * an empty one, and the publication date, which a shared row only carries as the stamp of its version, is
     * left out.
     */
    private function sameSharedValues(ContentRoot $root, DimensionKey $sharedKey, ?DimensionContent $draft, ?DimensionContent $live): bool
    {
        return $this->sharedValues($draft ?? $root->createDimensionContent($sharedKey)) === $this->sharedValues($live ?? $root->createDimensionContent($sharedKey));
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedValues(DimensionContent $row): array
    {
        $values = $this->normalizer->normalize($row);
        unset($values['workflowPublished']);

        return $values;
    }

    /**
     * Removes the live localized row of the locale, and returns the version it belonged to.
     */
    private function removeLive(ContentRoot $root, Locale $locale): ?Version
    {
        $version = $this->rows->latestLocalizedVersion($root, Stage::Live, $locale);

        if (!$version instanceof Version) {
            return null;
        }

        $live = $this->rows->find($root, DimensionKey::localized($locale, Stage::Live, $version));

        if ($live instanceof DimensionContent) {
            $this->rows->remove($live);
        }

        return $version;
    }

    /**
     * Removes the live shared row of `$version` once no live localized row of that version is left.
     */
    private function removeOrphanShared(ContentRoot $root, Version $version): void
    {
        $rows = $this->rows->findAll($root, Stage::Live, $version);

        if (1 === \count($rows) && !$rows[0]->dimension()->isLocalized()) {
            $this->rows->remove($rows[0]);
        }
    }
}
