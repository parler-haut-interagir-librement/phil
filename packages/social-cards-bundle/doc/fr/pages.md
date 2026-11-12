<!-- phil-dev source-sha256=b2a315e58d0bfc23a24970fdada06665deaca36ea6cf9600b1c0ae265d64a1d8 -->
# Pages

## Quatre couches

`ShareMetadataFactory` construit les métadonnées de la page en cours de rendu à partir de quatre couches, chacune
par-dessus la précédente ; une valeur nulle laisse la valeur du dessous :

1. les valeurs par défaut de la configuration : `site_name`, `locale`, `twitter_site`, `fediverse_creator`,
   `default_image` ;
2. les sources de l'application, chaque service qui implémente `ShareMetadataSource`, dans l'ordre de leurs
   étiquettes ;
3. ce que la requête a fixé, par `SharePage::set()` dans un contrôleur ou `social_page()` dans un gabarit ;
4. ce que le gabarit passe à `social_meta()`.

Les clés sont celles de `ShareMetadata::KEYS`, plus `card_template`, le gabarit de la carte de la page, et `card`, à
false pour aucune carte. Ensuite :

- l'adresse est celle de la requête — son schéma, son hôte, son chemin de base et son chemin, sans sa chaîne de
  requête — quand aucune couche n'en donne ;
- la locale est celle de la requête quand aucune couche n'en donne ;
- une image qui est un média de phil/media-bundle devient son adresse absolue signée, son texte de remplacement, sa
  taille et, pour une image, son type de média ;
- une page sans image reçoit sa carte, sauf si `card` vaut false : son titre, sa description ou le nom du site comme
  sous-titre, sa locale, dessinée à partir de `card_template` ou du gabarit par défaut.

Une page sans titre n'a pas de balises ; une page rendue hors d'une requête sans adresse non plus. Une valeur que les
balises ne peuvent pas porter — une adresse qui n'est pas absolue, une locale qui n'en est pas une — lance
`InvalidShareMetadata`, pour qu'on la corrige là d'où elle vient.

## Dans Twig

```twig
{# templates/blog/post.html.twig #}
{% do social_page({
    title: post.title,
    description: post.summary,
    type: 'article',
    author: post.author.name,
    published_time: post.publishedAt,
    modified_time: post.updatedAt,
    alternate_locales: post.translations|keys,
}) %}

{# templates/base.html.twig, in <head> #}
{{ social_meta() }}
{{ social_meta({card_template: 'event'}) }}
```

## Dans un contrôleur

```php
public function show(Post $post, SharePage $page): Response
{
    $page->set(['title' => $post->title, 'image' => $post->cover]);
    // ...
}
```

`SharePage` est réinitialisé entre les requêtes : un worker ne reporte jamais les valeurs d'une page sur la suivante.

## Sources

```php
final readonly class ProductSource implements ShareMetadataSource
{
    public function __construct(private RequestStack $requests) {}

    public function values(): array
    {
        $product = $this->requests->getCurrentRequest()?->attributes->get('product');

        return $product instanceof Product ? ['title' => $product->name, 'description' => $product->summary, 'type' => 'website'] : [];
    }
}
```

Une source est autoconfigurée. Un modèle que l'application partage entre ses pages, sa recherche et ses données
structurées donne son titre, sa description et son image une seule fois, par sa source.
