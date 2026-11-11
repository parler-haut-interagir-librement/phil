<!-- phil-dev source-sha256=b1252dbebed87a046e738cf8c9d6ddfb05495617042dafa804a19c50816be59d -->
# JavaScript

## Le contrôleur

`assets/src/collaboration_controller.js` est un contrôleur Stimulus, enregistré sous le nom
`phil--edit-lock-bundle--collaboration`. Il se place autour de l'éditeur de blocs de `phil/block-editor-bundle` :

```twig
<div data-controller="phil--edit-lock-bundle--collaboration"
     data-phil--edit-lock-bundle--collaboration-config-value="{{ collaboration|json_encode }}">
    {{ form_row(form.body) }}
    <aside data-phil--edit-lock-bundle--collaboration-target="presence"></aside>
    <p data-phil--edit-lock-bundle--collaboration-target="status" role="status"></p>
</div>
```

```php
// in the page's controller
$collaboration = $clientConfig->for('article:42', $holder, takeOver: $isOwner);
```

| Cible | Rôle |
|---|---|
| `presence` | L'endroit où la liste des rédacteurs est dessinée : chaque rédacteur, ce qu'il modifie, et un bouton *Take over* par bloc lorsque la reprise est offerte |
| `status` | Une région active qui annonce un bloc détenu par quelqu'un d'autre, un bail perdu, une reprise |

La valeur `config` est ce que donne `ClientConfig::for()`, plus `locale` et `messages`, facultatifs :

| Clé | Signification |
|---|---|
| `resource`, `holder` | La ressource, et le rédacteur `{id, name}` au nom duquel cette page écrit |
| `seconds` | La durée du bail ; le contrôleur pulse à chaque tiers de celle-ci |
| `takeOver` | S'il faut offrir la reprise des blocs d'autrui ; le serveur interroge tout de même la politique |
| `urls` | Les points de terminaison ; `null` désactive les baux |
| `topic`, `hub` | Le sujet et l'URL d'abonnement du hub ; `null` sans Mercure |
| `csrfCookie` | Le nom du témoin et de l'en-tête du jeton CSRF à double soumission ; `null` n'en envoie aucun, et les points de terminaison refusent chaque `POST` |
| `locale`, `messages` | La langue des libellés — le `lang` de la page par défaut — et les libellés remplacés par clé |

## Ce qu'il fait

| Quand | Le contrôleur |
|---|---|
| Le rédacteur entre dans un bloc | Libère le bloc détenu auparavant, s'il y en a un, et prend celui-ci ; un bloc que quelqu'un d'autre détient est annoncé |
| L'éditeur écrit une nouvelle valeur | La compare à la précédente, bloc par bloc, et enregistre le bloc détenu s'il a changé, ou le retire s'il a disparu ; un bloc où se trouve le rédacteur mais qu'il ne détient plus est d'abord redemandé |
| À chaque tiers de la durée | Envoie une pulsation pour le bloc détenu ; sans Mercure, demande aussi la présence |
| Un message de présence arrive | Ferme dans l'éditeur les blocs que d'autres détiennent — chaque bloc sous un bail sur la ressource entière —, dessine la liste des rédacteurs, et l'annonce lorsque le bloc détenu a été repris |
| Un enregistrement ou un retrait d'un autre rédacteur arrive | Le remet à l'éditeur, qui met le bloc en place |
| La page se ferme | Abandonne chaque bail du rédacteur sur la ressource, par une requête qui survit à la page |

Les requêtes sont envoyées l'une après l'autre, dans l'ordre des actions du rédacteur, afin qu'un enregistrement ne
dépasse jamais le bail dont il a besoin. Rien ne lève d'exception pour le réseau : une requête qui échoue équivaut à un
refus.

## Avec l'éditeur de blocs

Les deux contrôleurs communiquent par des événements DOM sur l'élément de l'éditeur, que `phil/block-editor-bundle`
documente dans son propre `doc/javascript.md` :

| Événement | Source | Détail | Rôle |
|---|---|---|---|
| `phil--block-editor-bundle--editor:focus` | l'éditeur | `{id}`, `null` lorsque le rédacteur quitte l'éditeur | Le bloc où se trouve le rédacteur |
| `phil--block-editor-bundle--editor:change` | l'éditeur | `{json}` | Le document, après chaque modification |
| `phil-block-editor:apply` | ce contrôleur | `{block, list, after}` | Un bloc qu'un autre rédacteur a enregistré |
| `phil-block-editor:remove` | ce contrôleur | `{id}` | Un bloc qu'un autre rédacteur a retiré |
| `phil-block-editor:lock` | ce contrôleur | `{blocks: {id: name}}` | Les blocs que d'autres détiennent, avec leurs noms |

L'éditeur met un bloc enregistré à la place de sa copie, ou l'insère là où il va, sans redessiner le bloc en cours de
modification, et recommence son historique d'annulation, puisque annuler ramènerait ce que l'autre rédacteur a remplacé.
Un bloc détenu est encadré, affiche le nom de son détenteur et est `inert` : il ne peut ni recevoir le focus ni être
modifié tant qu'il n'est pas libre.

La granularité est le bloc. Deux rédacteurs ne tapent jamais dans le même bloc ; un bloc qui en contient d'autres — une
bascule, des colonnes — est détenu, enregistré et appliqué avec eux.

## Modules

| Module | Rôle |
|---|---|
| `lib/leases.js` | Les points de terminaison, appelés pour une ressource avec un nouveau jeton CSRF à chaque `POST`, chaque réponse sous la forme `{ok, status, body}` |
| `lib/csrf.js` | Un nouveau jeton aléatoire pour chaque requête, son témoin et son en-tête |
| `lib/snapshot.js` | Le document bloc par bloc — champs propres, liste, bloc précédent —, et les blocs ajoutés, modifiés ou disparus |
| `lib/presence.js` | La fin de chaque bail selon l'horloge de la page, les blocs d'autrui, la liste des rédacteurs |
| `lib/streams.js` | Les Turbo Streams du bundle lus en actions |
| `lib/i18n.js` | Les libellés, en anglais et en français |

Chaque module, sauf le contrôleur, s'exécute sans navigateur et est testé sous Node.

## Libellés

`presence`, `editing`, `block`, `blocks`, `whole`, `you`, `takeOver`, `held`, `takenOver`, `lost`, `tookOver` et
`refused`, dans `lib/i18n.js` ; `{name}` et `{count}` sont remplacés. Une locale comme `fr_CA` prend le français, une
locale inconnue l'anglais ; `messages` remplace n'importe quel libellé par sa clé.

## La feuille de style

`assets/styles/edit-lock.css` met en forme la liste des rédacteurs sous `.phil-edit-lock-roster` avec `--pel-text`,
`--pel-muted`, `--pel-border`, `--pel-accent`, `--pel-focus` et `--pel-radius`, qui prennent les jetons de PHIL UX
lorsque cette feuille de style est chargée, et sinon les `Canvas` et `CanvasText` de la page. L'apparence d'un bloc
détenu dans l'éditeur vient de la propre feuille de style de l'éditeur de blocs.
