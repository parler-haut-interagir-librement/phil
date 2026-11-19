<!-- phil-dev source-sha256=0a1bfd96c6432ba8a0a8545f08cdc33c30ad7ec9481c2a8e1afadd07aec6c02c -->
# Comptage

`UsageMeter` se construit avec les compteurs, un stockage, une horloge PSR-20 et, facultativement, un fournisseur de
limites et un fournisseur d'ancres :

```php
use Phil\UsageMeter\UsageMeter;

$meter = new UsageMeter($meters, $store, $clock, $limits, $anchors);
```

Un **sujet** est la référence opaque de l'application, de 1 à 255 octets : un identifiant de compte, un locataire, un
projet. La bibliothèque ne l'interprète jamais et ne l'écrit jamais dans un message.

## Avant une action : `consume()`

```php
use Phil\UsageMeter\Exception\QuotaExceeded;

try {
    $meter->consume($accountId, 'exports', 1, $exportRequestId);
} catch (QuotaExceeded $exception) {
    // nothing was counted
    return $this->render('upgrade.html.twig', ['usage' => $exception->usage]);
}

$this->exporter->export($document);
```

`consume()` compte la quantité, 1 par défaut, avant l'action qu'elle représente, sauf si elle ferait dépasser sa
limite au sujet ; elle lance alors `QuotaExceeded` et ne compte rien. La vérification et le comptage forment une seule
étape atomique du stockage : deux requêtes au même moment ne peuvent jamais passer toutes deux la dernière unité. Sur
une fenêtre glissante, ce que contiennent les compartiments précédents est d'abord retiré de la limite.

Une action qui échoue après `consume()` rend sa quantité avec `refund()` et la même clé, plus bas ; consommez tout de
même le plus tard possible, juste avant le travail que le quota protège.

## Après coup : `record()`

```php
$meter->record($accountId, 'ai.tokens', $response->usage->totalTokens, $response->id);
```

`record()` compte ce qui a déjà été utilisé, quel que soit le quota : la fenêtre peut finir au-delà de sa limite, et
`consume()` refuse alors jusqu'à ce que la fenêtre libère assez. Elle convient à une utilisation connue seulement une
fois le travail fait, comme les jetons qu'un modèle a dépensés.

## Clés d'idempotence

Les deux prennent une clé facultative de 1 à 255 octets : l'identifiant de la requête, d'un message ou d'une tâche. Une
incrémentation avec une clé déjà comptée pour le même sujet et le même compteur ne compte rien et répond l'utilisation
telle quelle, marquée `replayed` : une requête reprise, un message livré deux fois, un double clic. Une clé rejouée
réussit même quand le quota est plein : elle a été comptée la première fois. Une incrémentation refusée laisse sa clé
libre : la même requête peut donc être tentée de nouveau une fois que la fenêtre le permet. Une clé est gardée aussi
longtemps que le compteur auquel elle est allée.

## Quand l'action échoue : `refund()`

```php
$meter->consume($accountId, 'exports', 1, $exportRequestId);

try {
    $this->exporter->export($document);
} catch (ExportFailed $exception) {
    $meter->refund($accountId, 'exports', $exportRequestId);

    throw $exception;
}
```

`refund()` rend ce que `consume()` ou `record()` a compté sous une clé, pour qu'une action comptée puis échouée ne
coûte rien. Sans quantité, elle rend tout ce que la clé a compté ; avec une quantité, celle-ci est ce que les
remboursements de la clé totalisent **en tout**, et non un ajout : rembourser 2 puis 2 de nouveau rend 2, rembourser 2
puis 5 rend 5. Elle ne rend jamais plus que ce que la clé a compté : un remboursement repris (un message livré deux
fois, un *handler* exécuté de nouveau) est donc sans danger.

La quantité retourne au compteur où la clé est allée, même quand la fenêtre a changé depuis : un remboursement ne libère
jamais de place dans une période où il n'a pas été compté. `refund()` répond l'utilisation de la fenêtre courante,
marquée `replayed` quand rien n'a été rendu : une clé inconnue, une clé déjà oubliée par la purge, ou une clé déjà
remboursée jusque-là. Seule une incrémentation avec une clé peut être remboursée.

## Lire : `usage()`, `allows()`, `limit()`

```php
$usage = $meter->usage($accountId, 'exports');

$usage->used;        // 2
$usage->limit;       // Limit::of(3)
$usage->remaining(); // 1, or null for no limit
$usage->allows(2);   // false
$usage->isExceeded();// true only when more is used than the limit
$usage->start;       // the first instant counted
$usage->resetsAt;    // when the window next changes

$meter->allows($accountId, 'exports');      // to show or hide a button
$meter->limit($accountId, 'exports');       // the subject's limit, whatever is used
```

`allows()` est une vérification, pas une réservation : entre elle et `consume()`, une autre requête peut prendre la
dernière unité, et c'est `consume()` qui décide.

## Quantités

Une quantité est un nombre entier à partir de 1 (celle d'un remboursement aussi, quand il en nomme une) ; zéro ou un
nombre négatif lance `InvalidUsage`. Comptez dans la plus
petite unité dont parle le quota : un jeton, un octet, une requête, un projet.
