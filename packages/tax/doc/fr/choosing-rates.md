<!-- phil-dev source-sha256=7377315934c7016e9a93f1e3084621671ee948e875d5d1f44c2be2382b7f92fb -->
# Choisir les taux

La bibliothèque calcule et détaille ; elle ne décide jamais quels taux s'appliquent. Ces décisions restent à
l'application, ou au moteur de taxation externe auquel elle les délègue :

- si un vendeur doit percevoir la taxe (inscription, seuils des petits fournisseurs) ;
- quel territoire taxe une vente (lieu de fourniture, emplacement de l'acheteur pour les ventes numériques) ;
- les taux en vigueur, que la loi modifie ;
- les mentions légales d'un reçu (numéros d'inscription).

## Taux conservés par l'application

Une application qui conserve ses taux dans sa configuration les lit en pourcentages, comme les autorités fiscales les
publient, et remet au calculateur la liste qui s'applique :

```php
use Phil\Tax\TaxRate;

final readonly class ConfiguredTaxRates
{
    /** @param array<string, array<string, array<string, string>>> $rates category => zone => code => percent */
    public function __construct(private array $rates)
    {
    }

    /** @return list<TaxRate> */
    public function ratesFor(string $taxCategory, string $zone): array
    {
        $rates = [];

        foreach ($this->rates[$taxCategory][$zone] ?? [] as $code => $percent) {
            $rates[] = TaxRate::ofPercent($code, $percent);
        }

        return $rates;
    }
}
```

Retournez chaque code une seule fois : `TaxBreakdown` refuse un code qui apparaît deux fois.

## Taux calculés par un moteur

Un adaptateur au-dessus d'un moteur de taxation externe construit la ventilation à partir des montants du moteur avec
`TaxLine::reported()` et `TaxBreakdown::of()`, pour que le reçu affiche ce que le moteur a calculé, au cent près.
