<!-- phil-dev source-sha256=18f4c33174a5a948bd61a3d449a308a4865df452c51c3e16c4cf667ec3fcb84d -->
# Mise à niveau

## Comment les contraintes évoluent

Le profil exige chaque membre à sa dernière version mineure publiée, `^0.x`. Sous la 1.0, une contrainte avec
circonflexe garde la version mineure : `^0.1` accepte `0.1.3-alpha`, jamais `0.2.0-alpha`. Ainsi `composer update`
apporte les correctifs de chaque membre, et une nouvelle version mineure d'un membre — qui peut changer son API —
n'arrive qu'avec une version du profil qui relève sa contrainte. Le CHANGELOG du profil énumère ces versions ; le
CHANGELOG du membre dit ce que sa nouvelle version mineure change.

## Mettre à jour

```sh
composer update phil/profile-editorial --with-dependencies
```

`--with-dependencies` permet à Composer de mettre à jour les membres avec le profil.

## Se passer d'un membre

Le profil est une commodité : une application qui ne veut pas d'un membre exige directement les autres, aux contraintes
que donne le profil, et retire le profil. Sans `phil/content-warnings-bundle`, par exemple :

```sh
composer remove phil/profile-editorial
composer require phil/markup-bundle:^0.1 phil/citations:^0.1 phil/footnotes-sidenotes:^0.1 phil/structured-data-bundle:^0.1
```

Les bundles déjà enregistrés restent dans `config/bundles.php` ; retirez la ligne du membre écarté, et son fichier de
configuration si l'application en a écrit un.

## Retenir un membre

Pour garder un membre à une version plus ancienne pendant que le profil avance, exigez directement ce membre à la
version voulue : Composer satisfait alors les deux contraintes, ou dit pourquoi il ne le peut pas.
