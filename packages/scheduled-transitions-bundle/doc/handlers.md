# Handlers

## A handler of the application's own

```php
final readonly class PagePublisher implements TransitionHandler
{
    public function supports(ResourceReference $resource, string $transition): bool
    {
        return 'page' === $resource->key && \in_array($transition, ['publish', 'unpublish'], true);
    }

    public function apply(ResourceReference $resource, string $transition): void
    {
        $page = $this->pages->get($resource->id) ?? throw new RuntimeException('The page is gone.');
        'publish' === $transition ? $page->publish() : $page->unpublish();
        $this->pages->save($page);
    }
}
```

With autoconfiguration, the bundle finds it.

## Through a workflow

With `symfony/workflow`, `WorkflowTransitionHandler` applies the transition as the subject's workflow does — its
guards, its events, its marking. A `SubjectResolver` the application writes finds the subject by its reference and
saves it once moved:

```php
final readonly class PageSubjects implements SubjectResolver
{
    public function resolve(ResourceReference $resource): ?object
    {
        return 'page' === $resource->key ? $this->entityManager->find(Page::class, $resource->id) : null;
    }

    public function saved(object $subject): void
    {
        $this->entityManager->flush();
    }
}
```

A transition the workflow does not allow now — a page already published — is a failed attempt.

## A content of `phil/content-bundle`

`phil/content-bundle` publishes a content in one locale through its own workflow, `phil_content_publication`,
which it adds to the registry; its subject is a `Publication`. Neither bundle requires the other: the application
writes the resolver, and picks how a reference names the locale — here, after an `@`:

```php
use Phil\ContentBundle\Application\Publication\Publication;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;

final readonly class PublicationSubjects implements SubjectResolver
{
    public function resolve(ResourceReference $resource): ?object
    {
        if ('news' !== $resource->key || !str_contains($resource->id, '@')) {
            return null;
        }

        [$id, $locale] = explode('@', $resource->id, 2);

        return Publication::of(ContentReference::of($resource->key, $id), $locale);
    }

    public function saved(object $subject): void
    {
        // The content is saved as it is published.
    }
}

// The French version of news:42 goes online on December 1st.
$scheduler->schedule(ResourceReference::of('news', '42@fr'), 'publish', new DateTimeImmutable('2026-12-01 08:00', new DateTimeZone('America/Toronto')));
```

The content's own guards apply: a publication its workflow refuses, or a locale with no draft, is a failed
attempt.
