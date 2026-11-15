<!-- phil-dev source-sha256=72e6c4aa188a51e95eb0f63b8fcd25f8190233b113e6ab88c4b8a8e063b27ab2 -->
# Console

## phil:terms:outstanding

```sh
bin/console phil:terms:outstanding terms             # the members not in order with the policy now
bin/console phil:terms:outstanding terms --latest    # those who have not accepted its latest material version
bin/console phil:terms:outstanding terms --count     # their number only
```

Les membres sont lus dans la source des membres par lots de 500 et énumérés à raison d'un identifiant par ligne, tels
quels, pour qu'une autre commande puisse les lire. Une politique non déclarée se termine avec le statut `2` (invalide).

## phil:terms:check-legal-links

```sh
bin/console phil:terms:check-legal-links
```

Quand `phil/legal-notices-bundle` est activé, vérifie que les liens légaux existent sur le site, une ligne pour chaque
élément manquant :

| Code | Ce qu'il signifie |
|---|---|
| `missing_route` | Une route des liens de pied de page n'est pas importée |
| `missing_document` | L'avis légal ou la politique de confidentialité n'a aucune version en vigueur |
| `missing_policy` | Une politique requise n'est pas dans `phil_legal_notices.footer.terms_policies` |
| `not_in_layout` | `pages.layout` n'affiche aucun lien vers cette adresse |
| `unreadable_layout` | Le rendu de `pages.layout` est impossible |

Les liens attendus sont ceux de `phil_legal_notices_links()`, y compris la déclaration d'accessibilité quand
`phil/accessibility-statement-bundle` l'ajoute. Le rendu de la mise en page est produit comme celui d'une page, avec
une requête pour `/` et sans variable propre. La commande se termine avec `1` quand un élément manque, pour qu'un
déploiement s'y arrête.
