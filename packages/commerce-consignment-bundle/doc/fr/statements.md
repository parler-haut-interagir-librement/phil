<!-- phil-dev source-sha256=dd4894c90093a5c332a670edcf9d58d99c56e571aaa64a4c52595e1a3ffe43a7 -->
# Relevés

Un **relevé** dit à un consignataire ce qui est arrivé à ses unités pendant une période : ce qui s'est vendu, ce que les
clients ont rapporté, ce que la boutique lui a acheté et ce qu'elle lui a rendu, ce qui lui est dû et ce que la
boutique a gardé, et ce qui reste en boutique, avec son âge.

```php
use Phil\CommerceConsignmentBundle\Application\ConsignorStatements;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\Money\Currency;

$author = Owner::consignor('author-17');
$statement = $statements->issue($author, Currency::of('CAD'), $statements->lastPeriod($author, $clock->now()), $clock->now());
$entityManager->flush();

$statement->number;         // ST-00000042
$statement->share();        // what the shop owes the author for the period
$statement->commission();   // what it kept
$statement->lines();        // the entries of the period
$statement->held();         // the units still in the shop at the end of the period, with their age
```

## La période

Une période est une `StatementPeriod` : deux instants en UTC, le début inclus et la fin exclue. Une écriture faite à la
dernière microseconde d'octobre est dans le relevé d'octobre ; une écriture faite à minuit le premier novembre est dans
celui de novembre. Une période dure au plus 367 jours, et un relevé ne couvre qu'une période écoulée : une période qui
se termine après l'instant d'émission est refusée, pour que ce qui arrive plus tard dans la période n'en soit pas exclu.

`StatementFrequency::lastClosedPeriod()` et `ConsignorStatements::lastPeriod()` donnent la dernière semaine, le dernier
mois ou le dernier trimestre écoulé à un instant ; toute autre période se construit avec `StatementPeriod::of()`.

## Ce qu'un relevé contient

Un relevé est lu dans les écritures du grand livre, jamais recalculé à partir des ventes :

| Ligne | Unités | Revenu | Part | Commission |
|---|---|---|---|---|
| Une vente (`sold`) | les unités vendues | le prix payé | la part du consignataire | la commission de la boutique |
| Le retour d'un client (`returned`) | négatives | négatif | négative | négative |
| Un rachat (`bought_out`) | les unités que la boutique a achetées | le coût convenu | le coût convenu | zéro |
| Des unités rendues (`given_back`) | les unités rendues | zéro | zéro | zéro |

Le retour d'un client diminue ce qui est dû au consignataire dans la période où il est écrit, même quand la vente
était dans une période antérieure : le relevé antérieur reste tel quel, et celui-ci porte la différence. Les totaux sont
les sommes des lignes ; la part et la commission font le revenu, ligne par ligne et au total, et un relevé dont les
sommes ne le feraient pas est refusé : aucun cent n'est perdu ni créé.

Un relevé est dans une devise : les ventes, les retours et les rachats dans une autre devise vont au relevé de cette
devise, émis à part. Les unités rendues, qui ne portent aucun argent, sont sur chaque relevé de la période.

Les unités encore en main sont additionnées à partir des écritures antérieures à la fin de la période, couche par couche,
la réception la plus ancienne d'abord, chacune avec son SKU, son emplacement, l'instant de sa réception et son âge en
jours entiers à la fin de la période.

Un relevé contient au plus 5 000 lignes et les unités de 1 000 couches : une période plus longue est refusée, et émise en
périodes plus courtes.

## Les numéros

Chaque relevé a un numéro de sa propre série, donné par le port `StatementNumbering` à son émission.
`DoctrineStatementNumbering`, la valeur par défaut, compte dans la table des relevés : le préfixe configuré, puis huit
chiffres, un de plus que le numéro le plus élevé de ce préfixe — `ST-00000001`, `ST-00000002`. Deux requêtes qui prennent
le même numéro en même temps ne peuvent pas toutes les deux valider : la contrainte d'unicité sur le numéro refuse la
seconde, qui émet de nouveau son relevé. Une série à soi — par année, par boutique — est un adaptateur de
l'application : voir [Points d'extension](extension-points.md).

## Émis une fois, jamais modifié

Un relevé est figé à son émission : ses chiffres et ses lignes sont copiés dans sa ligne de table, et aucune écriture
ultérieure, aucune nouvelle entente, ne les change. Un relevé émis de nouveau pour la même période est un nouveau relevé,
avec un nouveau numéro ; le premier reste. `ConsignorRepository::statement()` en trouve un par son numéro, et
`statementsOf()` liste les relevés d'un consignataire, le dernier émis d'abord.

## CSV

`StatementCsv::write()` donne un relevé en CSV : un en-tête, une rangée par ligne, puis une rangée de totaux, les
montants en décimal. Une cellule de texte qu'un tableur lirait comme une formule — qui commence par `=`, `+`, `-` ou
`@` — commence par une apostrophe.

```php
return new Response($statementCsv->write($statement), headers: [
    'Content-Type' => 'text/csv; charset=UTF-8',
    'Content-Disposition' => sprintf('attachment; filename="%s.csv"', $statement->number),
]);
```

## Une page ou un document

`StatementView::data()` donne un relevé en données simples — chaînes et entiers, montants en décimal, instants en UTC —
pour un gabarit. Le bundle en fournit un, `@PhilCommerceConsignment/statement.html.twig`, accessible et sans style, qu'une
application remplace dans `templates/bundles/PhilCommerceConsignmentBundle/statement.html.twig` :

```php
return $this->render(StatementView::TEMPLATE, ['statement' => $statementView->data($statement)]);
```

Le HTML qu'il produit peut être imprimé en PDF par n'importe quel convertisseur HTML-PDF que l'application utilise.

L'application décide qui lit un relevé : le bundle ne l'envoie pas et ne vérifie pas qui le demande.
