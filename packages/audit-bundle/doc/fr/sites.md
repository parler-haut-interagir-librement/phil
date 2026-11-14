<!-- phil-dev source-sha256=656a8c7fb1bf1f6cb5d6f4010f126983bd3edd499c7c3055b52cff1f94b1a6fa -->
# Une chaîne par site

Une application qui sert plusieurs sites à partir d'une même base de données — un locataire chacun, comme les résout
`phil/tenancy-bundle` — peut garder une seule piste d'audit pour tous ou donner à chaque site une chaîne qui lui est
propre. Avec une seule chaîne, les lignes de tous les sites s'entremêlent dans les mêmes segments : chaque ligne nomme
son locataire et les lectures lui sont limitées, mais vérifier un segment lit les lignes de tous les sites, et l'on ne
peut pas remettre à un site sa propre chaîne à contrôler. Avec les segments de site, chaque site est chaîné, ancré et
vérifié séparément.

## L'activer

```yaml
phil_audit:
    serializer: App\Audit\CanonicalSerializerV1
    site_segments: true
```

`AuditTrail::record($segment, $fields, $facts)` consigne alors une ligne dont le locataire est connu — donné dans ses
`Facts`, ou le locataire courant du `TenantScope` — dans le segment de ce locataire : le segment que vous nommez,
suivi de `.t-` et des 32 premiers chiffres hexadécimaux — 128 bits — du SHA-256 de la référence du locataire.

```php
$trail->record('orders', ['total' => '10.00'], Facts::of('order.paid', $actor, Subject::of('order', '42')));
// in the tenant "shop-a": segment "orders.t-<32 hex digits>", sequence 1 of that site
// outside any tenant: segment "orders", shared by every site
```

`AuditTrail::segmentFor('orders', 'shop-a')` donne le nom sans rien consigner. La référence du locataire n'est pas dans
le nom, puisqu'elle peut être longue ou contenir des caractères qu'un segment refuse; chaque ligne du segment la porte
comme fait de locataire. Le nom de segment que vous donnez compte au plus 29 caractères quand les segments de site sont
activés (`SiteSegment::MAX_BASE_LENGTH`), pour que le nom du site tienne dans les 64 caractères de la colonne de la
table.

L'empreinte distingue deux sites même quand des utilisateurs choisissent les références de locataire : nommer le
segment d'un locataire donné demande environ 2^128 essais, et trouver deux références d'un même segment, toutes deux
choisies, environ 2^64. Elle n'emploie aucune clé, exprès : le nom d'un segment est mis en empreinte dans chacune de
ses lignes, il ne doit donc jamais changer, quel que soit le secret que l'application fait tourner, et quiconque détient
la piste peut vérifier à quel locataire un segment appartient :

```bash
printf %s 'shop-a' | sha256sum | cut -c1-32
```

La restauration des données d'un site est consignée dans le segment de ce site en nommant le locataire :

```php
$trail->recordDiscontinuity('orders', 'restored from the backup of 2026-11-11', 'shop-a');
```

Sans locataire, la discontinuité va dans le segment partagé, comme sans segments de site.

## Ce que la vérification ajoute

`ChainVerifier` lit le site dans le nom du segment : dans un segment de site, chaque ligne doit porter le locataire de
ce site. Une ligne avec un autre locataire, ou sans locataire, est une alarme, `the row is not of the site the segment
belongs to` : une ligne déplacée de la chaîne d'un site vers celle d'un autre se voit, même si son empreinte et son
lien ont été recalculés. L'empreinte de chaque locataire rencontré est calculée une fois par segment, non une fois par
ligne.

`phil:audit:verify --tenant=shop-a` vérifie seulement les segments de ce site. `TrailVerification::verify(null,
'shop-a')` fait de même depuis le code. L'ancrage ne change pas : chaque segment de site est ancré comme n'importe quel
autre segment, de sorte que les ancres de chaque site peuvent être écrites vers une destination qui lui est propre par
un `AnchorWriter` qui lit le nom du segment.

## Ce qu'un locataire voit

Dans un locataire, `ChainStatus::segments()` ne liste que les segments de ce site, et `ChainStatus::verify()` répond
pour le segment d'un autre site, ou pour un segment partagé, comme pour un segment sans lignes : aucune tête, aucune
rupture, rien de lu. `AuditLog` limite déjà chaque lecture au locataire courant ([reading.md](reading.md)). Hors de
tout locataire, chaque segment est listé; un `SegmentStatus` nomme son site avec `site()` — le locataire que porte sa
ligne de tête — et le segment que vous avez nommé avec `base()`, ce qui permet à `phil/audit-viewer` de regrouper par
site la page de la chaîne.

## `SiteSegment`

| Méthode | Réponse |
|---|---|
| `SiteSegment::of(string $segment, string $tenant): string` | Le segment de site; `InvalidValue` pour un segment de plus de 29 caractères, un segment qui est déjà un segment de site, ou un locataire qui n'est pas une référence |
| `SiteSegment::isSite(string $segment): bool` | Si le nom se termine par `.t-` et 32 chiffres hexadécimaux en minuscules |
| `SiteSegment::belongsTo(string $segment, string $tenant): bool` | Si c'est le segment de ce locataire, comparé en temps constant |
| `SiteSegment::base(string $segment): string` | Le segment nommé, sans l'empreinte du site |

Soixante-quatre bits d'empreinte empêchent deux locataires d'une même application de partager un segment par accident;
la vérification le montrerait, puisque les lignes du second locataire sont refusées dans le segment du premier.

## L'activer plus tard

Les segments de site changent l'endroit où vont les nouvelles lignes, jamais celui des anciennes. Une piste tenue dans
des segments partagés avant l'activation de `site_segments` garde ces segments, vérifiés comme avant; les lignes
consignées ensuite commencent la chaîne de chaque site à la séquence 1. Le désactiver renvoie les nouvelles lignes
dans les segments partagés.
