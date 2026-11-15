<!-- phil-dev source-sha256=b93d4cb7cf95a35191f3efa23b3669ad502e86062e85b430de12bf3dbf6b9feb -->
# Le registre des documents

Chaque type de document commercial est déclaré dans un seul registre, chacun numéroté dans sa propre série sans trou :
le noyau déclare ses reçus et ses notes de crédit, un module les siens — un bordereau d'expédition, un bordereau de
retour —, l'application d'autres.

```yaml
phil_commerce:
    document_types:
        packing_slip: { prefix: 'PS-', digits: 6 }
        delivery_note: { prefix: 'DN-', visibility: unlisted }
```

```php
use Phil\CommerceBundle\Application\DocumentRegister;

// $register: DocumentRegister, from the container, inside the transaction that stores the document
$number = $register->next('packing_slip'); // PS-000001
```

- **Un type** est un code d'au plus 16 lettres minuscules, chiffres ou `_`, un préfixe d'au plus 16 caractères ASCII
  visibles, et de 1 à 18 chiffres ; un numéro prend 32 caractères au plus.
- **Deux types ne partagent jamais un code ni un préfixe**, si bien que deux séries ne donnent jamais le même numéro ;
  le noyau vérifie les types de la configuration à la construction du conteneur, et chaque type à la première lecture
  du registre.
- **Un module déclare ses types** avec un service qui implémente `DocumentTypes`, que l'autoconfiguration étiquette
  `phil_commerce.document_types` ([extension-points.md](extension-points.md#documenttypes)).
- **Les numéros viennent de `DocumentSeries`**, que `DoctrineDocumentNumbering` implémente avec une rangée par série de
  `phil_commerce_document_sequence`, verrouillée jusqu'à la fin de la transaction : un numéro pris est utilisé, ou
  rendu avec la transaction.

Les reçus et les notes de crédit gardent leur propre numérotation par `DocumentNumbering` ([receipts.md](receipts.md)) ;
le registre les connaît sous les codes `receipt` et `credit_note`, avec les préfixes de `phil_commerce.receipts`.

## Visibilité

Chaque type dit jusqu'où son acheteur voit un document du type, sur les trois niveaux de `Visibility` des canaux :

| `visibility` | L'acheteur |
|---|---|
| `listed` | Le trouve parmi les documents du compte : les reçus et les notes de crédit |
| `unlisted` | Y accède par son lien direct seulement — le lien du courriel qui l'envoie — jamais dans une liste |
| `hidden` | Ne le voit jamais : un bordereau d'expédition, une liste de cueillette, gardés pour la boutique ; la valeur par défaut |

`DocumentRegister::listed()` donne les types que montre la liste de documents d'un acheteur ; `type($code)->visibility`
dit à une page si un lien peut ouvrir un document. Un type est caché sauf si sa déclaration dit autrement, si bien que le
nouveau document d'un module n'atteint jamais un acheteur par erreur.
