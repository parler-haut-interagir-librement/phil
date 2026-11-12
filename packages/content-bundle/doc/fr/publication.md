<!-- phil-dev source-sha256=8dd7af1f4198b25d793ed47d0e7d7e6e74a27c7c6cbbf94929a82de3eec9e50b -->
# Publication et versions

Les brouillons atteignent les lecteurs en étant **copiés** vers l'étape en ligne. Le workflow éditorial de chaque
ligne de brouillon localisée est une machine à états de `symfony/workflow` ; les versions figent ce qui a été publié,
et la modification suivante crée une nouvelle version.

## Le workflow

`Application\Publication\ContentWorkflow` construit la machine à états et enregistre la place par le `workflowPlace`
de la ligne (magasin de marquage par méthode, état unique). Les constantes de la classe nomment les places et les
transitions.

| Place | Constante | Sens |
|---|---|---|
| `unpublished` | `ContentWorkflow::UNPUBLISHED` | jamais publiée, ou mise hors ligne : les lecteurs n'obtiennent rien dans cette locale. La place initiale |
| `draft` | `ContentWorkflow::DRAFT` | publiée, avec des changements que les lecteurs ne voient pas encore |
| `published` | `ContentWorkflow::PUBLISHED` | les lecteurs reçoivent exactement ce brouillon |

| Transition | Constante | De | Vers | Faite par |
|---|---|---|---|---|
| `publish` | `PUBLISH` | `unpublished`, `draft` | `published` | `ContentPublisher::publish()` |
| `unpublish` | `UNPUBLISH` | `published`, `draft` | `unpublished` | `ContentPublisher::unpublish()` |
| `create_draft` | `CREATE_DRAFT` | `published` | `draft` | `ContentEditor`, quand une modification crée une nouvelle version à partir d'une version publiée |
| `remove_draft` | `REMOVE_DRAFT` | `draft` | `published` | `ContentPublisher::removeDraft()` |

Une transition qui n'est pas permise lève `TransitionRefused` et laisse la ligne telle qu'elle était. Une classe de
ligne qui n'est pas `WorkflowCapable` ne peut pas être publiée (`TransitionRefused::withoutWorkflow()`).

Le workflow est construit par `ContentWorkflow::create(?EventDispatcherInterface)` ; `ContentWorkflow::definition()`
renvoie sa `Definition`, et `workflow()` le `WorkflowInterface` sous-jacent. Il n'est **pas** déclaré sous
`framework.workflows` : aucun service `workflow.phil_content` n'existe. Son nom est `phil_content`
(`ContentWorkflow::NAME`).

### Protéger une transition

Quand le conteneur a un service `event_dispatcher`, les événements habituels de `symfony/workflow` sont répartis sous
le nom `phil_content` : `workflow.phil_content.guard.publish`, `workflow.phil_content.completed.unpublish`, et ainsi
de suite. L'application protège ou suit une transition avec un écouteur :

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

Une protection bloquée rend la transition non permise : `publish()` lève donc `TransitionRefused`. Le sujet est la
ligne de brouillon localisée.

## Publier

`ContentPublisher::publish(ContentRoot $root, Locale $locale)` :

1. trouve la ligne de brouillon localisée de la **dernière** version de brouillon (`ContentNotFound` quand il n'y en a
   pas) et applique `publish` (`TransitionRefused` quand la locale est déjà `published` sans changements) ;
2. appose sur la ligne de brouillon et sur sa ligne partagée la date de publication, **en UTC**, tirée du service
   `clock` du conteneur — ce qui **fige la version** ;
3. retire la ligne localisée en ligne qu'avait la locale et la ligne partagée en ligne de cette version, puis vide
   vers la base, pour que la dimension unique ne soit jamais occupée deux fois ;
