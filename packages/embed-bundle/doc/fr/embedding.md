<!-- phil-dev source-sha256=d1d7939be0053a3f1131abf098499757629bae83598c55ef1d6a47ffa94e635f -->
# Intégration

## Déclarer une intégration

Une intégration est un nom dans la configuration, avec les origines autorisées à l'encadrer, ses thèmes et la durée de
validité de ses jetons. Sa page est n'importe quelle route de l'application, marquée par sa valeur par défaut `_embed` ou
par l'attribut `#[Embeddable]` de son contrôleur :

```php
use Phil\EmbedBundle\Application\Attribute\Embeddable;

#[Route('/embed/calendar/{id}', name: 'calendar_embed')]
#[Embeddable('calendar')]
final readonly class CalendarEmbedController
{
    public function __invoke(string $id): Response { /* … */ }
}
```

## La page

Une requête vers cette page qui porte `embed_token` est lue une fois le contrôleur connu :

- un jeton valide de cette intégration, pour une origine qu'elle autorise : la requête reçoit son contexte, la page peut
  être encadrée par cette origine seulement, et `X-Frame-Options` est retiré ;
- tout autre jeton : la page ne peut être encadrée par aucune origine — le cadre reste vide, et l'élément garde son
  lien ;
- aucun jeton : la page est servie comme n'importe quelle autre, selon les règles d'encadrement propres au site.

Le contexte est l'attribut de requête `_phil_embed`, un `EmbedContext`, et `phil_embed()` dans Twig : l'intégration,
l'origine, le thème, le sujet et les thèmes entre lesquels le partenaire peut basculer. Le thème est celui que demande
l'adresse du cadre dans `embed_theme`, si l'intégration l'a, sinon celui du jeton, sinon celui par défaut de
l'intégration.

```twig
<html lang="en" {{ phil_embed_frame_attributes() }}>
<body>
    {% set embed = phil_embed() %}
    {% if embed %}<p>Shown on {{ embed.origin }}</p>{% endif %}
</body>
</html>
```

`phil_embed_frame_attributes()` écrit, à l'intérieur d'une intégration seulement, les attributs qui démarrent le
contrôleur du cadre avec l'origine du jeton et les thèmes de l'intégration, ainsi que `data-theme`.

Une page montrée dans un cadre d'un autre site est un contexte tiers : les navigateurs ne lui envoient aucun témoin du
site, sauf si le témoin est `SameSite=None; Secure`, et le partitionnent alors. Une intégration destinée à des visiteurs
connectés demande sa propre décision sur les témoins ; le bundle ne les modifie pas.

## Le code qu'un partenaire colle

`EmbedCodes::snippet()`, `phil_embed_code()` dans Twig, l'écrit :

```twig
<pre>{{ phil_embed_code('calendar', 'https://partner.example', 'calendar_embed', {id: event.id}, url('calendar_show', {id: event.id}), 'Our calendar', 'dark', 'event:' ~ event.id) }}</pre>
```

```html
<phil-embed src="https://site.example/embed/calendar/7?embed_token=pe1.hs256.…" title="Our calendar" theme="dark">
    <a href="https://site.example/calendar/7">Our calendar</a>
</phil-embed>
<script type="module" src="https://site.example/_embed/phil-embed.js" crossorigin="anonymous"></script>
```

Les arguments sont l'intégration, l'origine du partenaire, la route de la page et ses paramètres, l'adresse absolue où va
un lecteur sans le cadre, le titre — celui du cadre et celui du lien —, et facultativement le thème et le sujet. Le
jeton est émis pour cette origine : une origine que l'intégration n'autorise pas, un thème qui lui manque ou un repli qui
n'est pas une adresse sont refusés. `phil:embed:token <embed> <origin> [--theme] [--subject]` en émet un depuis la
console.

## L'élément

`<phil-embed>` est la seule pièce du bundle qui s'exécute sur d'autres sites, où Stimulus ne l'est pas : un élément
personnalisé, dans le module que sert la route du bundle.

| Attribut | Effet |
|---|---|
| `src` | L'adresse du cadre, `http` ou `https` ; l'élément ne fait rien avec une autre. |
| `title` | Le titre du cadre ; le texte du lien en son absence. |
| `theme` | Le thème demandé à la page, transmis dans `embed_theme`, et envoyé au cadre lorsqu'il change. |
| `height` | La hauteur du cadre avant que la page dise la sienne, 150 pixels par défaut. |
| `timeout` | Combien de temps l'élément attend que la page dise qu'elle est prête, 10 000 millisecondes par défaut. |
| `sandbox` | Le bac à sable du cadre ; par défaut, les scripts, les formulaires, les fenêtres contextuelles et l'origine propre de la page sont permis. |

Il crée un cadre caché, chargé paresseusement, sans référent. Lorsque la page dit qu'elle est prête, il montre le cadre,
cache le lien et émet `phil-embed:ready` ; chaque hauteur que la page lui communique fixe celle du cadre et émet
`phil-embed:resize` avec `{height}`. Si la page ne dit jamais qu'elle est prête — encadrement refusé, erreur réseau —,
l'élément retire le cadre et garde le lien. `data-state` dit où il en est : `loading`, `ready` ou `fallback`.

## Commentaires portés par le forum d'un autre site

`RemoteCommentThreads` est le port des commentaires d'une page portés par le forum d'un autre site. Le bundle le déclare
et ne fournit aucun adaptateur : il s'écrit avec le forum.
