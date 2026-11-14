<!-- phil-dev source-sha256=c8fb436e60f36b17744972db040c5f8c66740e53bbecf97ef4a5f6b3f51352b8 -->
# Points d'extension

La librairie est du PHP simple et ne déclare ni service ni interface. Le paquet est en `0.x` : ses types ne sont
stables qu'à partir de sa version 1.0 ; d'ici là, une version mineure peut les changer, et son CHANGELOG dit comment.

## Déclarer les locales

Un bundle lit les locales dans sa configuration et les construit une seule fois, à la construction du conteneur, pour
qu'une déclaration mal formée échoue là et non à la première requête :

```php
$services->set(DeclaredLocales::class)
    ->factory([DeclaredLocales::class, 'fromArray'])
    ->args([$config['locales'], $config['default_fallbacks']]);
```

```yaml
locales:
    fr_CA: [fr, en]
    fr: [en]
    en_CA: ~        # the default chain: en_CA → en
    en: []
default_fallbacks: [en]
```

Une locale écrite `~` reçoit la chaîne par défaut, dérivée comme le traducteur de Symfony la dérive et coupée aux locales
servies ; `default_fallbacks` est la liste que contient l'option `fallbacks` du traducteur, quand l'application veut que
les deux s'accordent.

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

Une locale tapée par un visiteur est lue par `Locale::tryFromString()`, dont le `null`, comme le refus de `chainFor()`
pour une locale qui n'est pas servie, est l'endroit où répondre par une 404.