4. copie les lignes de brouillon partagée et localisée vers des lignes en ligne neuves de la même version, par les
   fusionneurs, pour que chaque capacité qui a un fusionneur soit copiée en entier. Le corps de la ligne en ligne est
   celui du brouillon **converti en UJO** par le `BodyConverter`, daté de la date de publication de l'étape 2, avec son
   HTML. Cette conversion se fait avant que l'étape 1 change quoi que ce soit : un corps illisible lève donc
   `InvalidContent` et laisse la locale telle qu'elle était
   ([capabilities.md](capabilities.md#le-cycle-de-vie-dun-corps)) ;
5. retire la ligne partagée en ligne de la version à partir de laquelle la locale était publiée auparavant, quand
   aucune ligne localisée en ligne ne la lit plus ;
6. fait du slug de la ligne publiée la **route actuelle** de la locale (`SlugTaken` quand il est devenu entre-temps
   l'adresse actuelle d'une autre racine ; [routing.md](routing.md)).

- **Les locales sont publiées une à la fois.** L'étape en ligne contient, pour chaque locale, les lignes de la
  version à partir de laquelle elle a été publiée en dernier : les lignes en ligne de deux locales peuvent donc
  appartenir à deux versions.
- **Une valeur partagée est partagée.** Publier n'importe quelle locale d'une version publie la ligne partagée de la
  version, avec les changements partagés faits pendant la modification d'une autre locale.

### Une publication qui perd du contenu est refusée

Avant que rien ne change, `publish()` demande à `ConversionLosses` ce que la conversion du corps du brouillon en UJO ne
garderait pas — un élément qu'aucun bloc ne contient, un lien d'un schéma refusé. Quand la liste n'est pas vide, la
publication est refusée par `Application\Exception\PublicationLosesContent`, une `DomainError` dont la propriété
`losses` contient les valeurs `ConversionLoss` et dont le message nomme les éléments, et la locale reste telle qu'elle
était. Le refus est le même depuis la console, depuis PHP, depuis l'administration et depuis une planification ; le
rédacteur accepte les pertes explicitement :

| Depuis | Accepte les pertes avec |
|---|---|
| PHP | `ContentManager::publish($reference, $locale, acceptLosses: true)` |
| La console | `phil:content:publish --accept-losses`, ou un oui à sa question dans un terminal ([console.md](console.md)) |
| Le workflow de publication | le contexte `['accept_losses' => true]` de `apply()` |
| L'administration | la case « accepter les pertes » sous leur liste ([administration.md](administration.md)) |

Une publication planifiée ne porte aucune acceptation : un brouillon qui perd du contenu échoue à sa tentative, et la
planification le signale.

## Les événements de page

`ContentManager` répartit les événements de `phil/publishing` sur l'`event_dispatcher` du conteneur, de sorte que le
plan du site, un cache HTTP, un signal aux moteurs de recherche ou un concentrateur de fils suit ce que les lecteurs
peuvent voir sans connaître le bundle :

| Événement | Réparti |
|---|---|
| `Phil\Publishing\PagePublished` | après le retour de `publish()`, pour la locale publiée |
| `Phil\Publishing\PageRetired` | après le retour de `unpublish()`, pour la locale mise hors ligne ; après le retour de `delete()`, pour chaque locale qui était en ligne, dans l'ordre de `phil_content.locales` |

| Champ | Contient |
|---|---|
| `canonicalUrl` | l'URL canonique de référencement de la ligne en ligne quand elle en a une, sinon l'URL absolue de la route actuelle de la locale |
| `locale` | l'étiquette BCP 47 de la locale : `fr-CA` pour `fr_CA` |
| `indexable` | `false` quand le référencement de la ligne en ligne dit `noindex`, `true` sinon |
| `ownerId` | la référence du contenu : `news:0192f3c4-…` |

- **Lus sur ce que voient les lecteurs.** Un retrait est lu avant le changement, tant que la ligne en ligne existe ; une
  publication, après. Une opération refusée ne répartit rien.
- **Après le retour de la transaction de `ContentManager`.** Appelés dans la transaction d'un appelant, les événements
  partent avant que l'appelant valide ; un appelant qui annule après eux doit en avertir lui-même ses écouteurs.
- **Un contenu sans adresse ne donne aucun événement** : un genre monté sur aucune route, une locale sans route
  actuelle. Les adresses viennent de `CanonicalAddresses`, implémenté par `ContentCanonicalAddresses` sur le routeur :
  une adresse sur `localhost` — une console sans `framework.router.default_uri` — n'a pas de forme canonique hors du mode
  débogage du noyau, et ne donne aucun événement non plus.
- **Sans service `event_dispatcher`**, rien n'est construit.

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

## Dépublier

`ContentPublisher::unpublish(ContentRoot $root, Locale $locale)` applique `unpublish` (refusé sauf si la locale est
`published` ou `draft`) et retire la ligne localisée en ligne de la locale, puis la ligne partagée en ligne de sa
version dès qu'aucune ligne localisée en ligne ne la lit. **Sa route reste**, pour que l'adresse ne soit pas donnée à
une autre racine ; elle répond 404 jusqu'à ce que la locale soit publiée de nouveau.

## Le workflow de publication

`ContentManager::publish()` et `unpublish()` forment aussi un workflow à part, `phil_content_publication`
(`PublicationWorkflow`), dont le sujet est un contenu dans une locale, `Publication`. Tout ce qui applique des
workflows — un planificateur, une administration — publie par lui sans connaître le bundle :

```php
use Phil\ContentBundle\Application\Publication\Publication;

$publication = Publication::of($reference, 'fr');
$workflow = $registry->get($publication);   // Symfony\Component\Workflow\Registry

if ($workflow->can($publication, 'publish')) {
    $workflow->apply($publication, 'publish');
}
```

| Transition | De | Vers | Ce qu'elle fait |
|---|---|---|---|
| `publish` | `unpublished`, `draft` | `published` | `ContentManager::publish()` |
| `unpublish` | `published`, `draft` | `unpublished` | `ContentManager::unpublish()` |

- **Les places sont lues, pas gardées.** `PublicationMarkingStore` lit la place de la dernière ligne de brouillon de
  la locale. Enregistrer `published` ou `unpublished` revient à publier ou à mettre hors ligne, dans une transaction
  propre et sous les protections du workflow `phil_content` : un refus à cet endroit arrête la transition.
- Une locale sans ligne de brouillon se trouve dans `no_draft`, la place initiale : aucune transition n'en sort. Une
  racine disparue lève `ContentNotFound`.
- Avec un service `event_dispatcher`, ses événements sont répartis sous `workflow.phil_content_publication.*` : un
  écouteur de protection sur `workflow.phil_content_publication.guard.publish` retient une publication planifiée, pas
  une publication faite à la main.
- Le workflow est le service `phil_content.publication_workflow` (`PublicationWorkflow::SERVICE`). Quand le conteneur
  a `workflow.registry` — `framework.workflows` activé —, le bundle y ajoute le workflow pour les sujets
  `Publication`.

## Publication planifiée

`Application\Contract\PublicationSchedule` publie ou met hors ligne un contenu dans une locale à un moment fixé :

| Méthode | Fait |
|---|---|
| `schedule(Publication $publication, string $transition, DateTimeImmutable $at)` | planifie `publish` ou `unpublish` (`ScheduledPublication::PUBLISH`, `UNPUBLISH`) à `$at` ; une transition déjà planifiée passe à `$at` ; toute autre transition est une `InvalidContent` |
| `cancel(Publication $publication, ?string $transition = null): int` | annule une transition, ou les deux, et renvoie combien |
| `pending(Publication $publication): list<ScheduledPublication>` | ce qui est planifié, le plus proche d'abord : chaque `transition` et `dueAt` |

Quand le noyau enregistre `phil/scheduled-transitions-bundle`, le bundle l'implémente avec
`ScheduledTransitionsPublicationSchedule` sur le `Scheduler` de ce bundle, et enregistre `PublicationSubjects`, le
résolveur par lequel son gestionnaire de workflow trouve les publications. Une publication se nomme `news:42@fr` — le
genre, l'identifiant et la locale après un `@` — ; les exécutions à l'échéance appliquent la transition par
`phil_content_publication`, sous ses protections, et une publication refusée est une tentative échouée. Aucun des deux
bundles n'exige l'autre : sans lui, le port n'a pas d'implémentation, et une application l'implémente sur son propre
planificateur ou s'en passe.

```php
use Phil\ContentBundle\Application\Publication\Publication;

$schedule->schedule(Publication::of($news, 'fr'), 'publish', new DateTimeImmutable('2026-12-01 08:00', new DateTimeZone('America/Toronto')));
```

## L'archive datée

Les genres listés sous `phil_content.archives` — une page « maintenant », un journal des changements — gardent lisible
chaque publication à sa version. Rien de plus n'est stocké : une publication fige les lignes de brouillon qu'elle a
publiées et les estampille de sa date, et la modification suivante crée une nouvelle version ; les lignes figées sont
l'archive.

`Application\Publication\ContentArchive` :

| Méthode | Renvoie |
|---|---|
| `history(ContentReference, Locale): list<ArchivedVersion>` | chaque version à partir de laquelle la locale a été publiée, avec sa date en UTC, la plus récente d'abord |
| `read(ContentReference, Locale, int $version): array` | ce que les lecteurs lisaient alors, normalisé : la ligne de brouillon localisée de la version fusionnée par-dessus la ligne partagée de cette version, le corps dans le format de son auteur avec son HTML |
| `isArchived(string $resourceKey): bool` | si l'archive du genre est publique |

- Une version est dans l'archive d'une locale quand la ligne de brouillon de cette locale pour la version porte une
  date de publication. Un brouillon en attente, ou une version à partir de laquelle une autre locale a été publiée, n'y
  est pas (`ContentNotFound`).
- Un genre non listé est refusé (`InvalidContent`) : son historique reste l'affaire des rédacteurs.

`Infrastructure\Http\ArchiveController` sert une version sur une route de l'application, et la fonction Twig
`content_archive()` liste les versions ([rendering.md](rendering.md#larchive-dun-contenu)).

## Retirer un brouillon

`ContentPublisher::removeDraft(ContentRoot $root, Locale $locale)` abandonne les changements faits à une locale publiée
depuis sa publication. Les lignes de brouillon figées de la version à partir de laquelle la locale est en ligne — sa
ligne localisée et la ligne partagée — sont recopiées dans la dernière version de brouillon, et la locale redevient
`published`. Le brouillon contient de nouveau le texte que son auteur a publié, dans le format où il a été écrit.

- `ContentNotFound` quand la locale n'a pas de ligne de brouillon ou n'est pas en ligne ; `TransitionRefused` quand
  la locale n'a pas de brouillon en attente (elle n'est pas dans `draft`).
- **La ligne de brouillon partagée est lue par chaque locale de la version.** Quand une autre locale a une ligne dans
  la dernière version de brouillon, la ligne partagée est laissée telle quelle, et le retrait est **refusé**
  (`TransitionRefused`) si ses valeurs diffèrent de celles de la ligne partagée figée : les remettre écarterait les
  changements en attente des autres locales. La comparaison se fait sur la forme normalisée, sans
  `workflowPublished`. Les changements sont alors publiés, ou défaits à la main. Le refus vient avant la transition :
  la place de la ligne ne change donc pas.
- **Une ligne figée qui n'existe plus est remplacée par sa jumelle en ligne**, la ligne localisée et la ligne
  partagée chacune de leur côté : historique élagué, ou locale publiée avant que la conversion en UJO existe. Le corps
  restauré à partir d'une ligne en ligne est l'UJO publié, au format `ujo`.
- Les lignes restaurées portent la date de publication de la version d'où elles viennent : la dernière version de
  brouillon compte de nouveau comme figée, et la modification suivante crée la version suivante.

## Versions

`Application\Publication\ContentVersions` :

| Méthode | Ce qu'elle fait |
|---|---|
| `latestDraft(ContentRoot): ?Version` | la plus haute version qui a une ligne de brouillon |
| `isFrozen(ContentRoot, Version): bool` | si une ligne de brouillon de la version porte une date de publication |
| `fork(ContentRoot, Version): list<DimensionContent>` | copie chaque ligne de brouillon de la version vers la version suivante, sans sa date de publication, vide vers la base et renvoie les copies |

- **Les brouillons se modifient dans la dernière version** ; les versions plus anciennes sont le registre de ce qui a
  été publié.
- **Une version est figée dès qu'une de ses locales est publiée** : ses lignes de brouillon portent une date de
  publication, et une ligne de brouillon avec une date de publication n'est plus jamais écrite.
- **Modifier une version figée crée une nouvelle version** : chaque ligne de brouillon de la version, partagée et
  localisée, est copiée vers `Version::next()`, et la modification y est écrite. Chaque ligne localisée de la nouvelle
  version qui était `published` passe à `draft` (`create_draft`), puisque la ligne partagée par-dessus laquelle elle
  est lue peut changer dans cette version. Les lecteurs restent sur la version publiée jusqu'à la publication de la
  nouvelle.
- **La dernière version** d'une étape est `DimensionContentRepository::latestVersion()` ; la version servie à un
  lecteur d'une locale est `latestLocalizedVersion()`, que `LiveContentReader` lit le long de la chaîne de repli.

## Modifier

`Application\Editing\ContentEditor::edit(ContentRoot $root, ContentReference $reference, Locale $locale, array $data)`
écrit des données soumises ([pipeline.md](pipeline.md#données-soumises)) sur le brouillon d'une locale :

1. prend la dernière version de brouillon, ou la version 1 pour une racine sans lignes ; crée d'abord une nouvelle
   version quand elle est figée ;
2. charge les lignes de brouillon partagée et localisée de cette version, en créant chacune de celles qui manquent —
   une nouvelle ligne localisée commence `unpublished` ;
3. exécute `ContentDataMapper` sur elles ;
4. vérifie le slug de la ligne localisée dans la table de routes : il doit être libre dans sa locale, appartenir à la
   racine elle-même, être une route d'historique d'une autre racine ou la route d'une racine qui n'existe plus
   (`SlugTaken` sinon).

Il s'exécute dans la `ContentTransaction` de l'appelant ; `ContentManager::edit()` en ouvre une.

## Lire ce que voient les lecteurs

`LiveContentReader::read(ContentRoot $root, FallbackChain $chain)` renvoie la ligne en ligne de la première locale de
la chaîne qui est publiée, dans la version à partir de laquelle cette locale a été publiée, fusionnée par-dessus la
ligne partagée en ligne de cette version. Le `dimension()->locale` de la ligne fusionnée nomme la locale servie.
`ContentNotFound` quand aucune locale de la chaîne n'est publiée.

## Exemple de cycle de vie

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

Toutes les opérations dont un appelant a besoin se trouvent sur `ContentManager` ([content-manager.md](content-manager.md)),
chacune dans sa propre transaction.
