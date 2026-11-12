<!-- phil-dev source-sha256=a26f98af24bc80bbd7ebbf48c083fe74ee32d3458563451acc4fa4bffddfd7b6 -->
# phil/forms-autosave

**La saisie d'un formulaire conservée pendant qu'on le remplit : des brouillons dans le navigateur, restaurés avec un
avis, et sur le serveur sur demande.**

phil/forms-autosave conserve ce qu'une personne tape dans un long formulaire : un onglet fermé, un plantage, une session
expirée ou un clic malheureux sur le bouton Précédent ne le font pas perdre. Chaque modification est enregistrée comme
brouillon dès que la frappe s'arrête, dans le stockage local du navigateur. Quand le formulaire s'affiche de nouveau, le
brouillon est remis en place avec un avis qui permet de l'abandonner. Une fois le formulaire envoyé et valide, le
brouillon est oublié. Les mots de passe, les fichiers, les jetons CSRF, les champs cachés et les champs qu'une
application marque comme sensibles ne sont jamais conservés.

Il s'adresse à toute application Symfony dont les formulaires prennent du temps à remplir : une demande d'inscription,
un long article, une fiche de personnage, un sondage. Une seule option l'active, `autosave: true`. Pour les utilisateurs
connectés, l'application peut aussi conserver les brouillons sur le serveur, pour qu'un brouillon les suive sur un autre
appareil. Le contenu est alors scellé par le composant KeyManagement de Symfony, et un brouillon enregistré entre-temps depuis un
autre appareil ou un autre onglet est signalé par un avis plutôt qu'écrasé.

C'est un bundle Symfony sur le composant Form, avec un contrôleur Stimulus servi par AssetMapper, sans étape de
construction. La clé d'un brouillon est une empreinte à clé du formulaire, de son sujet et de son utilisateur : ni le
stockage du navigateur, ni les URL, ni la table ne disent à qui est le brouillon ni de quoi il traite.

## État

| Partie | État |
|---|---|
| L'option `autosave` : la clé, la version de la structure, les réglages et les libellés sur la balise du formulaire ; les champs inclus ou exclus | Implémenté et testé |
| Le contrôleur `autosave` : brouillons dans le stockage local, avec anti-rebond, restaurés avec un avis, abandonnés, oubliés une fois envoyés | Implémenté ; ses modules et le contrôleur sont testés sous Node |
| Le gabarit d'état, et la recette `AutosaveStatus` des deux trousses PHIL UX | Implémenté et testé |
| Le stockage serveur : brouillons par utilisateur, par révision, scellés par le composant KeyManagement de Symfony, conflits signalés, brouillons expirés purgés | Implémenté et testé, sur SQLite |
| Les points de terminaison : une API JSON sans état derrière l'en-tête et le jeton CSRF du contrôleur | Implémenté et testé |
| Champs UJO (Universal JSON Output) de `phil/block-editor-bundle` conservés, leur éditeur remonté à la restauration | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, le contrôleur, l'état, le stockage serveur. |
| [Configuration](doc/fr/configuration.md) | Chaque option du bundle. |
| [Formulaires](doc/fr/forms.md) | L'option `autosave`, les clés et les sujets, la version de la structure, les champs conservés et exclus, les soumissions. |
| [JavaScript](doc/fr/javascript.md) | Le contrôleur, ses valeurs, ses modules, ses événements, les avis et la région active. |
| [Le stockage serveur](doc/fr/server-store.md) | Les utilisateurs, les révisions et les conflits, le chiffrement, l'expiration et la commande de purge. |
| [Points de terminaison](doc/fr/endpoints.md) | Chaque route, son corps, ses réponses, l'en-tête du contrôleur et son jeton CSRF. |
| [Exceptions](doc/fr/exceptions.md) | Les exceptions du bundle et les réponses de ses points de terminaison. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests PHP et Node, la cible de fuzzing et le seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque module du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/forms-autosave
```

```php
$form = $this->createForm(ApplicationType::class, $application, ['autosave' => true]);
```

```twig
{{ form_start(form) }}
    {{ include('@PhilFormsAutosave/status.html.twig') }}
    {{ form_widget(form) }}
{{ form_end(form) }}
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` et `ext-sodium`, Symfony 8.2 et Stimulus 3 dans le navigateur. Pour le
stockage serveur : Doctrine DBAL 4 et ORM 3, les composants de routage et CSRF, un moyen de nommer l'utilisateur
connecté (SecurityBundle ou un service de l'application) et, sauf si le chiffrement est désactivé,
`symfony/key-management` avec un client et une clé maîtresse.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
