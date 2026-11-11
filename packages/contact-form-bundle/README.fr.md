<!-- phil-dev source-sha256=6a97c6981218acc60498db8efd524a0c6650fcb77fc53a91d8de6271bf4cba36 -->
# phil/contact-form-bundle

**Une page « écrivez-nous » pour Symfony, protégée du pourriel sans captcha.**

phil/contact-form-bundle fournit une page de contact : un formulaire avec un nom, un courriel, un sujet et un message.
Un message valide part par Symfony Mailer vers les destinataires configurés, puis la page se redirige vers elle-même et
indique que le message est parti : recharger la page n'envoie donc rien deux fois. Les libellés, les messages et le
courriel sont en anglais et en français. Le bundle ne conserve pas les messages, mais une application qui envoie ses
courriels par un transport Messenger asynchrone les conserve dans sa file d'attente jusqu'à leur départ, comme
l'explique [Envoi du courriel](doc/fr/mailing.md). Il n'affiche aucun captcha. Il n'envoie aucune copie au visiteur, car
une copie permettrait à n'importe qui d'envoyer un texte de son choix à n'importe quelle adresse par l'intermédiaire du
site.

Il s'adresse à tout site Symfony qui veut que ses visiteurs lui écrivent sans exposer d'adresse de courriel. Le
formulaire porte la protection de `phil/spam-protection-bundle`, qui n'appelle aucun tiers. Le courriel part de
l'adresse propre au site, car l'adresse d'un visiteur dans `From` échoue aux vérifications SPF et DMARC du domaine et
aboutit dans les pourriels. Le visiteur se trouve dans `Reply-To` : répondre, c'est lui répondre.

La page fonctionne telle quelle, avec une route et un gabarit qu'une application remplace pour l'adapter à sa mise en
page. Pour une page de sa propre conception, une application utilise `ContactFormType` et `ContactMailer` dans son
propre contrôleur. Pour écrire à quelqu'un d'autre que le site, comme une organisation d'un répertoire, elle remplace le
port `Recipients` par son propre service.

## État

| Partie | État |
|---|---|
| `ContactFormType` et `ContactMessage`, protégés et vérifiés | Implémenté et testé |
| `ContactMailer` : depuis le site, `Reply-To` vers le visiteur, en-têtes sûrs | Implémenté et testé |
| `ContactFormController` et `config/routes.php` | Implémenté et testé |
| Le port `Recipients`, pour écrire à quelqu'un d'autre que le site | Implémenté et testé |
| Les gabarits et les traductions, en anglais et en français | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement, la route. |
| [Configuration](doc/fr/configuration.md) | Chaque clé et les services. |
| [La page](doc/fr/page.md) | Le formulaire, ce qui est vérifié, les réponses, les gabarits. |
| [Envoi du courriel](doc/fr/mailing.md) | Ce que contient le courriel, et pourquoi. |
| [Points d'extension](doc/fr/extension-points.md) | `Recipients`, et l'utilisation du formulaire ailleurs. |
| [Tests](doc/fr/testing.md) | La façon dont le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```sh
composer require phil/contact-form-bundle
```

```yaml
# config/packages/phil_contact_form.yaml
phil_contact_form:
    sender: contact@example.com
    recipients: [info@example.com]

# config/routes/phil_contact_form.yaml
phil_contact_form:
    resource: '@PhilContactFormBundle/config/routes.php'
    prefix: /contact
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-filter` ; `phil/spam-protection-bundle` 0.1 ; les composants de formulaire, de
fondation HTTP, d'envoi de courriels, MIME, de validation, le pont Twig et l'injection de dépendances de Symfony 8.2 ;
Twig 3 ; un journaliseur PSR-3.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
