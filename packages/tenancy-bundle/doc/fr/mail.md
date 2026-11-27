<!-- phil-dev source-sha256=809cc71c022c32808be00124b939175dc3a7a5936632ee6fcd2ed4d33d3ccd7a -->
# Courrier par locataire

Le courrier d'un locataire peut partir à sa façon : par son propre serveur SMTP, depuis sa propre adresse, ou — pour un
locataire sans domaine propre — depuis l'adresse de la plateforme, avec l'adresse du locataire en `Reply-To`. Le bundle
applique ces réglages à chaque message envoyé dans un locataire, par les transports mêmes du composant Mailer.

## La configurer

```yaml
# config/packages/framework.yaml
framework:
    mailer:
        transports:
            main: '%env(MAILER_DSN)%'
            rose_smtp: '%env(ROSE_MAILER_DSN)%'

# config/packages/phil_tenancy.yaml
phil_tenancy:
    mailer:
        platform_sender: 'Shops <mail@platform.example>'
        tenants:
            rose:
                transport: rose_smtp
                sender: 'Rose <hello@rose.example>'
            fair:
                name: Summer Fair
                reply_to: fair.organisers@mail.example
```

| Clé | Effet |
|---|---|
| `platform_sender` | L'expéditeur des locataires sans domaine propre |
| `tenants.<tenant>.transport` | Un transport nommé sous `framework.mailer.transports` |
| `tenants.<tenant>.sender` | L'expéditeur propre du locataire, sur son propre domaine |
| `tenants.<tenant>.name` | Le nom sous lequel son courrier est envoyé, celui de son propre expéditeur ou celui de la plateforme |
| `tenants.<tenant>.reply_to` | Où vont les réponses, pour un locataire envoyé depuis l'adresse de la plateforme |
| `settings` | L'identifiant d'un service `Infrastructure\Mailer\Contract\TenantMailSettings`, au lieu de `tenants` |

L'expéditeur qui en résulte pour un locataire :

| Locataire | `From` | `Reply-To` |
|---|---|---|
| `sender` réglé | L'expéditeur du locataire, sous `name` quand il est réglé | `reply_to` quand il est réglé |
| Ni `sender`, ni `reply_to` ou `name` réglé | `platform_sender`, sous `name` quand il est réglé | `reply_to` quand il est réglé |
| `transport` seulement | L'expéditeur propre de l'application | — |

Chaque adresse est vérifiée pendant la compilation du conteneur ; un locataire envoyé depuis l'adresse de la plateforme
sans `platform_sender` arrête la compilation. Envoyer depuis le domaine de la plateforme garde le courrier sous les
enregistrements SPF, DKIM et DMARC d'un domaine que la plateforme contrôle, tandis que les réponses atteignent quand
même le locataire.

## Comment ça marche

`Infrastructure\Mailer\TenantTransports` décore `mailer.transports`, le service par lequel le mailer et son *handler*
Messenger envoient. Pour un message envoyé dans un locataire qui a des réglages, il ajoute :

| En-tête | Quand |
|---|---|
| `X-Transport` | Le locataire a un `transport` ; les transports de Symfony lisent l'en-tête, envoient par ce transport et le retirent |
| `From` | Le locataire a un expéditeur ; le message n'a pas de `From` |
| `Reply-To` | Le locataire a un `reply_to` ; le message n'a pas de `Reply-To` |

Ce que le message dit déjà l'emporte : un en-tête qu'il porte n'est jamais remplacé. Un message envoyé dans aucun
locataire, ou dans un locataire sans réglages, part comme celui de l'application. Un message mis en file par Messenger
est envoyé par le *worker* dans le locataire où il a été réparti, que `TenantMiddleware` rétablit sur chaque bus, celui
qui transporte `SendEmailMessage` compris ([integrations.md](integrations.md)).

Le décorateur ne garde aucun état : le locataire est lu depuis le contexte à chaque message, et les connexions des
transports sont fermées entre les requêtes et les messages comme Symfony les ferme.

## Des réglages tirés d'une base de données

```php
final readonly class StoredTenantMail implements TenantMailSettings
{
    public function __construct(private Connection $database) {}

    public function for(TenantId $tenant): ?TenantMail
    {
        $row = $this->database->fetchAssociative('SELECT transport, sender, reply_to FROM tenant_mail WHERE tenant = ?', [$tenant->value]);

        return false === $row ? null : new TenantMail(
            $row['transport'],
            null === $row['sender'] ? null : Address::create($row['sender']),
            null === $row['reply_to'] ? null : Address::create($row['reply_to']),
        );
    }
}
```

```yaml
phil_tenancy:
    mailer:
        settings: App\Mail\StoredTenantMail
```

Un transport que nomme une ligne stockée doit tout de même être déclaré sous `framework.mailer.transports` : un nom
inconnu fait échouer l'envoi.
