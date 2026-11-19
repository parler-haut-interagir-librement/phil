<!-- phil-dev source-sha256=c07a90946b1cf46592c039b36f0ec33398126687f0f53515cda1eea38842acfc -->
# Le formulaire d'attribution

Avec `symfony/form` et `symfony/validator`, `AttributionType` modifie le crédit d'une ressource et rend une
`Attribution`, ou `null` pour un formulaire vide.

| Champ | Type | Ce qu'il contient |
|---|---|---|
| `kind` | `EnumType` de `ResourceKind` | Une source textuelle, une image, une carte, une police ou une autre ressource. |
| `title` | `TextType`, obligatoire | Le titre de la ressource. |
| `license` | `ChoiceType` | Les licences du catalogue, nommées dans la langue du lecteur. |
| `author` | `TextType` | Qui l'a créée. |
| `url` | `UrlType` | Une adresse http ou https absolue. |
| `notice` | `TextareaType` | La mention que son concédant demande de conserver. |
| `changes` | `TextareaType` | Comment l'œuvre a modifié la ressource. |
| `unchanged` | `CheckboxType` | « Aucune modification », qui remplit les modifications. |

Chaque texte est vérifié comme `Attribution` le vérifie, soit sa longueur une fois rogné, l'UTF-8, aucun caractère de
contrôle sauf les sauts de ligne et les tabulations, pour que les erreurs s'affichent sur leurs champs.

## Les modifications

Quand la licence de la ressource demande d'indiquer les modifications, soit Creative Commons Attribution et chacune de
ses variantes, les modifications sont obligatoires : un champ vide affiche `form.attribution.changes_required`, qui
nomme la licence. Une ressource inchangée le précise : la case « Aucune modification » remplit les modifications par
`form.attribution.unchanged_text` dans la langue du lecteur à la soumission du formulaire. Le formulaire fonctionne
sans JavaScript : réaffiché, le champ contient la phrase, et la case est cochée pour des modifications qui sont cette
phrase.

```php
$form = $this->createForm(AttributionType::class, $attribution);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    $declaration->attribute([...$declaration->attributions(), $form->getData()], $this->clock->now());
}
```

Une liste de ressources est une `CollectionType` d'`AttributionType`, donnée à `LicenseDeclaration::attribute()` une
fois soumise ; le gestionnaire d'entités enregistre la déclaration.

## Le rendu

Le préfixe de bloc est `phil_licensing_attribution` ; les libellés, les textes d'aide et les messages sont dans le
domaine `phil_licensing`, en anglais et en français. Les champs viennent dans l'ordre de lecture, « Aucune
modification » juste après les modifications, et s'affichent avec le thème de formulaire de l'application.
