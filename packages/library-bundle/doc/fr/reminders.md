<!-- phil-dev source-sha256=52b596845fd336b890b8403206c912c8811642d33a23f0ba6f4a2bc22b4635ef -->
# Rappels et vie privée

## Rappels et avis

`Reminders::send($batch)` lit les prêts actifs bientôt échus ou en retard et envoie :

- un rappel avant l'échéance, à chaque décalage de `reminders.before_due` — un rappel dont le moment est passé sans
  envoi est dépassé par le suivant ;
- un avis une fois le prêt en retard, puis un tous les `reminders.overdue_every_days`, jusqu'à `reminders.overdue_max`.

Chacun part une seule fois : le prêt enregistre le décalage du dernier rappel ainsi que le numéro et le moment du dernier
avis. Un renouvellement les fait recommencer. Aucune amende n'est comptée : un avis dit que l'exemplaire est attendu.

Ils passent par le port `ReminderSender` ; avec Messenger, son adaptateur envoie un `SendLoanReminder` :

| Propriété | Valeur |
|---|---|
| `loanId` | Le prêt |
| `kind` | `due` avant l'échéance, `overdue` après |
| `step` | Le décalage en minutes, ou le numéro de l'avis à partir de 1 |

Le message ne porte que des identifiants : le *handler* de l'application lit le prêt et son membre, et leur écrit comme
elle le fait, sur le transport vers lequel elle achemine le message.

```php
#[AsMessageHandler]
final readonly class LoanReminderHandler
{
    public function __invoke(SendLoanReminder $message): void
    {
        $loan = $this->loans->get($message->loanId);
        // … the member's address from the application's accounts, the message in their language
    }
}
```

Sans Messenger ni `ReminderSender` de l'application, il n'y a ni le service des rappels ni sa commande.

## Les commandes

| Commande | Ce qu'elle fait | Quand |
|---|---|---|
| `phil:library:send-reminders` | Envoie les rappels et les avis dus, pour `batch` prêts au plus | Toutes les heures environ |
| `phil:library:forget-history` | Oublie le membre des prêts clos depuis plus de `history_days` | Chaque jour |

## L'historique dans les demandes de vie privée

Avec `phil/privacy-bundle`, `LoanHistoryPrivacy` est le module `library` d'une demande d'accès ou d'effacement, la
personne concernée étant l'identifiant du membre :

- l'export liste chacun des prêts du membre — le titre, le code à barres de l'exemplaire, son état, sa période, le moment
  où il a été remis et clos, ses renouvellements —, 1000 au plus ;
- l'effacement oublie le membre sur tous ses prêts, qui demeurent comme des décomptes de l'usage des exemplaires
  (`phil_library_loan.member` expurgé). Un prêt actif demeure actif, celui de personne.

Les exécuter de nouveau ne trouve rien de plus et rapporte la même chose. Une personne concernée qui n'est pas un
identifiant — une adresse courriel — ne détient rien ici.

## Oublier l'historique

Bien des bibliothèques ne gardent aucun historique de ce qu'un membre a emprunté. Avec `history_days`,
`phil:library:forget-history` oublie le membre de chaque prêt retourné, annulé ou perdu depuis plus de ce nombre de
jours ; avec `0`, dès sa clôture. Les prêts demeurent, ceux de personne. Sans l'option, l'historique est conservé
jusqu'à l'effacement des données du membre.
