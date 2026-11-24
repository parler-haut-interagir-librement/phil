<!-- phil-dev source-sha256=f8b1cce8d8a661a6d8714077db3956397366525d6a56171841d8cca156743106 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les modifier, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Lire le DNS autrement | Implémenter `Contract\TxtResolver` et le passer à `MailAuthCheck` |
| Ajouter une vérification | Implémenter `Contract\Check` et passer les vérifications à `MailAuthCheck` |
| Exécuter moins de vérifications, ou les réordonner | Passer la liste voulue ; `MailAuthCheck::defaultChecks()` donne les quatre du paquet |
| Utiliser une seule partie | Appeler directement `SpfParser`, `SpfAnalyser`, `DkimKeyParser`, `DmarcParser` ou `DmarcTreeWalk` |

## Le résolveur

```php
interface TxtResolver
{
    /** @return list<string> */
    public function txt(string $asciiName): array;
}
```

`txt()` reçoit un nom en ASCII minuscule sans point final (`example.org`, `s1._domainkey.example.org`,
`_dmarc.example.org`) et retourne un texte par enregistrement TXT, ses chaînes de caractères jointes sans séparateur.
Un nom sans enregistrement, ou qui n'existe pas, donne une liste vide ; un résolveur qui n'a pas pu répondre lance
`DnsLookupFailed`, que les vérifications transforment en constat d'échec. Un résolveur qui suit les enregistrements
CNAME répond pour une clé DKIM déléguée à un fournisseur.

Le résolveur d'une application peut mettre les réponses en cache pour leur TTL, interroger un service DNS sur HTTPS ou
répondre à partir d'enregistrements qu'elle détient déjà. `MailAuthCheck` l'enveloppe pour que chaque nom soit
interrogé une seule fois par vérification ; il ne garde rien d'une vérification à l'autre.

## Une vérification à vous

```php
use Phil\MailAuthCheck\Contract\Check;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Exception\DnsLookupFailed;
use Phil\MailAuthCheck\Finding;
use Phil\MailAuthCheck\MailAuthCheck;
use Phil\MailAuthCheck\SenderProfile;

final readonly class MtaStsCheck implements Check
{
    public function run(SenderProfile $profile, TxtResolver $dns): array
    {
        try {
            $records = $dns->txt('_mta-sts.'.$profile->sendingDomain);
        } catch (DnsLookupFailed) {
            return [Finding::fail('mta-sts', 'mta-sts.lookup-failed', 'The MTA-STS record could not be read.')];
        }

        return [] === $records
            ? [Finding::warning('mta-sts', 'mta-sts.missing', 'No MTA-STS record is published.')]
            : [Finding::pass('mta-sts', 'mta-sts.present', 'An MTA-STS record is published.')];
    }
}

$checker = new MailAuthCheck(checks: [...MailAuthCheck::defaultChecks(), new MtaStsCheck()]);
```

Une vérification retourne au moins un constat, pour que le rapport montre qu'elle a été exécutée, et transforme ce que
répond le DNS (un échec de requête compris) en constats plutôt qu'en exceptions. Elle ne garde aucun état d'un appel à
l'autre : le résolveur qu'elle reçoit sert une vérification d'un profil.
