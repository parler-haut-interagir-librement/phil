<!-- phil-dev source-sha256=97ae2a89f5fc01f121b19e5df1208e78c8b81f9fdeecd2dd330658c290640d84 -->
# Points d'extension

La librairie est du PHP simple et ne déclare ni service ni interface. Le paquet est en `0.x` : ses types ne sont
stables qu'à partir de sa version 1.0 ; d'ici là, une version mineure peut les changer, et son CHANGELOG dit comment.

## Déclarer les locales

Un bundle lit les locales dans sa configuration et les construit une seule fois, à la construction du conteneur, pour
qu'une déclaration mal formée échoue là et non à la première requête :

```php
$services->set(DeclaredLocales::class)
    ->factory([DeclaredLocales::class, 'fromArray'])
    ->args([$config['locales']]);
```

```yaml
locales:
    fr_CA: [fr, en]
    fr: [en]
    en: []
```

Gardez les clés telles qu'elles sont écrites : la normalisation par défaut de la configuration de Symfony lirait
`fr-CA` comme `fr_CA`, là où la librairie le refuse.

## Parcourir une chaîne

Une lecture demande la chaîne de la locale demandée, puis donne à `resolve()` les locales qui ont quelque chose à
servir — les lignes d'une page, les traductions d'une fiche —, lues en une requête pour les locales de la chaîne :

```php
$chain = $locales->chainFor($requested);
$available = $repository->localesOf($record, $chain->locales);
$served = $chain->resolve($available) ?? throw new NotFoundHttpException();
```

Une locale tapée par un visiteur est lue par `Locale::fromString()` ; son refus, et celui de `chainFor()` pour une
locale qui n'est pas servie, sont l'endroit où répondre par une 404.
