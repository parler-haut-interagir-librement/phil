<!-- phil-dev source-sha256=ec50b2672a1fd07e6c82fdc3da1dc8a89c39cb2743b1fef4e6ff8e5cde07081b -->
# Déclarations

Un `LicenseDeclaration` est la licence d'un sujet, attachée par un `SubjectReference` : un type (`book`, ou un nom de
classe) de 1 à 128 lettres, chiffres, `_`, `.`, `:`, `\` ou `-`, et un identifiant de 1 à 64 caractères ASCII
imprimables. Un objet de l'application qui implémente `Licensable` donne sa propre référence.

```php
$declaration = LicenseDeclaration::declare(SubjectReference::of('book', 42), $catalog->get(LicenseCatalog::OGL), $now);
$declaration->describe(
    notice: 'Copyright 2026, Example Press.',
    reservedMaterial: null,
    productIdentity: 'The names, logos and artwork of the setting.',
    designatedContent: 'The rules of chapters 3 to 5.',
    at: $now,
);
$declaration->includeLicenseText($oglText, $now);   // the text the application provides
$declaration->attribute([
    Attribution::of(ResourceKind::Text, 'System Reference Document', LicenseCatalog::OGL, notice: $section15Line),
], $now);
$declaration->relicense($catalog->get(LicenseCatalog::ORC), $now);
```

Chaque changement date `changedAt()`. Un texte vide vaut `null` ; les textes sont en UTF-8, sans caractère de contrôle
sauf les tabulations et les sauts de ligne.

| Champ | Au plus |
|---|---|
| Mention | 4 000 caractères |
| Reserved Material, Product Identity, contenu désigné | 20 000 caractères chacun |
| Texte de la licence | 100 000 caractères |
| Attributions | 500 |

## Attributions

Un `Attribution` crédite une ressource : son `ResourceKind` (`text`, `image`, `map`, `font`, `other`), un titre d'au
plus 255 caractères, le code de sa licence et, de façon facultative, son auteur (255), une adresse http ou https (2
048), la mention que son concédant demande de conserver (4 000) et les modifications qui y ont été apportées (1 000). La
déclaration les stocke en JSON, dans l'ordre ; `attributions()` les relit.

## Le dépôt

`LicenseDeclarationRepository` trouve la déclaration d'un sujet, en ajoute et en retire une ; avec Doctrine, l'ajout et
le retrait prennent effet quand l'application vide son gestionnaire d'entités. La licence est conservée par son code :
une licence retirée du catalogue laisse la déclaration lisible, et la vérification de conformité le signale.
