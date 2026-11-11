<!-- phil-dev source-sha256=b4aa4452e94fccd4972ca683b45c17a481ad3b3ec0a2b652bcd9e4f7c788fcc8 -->
# Twig

Quand Twig est installé, le bundle ajoute deux filtres, chacun aussi disponible comme fonction :

| Filtre ou fonction | Donne |
|---|---|
| `ujo_html` | le HTML d'un document ou de son JSON, vérifié et purifié, marqué sûr |
| `ujo_text` | son texte brut, échappé par Twig comme n'importe quel texte |

```twig
<article>{{ article.body|ujo_html }}</article>
<aside>{{ ujo_html(article.summary) }}</aside>
<meta name="description" content="{{ article.body|ujo_text|u.truncate(160, '…') }}">
```

La valeur est un `Phil\Ujo\Document`, un texte JSON ou `null`, qui ne produit rien. Le document est normalisé de
nouveau avant son rendu : un document stocké suit donc les règles actuelles (un type retiré du registre, un
assainisseur resserré). Un document qui n'est plus valide lance l'exception de la bibliothèque, comme toute erreur d'un
gabarit ; vérifiez les documents à leur enregistrement, avec [le champ et la contrainte](forms-and-validation.md), pour
que cela n'arrive pas.

Le HTML de chaque type et les classes qu'il porte sont décrits dans le `doc/html-rendering.md` de `phil/ujo`.
