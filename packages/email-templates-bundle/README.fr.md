<!-- phil-dev source-sha256=ea761ecc00bf5fdd811de145b0579096bcf2cced02620e67172a64e892767145 -->
# phil/email-templates-bundle

**Des gabarits de courriel que plusieurs sites partagent et que chaque site peut modifier — une confirmation, un billet,
une annulation —, écrits en Twig isolé avec des composants sûrs pour le courriel, rédigés, publiés et versionnés par
site et par locale, prévisualisés, puis rendus dans un `Email` de Symfony Mailer.**

phil/email-templates-bundle produit les courriels qu'une application envoie à partir de gabarits que d'autres personnes
que ses développeurs peuvent modifier. Un gabarit a un **objet**, un **pré-en-tête**, un **corps** en HTML ou en
Markdown, une **partie en texte brut** facultative écrite à la main, le **schéma de ses variables** — des emplacements
nommés, avec leur type, leur caractère obligatoire, leurs valeurs par défaut et leurs valeurs d'exemple — et les
**segments** que testent ses blocs conditionnels. Les valeurs par défaut sont fournies en fichiers YAML, ceux du bundle
et ceux de l'application, ces derniers l'emportant. Chaque **site**, ou la portée que tous les sites partagent,
conserve par locale un **brouillon** à lui et les **versions** qu'il a publiées, comme des contenus de
`phil/content-bundle` : un brouillon n'est envoyé qu'une fois publié, une version publiée n'est jamais modifiée, et la
dernière version publiée est celle qui est envoyée. Tant qu'un site ne publie rien dans une locale, le gabarit partagé
ou le fichier par défaut est envoyé. Une locale se rabat sur sa langue, puis sur la locale par défaut.

Les sources produisent leur rendu dans un **bac à sable Twig** distinct, en mode strict de Twig : une liste d'éléments
autorisés de balises, de filtres, de fonctions, de tests et de méthodes, aucune inclusion, rien du Twig de l'application
à portée de main, des boucles imbriquées sur deux niveaux au plus, un plafond de sortie. Les **composants** — `button()`,
`table()`, `image()`, `panel`, `footer` et `segment()` — dessinent des tables avec des styles en ligne que les clients de
courriel affichent de la même façon. Un corps en Markdown est lu par `phil/markup` et purifié, ses composants restant
entiers. La **mise en page de la maison**, un gabarit du Twig de l'application, enveloppe chaque courriel : le
pré-en-tête caché, la marque, le corps, les couleurs d'un thème clair et d'un **thème sombre** par `color-scheme` et
`prefers-color-scheme` ; les styles sont ensuite mis en ligne par `twig/cssinliner-extra`, et la partie en texte brut est
écrite à partir du HTML, sauf si le gabarit écrit la sienne. Les images sont intégrées par identifiant de contenu,
`cid:logo`.

Chaque valeur donnée à un rendu est **vérifiée selon le schéma** ; chaque gabarit est **vérifié à son enregistrement et à sa
publication** : ses sources se compilent dans le bac à sable, ne lisent que ce que le schéma déclare et produisent leur rendu dans chaque
variante avec les valeurs d'exemple. Le résultat est un `RenderedEmail` — objet, pré-en-tête, HTML, texte, images — ou un
`Email` prêt à envoyer ; un message, `SendTemplatedEmail`, ne porte que des identifiants et l'envoie par Messenger. Une
**administration** réservée à un rôle configurable modifie, publie, rétablit, abandonne et met hors ligne les gabarits d'un site, et
**prévisualise** le brouillon dans chaque variante — aucun segment, chaque segment, tous — dans un `<iframe sandbox>` servi avec une
politique qui n'exécute aucun script ; ses pages sont dessinées par les recettes du kit PHIL UX ou du kit Shadcn, en
anglais et en français.

