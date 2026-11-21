<!-- phil-dev source-sha256=aba7874cf21b147b58ba921bb81e9815a9ce420ac4a248b0c334bdb71d97e590 -->
# phil/profile-secure-input

[![OpenSSF Scorecard](https://api.scorecard.dev/projects/github.com/parler-haut-interagir-librement/profile-secure-input/badge)](https://scorecard.dev/viewer/?uri=github.com/parler-haut-interagir-librement/profile-secure-input)

**Un seul `composer require` pour les paquets PHIL qui protègent ce que les utilisateurs envoient à une application
Symfony : les adresses qu'ils saisissent, les adresses que l'application va ensuite chercher, la fréquence de leurs
requêtes, le pourriel dans leurs formulaires et les fichiers qu'ils téléversent.**

phil/profile-secure-input est un métapaquet Composer : il ne contient aucun code. L'exiger installe cinq paquets PHIL
souvent employés ensemble là où une application reçoit des données du public. Chaque membre reste un paquet
indépendant, avec sa propre configuration, sa propre documentation et ses propres versions : le profil ne couple aucun
service à un autre, il évite seulement de les chercher un à un et de choisir des contraintes qui fonctionnent
ensemble.

Il s'adresse aux applications Symfony qui ont des formulaires publics — inscription, contact, commentaires, soumissions
— et qui reçoivent des liens ou des fichiers de visiteurs. Une application qui n'a besoin que d'une de ces protections
exige ce membre seul.

## Ce qu'il installe

| Membre | Contrainte | Apporte | Pourquoi il est ici |
|---|---|---|---|
| `phil/url-bundle` | `^0.1.3` | `phil/url`, `phil/enums` | Chaque adresse saisie par une personne est lue par l'analyseur WHATWG de PHP, normalisée et typée avant d'être stockée : un champ de formulaire de lien, une contrainte de validation, un type de colonne Doctrine. |
| `phil/safe-http` | `^0.2` | `phil/url` | Quand l'application va chercher une adresse donnée par un utilisateur — un aperçu, un fil, un webhook —, le client refuse les réseaux privés, vérifie chaque redirection et borne le temps et la taille. |
| `phil/rate-limit-bundle` | `^0.1` | `phil/rate-limit` | Des limites déclarées sur les contrôleurs, les routes et les formulaires répondent 429 avec les en-têtes RateLimit standard, et le conteneur refuse un limiteur qui compte dans un stockage que chaque serveur garde pour lui. |
| `phil/spam-protection-bundle` | `^0.3` | `phil/spam-protection`, `phil/bot-detection-bundle`, `phil/rate-limit` | Le pourriel reste hors des formulaires sans captcha, sans tiers et sans pistage : jetons signés, temps de remplissage, petite preuve de travail, règles de contenu. |
| `phil/upload-scan-bundle` | `^0.1.1` | `phil/upload-scan` | Chaque fichier téléversé passe par un antivirus pendant la validation du formulaire ; un fichier infecté est mis en quarantaine, hors d'atteinte. |

La page des [membres](doc/fr/members.md) donne le rôle de chacun, ce qu'il ajoute à l'application et ce dont il a
besoin.

## Ce que chaque membre apporte à l'application

Aucun membre ne fournit de recette Symfony Flex : Flex enregistre chaque bundle dans `config/bundles.php` et ne copie
aucun fichier. Chaque membre fonctionne ensuite avec ses valeurs par défaut, et sa propre documentation dit quoi
configurer.

| Membre | Bundle enregistré | Fichiers qu'il fournit |
|---|---|---|
| `phil/url-bundle` | `Phil\UrlBundle\PhilUrlBundle` | Les traductions de ses messages |
| `phil/rate-limit-bundle` | `Phil\RateLimitBundle\PhilRateLimitBundle` | Le gabarit d'un refus, des traductions |
| `phil/spam-protection-bundle` | `Phil\SpamProtectionBundle\PhilSpamProtectionBundle` | Le contrôleur Stimulus de preuve de travail dans `assets/package.json`, des traductions |
| `phil/bot-detection-bundle` | `Phil\BotDetectionBundle\PhilBotDetectionBundle` | Les détecteurs Stimulus dans `assets/package.json` |
| `phil/upload-scan-bundle` | `Phil\UploadScanBundle\PhilUploadScanBundle` | Les routes de la page de quarantaine dans `config/admin_routes.php`, ses gabarits |
| `phil/safe-http` | aucun : une bibliothèque | aucun |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | La stabilité qu'exige une alpha de PHIL, l'enregistrement des bundles sans Flex, ce qu'il faut configurer ensuite. |
| [Membres](doc/fr/members.md) | Chaque membre : son rôle, ce qu'il ajoute, ce dont il a besoin, sa documentation. |
| [Mise à niveau](doc/fr/upgrading.md) | Comment les contraintes évoluent, et comment se passer d'un membre. |
| [Points d'extension](doc/fr/extension-points.md) | Où une application change le comportement : dans les membres. |

## Démarrage rapide

```sh
composer config minimum-stability alpha
composer config prefer-stable true
composer require phil/profile-secure-input
```

Pour se passer d'un membre — ici `phil/upload-scan-bundle` —, exigez directement les autres au lieu du profil :

```sh
composer remove phil/profile-secure-input
composer require phil/url-bundle:^0.1.3 phil/safe-http:^0.2 phil/rate-limit-bundle:^0.1 phil/spam-protection-bundle:^0.3
```

## Prérequis

PHP 8.5 ou une version plus récente et Symfony 8.2 ou une version plus récente, avec les extensions dont les membres
ont besoin : `ext-intl`, `ext-mbstring`, `ext-sodium` et `ext-filter`. Les membres sont des versions alpha : le projet
les accepte avec `minimum-stability: alpha` et `prefer-stable: true`. L'analyse des téléversements exige un démon
ClamAV.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
