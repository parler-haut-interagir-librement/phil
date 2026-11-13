<!-- phil-dev source-sha256=11a4fdc1dcb13e9a6a4d2c73706832cc50be9ed37edb8e4becf9de9853e28873 -->
# Échange

```php
$redemption = $codes->redeem($typed, 'member-1842', $request->getClientIp() ?? 'unknown');

if ($redemption->isRedeemed()) {
    $this->unlock('member-1842', $redemption->batchId);
}
```

## Les étapes

1. La personne qui échange est vérifiée : un identifiant stable de l'application, de 1 à 191 octets, jamais une adresse
   courriel — une valeur contenant `@` est refusée par `InvalidConfiguration`, avant qu'une tentative soit dépensée.
2. Une tentative est dépensée sur le limiteur de débit, avec le troisième argument pour clé : l'adresse du client, un
   compte, une session. Une fois les tentatives épuisées, la réponse est `TooManyAttempts` avec `retryAfter`, et rien
   n'est recherché.
3. Le code est lu selon son format ; un code qu'il refuse donne `Unknown`.
4. L'empreinte du code est calculée sous chaque clé, et le magasin l'échange en une seule étape atomique.
5. Quand rien n'a été échangé, le magasin dit pourquoi.

## Les résultats

| `RedemptionOutcome` | Quand | `batchId` | Autres champs |
|---|---|---|---|
| `Redeemed` | Le code était libre et appartient maintenant à la personne qui l'échange | Le lot du code | `redeemedAt` : maintenant |
| `Unknown` | Mal saisi, échouant à son symbole de contrôle, ou jamais créé | `null` | — |
| `AlreadyRedeemed` | Échangé auparavant, par n'importe qui | Le lot du code | `redeemedAt` : alors |
| `Expired` | Son lot a expiré | Le lot du code | — |
| `Revoked` | Son lot a été révoqué | Le lot du code | — |
| `TooManyAttempts` | L'appelant a dépensé ses tentatives | `null` | `retryAfter` |

Que la page distingue `AlreadyRedeemed`, `Expired` et `Revoked` est un choix de l'application : les distinguer aide la
personne qui s'est trompée de carte, et le limiteur de débit empêche d'aider la personne qui essaie des codes.

## Atomicité

Le magasin marque un code comme échangé seulement s'il n'est ni échangé, ni révoqué, ni expiré à cet instant, par un
seul `UPDATE` conditionnel de la ligne du code. La base de données verrouille la ligne : de deux échanges au même
instant, l'un la modifie et l'autre la trouve échangée. La suite d'intégration démarre huit processus qui échangent le
même code au même instant sur MySQL 8.4 et sur PostgreSQL 17 : un seul l'emporte, sept sont refusés.

## Les tentatives

`AccessCodes` accepte n'importe quel `Symfony\Component\RateLimiter\RateLimiterFactoryInterface`. Une fenêtre glissante
de dix tentatives par quart d'heure et par adresse de client convient à une page publique ; l'identifiant d'un compte
convient à une page derrière une connexion. Chaque tentative compte, les réussies aussi, pour qu'un code valide ne
remette pas la fenêtre à zéro.
