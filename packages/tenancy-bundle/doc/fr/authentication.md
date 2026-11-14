<!-- phil-dev source-sha256=a521659227bfb6a2175b650e75c0f7940643245eab5649671b09cbe8ae711b89 -->
# Authentification par locataire

Trois choses peuvent différer d'un locataire à l'autre : une session ouverte sur un locataire est-elle ouverte sur un
autre, quel pare-feu connecte un compte, et d'où viennent les comptes. Chacune est désactivée tant qu'elle n'est pas
configurée.

## Des sessions limitées à leur locataire

Quand plusieurs locataires partagent un même témoin de session — plusieurs hôtes sous un domaine de témoin commun, ou
plusieurs locataires sur un même hôte —, un compte connecté sur un locataire serait connecté sur tous. Le bundle limite
les sessions des fournisseurs d'utilisateurs qu'on lui donne : un compte est connecté sur le locataire où il s'est
connecté, et sur aucun autre, à moins que l'application ne déclare le compte partagé.

### L'activer

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    hosts:
        rose.example: rose
        tavern.example: tavern
    authentication:
        user_providers:
            - security.user.provider.concrete.members # the provider "members" of security.yaml
        shared_tenants:
            - [fair, fair-backstage]
```

`user_providers` liste les identifiants de service des fournisseurs à limiter ; SecurityBundle nomme
`security.user.provider.concrete.members` le fournisseur `members` de `security.providers`. La limitation exige
`symfony/security-http`.

### Comment ça marche

| Quand | Ce qui se passe |
|---|---|
| Une connexion réussit (`LoginSuccessEvent`) | `RememberSignInTenant` écrit le locataire de la requête de connexion dans la session de l'application, sous `_phil_tenancy.signed_in_on` ; une connexion dans aucun locataire le supprime |
| Une déconnexion (`LogoutEvent`) | Le locataire est supprimé, même quand le pare-feu garde la session |
| Le pare-feu rafraîchit le compte d'une session | `TenantScopedUserProvider` laisse le fournisseur de l'application rafraîchir le compte, puis le refuse quand la requête agit dans un autre locataire que celui où la session s'est connectée, à moins qu'`AccountSharing` ne le partage |

Le refus est une `UserNotFoundException`, la réponse d'un fournisseur dont le compte n'existe plus : l'écouteur de
contexte du pare-feu abandonne le jeton **avant** `access_control` et les authentificateurs. La requête continue en
anonyme, et une page protégée demande une connexion sur ce locataire. Le pare-feu retire aussi le jeton de la session
partagée : le compte doit donc se reconnecter aussi sur le premier locataire.

Le locataire de la requête doit être connu pendant que le pare-feu s'exécute : il l'est quand l'hôte de la requête le
sert ([hosts-and-shops.md](hosts-and-shops.md)). Le locataire que `ResolveActiveTenant` trouve d'après le choix du
compte est résolu après le pare-feu : un tel compte atteint plusieurs locataires par conception, et sa session n'est
pas limitée.

Une connexion sur un hôte qui ne sert aucun locataire, un domaine central dont les hôtes partagent le témoin, ouvre une
session hors de tout locataire, et une telle session n'est connectée que là : sur l'hôte d'un locataire, le compte est
refusé comme tout autre, à moins que l'application ne l'ouvre explicitement :

- `shared_sessions: true` connecte une session ouverte hors de tout locataire sur chaque locataire, pour un back-office
  central qui les gère tous ;
- avec `reachable_tenants` et l'écouteur de requêtes, le fournisseur laisse passer la session et les locataires
  atteignables décident après le pare-feu : un fournisseur qui répond chaque locataire les ouvre tous, et
  `ResolveActiveTenant` refuse par un `403` un locataire que le compte ne peut pas atteindre
  ([integrations.md](integrations.md)) ;
- un `AccountSharing` de l'application répond à `isShared($account, null, $visited)`.

| Session | Requête | Connecté ? |
|---|---|---|
| Connectée sur `rose` | sur `rose` | Oui |
| Connectée sur `rose` | sur `tavern` | Non, à moins qu'elle ne soit partagée |
| Connectée sur `rose` | dans aucun locataire | Non |
| Connectée hors de tout locataire | dans aucun locataire | Oui |
| Connectée hors de tout locataire | sur n'importe quel locataire | Non, à moins que `shared_sessions` ou l'`AccountSharing` de l'application ne la partage ; avec `reachable_tenants` et l'écouteur de requêtes, sur un locataire que le compte peut atteindre, un `403` ailleurs |
| Une valeur mémorisée qui n'est plus une référence valide | sur n'importe quel locataire | Non |
| Un pare-feu sans état | — | Non limitée : son authentificateur lit lui-même `TenantContext` |

Savoir quels comptes peuvent se connecter sur un locataire reste la décision de l'application. Son fournisseur
d'utilisateurs ou son authentificateur peut lire `TenantContext`, qui contient déjà le locataire de l'hôte à la
connexion.

### Les comptes partagés

`Application\Contract\AccountSharing::isShared(string $account, ?TenantId $signedInOn, ?TenantId $visited)` n'est
interrogé que lorsque les deux locataires diffèrent ; `$account` est l'identifiant que le fournisseur d'utilisateurs
donne au compte, qui désigne un membre par un identifiant stable — un UUID, un numéro de compte — et jamais par une
adresse courriel, qu'un membre peut changer et qui ferait alors partager ou refuser le mauvais compte ; `$signedInOn`
vaut `null` pour une session ouverte hors de tout locataire, et les deux ne valent jamais `null` ensemble.
`ConfiguredAccountSharing`, du bundle, partage un compte :

- entre les locataires d'un groupe de `shared_tenants` ;
- entre les boutiques liées par le réseau de boutiques : une boutique générale et les boutiques qu'elle inclut, ou deux
  boutiques incluses par la même, partagent leurs comptes clients ;
- d'aucun locataire vers chaque locataire, avec `shared_sessions: true`.

Il traite de la même façon chaque compte d'un locataire, et ne partage jamais une session ouverte sur un locataire avec
une requête hors de tout locataire.
Une application qui ne partage que certains comptes, comme les comptes du personnel à travers tous les locataires,
implémente le port et le nomme :

```yaml
phil_tenancy:
    authentication:
        user_providers: [security.user.provider.concrete.members]
        account_sharing: App\Security\StaffAccountsEverywhere
