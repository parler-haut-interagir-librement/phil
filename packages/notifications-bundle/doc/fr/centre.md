<!-- phil-dev source-sha256=7003383be902df62d35588c89fa24845bd46ec308316bf5329ad98fb9db91dbf -->
# Centre de notifications

## Les composants

| Composant | Affiche |
|---|---|
| `<twig:PhilNotifications:Bell limit="10" />` | Un bouton avec le nombre de non-lues qui ouvre les dernières notifications ; rien pour un visiteur |
| `<twig:PhilNotifications:List limit="20" />` | Les dernières notifications sur une page qui leur est propre |
| `<twig:PhilNotifications:Toasts />` | La région où apparaissent les messages éphémères, une fois par mise en page : ceux que la session a gardés, et ceux qu'apporte Mercure |
| `<twig:PhilNotifications:WebPush />` | Avec `web_push` activé, le bouton qui active les notifications dans ce navigateur ; voir [Canaux](channels.md#web-push) |

Chaque notification est listée avec son titre (un lien vers son sujet quand son résolveur donne une URL), son corps, son
heure et ses boutons : la marquer lue, l'archiver, et tout marquer lu. Chaque bouton est un formulaire envoyé aux points
de terminaison du centre avec le jeton CSRF : il fonctionne sans JavaScript et revient à la page. Les gabarits se
trouvent sous `@PhilNotifications/` et se redéfinissent comme ceux de tout bundle.

## Les points de terminaison

Sous le préfixe qu'a choisi l'application, pour le seul destinataire de la requête courante :

| Route | Méthode | Réponse |
|---|---|---|
| `phil_notifications_unread`, `/unread` | `GET` | `{"unread": n}`, sans cache |
| `phil_notifications_read`, `/{id}/read` | `POST` | Marque la notification lue |
| `phil_notifications_read_all`, `/read-all` | `POST` | Marque chaque notification lue |
| `phil_notifications_archive`, `/{id}/archive` | `POST` | Archive la notification |

Une modification répond `{"unread": n}` à une requête qui accepte le JSON, et redirige un formulaire avec `303` vers la
page d'où il vient sur ce site, ou vers `/`. Un visiteur, une requête sans le jeton ou envoyée depuis un autre site
reçoit un `403` ; la notification de quelqu'un d'autre, un `404`. Le jeton est `phil_notifications`, sans état : dans le
champ de formulaire `_token` ou dans l'en-tête `X-Phil-Notifications-Token`, vérifié avec le `Sec-Fetch-Site`,
l'`Origin` ou le `Referer` du navigateur.

## Messages éphémères

| `toasts.transport` | Comment un message éphémère parvient au navigateur |
|---|---|
| `mercure` | Poussé vers le sujet privé du destinataire sous la forme `{"unread": n, "toast": {"id", "type", "title", "body", "url", "count"}}` |
| `flash` | Gardé dans le sac de messages flash de la session quand la notification est pour celui qui fait la requête, affiché par la page suivante |
| `none` | Aucun message éphémère ; la notification est dans le centre |

L'application autorise chaque destinataire à s'abonner à son seul sujet, `urn:phil:notifications:<id>`, par exemple avec
le témoin d'autorisation de MercureBundle :

```twig
{% do mercure('urn:phil:notifications:' ~ app.user.id, {subscribe: ['urn:phil:notifications:' ~ app.user.id]}) %}
```

Un hub injoignable, ou qui refuse la mise à jour, est journalisé comme avertissement et ne fait pas échouer la
livraison : la notification est dans le centre, et la page suivante affiche le nombre.

### Envoyer hors d'une requête

Avec le hub intégré de FrankenPHP — MercureBundle le choisit quand l'`url` du hub est vide —, une mise à jour est
publiée dans la requête que sert FrankenPHP, par `mercure_publish()`. Cette fonction n'existe ni dans une commande de la
console ni dans un consommateur de Messenger, où les notifications sont le plus souvent livrées : chaque message
éphémère y serait journalisé comme non poussé. Une application qui envoie des notifications hors d'une requête nomme,
dans `toasts.hub`, un hub joint par son URL : le hub intégré de FrankenPHP répond aussi en HTTP au jeton d'un
éditeur.

```yaml
# config/packages/mercure.yaml
mercure:
    hubs:
        default:
            url: ~                                   # FrankenPHP's built-in hub, for the pages
            jwt: { secret: '%env(MERCURE_JWT_SECRET)%', subscribe: '*' }
        publisher:
            url: '%env(MERCURE_URL)%'                # the same hub, reached over HTTP
            jwt: { secret: '%env(MERCURE_JWT_SECRET)%', publish: '*' }

# config/packages/phil_notifications.yaml
phil_notifications:
    toasts:
        hub: mercure.hub.publisher
```

## Le contrôleur Stimulus

`phil--notifications-bundle--notifications`, posé par la cloche, avec sa configuration dans une valeur : les URL des
points de terminaison, le jeton CSRF et son en-tête, l'URL d'abonnement au sujet du destinataire, le libellé du bouton
de fermeture. Il ouvre et ferme le panneau et garde `aria-expanded` à jour, envoie les formulaires en arrière-plan et met
le nombre à jour, et s'abonne au sujet pour ajouter des messages éphémères et mettre le nombre à jour à mesure que les
notifications arrivent. Le texte d'un message éphémère est posé comme du texte, jamais comme du balisage, et son lien
n'est suivi que vers un chemin du site ou une adresse `http(s)`.

`phil--notifications-bundle--web-push`, posé par le bouton Web Push, enregistre le *service worker*, abonne le
navigateur et envoie l'abonnement avec le jeton CSRF ; voir [Canaux](channels.md#web-push).

## Accessibilité

La région des messages éphémères est une région active polie. La cloche nomme son nombre en toutes lettres pour les
lecteurs d'écran. Le bouton Web Push garde un seul libellé et dit son état par `aria-pressed` ; sa région d'état, polie,
dit si les notifications sont activées, désactivées ou bloquées par le navigateur. Les messages éphémères glissent, à moins que le visiteur ne demande moins de mouvement : ils
apparaissent alors sans bouger et restent plus longtemps, dix secondes au lieu de six. La feuille de style prend les
jetons de PHIL UX (couleurs, rayon, ombre, anneau de focus, durées) quand cette feuille de style est chargée, sinon les
couleurs propres à la page.

## Turbo

Le contrôleur se reconnecte à chaque page que Turbo rend. Avec `symfony/ux-turbo`, donnez à la cloche un `id` et
`data-turbo-permanent`, que le composant transmet : Turbo garde alors le même élément d'une visite à l'autre, et avec
lui un seul `EventSource` vers le hub, au lieu d'en fermer un et d'en ouvrir un autre à chaque page.

```twig
<twig:PhilNotifications:Bell id="notifications-bell" data-turbo-permanent />
```

Les formulaires du centre répondent par une redirection `303`, que Turbo suit après l'envoi d'un formulaire, et le nombre
de non-lues reste en JSON pour une requête qui accepte aussi les Turbo Streams.
