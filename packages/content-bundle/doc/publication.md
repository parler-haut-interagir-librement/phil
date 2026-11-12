# Publication and versions

Drafts reach readers by being **copied** to the live stage. The editorial workflow of each localized draft row is
a state machine of `symfony/workflow`; versions freeze what was published, and the next edit forks a new one.

## The workflow

`Application\Publication\ContentWorkflow` builds the state machine and stores the place through the row's
`workflowPlace` (method marking store, single state). The constants of the class name the places and
transitions.

| Place | Constant | Means |
|---|---|---|
| `unpublished` | `ContentWorkflow::UNPUBLISHED` | never published, or taken offline: readers get nothing in this locale. The initial place |
| `draft` | `ContentWorkflow::DRAFT` | published, with changes readers do not see yet |
| `published` | `ContentWorkflow::PUBLISHED` | readers are served exactly this draft |

| Transition | Constant | From | To | Done by |
|---|---|---|---|---|
| `publish` | `PUBLISH` | `unpublished`, `draft` | `published` | `ContentPublisher::publish()` |
| `unpublish` | `UNPUBLISH` | `published`, `draft` | `unpublished` | `ContentPublisher::unpublish()` |
| `create_draft` | `CREATE_DRAFT` | `published` | `draft` | `ContentEditor`, when an edit forks a published version |
| `remove_draft` | `REMOVE_DRAFT` | `draft` | `published` | `ContentPublisher::removeDraft()` |

A transition that is not enabled throws `TransitionRefused` and leaves the row as it was. A row class that is not
`WorkflowCapable` cannot be published (`TransitionRefused::withoutWorkflow()`).

The workflow is built by `ContentWorkflow::create(?EventDispatcherInterface)`; `ContentWorkflow::definition()`
returns its `Definition`, and `workflow()` the underlying `WorkflowInterface`. It is **not** declared under
`framework.workflows`, so no `workflow.phil_content` service exists; its name is `phil_content`
(`ContentWorkflow::NAME`).

### Guarding a transition

When the container has an `event_dispatcher` service, the usual events of `symfony/workflow` are dispatched
under the name `phil_content`: `workflow.phil_content.guard.publish`, `workflow.phil_content.completed.unpublish`,
and so on. The application guards or follows a transition with a listener:

```php
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsEventListener(event: 'workflow.phil_content.guard.publish')]
final class PublishOnlyWithATitle
{
    public function __invoke(GuardEvent $event): void
    {
        $row = $event->getSubject();

        if ($row instanceof ExcerptCapable && null === $row->getExcerptTitle()) {
            $event->setBlocked(true, 'A news item is published with a title.');
        }
    }
}
```

A blocked guard makes the transition not enabled, so `publish()` throws `TransitionRefused`. The subject is the
localized draft row.

## Publishing

`ContentPublisher::publish(ContentRoot $root, Locale $locale)`:

1. finds the localized draft row of the **latest** draft version (`ContentNotFound` when there is none) and
   applies `publish` (`TransitionRefused` when the locale is already `published` without changes);
2. stamps the draft row and its shared row with the publication date, **in UTC**, from the container's `clock`
   service — which **freezes the version**;
3. removes the live localized row the locale had and the live shared row of this version, and flushes, so the
   unique dimension is never held twice;
