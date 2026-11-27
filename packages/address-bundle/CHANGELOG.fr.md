<!-- phil-dev source-sha256=134e50770219c32385e0efb64f575110f917c4a596a442af1bd5af1d1130886a -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/address-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.2-alpha.

Tests seulement.

### Modifié

- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.1-alpha - 2026-11-24

### Modifié

- Les tests Node résolvent `@hotwired/stimulus` vers leur substitut par un hook synchrone donné à
  `module.registerHooks()`, sur le fil de chargement, au lieu de `module.register()`, que Node marque obsolète
  (DEP0205).
- Exige la dernière mineure de chaque dépendance (`symfony/translation-contracts` 3.8), and of each development
  requirement (`doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31).

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `CountryFields` et `KnownCountries`.
- `ValidAddress` et son validateur.
- `AddressColumnType`.
- `PhilAddressBundle`, alias d'extension `phil_address`, avec les clés `default_country` et `format_url`.
- `AddressType` et `AddressTransformer` : le formulaire ne garde que les champs qu'écrit le pays soumis, si bien que
  changer le pays d'une adresse en cours de modification retire les champs cachés de l'autre pays, et qu'une violation
  sur un champ que le formulaire ne montre pas va au formulaire. L'URL du format que le formulaire donne au contrôleur
  demande les champs du destinataire avec `with_recipient`.
- `AddressFormatController` et `config/routes.php` : le point d'accès du format prend sa langue du paramètre de requête
  `locale` que le formulaire écrit dans l'URL, et n'est public qu'alors ; sans lui, la réponse suit la langue de la
  requête et est privée, si bien qu'un cache partagé ne sert jamais les libellés d'une langue à une personne qui en lit
  une autre.
- `AddressExtension`, avec la fonction Twig `address_schema` : des données schema.org pour le JSON-LD. La suggestion
  `twig/twig` nomme les filtres et les fonctions ; le bundle n'a pas de thème de formulaire.
- Le contrôleur Stimulus `address`, dans `assets/`, qui n'applique que la réponse pour le pays encore choisi, et
  déclenche `failed` quand la requête échoue.
- Les libellés et les messages en anglais et en français, dans `translations/`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
