<!-- phil-dev source-sha256=949d58dd2d18f9c07091f865c0f1d3318202d9bdbecc7c379c7f0a07f2cd8602 -->
# Gestionnaires

## Un gestionnaire propre à l'application

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

Avec la configuration automatique, le bundle le trouve.

## Par un workflow

Avec `symfony/workflow`, `WorkflowTransitionHandler` applique la transition comme le fait le workflow du sujet : ses
gardes, ses événements, son marquage. Un `SubjectResolver` qu'écrit l'application trouve le sujet par sa référence et
l'enregistre une fois déplacé :

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

Une transition que le workflow n'autorise pas en ce moment, comme une page déjà publiée, est une tentative échouée.

## Un contenu de `phil/content-bundle`

`phil/content-bundle` publie un contenu dans une locale par son propre workflow, `phil_content_publication`, qu'il
ajoute au registre ; son sujet est une `Publication`. Aucun des deux bundles n'exige l'autre : l'application écrit le
résolveur et choisit comment une référence nomme la locale, ici après un `@` :

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

Les gardes propres au contenu s'appliquent : une publication que son workflow refuse, ou une locale sans brouillon, est
une tentative échouée.
