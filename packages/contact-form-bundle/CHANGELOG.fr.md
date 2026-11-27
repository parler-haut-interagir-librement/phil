<!-- phil-dev source-sha256=20773821eabfbe0393089f14e697d9fc676d6bc24152d7f8e4e5b744d2715030 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/contact-form-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.3-alpha - 2026-11-25

### Sécurité

- Exige `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable publiés le 2026-11-24.

## 0.1.2-alpha - 2026-11-24

### Modifié

- `ContactMailer` prend un `TransportInterface` de Mailer, le service `mailer.transports`, au lieu de `MailerInterface`.
- `symfony/messenger` est dans `require-dev`, pour le test qui achemine `SendEmailMessage` vers un transport asynchrone.
- Exige la dernière mineure de chaque dépendance (`twig/twig` 3.31, `phil/spam-protection-bundle` 0.3).

### Corrigé

- `phil/spam-protection-bundle` 0.3 est accepté à côté de 0.1 et 0.2 : le paquet ne s'installait pas à côté de la
  version publiée le 2026-11-20.

### Sécurité

- Un message de contact ne passe plus par `MailerInterface`, qui mettait un `SendEmailMessage` sur le bus quand
  l'application l'acheminait : le courriel non rendu, avec le nom, l'adresse et le message du visiteur dans son
  contexte, gardé par le transport de Messenger et son transport d'échec. Le courriel est rendu et envoyé dans la
  requête, par les transports de Mailer, et un refus du transport atteint la page aussitôt.

## 0.1.1-alpha - 2026-11-17

### Modifié

- Il accepte `phil/spam-protection-bundle` 0.2 à côté de 0.1.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `ContactMessage`, qui vérifie l'adresse courriel selon la RFC 5322 sans avertissements, si bien qu'une adresse que
  Symfony Mime refuse dans `Reply-To`, comme `a..b@example.com`, est un champ erroné répondu par une `422` ;
  `Recipients` et `ConfiguredRecipients` ; `ContactFormType`, `ContactMailer`, `ContactFormController` ;
  `config/routes.php`.
- `PhilContactFormBundle`, alias d'extension `phil_contact_form`, avec les clés `sender`, `recipients`, `subject_prefix`
  et `template`.
- Les gabarits de la page et du courriel ; les libellés en anglais et en français, dans `translations/`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; `doc/mailing.md` dit qu'avec un transport
  asynchrone de Messenger un échec ressort dans le worker plutôt que sur la page `503`, et que les messages en file
  contiennent des renseignements personnels qui demandent une règle de conservation.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
