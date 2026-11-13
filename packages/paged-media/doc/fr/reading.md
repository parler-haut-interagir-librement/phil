<!-- phil-dev source-sha256=495799465304d1ac93090a1ad0be25362c8b53fcdb6602455a8e806acd0390ed -->
# Lire un PDF

Les routes de lecture répondent en JSON, une entrée par PDF sous son nom de fichier ; le client la lit sous forme de
valeurs typées. Chacune accepte le mot de passe d'un PDF chiffré.

| Méthode | Réponse, par nom de fichier |
|---|---|
| `info($files, $password)` | `PdfInfo` : `pages`, `version`, `encrypted`, `tagged`, `linearized`, `formFields` |
| `metadata($files, $password)` | `DocumentInformation` : le titre, l'auteur, le sujet, les mots-clés, le créateur, le producteur et les dates, chacun null s'il n'est pas défini |
| `bookmarks($files, $password)` | `list<Bookmark>`, imbriqués par leurs `children`, sous la forme qu'écrit `SetBookmarks` |
| `attachments($files, $password)` | `list<Attachment>` : `name`, et `mimeType` et `relationship` quand le PDF les indique |
| `form($files, $password, $pages)` | `list<FormField>` : le `name` pleinement qualifié, le `type` (`FormFieldType`), la `value`, les `pages`, les `states` d'une case à cocher ou d'un groupe de boutons radio, les `options` d'un choix, et `readOnly`, `required`, `multiSelect`, `editable`, `maxLength` |
| `validate($files, $profile, $password)` | `ValidationReport` ([Conformité](conformance.md)) |

```php
$info = $gotenberg->info(Files::of(InputFile::fromPath('report.pdf')))['report.pdf'];
$info->pages;       // 3

$fields = $gotenberg->form(Files::of($pdf), pages: '2')['form.pdf'];
$gotenberg->run(Files::of($pdf), new SetForm([$fields[0]->name => $fields[0]->options[0]]));
```

Une date revient telle que le PDF la conserve : une date PDF, `D:20260101120000Z`, ou une date XMP quand seules les
métadonnées XMP la contiennent. `SetMetadata::from()` écrit ce que `metadata()` a lu. Un JSON d'une autre forme que la
réponse de la route lève `UnexpectedResponse`.
