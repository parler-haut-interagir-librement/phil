<!-- phil-dev source-sha256=8f1766c0525685fc1eb16743b3bb5cbf3b46cc29f836b6bad7740f37b4e7e3c7 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/money-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.3-alpha.

### Modifié

- L'association ORM de `Money` est écrite en attributs Doctrine, sur `Infrastructure\Doctrine\MoneyEmbeddable`, au lieu
  de `config/doctrine/Money.orm.xml`, qui est retiré : `Infrastructure\Doctrine\MoneyMappingDriver` les lit pour
  `Money`, et une passe de compilation du bundle l'ajoute au gestionnaire d'entités par défaut quand l'ORM est configuré
  et que `doctrine_mapping` est activé (`PhilMoneyBundle::DOCTRINE_MAPPING`). Les colonnes ne changent pas. Plus rien
  n'est ajouté en tête de `doctrine.orm.mappings` ; une application qui déclarait l'association `PhilMoney` sur un autre
  gestionnaire d'entités y ajoute plutôt le pilote avec `DoctrineOrmMappingsPass`.
- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`doctrine/orm` 3.8, `doctrine/dbal`
  4.6, `doctrine/doctrine-bundle` 3.4, `twig/twig` 3.31, …).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.2-alpha - 2026-11-20

Les montants suisses sont lus quelle que soit l'apostrophe qui les groupe.

### Corrigé

- `LocalizedDecimal::parse()` lit l'apostrophe typographique `’` comme l'apostrophe droite `'`, dans l'entrée et dans le
  séparateur de groupes de la locale : `1'234'567.5` et `1’234’567.5` sont tous deux lus en `de_CH`, que l'ICU du
  serveur groupe cette locale par l'une ou par l'autre.

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- La configuration `phil_money` — `currencies`, `default_currency`, `doctrine_mapping` — vérifiée d'après les données
  des devises, et `AcceptedCurrencies`. Une liste `currencies` ouverte n'offre, par `AcceptedCurrencies::all()` et
  `MoneyType`, que les devises en usage (ayant cours légal dans au moins un pays aujourd'hui), calculées une fois, et
  accepte chaque devise connue.
- Le type de colonne Doctrine `phil_currency` et l'association de `Phil\Money\Money` comme objet intégrable de l'ORM.
- `MoneyType` : un montant lu et écrit dans la locale de la requête sans nombre à virgule flottante, et un choix de
  devise quand plus d'une est offerte ; un `MoneyType` fixé à une devise enregistre le montant soumis dans cette devise,
  même quand le formulaire a été rempli à partir d'un montant dans une autre. Son transformateur de modèle
  `MoneyTransformer` et `LocalizedDecimal`, dont `parse()` n'accepte les groupes que de la taille de ceux de la locale
  avant la virgule décimale : `12,50` en `en`, `12.50` en `de` et `1,2,3` sont refusés.
- Les contraintes `AcceptedCurrency` et `MoneyRange`, avec des messages en anglais et en français, et le message
  français d'un montant illisible. `MoneyRange` refuse une borne qui n'est pas un décimal à sa construction, et arrondit
  vers l'intérieur de la plage une borne plus fine que la devise du montant, pour qu'aucun montant soumis ne le fasse
  lancer d'exception.
- Le filtre Twig `format_money`, qui avec `LocalizedDecimal` construit un formateur ICU par locale et garde ceux de 64
  locales au plus, et les fonctions Twig `money_schema` et `price_schema` : le `MonetaryAmount` et le prix d'une `Offer`
  de schema.org pour le JSON-LD.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
