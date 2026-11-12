<!-- phil-dev source-sha256=a166b1df26fb1ed9dd7f5b66819f032f4fd0afca0849312b07a5cf1bdb085267 -->
# phil/social-cards-bundle

**Les balises de partage et la carte de chaque page d'un site Symfony, des aperçus sûrs des liens vers d'autres
sites, et une vérification des balises d'une page.**

phil/social-cards-bundle intègre phil/social-cards à une application Symfony. `{{ social_meta() }}` dans l'en-tête
d'une mise en page écrit le lien canonique, la description, les balises Open Graph, X et du fédivers de la page, à
partir de quatre couches : les valeurs par défaut de la configuration, les sources de l'application — une page de
contenu, un article, un produit —, ce que fixe le contrôleur ou le gabarit, et ce que passe l'appel. Une page sans
image reçoit sa carte, dessinée à partir d'un gabarit de la configuration et servie depuis
`/social-cards/{fingerprint}.png` avec un cache d'un an ; avec Messenger, un worker la dessine, et le message ne porte
que l'empreinte.

`<twig:PhilSocialCards:LinkPreview url="…" />` affiche la carte d'un lien vers un autre site, lue dans les balises de
cette page par phil/safe-http — un client qui refuse les réseaux privés, vérifie chaque redirection, borne la durée, la
taille et les redirections — et mise en cache. L'aperçu d'un lien qu'un membre colle dans un formulaire vient d'un
point de terminaison protégé par une limite de débit et d'un contrôleur Stimulus qui permet à l'auteur de le retirer ;
l'application le garde avec le message, et le composant montre cet instantané sans récupérer la page de nouveau.
`phil:social-cards:check` produit le rendu d'une page de l'application, ou va chercher une page d'un autre site, et dit
ce qui manque à ses balises.

Utilisez-le dans tout site Symfony dont les pages sont partagées : chaque page reçoit des balises complètes et une
image sans que personne n'en dessine. phil/media-bundle donne à une page un média de sa médiathèque comme image ;
phil/http-cache-tags-bundle étiquette les cartes servies pour une purge.

## État

| Partie | État |
|---|---|
| `social_meta()`, `social_page()`, `SharePage`, les sources | Implémenté et testé |
| Les cartes, leur route, leur stockage et leur worker | Implémenté et testé |
| Le récupérateur protégé, `LinkPreviews` et le composant `LinkPreview` | Implémenté et testé |
| Les liens collés : `LinkPreviews::firstIn()`, le point de terminaison et le contrôleur `link-preview` | Implémenté et testé |
| `phil:social-cards:check` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, enregistrement, la route. |
| [Configuration](doc/fr/configuration.md) | Chaque option et sa valeur par défaut. |
| [Pages](doc/fr/pages.md) | Les couches des métadonnées d'une page, `social_meta()`, `social_page()`, les sources. |
| [Cartes](doc/fr/cards.md) | Les gabarits, la route, le cache, le worker, le stockage. |
| [Aperçus de liens](doc/fr/previews.md) | Le récupérateur protégé, le cache, le composant et les liens collés. |
| [Commandes](doc/fr/commands.md) | `phil:social-cards:check`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure ou remplace. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |

## Démarrage rapide

```yaml
# config/packages/phil_social_cards.yaml
phil_social_cards:
    site_name: 'Example'
    cards:
        templates:
            default: { title_font: '%kernel.project_dir%/assets/fonts/Inter-Bold.ttf', accent: '#e11d48' }

# config/routes/phil_social_cards.yaml
phil_social_cards:
    resource: '@PhilSocialCardsBundle/config/routes.php'
```

```twig
{# a page's template #}
{% do social_page({title: post.title, description: post.summary, type: 'article', published_time: post.publishedAt}) %}

{# the layout's head #}
{{ social_meta() }}
```

## Prérequis

PHP 8.5 ou plus récent avec GD, Symfony 8.2 ou plus récent, Twig 3.21 ou plus récent, `phil/social-cards` et
`phil/safe-http`. Messenger, la console, le limiteur de débit, Symfony UX Twig Component et le StimulusBundle sont
facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