C'est un bundle Symfony fondé sur Twig, `phil/content-bundle` et l'ORM de Doctrine, `phil/markup`, `phil/url`,
`phil/email-address` et Symfony Mime.
`phil/tenancy-bundle` fait du locataire actif le site ; `phil/audit-bundle` consigne chaque changement dans son journal ;
la console, Mailer, Messenger, Security, la protection CSRF, Symfony UX Twig Component, `twig/cssinliner-extra` et
`twig/intl-extra` sont facultatifs. Les gabarits vivent dans deux tables à côté de celles des contenus, sur SQLite, MySQL,
MariaDB ou PostgreSQL, leurs sites et leurs clés étant comparés octet par octet, et testées sur MySQL 8.4 et
PostgreSQL 17 ; un magasin en mémoire sert aux tests. Ses services ne gardent aucun état entre les requêtes et
conviennent à un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| `TemplateKey`, `Site`, `Locale`, `TemplateDefinition`, `TemplateDraft`, `TemplateVersion`, `BodyFormat`, `TemplateOrigin`, `PublicationState`, les exceptions et les événements | Implémenté et testé |
| `VariableSchema`, `Variable`, `VariableType` : emplacements nommés, neuf types, valeurs par défaut, exemples, limites, valeurs vérifiées et normalisées | Implémenté et testé |
| `TemplateResolver` : la version publiée du site, celle de la portée partagée, les fichiers par défaut, locale par locale ; le brouillon d'un aperçu | Implémenté et testé |
| `SandboxedEngine`, `SandboxRules`, `LimitsVisitor`, `SourceLoader` : le bac à sable strict de Twig, ses vérifications et son cache | Implémenté et testé |
| `EmailComponents` : button, table, image, panel, footer, segment, en HTML, en Markdown et en texte | Implémenté et testé |
| `EmailRenderer`, `HtmlToText`, `TwigLayout`, `CssInlinerStyles`, `DirectoryEmbedLoader`, `MarkupMarkdown` : produire le rendu sans envoyer | Implémenté et testé |
| `EmailComposer`, `SendTemplatedEmail` et son *handler* : un `Email` prêt à envoyer, un message qui ne porte que des identifiants | Implémenté et testé |
| `EmailTemplates`, `TemplateValidator` : enregistrer, publier, rétablir, abandonner, réinitialiser, historique, catalogue d'un site | Implémenté et testé |
| `ContentTemplateStore`, `EmailTemplateContent`, `EmailTemplateDimension`, `TemplatePayload`, `TemplateKeysCollation`, `InMemoryTemplateStore`, `YamlDefaultTemplates` | Implémenté et testé |
| `PreviewRenderer`, `SamplePreviews`, `AdminController` et ses pages, PHIL UX et Shadcn | Implémenté et testé |
| `TenancySiteScope`, `AuditTemplateChanges` ; les commandes `list`, `check` et `render` ; la recette Flex | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les contenus et leurs locales, les gabarits conservés dans une table de versions, les recettes du kit. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Gabarits](doc/fr/templates.md) | Les fichiers par défaut, les champs d'un gabarit, le schéma des variables, les segments et les images. |
| [Le bac à sable et les composants](doc/fr/sandbox.md) | Ce qu'un gabarit peut écrire, les composants, le Markdown, les limites. |
| [Rendu et envoi](doc/fr/rendering.md) | Trouver un gabarit, la mise en page, le mode sombre, les styles en ligne, le texte brut, Mailer et Messenger. |
| [Sites, brouillons et versions](doc/fr/sites-and-versions.md) | Les sites, la portée partagée, les brouillons, la publication et les versions, les contenus, la multilocation et l'audit. |
| [Administration et aperçu](doc/fr/administration.md) | Les pages, les cadres d'aperçu, le rôle, le jeton CSRF, le kit. |
| [Console](doc/fr/console.md) | `phil:email-templates:list`, `check` et `render`. |
| [Sécurité](doc/fr/security.md) | Ce que protègent le bac à sable, les vérifications et l'aperçu, et ce qui reste à l'application. |
| [Points d'extension](doc/fr/extension-points.md) | Les services à remplacer, les étiquettes à compléter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, les serveurs que lisent ses tests d'intégration, la cible de fuzzing et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
Phil\ContentBundle\PhilContentBundle::class => ['all' => true],
Phil\EmailTemplatesBundle\PhilEmailTemplatesBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_content.yaml
phil_content:
    locales: { en: [], fr: [] }

# config/packages/phil_email_templates.yaml
phil_email_templates:
    from: 'Shop <shop@example.com>'
    brand: { name: 'Shop' }
```

```php
use Phil\EmailTemplatesBundle\Application\EmailComposer;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderOptions;

$email = $composer->compose('ticket', [
    'recipient' => ['name' => 'Ada'],
    'event' => ['title' => 'Autumn concert', 'starts_at' => '2026-10-03T20:00:00-04:00'],
    'ticket' => ['number' => 'T-7781'],
    'ticket_url' => 'https://example.com/tickets/T-7781',
], new RenderOptions(locale: 'fr'));

$mailer->send($email->to('ada@example.com'));
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Twig 3.31 ou une version plus récente,
et `phil/content-bundle` avec DoctrineBundle pour les gabarits qu'écrivent les sites.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
