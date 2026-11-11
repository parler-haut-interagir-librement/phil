<!-- phil-dev source-sha256=410a3e4d4c8a1e2557aed55235d3cc522d1740399c68e904b41772bcb196d16f -->
# Accessibilité

| Point | Ce que fait la sortie |
|---|---|
| Renvoi et note | Un lien vers la note, avec le rôle `doc-noteref` et le nom accessible « Note 1 », et un lien de retour, avec `doc-backlink` et « Back to reference 1 » |
| Une note appelée plusieurs fois | Un lien de retour par renvoi, chacun nommé avec son occurrence |
| La région des notes | Une `section` avec `doc-endnotes`, nommée par son titre au moyen de `aria-labelledby` |
| La liste des notes | `role="list"` sur le `ol` : la feuille de style retire ses puces, ce qui enlèverait la sémantique de liste dans certains navigateurs |
| Les notes de marge | `role="note"` sur la note, placée dans le texte juste après son appel : un lecteur d'écran rencontre les deux ensemble |
| La bascule des écrans étroits | Une vraie case à cocher, qui reçoit le focus et s'appelle « Show note 1 », avec un anneau de focus visible sur son libellé et une cible d'au moins 24 pixels |
| Sans script | Tout fonctionne : un lien, une case à cocher et du CSS |
| Réduction des animations | La seule animation de la feuille, l'ouverture d'une note, ne s'exécute que sous `prefers-reduced-motion: no-preference` |
| Un lien qui ouvre une note | `:target` affiche une note repliée sur un écran étroit et la marque de l'anneau de focus |
| Plusieurs documents sur une page | Chaque identifiant commence par un préfixe ; donnez-en deux quand un document est affiché deux fois |

## Libellés

`NoteLabels` contient les mots qu'un lecteur entend ou voit. `NoteLabels::english()`, `french()` et `forLocale()`
donnent ceux que le paquet fournit. Une application construit les siens pour une autre langue, avec `%label%` et
`%occurrence%` dedans.

```php
new NoteLabels('Anmerkungen', 'Anmerkung %label%', 'Zurück zu Verweis %label%', 'Zurück zu Verweis %label%, Vorkommen %occurrence%', 'Anmerkung %label% zeigen');
```
