<!-- phil-dev source-sha256=62cd99401491ccb9c048c7f157da36e27611694bd1bb3bdd91ca5f721ef31095 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/link-checker` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-24

### Ajouté

- `LinkExtractor` : les liens d'un contenu HTML — `a[href]`, `img[src]` et `srcset`, `link[href]`, `iframe[src]`,
  `source` — lus par `Dom\HTMLDocument` dans l'ordre du document, résolus d'après la page ou son premier `<base href>`
  selon la norme URL du WHATWG et lus par phil/url ; les autres schémas, les liens vides et `preconnect` laissés de côté
  ; les adresses qui ne peuvent pas être analysées gardées comme invalides, celles qui ne sont pas publiques comme
  refusées ; les mots de chaque lien sur une ligne ; borné en octets et en liens, avec `ExtractedLinks::$truncated`.
- `UrlNormalizer` : la clé de dédoublonnage sans le fragment, la requête triée par nom seulement avec `sortQuery`.
- `HttpLinkProbe` par-dessus phil/safe-http : `HEAD`, puis un `GET` qui ne lit aucun corps après une 405 ou une 501, ou
  un `GET` qui lit le HTML d'une page interne ; les réseaux privés refusés ; les redirections suivies dans les limites,
  permanentes seulement quand chacune est une 301 ou une 308 ; les 404 et 410 et les hôtes inconnus morts ; tout le
  reste à revoir, avec une courte raison ; le `User-Agent` `PhilLinkChecker/0.1`, fixé par les limites.
- `HostThrottle` et `IntervalThrottle` : un rythme par hôte sur une horloge Symfony, borné en hôtes, remis à zéro sur
  demande.
- `LinkChecker` : chaque adresse vérifiée une fois par lot, les résultats gardés dans un cache Symfony par une clé
  xxh128, un résultat à revoir pour une durée plus courte ; les ancres du contenu lui-même et des pages du site
  cherchées par `id` et `a[name]`, par `InternalPages` sans requête ou par HTTP, jamais jugées sur une page lue en
  partie ; des lots par `check(iterable)`.
- `LinkReport` et `CheckedLink` : les liens d'un contenu avec leur place, leur statut, leur code, leur cible et leur
  raison, triés en brisés, à revoir, redirigés et refusés, rendus en texte et en JSON.
- Les ports `LinkProbe`, `InternalPages` et `DeadLinkFixer`, et `InvalidOption` sous `LinkCheckerException`.
- La cible de fuzzing `extract`, le banc d'essai `ExtractBench`, et la documentation dans `doc/` : installation,
  vérification, points d'extension, intégration continue avec lychee, tests et référence.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
