<!-- phil-dev source-sha256=dc1ed90653db69c171bcb98ced2271cdedcd2eec6f2581ff262ad74efe449b5f -->
# Actions et modification

## Actions

```php
$table
    ->action(Action::row('archive', 'books.archive', 'BOOK_ARCHIVE', $this->archive(...))
        ->alsoOnSelection()
        ->confirmedBy('books.archive.question')
        ->destructive())
    ->action(Action::bulk('tag', 'books.tag', 'BOOK_TAG', $this->tag(...)));
```

Un gestionnaire reçoit les lignes, relues dans la source de données du tableau. Une action demande une confirmation, à
moins que `withoutConfirmation()` dise le contraire, et un navigateur sans JavaScript la reçoit toujours. La question est
un `<dialog>` natif, modal avec JavaScript et affiché dans la page sans lui ; voyez [Accessibilité](accessibility.md). Un
gestionnaire lance `ActionRefused` avec une clé de traduction pour dire à la personne pourquoi il s'est arrêté.

## Modification sur place

```php
Column::text('title', 'books.title')->editable(new Editing(
    'BOOK_EDIT',
    function (Book $book, string $title): void {
        $book->rename($title);
        $this->entities->flush();
    },
    [new NotBlank(), new Length(max: 200)],
));
```

La valeur parvient à la fonction typée selon sa colonne : une chaîne, un nombre, un `DateTimeImmutable`, un `bool`, le
cas d'une énumération adossée ou la valeur d'un choix. Un nombre, une date ou une énumération vidé vaut `null`. Les
liens et les blocs ne peuvent pas être modifiés.

Quand le tableau déclare `version()`, une modification part de la version de la ligne et est refusée quand la ligne a
changé depuis. La valeur courante s'affiche alors de nouveau avec un message. La fonction peut aussi lancer
`EditConflict`, autour d'une `OptimisticLockException` par exemple, ou `ActionRefused`.

Quand un tri, un filtre ou une autre page retire de la page la ligne en cours de modification, la modification reste
dans l'adresse et la région active dit que la ligne n'est plus dans la page ; le contrôle revient avec la ligne.

## Ce qui est vérifié à chaque appel

| Vérification | Action | Modification |
|---|---|---|
| L'attribut du tableau, quand il en a un | oui | oui |
| Le CSRF sans état : le jeton `phil_data_table` et l'origine du navigateur | oui | oui |
| L'action ou la colonne est déclarée, pour ce nombre de lignes | oui | oui |
| Les lignes appartiennent à la source de données du tableau | oui | oui |
| L'attribut de *voter*, sur chaque ligne | oui | oui |
| La version de la ligne | | oui |
| Le type de la valeur, puis Symfony Validator | | oui |

Afficher un bouton n'est pas une permission : les boutons sont cachés quand le *voter* refuse, et la vérification
s'exécute de nouveau quand l'appel arrive. Un simple formulaire porte aussi le contexte du tableau avec un sceau tiré de
`kernel.secret`, et la page vers laquelle il revient doit être un chemin de ce site.

Quand la modification d'un simple formulaire s'arrête, le gabarit `@PhilDataTable/table/stopped.html.twig` dit pourquoi,
avec un lien de retour vers le tableau. Redéfinissez-le sous `templates/bundles/PhilDataTableBundle/table/` pour
l'habiller.
