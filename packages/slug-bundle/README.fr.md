<!-- phil-dev source-sha256=1649c40a9b0f28ab216a19b98cd833db6619f00d2b3a1b6b6e39ef711a13b135 -->
# phil/slug-bundle

**Des générateurs de slugs nommés, déclarés dans la configuration, autoconnectés par leur nom et utilisables dans
Twig.**

phil/slug-bundle intègre phil/slug à une application Symfony. La configuration nomme les générateurs dont une
application a besoin, un par usage, chacun avec sa locale, son slug le plus long et les mots de tête qu'il retire.
Chaque générateur nommé est un service à part : l'application le décore comme n'importe quel service Symfony, et le
décorateur prend sa place partout. Le générateur par défaut s'autoconnecte comme `Slugger`, chaque générateur nommé
comme `Slugger $<name>Slugger`, et `Sluggers` les contient tous par nom. Quand Twig est installé, le filtre
`phil_slug` fait un slug dans un gabarit. Quand l'historique est activé, les anciens slugs de chaque sujet
pointent vers son slug actuel, pour qu'une ancienne adresse réponde par une redirection.

Utilisez-le quand une application Symfony fait des slugs de plus d'une façon, ou veut le même générateur dans ses
services et dans ses gabarits : des titres coupés à 80 caractères, des noms de répertoire sans leur article de tête.
Hors de Symfony, phil/slug construit à lui seul les mêmes générateurs à la main. Aucun générateur ne retire de mots si
la configuration ne le dit pas, puisque les articles d'une langue et les préfixes propres à un site sont le choix de
chaque application.

Le bundle ne contient aucune logique de slug à lui. Il transforme chaque générateur configuré en
`TransliteratingSlugger`, enveloppé dans un `LeadingWordsRemoved` quand il liste des mots de tête, et enregistre les
alias qui font fonctionner l'autoconnexion par nom d'argument.

## État

| Partie | État |
|---|---|
| Générateurs nommés d'après la configuration, `Sluggers`, autoconnexion par nom | Implémenté et testé |
| Décoration d'un générateur nommé | Implémenté et testé |
| Le filtre Twig `phil_slug` | Implémenté et testé |
| L'historique des anciens slugs dans une table Doctrine DBAL, facultatif | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et enregistrement. |
| [Configuration](doc/fr/configuration.md) | Les générateurs, leurs options, celui par défaut. |
| [Services](doc/fr/services.md) | Les identifiants de service, l'autoconnexion par nom, décorer un générateur. |
| [Twig](doc/fr/twig.md) | Le filtre `phil_slug`. |
| [Historique des anciens slugs](doc/fr/history.md) | La table facultative des anciens slugs, enregistrer un changement, le lire pour rediriger. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```yaml
# config/packages/phil_slug.yaml
phil_slug:
    sluggers:
        default: ~
        directory:
            leading_words: [le, la, les, l, the]
```

```php
public function __construct(private Slugger $directorySlugger) {}

$this->directorySlugger->slug('Les Gardiens')->value;   // 'gardiens'
```

```twig
<li data-sort-name="{{ name|phil_slug('directory') }}">{{ name }}</li>
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent, `phil/slug` ; Twig pour le filtre ; `doctrine/dbal` pour l'historique.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