4. copies the shared and the localized draft rows onto fresh live rows of the same version, through the mergers,
   so every capability with a merger is copied whole; the live row's body is the draft's **converted into UJO** by
   the `BodyConverter`, dated with the publication date of step 2, with its HTML — a conversion made before step 1
   changes anything, so a body that cannot be read throws `InvalidContent` and leaves the locale as it was ([capabilities.md](capabilities.md#the-lifecycle-of-a-body));
5. removes the live shared row of the version the locale was previously published from, when no live localized
   row reads it any more;
6. makes the slug of the published row the locale's **current route** (`SlugTaken` when it became another
   root's current address meanwhile; [routing.md](routing.md)).

- **Locales are published one at a time.** The live stage holds, for each locale, the rows of the version it was
  last published from, so the live rows of two locales may belong to two versions.
- **A shared value is shared.** Publishing any locale of a version publishes the version's shared row, with the
  shared changes made while editing another locale.

### A publication that loses content is refused

Before anything changes, `publish()` asks `ConversionLosses` what the conversion of the draft's body into UJO would not
keep — an element no block holds, a link of a refused scheme. When the list is not empty, the publication is refused with
`Application\Exception\PublicationLosesContent`, a `DomainError` whose `losses` property holds the `ConversionLoss`
values and whose message names the elements, and the locale stays as it was. The refusal is the same from the console,
from PHP, from the administration and from a schedule; the editor accepts the losses explicitly:

| From | Accepts the losses with |
|---|---|
| PHP | `ContentManager::publish($reference, $locale, acceptLosses: true)` |
| The console | `phil:content:publish --accept-losses`, or a yes to its question in a terminal ([console.md](console.md)) |
| The publication workflow | the context `['accept_losses' => true]` of `apply()` |
| The administration | the « accept the losses » box under their list ([administration.md](administration.md)) |

A scheduled publication carries no acceptance: a draft that loses content fails its attempt, and the schedule reports it.

## Page events

`ContentManager` dispatches the events of `phil/publishing` on the container's `event_dispatcher`, so the sitemap, an
HTTP cache, a search engine ping or a feed hub follows what readers can see without knowing the bundle:

| Event | Dispatched |
|---|---|
| `Phil\Publishing\PagePublished` | after `publish()` returned, for the locale published |
| `Phil\Publishing\PageRetired` | after `unpublish()` returned, for the locale taken offline; after `delete()` returned, for each locale that was live, in the order of `phil_content.locales` |

| Field | Holds |
|---|---|
| `canonicalUrl` | the SEO canonical URL of the live row when it has one, else the absolute URL of the locale's current route |
| `locale` | the locale's BCP 47 tag: `fr-CA` for `fr_CA` |
| `indexable` | `false` when the live row's SEO says `noindex`, `true` otherwise |
| `ownerId` | the content reference: `news:0192f3c4-…` |

- **Read from what readers see.** A retirement is read before the change, while the live row is there; a publication
  after. A refused operation dispatches nothing.
- **After the transaction of `ContentManager` returned.** Called inside a caller's transaction, the events go out
  before the caller commits; a caller that rolls back after them must tell its listeners itself.
- **A content without an address gives no event**: a kind mounted on no route, a locale without a current route. The
  addresses come from `CanonicalAddresses`, implemented by `ContentCanonicalAddresses` over the router: an address on
  `localhost` — a console without `framework.router.default_uri` — has no canonical form outside the kernel's debug
  mode, and gives no event either.
- **Without an `event_dispatcher` service**, nothing is built.

```php
use Phil\Publishing\PagePublished;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class PingSearchEngines
{
    public function __invoke(PagePublished $event): void
    {
        if ($event->indexable) {
            $this->pings->queue($event->canonicalUrl);
        }
    }
}
```

## Unpublishing

`ContentPublisher::unpublish(ContentRoot $root, Locale $locale)` applies `unpublish` (refused unless the locale is
`published` or `draft`) and removes the live localized row of the locale, and the live shared row of its version
once no live localized row reads it. **Its route stays**, so the address is not given to another root; it answers
404 until the locale is published again.

## The publication workflow

`ContentManager::publish()` and `unpublish()` are also a workflow of their own, `phil_content_publication`
(`PublicationWorkflow`), whose subject is a content in one locale, `Publication`. Whatever applies workflows — a
scheduler, an administration — publishes through it without knowing the bundle:

```php
use Phil\ContentBundle\Application\Publication\Publication;

$publication = Publication::of($reference, 'fr');
$workflow = $registry->get($publication);   // Symfony\Component\Workflow\Registry

if ($workflow->can($publication, 'publish')) {
    $workflow->apply($publication, 'publish');
}
```

| Transition | From | To | Does |
|---|---|---|---|
| `publish` | `unpublished`, `draft` | `published` | `ContentManager::publish()` |
| `unpublish` | `published`, `draft` | `unpublished` | `ContentManager::unpublish()` |

- **The places are read, not kept.** `PublicationMarkingStore` reads the place of the locale's latest draft row,
  and storing `published` or `unpublished` is publishing or taking offline, in a transaction of its own and under
  the guards of the `phil_content` workflow, so a refusal there stops the transition.
- A locale without a draft row stands in `no_draft`, the initial place: no transition leaves it. A root that is
  gone throws `ContentNotFound`.
- With an `event_dispatcher` service, its events are dispatched under `workflow.phil_content_publication.*`: a
  guard listener on `workflow.phil_content_publication.guard.publish` holds back a scheduled publication, not a
  publication by hand.
- The workflow is the service `phil_content.publication_workflow` (`PublicationWorkflow::SERVICE`). When the
  container has `workflow.registry` — `framework.workflows` enabled —, the bundle adds the workflow to it for
  `Publication` subjects.

## Scheduled publication

`Application\Contract\PublicationSchedule` publishes or takes offline a content in one locale at a set moment:

| Method | Does |
|---|---|
| `schedule(Publication $publication, string $transition, DateTimeImmutable $at)` | plans `publish` or `unpublish` (`ScheduledPublication::PUBLISH`, `UNPUBLISH`) at `$at`; a transition already planned moves to `$at`; any other transition is an `InvalidContent` |
| `cancel(Publication $publication, ?string $transition = null): int` | cancels one transition, or both, and returns how many |
| `pending(Publication $publication): list<ScheduledPublication>` | what is planned, soonest first: each `transition` and `dueAt` |

When the kernel registers `phil/scheduled-transitions-bundle`, the bundle implements it with
`ScheduledTransitionsPublicationSchedule` over that bundle's `Scheduler`, and registers `PublicationSubjects`, the
resolver its workflow handler finds the publications by. A publication is named `news:42@fr` — the kind, the identifier
and the locale after an `@` —; the due runs apply the transition through `phil_content_publication`, under its guards,
and a refused publication is a failed attempt. Neither bundle requires the other: without it, the port has no
implementation, and an application implements it over its own scheduler or does without.

```php
use Phil\ContentBundle\Application\Publication\Publication;

$schedule->schedule(Publication::of($news, 'fr'), 'publish', new DateTimeImmutable('2026-12-01 08:00', new DateTimeZone('America/Toronto')));
```

## The dated archive

The kinds listed under `phil_content.archives` — a « now » page, a changelog — keep every publication readable at its
version. Nothing more is stored: a publication freezes the draft rows it published and stamps them with its date, and
the next edit forks a new version; the frozen rows are the archive.

`Application\Publication\ContentArchive`:

| Method | Returns |
|---|---|
| `history(ContentReference, Locale): list<ArchivedVersion>` | every version the locale was published from, with its date in UTC, the latest first |
| `read(ContentReference, Locale, int $version): array` | what readers read then, normalized: the localized draft row of the version merged over the shared row of that version, the body in its author's format with its HTML |
| `isArchived(string $resourceKey): bool` | whether the kind's archive is public |

- A version is in the archive of a locale when that locale's draft row of the version carries a publication date. A
  pending draft, or a version another locale was published from, is not (`ContentNotFound`).
- A kind not listed is refused (`InvalidContent`): its history stays the editors' business.

`Infrastructure\Http\ArchiveController` serves one version on a route of the application's, and the Twig function
`content_archive()` lists the versions ([rendering.md](rendering.md#the-archive-of-a-content)).

## Removing a draft

`ContentPublisher::removeDraft(ContentRoot $root, Locale $locale)` abandons the changes made to a published
locale since it was published: the frozen draft rows of the version the locale is live from — its localized row
and the shared row — are copied back into the latest draft version, and the locale is `published` again. The
draft holds again the text its author published, in the format it was written in.

- `ContentNotFound` when the locale has no draft row or is not live; `TransitionRefused` when the locale has no
  pending draft (it is not in `draft`).
- **The shared draft row is read by every locale of the version.** When another locale has a row in the latest
  draft version, the shared row is left as it is, and the removal is **refused** (`TransitionRefused`) if its
  values differ from those of the frozen shared row, since putting them back would discard the other locales'
  pending changes. The comparison is made on the normalized form, without `workflowPublished`. The changes are
  then published, or edited back by hand. The refusal comes before the transition, so the row's place does not
  change.
- **A frozen row that no longer exists is replaced by its live twin**, the localized and the shared row each on
  its own: history pruned, or a locale published before the UJO conversion existed. The body restored from a live
  row is the published UJO, format `ujo`.
- The restored rows carry the publication date of the version they come from: the latest draft version counts as
  frozen again, and the next edit forks the next version.

## Versions

`Application\Publication\ContentVersions`:

| Method | Does |
|---|---|
| `latestDraft(ContentRoot): ?Version` | the highest version that has a draft row |
| `isFrozen(ContentRoot, Version): bool` | whether a draft row of the version carries a publication date |
| `fork(ContentRoot, Version): list<DimensionContent>` | copies every draft row of the version to the next version, without its publication date, flushes, and returns the copies |

- **Drafts are edited in the latest version**; older versions are the record of what was published.
- **A version is frozen once one of its locales is published**: its draft rows carry a publication date, and a
  draft row with a publication date is never written again.
- **Editing a frozen version forks it**: every draft row of the version, shared and localized, is copied to
  `Version::next()`, and the edit is written there. Every localized row of the fork that was `published` moves to
  `draft` (`create_draft`), since the shared row it is read over may change in this version. Readers stay on the
  published version until the fork is published.
- **The latest version** of a stage is `DimensionContentRepository::latestVersion()`; the version a reader of one
  locale is served is `latestLocalizedVersion()`, which `LiveContentReader` reads along the fallback chain.

## Editing

`Application\Editing\ContentEditor::edit(ContentRoot $root, ContentReference $reference, Locale $locale, array $data)`
writes submitted data ([pipeline.md](pipeline.md#submitted-data)) onto the draft of one locale:

1. takes the latest draft version, or version 1 for a root without rows; forks it first when it is frozen;
2. loads the shared and the localized draft rows of that version, creating each one that is missing — a new
   localized row starts `unpublished`;
3. runs `ContentDataMapper` over them;
4. checks the slug of the localized row against the route table: it must be free in its locale, the root's own,
   a history route of another root, or the route of a root that no longer exists (`SlugTaken` otherwise).

It runs inside the caller's `ContentTransaction`; `ContentManager::edit()` opens one.

## Reading what readers see

`LiveContentReader::read(ContentRoot $root, FallbackChain $chain)` returns the live row of the first locale of the
chain that is published, in the version that locale was published from, merged over the live shared row of that
version. The merged row's `dimension()->locale` names the locale served. `ContentNotFound` when no locale of the
chain is published.

## Lifecycle example

```php
$news = $contents->create('news', 'fr', ['slug' => 'opening', 'excerptTitle' => 'Opening']);
// fr/draft/1 unpublished

$contents->publish($news, 'fr');
// fr/draft/1 published, version 1 frozen; */live/1 and fr/live/1 written, the body of fr/live/1 in UJO;
// route fr "opening" current

$contents->edit($news, 'fr', ['excerptTitle' => 'Grand opening']);
// version 1 forked: */draft/2 and fr/draft/2, fr in draft; readers still get version 1

$contents->removeDraft($news, 'fr');
// fr/draft/2 is version 1's frozen draft again, its body in the author's format, fr published

$contents->unpublish($news, 'fr');
// fr/live/1 and */live/1 removed, fr unpublished; the route stays and answers 404
```

All the operations a caller needs are on `ContentManager` ([content-manager.md](content-manager.md)), each in
its own transaction.
