<!-- phil-dev source-sha256=76930aa3d43c6c910691ac02db61973eeae0e9daac144aff735ed6e0feae0ec1 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, bâtit des `ContainerBuilder` à la main : ni noyau, ni cache, ni réseau. Elle
couvre chaque adaptateur et chaque classe locale, les parents, les alias et le `CacheStorage` suivis, la borne de la
chaîne et une boucle, les messages avec et sans l'option qui coupe une limite, et la vérification en `prod` et hors de
`prod`. Dans le monodépôt, Infection tient le paquet à 100 % des mutants couverts.

## Un bundle qui utilise la garde

Un bundle teste son propre câblage en chargeant son extension dans un `ContainerBuilder` et en lisant
`RateLimitGuard::watched($container)`, et son refus avec un noyau démarré en `prod` sur son `cache.app` par défaut.
