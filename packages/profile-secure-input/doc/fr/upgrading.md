<!-- phil-dev source-sha256=2c40c05cfb85d74c4150ac927e4332339a448f4133d7aef9d69ba6f4f1003045 -->
# Mise à niveau

## Comment les contraintes évoluent

Le profil exige chaque membre à sa dernière version mineure publiée, `^0.x`. Sous la 1.0, une contrainte avec
circonflexe garde la version mineure : `^0.1` accepte `0.1.3-alpha`, jamais `0.2.0-alpha`. Ainsi `composer update`
apporte les correctifs de chaque membre, et une nouvelle version mineure d'un membre — qui peut changer son API —
n'arrive qu'avec une version du profil qui relève sa contrainte. Le CHANGELOG du profil énumère ces versions ; le
CHANGELOG du membre dit ce que sa nouvelle version mineure change.

## Mettre à jour

```sh
composer update phil/profile-secure-input --with-dependencies
```

`--with-dependencies` permet à Composer de mettre à jour les membres avec le profil.

## Se passer d'un membre

Le profil est une commodité : une application qui ne veut pas d'un membre exige directement les autres, aux contraintes
que donne le profil, et retire le profil. Sans `phil/upload-scan-bundle`, par exemple :

```sh
composer remove phil/profile-secure-input
composer require phil/url-bundle:^0.1 phil/safe-http:^0.2 phil/rate-limit-bundle:^0.1 phil/spam-protection-bundle:^0.2
```

Les bundles déjà enregistrés restent dans `config/bundles.php` ; retirez la ligne du membre écarté, et son fichier de
configuration si l'application en a écrit un.

## Retenir un membre

Pour garder un membre à une version plus ancienne pendant que le profil avance, exigez directement ce membre à la
version voulue : Composer satisfait alors les deux contraintes, ou dit pourquoi il ne le peut pas.
