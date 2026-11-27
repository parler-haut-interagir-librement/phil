<!-- phil-dev source-sha256=7eba934de4f60e526c2eae5d65e5d8803ee2a563558a59d2bd0020ef5bdd0ceb -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/profile-editorial` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-18

### Ajouté

- Le métapaquet `phil/profile-editorial`, qui installe les paquets PHIL qui publient du contenu éditorial dans une
  application Symfony : `phil/markup-bundle` `^0.1`, `phil/citations` `^0.1`, `phil/footnotes-sidenotes` `^0.1`,
  `phil/content-warnings-bundle` `^0.1` et `phil/structured-data-bundle` `^0.1`.
- La documentation : ce que fait chaque membre et pourquoi il appartient au profil, les fichiers qu'apporte chaque
  membre, la stabilité qu'exige une alpha de PHIL, l'enregistrement des bundles sans Flex, comment bougent les
  contraintes, et comment laisser de côté un membre en exigeant directement les autres.
- Un test fonctionnel qui démarre un noyau Symfony avec les bundles de chaque membre enregistrés.
