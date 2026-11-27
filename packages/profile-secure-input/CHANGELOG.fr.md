<!-- phil-dev source-sha256=7fe848cdde3bbc2ca9420553606ab0c41965a3f24a61bd988bcb9a7bc15b6204 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/profile-secure-input` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-24

### Corrigé

- Il suit `phil/spam-protection-bundle` 0.3, la mineure publiée le 2026-11-20, au lieu de 0.2, à côté de laquelle il ne
  s'installait pas. En production, les jetons déjà employés demandent une connexion Doctrine ou une fabrique de verrous
  que partagent tous les serveurs.

## 0.1.1-alpha - 2026-11-20

Une application installe le profil sans `doctrine/dbal` et sans sessions. Une version corrective et non mineure : les
membres gardent leurs versions mineures, seuls leurs planchers bougent.

### Modifié

- Il exige `phil/url-bundle` 0.1.3 ou plus récent, qui démarre sans `doctrine/dbal`, et `phil/upload-scan-bundle` 0.1.1
  ou plus récent, dont la page de quarantaine vérifie son jeton par l'origine de la requête, sans session.
- La documentation ne demande plus à l'application `doctrine/dbal`, ni la protection CSRF avec sessions.

## 0.1.0-alpha - 2026-11-18

### Ajouté

- Le métapaquet `phil/profile-secure-input`, qui installe les paquets PHIL qui gardent ce que les usagers envoient à une
  application Symfony : `phil/url-bundle` `^0.1`, `phil/safe-http` `^0.2`, `phil/rate-limit-bundle` `^0.1`,
  `phil/spam-protection-bundle` `^0.2` et `phil/upload-scan-bundle` `^0.1`.
- La documentation : ce que fait chaque membre et pourquoi il appartient au profil, les fichiers qu'apporte chaque
  membre, la stabilité qu'exige une alpha de PHIL, l'enregistrement des bundles sans Flex, comment bougent les
  contraintes, et comment laisser de côté un membre en exigeant directement les autres.
- Un test fonctionnel qui démarre un noyau Symfony avec les bundles de chaque membre enregistrés.
