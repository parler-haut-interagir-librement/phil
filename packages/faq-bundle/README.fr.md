<!-- phil-dev source-sha256=8569fab4de8016dc4a1a0c66ca8a8b29cebe068e6278b304b85b9bdcf2dd21af -->
# phil/faq-bundle

**Questions fréquentes par section, consultables sans JavaScript, chacune avec une ancre stable, un vote anonyme
« Cette réponse a-t-elle été utile ? » et le JSON-LD `FAQPage` de la page.**

phil/faq-bundle affiche les questions d'un site et leurs réponses. Les questions vivent dans des **sections**, chacune
dans son ordre ; une question a une **traduction par langue** : sa formulation, sa réponse écrite en Markdown, en HTML
ou en texte brut et conservée en HTML purifié, sa publication dans cette langue et le moment de son dernier changement,
en UTC. Une locale se rabat sur sa langue : `fr_CA` affiche les questions `fr` qui lui manquent.

Chaque question est un élément `details` dont l'`id` est son **ancre**, faite une seule fois à partir de sa première
formulation et conservée quand la formulation change, de sorte qu'un lien partagé ouvre toujours la même question ;
chacune a un lien pour la partager et un bouton qui le copie. La page fait une **recherche** par un simple formulaire
GET que le serveur filtre, sans égard aux accents ni à la casse, et que le contrôleur Stimulus filtre à mesure que l'on
tape. Une recherche sans résultat affiche le lien vers le **formulaire de contact** et est **comptée**, normalisée, par
langue et par jour : un total, jamais qui a cherché.

Le **vote** est oui ou non, compté par question. Une page porte un jeton de vote signé qui vote une fois pour chacune de
ses questions, que le serveur retient par une empreinte jusqu'à l'expiration du jeton ; le contrôleur Stimulus retient,
dans le stockage propre au navigateur, les questions pour lesquelles il a voté. Les votes sont freinés par question par
`symfony/rate-limiter` et vérifiés par la protection CSRF sans état de Symfony. Rien de personnel n'est lu ni conservé
(ni adresse, ni identifiant, ni témoin), de sorte que le bundle n'a besoin d'aucun module d'un bundle de vie privée.

Le JSON-LD `FAQPage`, écrit avec `phil/structured-data`, décrit les questions que la page affiche ; la façon dont un
moteur de recherche l'affiche est le choix de ce moteur. La même page s'exporte en Markdown pour un fichier `llms.txt`.
Une **administration** facultative, protégée par un rôle et accessible depuis un tableau de bord EasyAdmin, écrit les
sections et les questions dans chaque langue, les ordonne, les publie et montre les totaux des votes et des recherches
sans réponse ; `phil:faq:export` et `phil:faq:import` les déplacent par YAML.

C'est un bundle Symfony fondé sur l'ORM de Doctrine, Twig, Symfony UX Twig Component, `phil/markup`, `phil/slug` et
`phil/structured-data`. Ses tables, préfixées `phil_faq_`, sont testées sur SQLite, MySQL 8.4 et PostgreSQL 17. Ses
services ne gardent aucun état entre les requêtes et conviennent à un *worker* de longue durée.

## État

| Partie | État |
|---|---|
| `Section`, `Question` et leurs traductions, l'ancre conservée quand la formulation change, l'ordre par « avant » | Implémenté et testé, sur SQLite, MySQL 8.4 et PostgreSQL 17 |
| `FaqEditor` : sections, questions, traductions, ordre, publication ; réponses lues par `phil/markup` et purifiées | Implémenté et testé |
| `FaqPages` : la page dans une langue avec son repli, la recherche normalisée comme le serveur et le navigateur la normalisent | Implémenté et testé |
| `HelpfulVotes`, `VoteTokens` : un vote par jeton de page et par question, la limite de débit, rien du lecteur conservé | Implémenté et testé |
| `UnansweredSearches` : les recherches sans réponse, normalisées, comptées par langue et par jour | Implémenté et testé |
| `FaqStructuredData`, `FaqMarkdown` : le JSON-LD `FAQPage`, vérifié par le `Validator` de `phil/structured-data` ; le Markdown d'un `llms.txt` | Implémenté et testé |
| Le composant Twig `PhilFaq`, la page et ses routes, avec les recettes du kit PHIL UX | Implémenté et testé |
| Le contrôleur Stimulus : la recherche à mesure que l'on tape, la question de l'adresse ouverte, les votes sans quitter la page | Implémenté et testé sous Node, avec AssetMapperBundle et StimulusBundle |
| L'administration, son menu EasyAdmin ; `phil:faq:export` et `phil:faq:import` | Implémenté et testé |
| Un assistant qui répond avec un modèle de langage | Ne fait pas partie du bundle |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le bundle, les tables, les routes, les recettes du kit, le contrôleur. |
| [Configuration](doc/fr/configuration.md) | Chaque option, les limites de débit, les services enregistrés. |
| [Gabarits](doc/fr/templates.md) | Le composant, la page, la recherche, les votes, le JSON-LD, l'export Markdown, l'accessibilité. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application configure, remplace ou surcharge. |
| [Tests](doc/fr/testing.md) | Les tests, les serveurs qu'ils lisent, les vérifications dans un navigateur. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque fichier du bundle. |

## Démarrage rapide

```sh
composer require phil/faq-bundle
php bin/console ux:install button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install input --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install copy-button --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/routes/phil_faq.yaml
phil_faq:
    resource: '@PhilFaqBundle/config/routes.php'
    prefix: /faq
```

```yaml
# config/packages/phil_faq.yaml
phil_faq:
    contact_url: /contact
```

```php
$section = $editor->addSection(Locale::fromString('en'), 'Orders');
$editor->addQuestion($section->id, Locale::fromString('en'), 'How do I pay?', 'By **card** or transfer.');
```

Les questions sont alors à `/faq/`, ou partout où un gabarit écrit `<twig:PhilFaq />`.

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-intl`, `ext-mbstring` et `ext-sodium`, Symfony 8.2 ou une version plus
récente avec sa protection CSRF, Symfony UX Twig Component 3.7 ou une version plus récente, Doctrine DBAL 4.5 et ORM
3.7. Symfony Security, la console, le composant YAML, AssetMapper, StimulusBundle et EasyAdmin sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
