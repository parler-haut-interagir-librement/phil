<!-- phil-dev source-sha256=8ddf5bffbff0ef7f49c5e8afdbca99c929952846df2c58ecca6a5803f5572b3f -->
# Aperçus de liens

Le bundle affiche la carte d'un lien vers un autre site à deux endroits : un lien qu'écrit un gabarit, par le composant
`PhilSocialCards:LinkPreview`, et un lien qu'un membre colle dans un formulaire, par le contrôleur Stimulus
`link-preview` et son point de terminaison. Les deux lisent la page par le même récupérateur protégé et le même cache.

## Le récupérateur protégé

`GuardedPageFetcher` récupère l'en-tête d'une page d'un autre site par phil/safe-http. Les adresses viennent des
membres et des rédacteurs : le client se protège donc contre la falsification de requête côté serveur :

- une adresse est une adresse HTTP ou HTTPS absolue d'un nom d'hôte, comme phil/url la lit : aucune adresse IP, sous
  aucune de ses écritures, ni nom d'utilisateur ni mot de passe, 2 048 caractères au plus ;
- le client est enveloppé dans `NoPrivateNetworkHttpClient`, qui refuse un hôte qui est une adresse de bouclage, privée,
  de lien local ou réservée, ou qui s'y résout — un point de terminaison de métadonnées d'un nuage, un service du réseau
  interne —, à la première requête et à chaque redirection ;
- les redirections sont suivies une à une, chacune vérifiée comme la première adresse, jusqu'à `preview.max_redirects` ;
  le silence est borné par `preview.timeout` et toute la récupération, redirections comprises, par
  `preview.max_duration` ;
- seule une réponse `200` en `text/html` ou `application/xhtml+xml` est lue, par morceaux, jusqu'à la fin de son
  en-tête ou jusqu'à `preview.max_bytes`, puis le transfert est annulé.

Ce qui échoue ne donne rien, jamais une exception. Le client est le service `phil_social_cards.safe_http_client`, fait à
partir de `http_client` quand l'application en a un, et il oublie entre deux requêtes les adresses qu'il a résolues.

## Les aperçus

`LinkPreviews::preview($url)` lit les balises de la page là où elle a été servie après ses redirections, produit son
`LinkPreview` et le garde dans `cache.app` pendant `preview.cache_ttl` secondes ; une page impossible à récupérer est
gardée comme rien, pour qu'un lien mort ne soit pas récupéré à chaque consultation. Sans `cache.app`, chaque appel
récupère la page.

`LinkPreviews::firstIn($text)` est l'aperçu d'un lien collé : le premier lien du texte, tel que `LinkFinder` de
phil/social-cards le trouve, que le client récupère. Au plus un lien est récupéré, quoi que contienne le texte.

## Le composant

```twig
<twig:PhilSocialCards:LinkPreview url="https://example.org/post" />
<twig:PhilSocialCards:LinkPreview url="{{ link }}" :image="false" class="compact" />
<twig:PhilSocialCards:LinkPreview :snapshot="message.linkPreview" />
```

Il produit un lien avec l'image, le site, le titre et la description de la page, sous `phil-link-preview` et ses
parties `__image`, `__body`, `__site`, `__title` et `__description`, ou un lien simple, `phil-link-preview--plain`,
quand la page ne donne rien ; une adresse qui ne peut pas être lue, dont une adresse `javascript:` ou `data:`, ne donne
aucun lien. L'image est chargée de façon différée et sans référent ; `:image="false"` l'omet, pour que
le navigateur d'un lecteur ne récupère rien de l'autre site. Les liens sont `rel="noopener nofollow"`.

`:snapshot` prend un aperçu gardé avec un message, le tableau de `LinkPreview::toArray()` : rien n'est récupéré, et la
carte reste telle qu'elle était quand le message a été écrit, quoi que la page devienne. Il l'emporte sur `url` ; un
tableau qui n'est pas un aperçu donne le lien simple de `url`, ou rien.

Une `url` est récupérée pendant le rendu de la page ; une page qui montre beaucoup de liens vers des sites pas encore en
cache est aussi lente que le plus lent d'entre eux, chacun borné par `preview.max_duration`. L'aperçu d'un message se
lit mieux quand le message est écrit, et se garde avec lui.

## Les liens collés

Le point de terminaison et le contrôleur Stimulus montrent, sous un texte qu'un membre écrit, l'aperçu de son premier
lien, et permettent à l'auteur de le retirer.

```yaml
# config/routes/phil_social_cards.yaml
phil_social_cards_link_preview:
    resource: '@PhilSocialCardsBundle/config/link_preview_routes.php'

# config/packages/framework.yaml
framework:
    rate_limiter:
        link_preview: { policy: sliding_window, limit: 30, interval: '10 minutes' }

# config/packages/phil_social_cards.yaml
phil_social_cards:
    preview:
        limiter: limiter.link_preview
```

`GET /social-cards/link-preview?url=…`, `LinkPreviewController`, répond :

| Statut | Quand | Corps |
|---|---|---|
| `200` | La page donne un aperçu | Le JSON de `LinkPreview::toArray()` |
| `204` | La page n'en donne aucun, ou ne peut pas être récupérée | Vide |
| `400` | `url` manque, ou n'est pas une adresse que le client récupère | `{"error": "…"}` |
| `429` | Le limiteur de débit du visiteur est épuisé | `{"error": "…"}`, avec `Retry-After` |

Chaque réponse est `private`, gardée cinq minutes par le navigateur, et `nosniff`. Chaque appel peut récupérer une page
d'un autre site : importez la route derrière le pare-feu des membres qui écrivent, et donnez à `preview.limiter` un
limiteur de débit, par adresse du client. Une adresse refusée ne compte pas dans la limite.

```twig
<div data-controller="phil--social-cards-bundle--link-preview"
     data-phil--social-cards-bundle--link-preview-endpoint-value="{{ path('phil_social_cards_link_preview') }}">
    {{ form_widget(form.message, {attr: {
        'data-phil--social-cards-bundle--link-preview-target': 'source',
        'data-action': 'input->phil--social-cards-bundle--link-preview#scan paste->phil--social-cards-bundle--link-preview#scan',
    }}) }}
    {{ form_widget(form.link, {attr: {'data-phil--social-cards-bundle--link-preview-target': 'field'}}) }}
    <div data-phil--social-cards-bundle--link-preview-target="preview" hidden></div>
</div>
```

Le contrôleur analyse le texte `delay` millisecondes — 400 par défaut — après que l'auteur cesse de taper, en trouve le
premier lien comme le fait `LinkFinder`, demande son aperçu au point de terminaison et construit la carte à partir de
nœuds de texte, jamais de HTML, avec un bouton, `phil-link-preview__remove`, dont l'étiquette vient de sa valeur
`remove-label`. Le lien montré est écrit dans la cible `field`, un champ caché du formulaire ; un lien retiré le vide, et
reste retiré tant qu'il est dans le texte. Une réponse qui arrive après que le texte a changé est abandonnée. Le
contrôleur émet `shown` avec l'aperçu et `removed` avec l'adresse.

À la soumission, l'application lit le champ, reprend l'aperçu — depuis le cache — et garde son tableau avec le message :

```php
$link = $form->get('link')->getData();
$message->linkPreview = is_string($link) && '' !== $link ? $previews->preview($link)?->toArray() : null;
```

L'adresse du champ vient du navigateur : `preview()` la vérifie comme toute autre.
