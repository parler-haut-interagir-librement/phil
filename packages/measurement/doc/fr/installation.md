<!-- phil-dev source-sha256=4abd42c744b774bdcde5272e26a349b78bdc22472b52b780530852906831f3ee -->
# Installation

```sh
composer require phil/measurement
```

Le paquet exige PHP 8.5 ou une version plus récente, avec l'extension `intl`, dont ICU fournit les symboles des
nombres, les règles de pluriel et les motifs de liste de chaque locale, et l'extension `mbstring`. Il n'a aucune autre
dépendance.

Les données de CLDR qu'il lit — préférences d'unités et motifs d'unités — sont livrées avec lui sous `data/cldr/`, à la
version nommée dans `data/cldr/VERSION` : rien n'est téléchargé à l'exécution.
[Canaux, préférences et mise en forme](cldr.md#les-données-de-cldr) explique comment les données sont importées de nouveau
pour une nouvelle version de CLDR.

Avec Symfony, `phil/measurement-bundle` enregistre les services, un type de formulaire, des contraintes de validation,
des filtres Twig et un type de colonne Doctrine.
