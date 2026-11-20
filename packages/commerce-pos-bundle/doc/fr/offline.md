<!-- phil-dev source-sha256=f38d3f0870ae3f5ee352730148cd9b38613e4b0af27f9ebec774222593eec2cf -->
# Ventes hors ligne

La caisse principale — la tablette au comptoir — continue de vendre quand elle perd sa connexion ; une caisse mobile
attend plutôt le réseau ([devices.md](devices.md)). L'écran de la caisse principale donne un identifiant à chaque
vente, et le contrôleur Stimulus du module garde les ventes dans l'IndexedDB du navigateur jusqu'à ce que le serveur les
prenne.

## Dans le navigateur

```html
<div data-controller="phil--commerce-pos-bundle--offline-queue"
     data-phil--commerce-pos-bundle--offline-queue-url-value="/register/offline-sales"
     data-action="pos:sale->phil--commerce-pos-bundle--offline-queue#sell">
    Waiting: <span data-phil--commerce-pos-bundle--offline-queue-target="count">0</span>
</div>
```

Le script propre à la caisse émet `pos:sale` avec la vente dans `detail.sale`, son identifiant tiré de `newSaleId()`
de `offline_queue.js`. Le contrôleur la met en file d'attente dans une base IndexedDB à lui — nommée par sa valeur `database`,
`phil-commerce-pos-offline-sales` par défaut —, puis envoie la file dans l'ordre ; il l'envoie de nouveau quand la page
se connecte et chaque fois que le navigateur revient en ligne. La base survit à un rechargement, à un onglet fermé et à
une tablette redémarrée ; une vente mise deux fois en file d'attente sous son identifiant n'est gardée qu'une fois.

| Réponse | Ce que fait la file d'attente |
|---|---|
| 2xx dont le corps JSON nomme l'identifiant de la vente | La vente est faite, importée maintenant ou avant |
| 4xx autre que 401, 408 et 429 | La vente est mise de côté parmi les rejetées, avec son statut, pour que quelqu'un l'examine |
| Aucune, une redirection, 401, 408, 429, 5xx, ou un 2xx qui ne nomme pas la vente | L'envoi s'arrête ; la vente et celles qui la suivent attendent |

Le contrôleur ne suit jamais une redirection (`redirect: 'manual'`) : une session qui a expiré pendant que la caisse
était hors ligne répond par une redirection vers la page de connexion, et la vente attend que le caissier se reconnecte,
au lieu d'être prise pour envoyée. Une vente quitte la file par son identifiant une fois la réponse reçue : deux
onglets qui vident le même stockage envoient donc une vente deux fois au pire — ce que le serveur reconnaît — et n'en
perdent jamais une non envoyée.

Après chaque envoi, le contrôleur émet `flushed` avec `{sent, rejected, left}`.

## Sur le serveur

`OfflineSaleController` lit une vente, envoyée en `application/json` — tout autre type est refusé avec `415`, pour
qu'un formulaire d'un autre site ne puisse pas en publier une sans que le navigateur demande d'abord :

```json
{"id": "0192f3c4-…", "session": "0192f3a0-…", "by": "cashier-7", "rung_at": "2026-11-03T14:05:00-05:00",
 "total": 2415, "tip": 200,
 "lines": [{"type": "product", "id": "TEA-1", "quantity": 2}],
 "payments": [{"method": "cash", "amount": 1415}, {"method": "debit", "amount": 1000, "approval": "A1B2C3"}]}
```

`OfflineSales` ne l'importe que dans une session d'une caisse principale (sinon `409`), et jamais par-dessus une
commande qui existe déjà : une vente mise en attente sur un téléphone est reprise au comptoir avec le réseau, jamais
hors ligne. Elle la transforme en commande numérotée par son identifiant, enregistrée dans sa session, **avec les prix
recalculés par le serveur** — les prix, les promotions et les taxes du moment —, et prend ses paiements jusqu'à ce que
coûte la commande. La caisse a déjà rendu la monnaie : l'argent comptant reçu est l'argent comptant dû, arrondi. **Un
écart n'est jamais facturé ni remboursé en silence** : la vente importée le garde, le total du serveur moins celui de
la caisse, pour que quelqu'un le révise.

`rung_at` est un moment RFC 3339 avec les secondes et un décalage ou `Z`, une fraction étant permise : ce que donne
`Date.prototype.toISOString()`, `2026-11-03T19:05:00.000Z`, est lu. Une date ou une heure qui n'existe pas — le 30
février — est refusée, pas reportée. `total`, `tip` et le `amount` de chaque paiement valent de 0 à 10^12 unités
mineures, la `quantity` d'une ligne de 1 à 100000, et chaque paiement est en argent comptant ou dans une méthode que la
caisse enregistre (`recorded_methods`).

| Réponse | Sens |
|---|---|
| `201` | Importée maintenant : `{"id": …, "discrepancy": 0}` |
| `200` | Déjà importée : la même réponse, rien de fait |
| `403` | Le `OfflineSaleGate` de l'application a refusé la vente |
| `409` | La session est inconnue ou fermée — synchronisez avant de fermer la session —, sa caisse est mobile, ou l'identifiant de la vente est déjà le numéro d'une commande |
| `415` | Le corps n'a pas été envoyé en `application/json` |
| `422` | La vente ne peut pas être lue, sort des bornes ou déborde une fois les prix recalculés, est payée par une méthode que la caisse ne prend pas, ou vend ce qui n'est plus vendu |

## Qui peut envoyer une vente

La vente nomme son caissier, `by`, et sa session, tels que la caisse les a envoyés. Placer le contrôleur derrière le
contrôle d'accès de l'application décide qui peut publier tout court ; **lier la vente à l'utilisateur connecté
relève de l'application**, par `Application\Contract\OfflineSaleGate`, que `OfflineSales` reçoit par injection
automatique :

```php
final class SignedInCashier implements OfflineSaleGate
{
    public function allows(OfflineSale $sale, CashSession $session): bool
    {
        $user = $this->security->getUser();

        return null !== $user
            && $sale->by === $user->getUserIdentifier()
            && $this->rosters->works($user, $session->register);
    }
}
```

Sans porte, le module prend `by` et `session` tels qu'envoyés : tout utilisateur autorisé à publier pourrait importer
une vente dans la session d'une autre caisse, au nom d'un autre caissier. Gardez le témoin de session `SameSite=Lax` de
Symfony, et ajoutez un en-tête CSRF propre à l'application si ses témoins sont `SameSite=None`.
