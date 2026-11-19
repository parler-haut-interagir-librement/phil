<!-- phil-dev source-sha256=6ead80057fbf37e1042570556d58e5890ad1a92a8f3307b77726e6e021e744ed -->
# phil/reactions-bundle

**Des réactions à n'importe quel contenu pour les applications Symfony, sous forme de Live Component qui fonctionne
aussi comme un simple formulaire.**

phil/reactions-bundle permet aux membres d'un site de réagir à un article, à une page, à un message ou à tout ce que
l'application sait nommer. Par défaut, chaque membre a une réaction par contenu, et peut la changer ou la retirer ; un site qui en veut
plus laisse chaque membre en donner plusieurs, chaque émoji une fois, jusqu'à un plafond qu'il choisit. Le site
dit quelles réactions il offre, dans des sources étiquetées (des émojis Unicode et ses propres émojis, chacun une image
avec un texte de remplacement), et qui voit les réactions, leurs compteurs et les noms de ceux qui ont réagi. Une
réaction peut être privée, comme un « pas utile » : elle n'a jamais de compteur ni de noms publics, si bien qu'un site
n'offre aucune réaction comptée publiquement contre un contenu.

Il s'adresse à toute application Symfony qui a du contenu auquel répondre : un blogue, une documentation, un forum, un
wiki, des annonces. L'application nomme ses contenus par des références de `phil/resource` (`article:42`), ses membres par un
identifiant stable — jamais une adresse courriel — et écrit un *voter* ; le bundle garde les réactions, les dessine avec les recettes du kit qu'il exige, et refuse chaque réaction
jusqu'à ce qu'un *voter* dise le contraire.

Une page de nombreux contenus les lit en une requête pour chaque chose à savoir. Chaque bouton est le bouton d'un simple
formulaire que le composant prend en charge : réagir fonctionne donc sans JavaScript ; le sélecteur ajoute une
recherche et le clavier par Stimulus. Les services ne gardent rien entre deux requêtes d'un *worker*.

## État

| Partie | État |
|---|---|
| `Reaction`, une par membre et par contenu ou plusieurs jusqu'à un plafond, son instant en UTC ; stockage Doctrine, et stockage en mémoire vidé entre deux requêtes | Implémenté et testé, sur SQLite |
| Un membre nommé par un identifiant stable du site, `@` refusé | Implémenté et testé |
| La liste en sources, les réactions privées, la visibilité | Implémenté et testé |
| `Reactions`, l'attribut de *voter*, la limite de débit, les événements | Implémenté et testé |
| `ReactionBoard`, les compteurs d'une page lus d'un coup, les noms en liste dans la langue de la page | Implémenté et testé, avec un banc d'essai et une page mesurée en mode *worker* |
| Le Live Component `PhilReactions` et le simple formulaire avec son jeton CSRF et son adresse signée | Implémenté et testé, avec chaque kit |
| Les contrôleurs Stimulus, chargés paresseusement, et leurs chemins pour AssetMapper | Implémenté et testé sous Node, avec AssetMapperBundle et StimulusBundle, et dans un Chrome sans fenêtre ; les vérifications qu'une personne fait avec un lecteur d'écran listées dans [tests](doc/fr/testing.md) |
| L'effacement et l'exportation des réactions d'un membre, les images comme médias lues en une requête, la visibilité parmi les réglages | Implémenté et testé, quand leur paquet est présent |
| Notifications, modération, réputation | Hors du bundle : elles écoutent les événements ou lisent le stockage |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, le bundle, la table, les routes, les recettes, la feuille de style. |
| [Configuration](doc/fr/configuration.md) | Chaque option, les services enregistrés. |
| [Réactions](doc/fr/reactions.md) | La liste, plusieurs réactions par membre, les réactions privées, la visibilité, le membre, le *voter*, la limite de débit. |
| [Le composant](doc/fr/component.md) | Le Live Component, le simple formulaire, une page de nombreux contenus, l'accessibilité, le sélecteur. |
| [Événements](doc/fr/events.md) | Les événements, le bus de messages, l'effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure ou remplace. |
| [Tests](doc/fr/testing.md) | Les tests, le banc d'essai, les vérifications dans un navigateur. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque fichier du bundle. |

## Démarrage rapide

```sh
composer require phil/reactions-bundle
php bin/console ux:install reaction-button --kit=github.com/parler-haut-interagir-librement/ux-kit
php bin/console ux:install reaction-picker --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```yaml
# config/routes/phil_reactions.yaml
phil_reactions:
    resource: '@PhilReactionsBundle/config/routes.php'
    prefix: /_reactions
```

```yaml
# config/packages/phil_reactions.yaml
phil_reactions:
    current_member: App\Reactions\AccountMember # gives the stable identifier of the signed-in member
```

```php
final class ReactionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return Reactions::REACT === $attribute && $subject instanceof ReactionSubject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $token->getUser() instanceof UserInterface;
    }
}
```

```twig
{{ reactions_preload(articles|map(article => 'article:' ~ article.id)) }}
{% for article in articles %}
    <twig:PhilReactions content="article:{{ article.id }}" />
{% endfor %}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-intl`, Symfony 8.2 ou plus récent, Live Component de Symfony UX 3.7 ou plus récent,
twig/intl-extra, Doctrine DBAL 4.5 et ORM 3.7. Symfony Security, Messenger, AssetMapper, `phil/media-bundle`,
`phil/settings-bundle` et `phil/privacy-bundle` sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
