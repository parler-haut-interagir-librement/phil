<!-- phil-dev source-sha256=e729df0123d0f70a3d1641a9e217d081f23ebf92d0f5ed0d1897b983876d91e3 -->
# Voir comme ce membre

Un administrateur de l'espace voit le site comme un membre le voit, pour répondre à « pourquoi ne puis-je pas voir cette
page ? » sans demander son mot de passe ni prendre sa session.

- **Administrateurs seulement** : le membre détient `admin` sur l'espace du site. `is_granted('access.view_as')` le
  dit.
- **Membres de cet espace seulement** : le membre vu détient `member` sur le même espace — chaque administrateur le
  détient aussi. Avec des locataires, l'espace est celui du locataire : un administrateur d'un locataire ne voit
  jamais un membre d'un autre.
- **Lecture seule** : tant que la vue dure, chaque vérification est celle du membre, et seul le niveau le plus étroit —
  la consultation — peut être accordé ; une liste à un niveau plus large est vide. Le garde répond 403 à une requête
  qui changerait quelque chose. Voir [La limite](#la-limite).
- **Journalisé** : chaque début, chaque fin et chaque refus sont une notice ou un avertissement du canal de journal
  `phil_access`, par identifiants seulement ; chaque début et chaque fin envoient `ViewAsStarted` ou `ViewAsStopped`.
- **Conservé dans la session**, par l'identifiant du membre ; retiré de la session dès que l'administrateur n'en est
  plus un, n'est plus connecté, ou que le membre quitte l'espace.

Un administrateur ne voit pas le site comme lui-même ; un visiteur, un membre qui n'est pas administrateur, ou un
administrateur qui nomme quelqu'un qui n'est pas membre de l'espace, est refusé avec `ViewAsRefused`.

## La limite

La vue change pour qui **les vérifications de ce bundle** répondent : `is_granted('access.<level>', $item)`, le voter
d'EasyAdmin, `access_can()`, `ItemAccess`. Le jeton de sécurité contient toujours l'administrateur, avec tous ses
rôles. Ce qui ne demande rien au bundle — `is_granted('ROLE_ADMIN')`, un autre voter, un contrôleur ou un service de
l'application — voit toujours l'administrateur et accorde ce que l'administrateur peut faire.

Deux garde-fous gardent la vue en lecture seule pour le reste de l'application :

- **Le garde**, `Infrastructure\Http\ViewAsGuard`, sur `kernel.controller`. Tant qu'une vue dure, une requête dont la
  méthode n'est pas sûre — `POST`, `PUT`, `PATCH`, `DELETE` — reçoit un 403 et est journalisée. Un contrôleur marqué
  `#[AllowedDuringViewAs]` passe : les formulaires qui démarrent et arrêtent la vue le sont. Un contrôleur marqué
  `#[RefusedDuringViewAs]` est refusé quelle que soit la méthode : un `GET` qui change quelque chose, une exportation,
  un téléchargement. `view_as.read_only: false` retire le garde.

  ```php
  use Phil\AccessBundle\Infrastructure\Http\Attribute\RefusedDuringViewAs;

  #[RefusedDuringViewAs]
  #[Route('/members/export', methods: ['GET'])]
  public function export(): Response
  ```

- **`ViewAs::refuseWhileViewing()`**, pour un changement qui ne vient pas d'un contrôleur que le garde voit — un
  *handler* de commande, un service appelé depuis un gabarit, une action de Live Component. Elle lance `ViewAsRefused`
  tant qu'une vue dure.

  ```php
  public function __invoke(RenameItem $command): void
  {
      $this->viewAs->refuseWhileViewing();
      // …
  }
  ```

## La fin d'une vue

Une vue prend fin quand l'administrateur l'arrête, et d'elle-même dès que `ViewAs::member()` constate que
l'administrateur n'est plus administrateur de l'espace, n'est plus connecté, ou que le membre a quitté l'espace. Le
membre vu est alors retiré de la session — retrouver le droit plus tard ne ramène pas la vue —, une notice est
journalisée, et `ViewAsStopped` porte la raison : `ViewAsEnd::Stopped`, `ViewAsEnd::AdminRightLost` ou
`ViewAsEnd::MemberLeftSpace`. Son `admin` est vide quand plus personne n'est connecté.

## Les formulaires

Les routes du bundle démarrent et arrêtent la vue à partir de simples formulaires envoyés avec le jeton
`phil_access_view_as`, puis reviennent à `view_as.redirect` :

```twig
{% if is_granted('access.view_as') %}
<form method="post" action="{{ path('phil_access_view_as_start', {member: member.id}) }}">
    <input type="hidden" name="_token" value="{{ csrf_token('phil_access_view_as') }}">
    <button type="submit">View as this member</button>
</form>
{% endif %}
```

Un formulaire sans son jeton est refusé avec un 403, une vue refusée avec un 403, un identifiant contraire aux règles
avec un 404.
Le contrôleur porte `#[AllowedDuringViewAs]` : le garde laisse donc passer les deux formulaires pendant une vue.

## La bannière

`<twig:PhilAccess:ViewAsBanner />`, dans la mise en page, dit à l'administrateur comme qui il voit le site, que la vue
ne fait que lire, et contient le formulaire qui l'arrête. Rien n'est dessiné autrement. Voir [Rendu](rendering.md).

## Dans le code

```php
$viewAs->start(MemberId::of('01JABC'));
$viewAs->member();             // ?MemberId; ends a view whose admin or member no longer qualifies
$viewAs->isAllowed();          // bool
$viewAs->refuseWhileViewing(); // throws ViewAsRefused during a view
$viewAs->stop();
```
