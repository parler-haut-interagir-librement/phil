<!-- phil-dev source-sha256=f1c3920802ffe0c16743b15c0783dfae4702072082d4abe8149a69d3265c6353 -->
# Voir comme ce membre

Un administrateur de l'espace voit le site comme un membre le voit, pour répondre à « pourquoi ne puis-je pas voir cette
page ? » sans demander son mot de passe ni prendre sa session.

- **Administrateurs seulement** : le membre détient `admin` sur l'espace du site. `is_granted('access.view_as')` le
  dit.
- **Lecture seule** : tant que la vue dure, chaque vérification est celle du membre, et seul le niveau le plus étroit —
  la consultation — peut être accordé ; une liste à un niveau plus large est vide. Rien n'est fait au nom du membre.
- **Journalisé** : chaque début et chaque fin sont une notice du canal de journal `phil_access` avec les deux
  identifiants, et envoient `ViewAsStarted` ou `ViewAsStopped`.
- **Conservé dans la session**, par l'identifiant du membre ; oublié dès que l'administrateur n'en est plus un.

Un administrateur ne voit pas le site comme lui-même ; un visiteur, ou un membre qui n'est pas administrateur, est
refusé avec `ViewAsRefused`.

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

## La bannière

`<twig:PhilAccess:ViewAsBanner />`, dans la mise en page, dit à l'administrateur comme qui il voit le site, que la vue
ne fait que lire, et contient le formulaire qui l'arrête. Rien n'est dessiné autrement. Voir [Rendu](rendering.md).

## Dans le code

```php
$viewAs->start(MemberId::of('01JABC'));
$viewAs->member();    // ?MemberId
$viewAs->isAllowed(); // bool
$viewAs->stop();
```
