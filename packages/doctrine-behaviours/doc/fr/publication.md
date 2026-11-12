<!-- phil-dev source-sha256=de3999ab46494b09e8c38f7061272728777881341cdb64775e0b0e6f971c7dfc -->
# Publication

| Type | Genre | Rôle |
|---|---|---|
| `Contract\Publishable` | interface | L'état dans les accesseurs propres au workflow, `isPublished()`, les dates |
| `PublishableEntity` | trait | Les colonnes `publication_state`, 32 caractères, `published_at` et `unpublished_at` |
| `PublicationWorkflow` | constantes, `definition()`, `markingStore()` | La machine à états, sous le nom `phil_publication` |
| `PublicationListener` | écouteur : `workflow.<name>.entered` | Horodate les dates, en UTC |

L'état est tenu par `symfony/workflow`, une dépendance facultative : une garde ou un écouteur de l'application peut
retenir ou suivre une publication comme toute autre transition.

| Transition | De | Vers |
|---|---|---|
| `publish` | `draft`, `unpublished` | `published` |
| `unpublish` | `published` | `unpublished` |

| En entrant dans | `published_at` | `unpublished_at` |
|---|---|---|
| `published` | mis à maintenant, sauf si l'enregistrement en a déjà une | oublié |
| `unpublished` | gardé | mis à maintenant |

La date de la première publication reste quand un enregistrement est retiré puis publié de nouveau. Un enregistrement
qui a reçu une date à l'avance, par une importation ou par un éditeur qui choisit la date affichée, la garde. Le vidage
reste l'affaire de l'application, comme pour tout workflow.

Hors de Symfony :

```php
$dispatcher->addListener('workflow.'.PublicationWorkflow::NAME.'.entered', new PublicationListener($clock));
$workflow = new StateMachine(PublicationWorkflow::definition(), PublicationWorkflow::markingStore(), $dispatcher, PublicationWorkflow::NAME);

$workflow->apply($article, PublicationWorkflow::PUBLISH);
$manager->flush();
```

## Publier à une heure fixée

Une date future est un travail pour un planificateur, pas une colonne : l'état change quand l'heure vient, par le
workflow, avec ses gardes. `phil/scheduled-transitions-bundle` le fait pour tout objet que connaît `workflow.registry`,
et atteint ce comportement par les seuls noms des transitions : aucun des deux paquets ne dépend de l'autre.
L'application écrit le `SubjectResolver` qui trouve ses enregistrements :

```php
$scheduler->schedule(ResourceReference::of('article', $article->id), 'publish', new DateTimeImmutable('2026-11-09 09:00'));
```

Un workflow à plus de places, comme une relecture avant la publication, garde ces trois noms pour que l'écouteur
horodate les dates ; le bundle laisse alors sa déclaration à l'application.