```

```php
final readonly class StaffAccountsEverywhere implements AccountSharing
{
    public function __construct(private StaffDirectory $staff) {}

    public function isShared(string $account, ?TenantId $signedInOn, ?TenantId $visited): bool
    {
        return $this->staff->isStaff($account);
    }
}
```

## Un pare-feu pour chaque locataire

Un locataire, ou un groupe de locataires, peut connecter ses comptes par un pare-feu à lui : ses propres
authentificateurs, sa propre page de connexion, son propre contexte de session, de sorte qu'une session d'un pare-feu
n'est jamais lue par un autre. Chaque nom de `tenant_firewalls` enregistre un détecteur de requêtes,
`phil_tenancy.request_matcher.<name>`, qui retient les requêtes de ses locataires ; le `request_matcher` de
SecurityBundle le place devant un pare-feu :

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    hosts:
        club-a.example: club-a
        club-b.example: club-b
    authentication:
        tenant_firewalls:
            club_b: [club-b]

# config/packages/security.yaml
security:
    firewalls:
        club_b:
            request_matcher: phil_tenancy.request_matcher.club_b
            provider: club_b_members
            form_login: { login_path: club_b_login, check_path: club_b_login }
        main:
            provider: members
            form_login: ~
```

SecurityBundle lit ses pare-feu dans l'ordre : placez les pare-feu des locataires avant celui qui retient toutes les
requêtes. Le détecteur lit le locataire que l'hôte de la requête sert, entré avant que la carte des pare-feu ne soit
lue ; une requête dans aucun locataire, ou dont le locataire est résolu plus tard d'après le choix du compte, ne
correspond à aucun pare-feu de locataire. Une liste vide de locataires arrête la compilation.

## Un fournisseur d'utilisateurs pour chaque locataire

Les comptes de chaque locataire peuvent venir de son propre fournisseur — sa propre table, son propre annuaire, son
propre fournisseur d'identité —, et ceux des autres locataires d'un fournisseur par défaut. Chaque nom de
`tenant_user_providers` enregistre `phil_tenancy.user_provider.<name>`, un `TenantUserProviders`, que SecurityBundle
lit comme l'`id` d'un fournisseur :

```yaml
# config/packages/phil_tenancy.yaml
phil_tenancy:
    authentication:
        tenant_user_providers:
            members:
                tenants:
                    club-a: security.user.provider.concrete.club_a_members
                    club-b: security.user.provider.concrete.club_b_members
                default: security.user.provider.concrete.staff

# config/packages/security.yaml
security:
    providers:
        club_a_members: { entity: { class: App\Entity\ClubAMember, property: memberNumber } }
        club_b_members: { entity: { class: App\Entity\ClubBMember, property: memberNumber } }
        staff: { entity: { class: App\Entity\Staff, property: staffNumber } }
        members: { id: phil_tenancy.user_provider.members }
    firewalls:
        main:
            provider: members
```

| Requête | Fournisseur |
|---|---|
| Dans un locataire listé sous `tenants` | Le fournisseur de ce locataire |
| Dans un autre locataire, ou dans aucun | `default` |
| Dans un autre locataire, ou dans aucun, sans `default` | Aucun : chaque compte est inconnu |

| Méthode | Ce qu'elle fait |
|---|---|
| `loadUserByIdentifier()` | Charge le compte depuis le fournisseur du locataire de la requête |
| `refreshUser()` | Rafraîchit le compte par ce fournisseur ; un compte d'une classe qu'il ne gère pas est inconnu, de sorte que le pare-feu abandonne le jeton plutôt que de le confier aux comptes d'un autre locataire |
| `supportsClass()` | Si l'un des fournisseurs gère la classe |
| `upgradePassword()` | Passe la nouvelle empreinte au fournisseur du locataire de la requête, quand il met à niveau les mots de passe |

Un compte inconnu est une `UserNotFoundException`, la réponse d'un fournisseur dont le compte n'existe pas. Le locataire
est celui de la requête au moment où le pare-feu s'exécute, que l'hôte de la requête donne. Les fournisseurs des
locataires ne sont construits que lorsqu'une requête en demande un. Un fournisseur d'utilisateurs que `user_providers`
limite peut être l'un d'eux, ou celui-ci.

Désignez chaque membre par un identifiant stable — la `property` d'un fournisseur d'entités, l'identifiant d'un
fournisseur d'identité —, jamais par une adresse courriel : l'identifiant est ce que la session mémorise et ce que lit
`AccountSharing`.

## Serveurs de longue durée

Aucun de ces services ne garde quoi que ce soit d'une requête à l'autre : le locataire de la session est lu depuis la
session, et celui de la requête depuis `TenantContext`, qui est vidé entre les requêtes.
