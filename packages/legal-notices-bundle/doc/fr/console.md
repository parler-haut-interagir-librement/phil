<!-- phil-dev source-sha256=85a9dabae8eb9ea6ab3b8127f5bcb69ddc022ccbbdf9d32e4f914c01d92b5783 -->
# Console

## phil:legal-notices:check

```sh
bin/console phil:legal-notices:check
```

Liste, une ligne chacun, ce qui manque aux documents publics : un champ de l'identité qu'un document exige
(`missing_field`), un corps que le moteur de rendu refuse (`unreadable_body`), un espace réservé sans valeur
(`unknown_placeholder`). Elle se termine avec le code `1` s'il y en a au moins un, pour qu'un déploiement s'y arrête.

## phil:legal-notices:announce

```sh
bin/console phil:legal-notices:announce --days=1
```

Distribue `Phil\LegalNotices\Event\DocumentPublished` pour chaque version dont la date de publication tombe dans les
`--days` derniers jours, de 1 à 366, et l'affiche. Elle ne garde rien : lancée une fois par jour par un planificateur,
elle annonce chaque version une seule fois. Un écouteur prévient les membres, vide un cache ou enregistre le
changement.

```php
#[AsEventListener]
public function __invoke(DocumentPublished $event): void
{
    $this->bus->dispatch(new TellMembers($event->type, $event->version));
}
```
