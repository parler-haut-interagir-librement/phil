<!-- phil-dev source-sha256=99a3b89fa3cec7ed9b384c0040783aeeee0b5bc04b86ff6d5d30029f4e35ce66 -->
# Installation

```sh
composer require phil/number-words
```

Le paquet exige PHP 8.5 ou une version plus récente, avec `ext-intl`, l'extension qui apporte ICU à PHP. Sans elle, la
construction d'un `NumberWords` lève `IntlUnavailable` : la bibliothèque n'a aucune règle d'épellation de son cru
vers laquelle se rabattre.

Les mots et les jeux de règles viennent des données ICU de l'extension, et changent d'une version d'ICU à la suivante :
les ordinaux genrés et au pluriel de l'allemand, par exemple, n'existent que dans les données récentes, et une langue
sans jeux de règles genrés donne une seule forme pour tous les genres. Les tests du paquet attendent les mots que
donnent à l'identique ICU 76 et les versions suivantes. Aucune configuration ni aucun service n'est nécessaire ; dans
une application Symfony, un `NumberWords` par locale peut être construit là où on l'emploie, ou être enregistré comme
service avec sa locale en argument.
