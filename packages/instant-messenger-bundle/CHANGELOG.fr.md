<!-- phil-dev source-sha256=1f1ba784a44d330cac12ebb9c6863f5d4a3a134a39d0b5f60ff88837f9884dea -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/instant-messenger-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.1-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`twig/twig` 3.31).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `AcceptedMessengers`.
- `PhilInstantMessengerBundle`, alias d'extension `phil_instant_messenger`.
- `HandleType` et `HandleTransformer`, qui montre un identifiant comme les gens le lisent seulement quand son service
  relit ce texte comme le même identifiant, et sa forme canonique sinon, si bien qu'un formulaire de modification soumis
  sans changement est toujours valide ; `HandleOfType`, dont le `@param` nomme l'`EnumSet` qu'il accepte, et son
  validateur.
- `MessengerExtension`.
- Les messages en anglais et en français, dans `translations/`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; l'exemple de stockage de
  `doc/forms-and-validation.md` reprend l'identifiant avec `Handle::fromCanonical()`, si bien qu'un numéro de téléphone
  que les plans de numérotation ne connaissent pas n'empêche pas l'entité de se charger.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
