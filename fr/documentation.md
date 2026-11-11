---
title: Documentation
description: Gestion d'évènements et des réservations
---

Ce plugin fournit :

* gestion d'évènements,
* association d'activités avec des évènements,
* gestion des réservations,
* un calendrier, avec les évènements pour les applications d'agenda,
* une page publique listant les évènements que vous publiez.

## Installation

Tout d'abord, téléchargez le plugin :

* [Obtenir le dernier plugin Events !](https://github.com/galette-plugins/plugin-events/releases/latest)
* [Obtenir la nightly du plugin Events !](https://github.com/galette-plugins/plugin-events/releases/tag/nightly)

Extrayez l'archive téléchargée dans le dossier `plugins` de Galette. Par exemple, sous linux (en remplaçant `{url}` et `{version}` par les valeurs adéquates) :

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-events-{version}.tar.bz2
```

## Initialisation de la base de données

Pour fonctionner, ce plugin requiert des tables dans la base de données. Référez-vous [à l'interface de gestion des plugins de Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

Et c'est terminé, le plugin Events est installé :)

## Utilisation du plugin

Lorsque le plugin est installé, un groupe `Évènements` est ajouté au menu de Galette lorsqu'un utilisateur est connecté. Il y a plusieurs possibilités qui diffèrent en fonction du profil de l'utilisateur (simple adhérent, responsable de groupe, administrateur, ...).

### Activités

Vous pouvez définir autant d'activités que vous le souhaitez, et les associer à un évènement. Une activité peut être un voyage organisé, un repas, une sortie, un hébergement, ...

![La liste des activités](images/list_activities.png)

Une activité se compose d'un nom, d'un statut et éventuellement d'un commentaire.

Pour ajouter une nouvelle activité, cliquez sur le lien « Nouvelle activité » :

![Le formulaire d'une nouvelle activité](images/new_activity.png)

### Évènements

Les évènements sont le cœur du plugin. Vous pouvez définir diverses informations, comme un nom, des dates de début et de fin, un lieu, ...

![Le formulaire d'un nouvel évènement](images/new_event.png)

Les nom, date de début et ville sont requis. Toutes les autres informations sont optionnelles.

Le **Résumé** présente l'évènement en quelques mots, dans les listes et sur les pages publiques ; s'il est vide, le début du commentaire est utilisé à la place. Le **Commentaire** est affiché en entier dans le détail de l'évènement.

La partie **Quand** définit les dates et heures de l'évènement. Cochez **Toute la journée** pour un évènement sans heures : les champs d'heure sont alors masqués, et les heures saisies auparavant sont ignorées. Sinon, les heures restent facultatives :

* **Sans heure de début** : l'évènement dure toute la journée, de sa date de début à sa date de fin,
* **Heure de début seule** : l'évènement est affiché à son heure de début, sans fin,
* **Heures de début et de fin** : sur une même journée, l'heure de fin ne peut pas précéder l'heure de début.

Une heure de fin sans heure de début est refusée. **Affiché ainsi** montre, pendant la saisie, la période telle qu'elle apparaîtra dans les listes, dans le calendrier et sur les pages publiques.

> **Note** — Les heures, le résumé et le choix « Qui peut le voir » sont apparus en version 2.4.0.

La partie **Qui peut le voir** définit qui a accès à l'évènement :

![Qui peut voir un évènement](images/event_visibility.png)

* **Tous les membres** : l'évènement est accessible à tous les adhérents,
* **Un groupe** : seuls les membres et responsables du groupe choisi (et de ses sous-groupes) y auront accès,
* **Public** : l'évènement est accessible à tous les adhérents, et il est aussi publié sur les pages publiques de Galette, voir [Évènements publics](#public-events).

> **Note**
>
> Les responsables de groupe n'ont pas ce choix : ils doivent choisir l'un des groupes qu'ils gèrent, et ne peuvent pas publier d'évènements.

Vous pouvez attacher une ou plusieurs activités à chaque évènement, et pour chacune d'entre elles si elle est disponible, non disponible ou encore obligatoire. Choisissez l'activité à ajouter et cliquez sur le bouton.

![Les activités liées à un évènement](images/event_activities.png)

> **Attention**
>
> Ajouter ou supprimer une activité d'un évènement rechargera la page et vous demandera d'enregistrer les informations requises. Néanmoins (et c'est précisé à chaque fois), l'évènement **ne sera pas enregistré** pendant cette opération.
>
> Assurez-vous de sauvegarder l'évènement :)

Depuis la liste des évènements, vous pouvez modifier ou supprimer des entrées, accéder à la liste des réservations, exporter ces dernières en CSV, ou télécharger l'évènement pour les applications d'agenda. La colonne de date montre toute la période de l'évènement, avec ses heures s'il en a.

![La liste des évènements](images/events_list.png)

### Calendrier

L'entrée `Calendrier` du menu affiche les évènements que vous pouvez voir, par mois ou en listes quotidienne, hebdomadaire et mensuelle. Pour les adhérents, il montre aussi les évènements passés et fermés, que la liste des évènements laisse de côté. Cliquez sur un évènement pour en voir le détail :

![Un évènement dans le calendrier](images/calendar_event.png)

De là, vous pouvez modifier l'évènement (si vous y êtes autorisé), ajouter une nouvelle réservation ou télécharger l'évènement.

### Applications d'agenda

Les évènements peuvent être ajoutés aux applications d'agenda, comme Thunderbird, Google Agenda, Outlook ou l'agenda de votre téléphone, grâce au format iCalendar (fichiers `.ics`).

> **Note** — Ajouté en version 2.4.0.

#### Télécharger un évènement

Utilisez l'icône de téléchargement de la liste des évènements, ou le bouton **Télécharger** du détail de l'évènement dans le calendrier. Vous obtenez un fichier `.ics` à ouvrir ou à importer dans votre application d'agenda. Il contient les dates et heures de l'évènement, son lieu, son commentaire, les activités proposées et un lien pour réserver.

Un évènement téléchargé est une copie : il ne changera pas dans votre application d'agenda si l'évènement est modifié dans Galette. Téléchargez-le à nouveau, ou abonnez-vous plutôt.

#### S'abonner aux évènements

Un abonnement tient votre application d'agenda à jour toute seule. Utilisez le bouton **S'abonner** de la page du calendrier : il ajoute l'abonnement dans votre application d'agenda par défaut. La première fois, il crée votre adresse d'abonnement personnelle.

La flèche à côté du bouton donne d'autres options :

![Options d'abonnement](images/calendar_subscription.png)

* **Copier mon adresse d'abonnement**, pour l'ajouter à la main comme abonnement (parfois appelé « agenda depuis Internet » ou « nouvel agenda depuis une URL ») dans votre application,
* **Changer mon adresse d'abonnement…**, voir plus bas,
* **Comment s'abonner ?** explique comment s'abonner depuis les applications d'agenda les plus courantes :

![Comment s'abonner](images/subscription_help.png)

L'abonnement contient les mêmes évènements que votre calendrier : les évènements qui ne sont pas restreints à un groupe, ceux de vos groupes et ceux que vous avez réservés, fermés compris. Les évènements terminés depuis plus d'un an sont laissés de côté. Les textes sont dans votre langue.

Les applications d'agenda rafraîchissent les abonnements à leur propre rythme ; Galette demande toutes les heures, mais certaines applications rafraîchissent moins souvent (Google Agenda peut mettre jusqu'à un jour).

> **Attention**
>
> L'adresse d'abonnement est personnelle : toute personne qui la connaît peut voir vos évènements, sans connexion. Si elle a été divulguée, utilisez **Changer mon adresse d'abonnement…** : une fois confirmé, l'ancienne cesse aussitôt de fonctionner, et vous devrez vous abonner à nouveau avec la nouvelle.

L'adresse cesse aussi de fonctionner lorsque le compte de l'adhérent est désactivé. Le super administrateur, qui n'est pas un adhérent, n'a pas d'abonnement.

#### Heures et fuseau horaire

Les heures sont transmises aux applications d'agenda en temps universel (UTC), converties depuis le fuseau horaire du serveur : chaque application les affiche ensuite dans son propre fuseau. Si les heures sont décalées dans les applications d'agenda, le fuseau horaire PHP du serveur (réglage `date.timezone`) n'est probablement pas celui de votre association ; demandez à votre hébergeur ou à votre administrateur de le corriger.

### Réservations

Les réservations peuvent être enregistrées sur chaque évènement. Comme dit précédemment, les simples membres et responsables de groupes seront limités aux évènements liés aux groupes auxquels ils appartiennent, ou à ceux qui ne sont pas restreints à un groupe.

Ajouter une nouvelle réservation se fait depuis l'entrée de menu « Nouvelle réservation » ou depuis la liste des réservations.

![Le formulaire de nouvelle réservation](images/new_booking.png)

Les réservations sont closes dès que l'évènement est marqué comme fermé, ou si la date de début est dépassée. Les administrateurs et membres du bureau peuvent toujours ajouter de nouvelles réservations.

La liste des activités est récupérée depuis l'évènement ; celles qui sont obligatoires doivent bien sûr être cochées lors de la réservation.

![La liste des réservations](images/bookings_list.png)

Vous pouvez filtrer la liste des réservations par évènement, type ou statut de paiement. Vous pouvez envoyer des mailings aux membres qui ont réservé, en utilisant le mécanisme de mailing de Galette.

### Évènements publics

Les évènements peuvent être montrés aux personnes qui ne sont pas connectées, sur une page publique, avec un abonnement pour les applications d'agenda qui ne demande aucun compte.

> **Note** — Ajouté en version 2.4.0.

Pour publier un évènement, choisissez **Public** dans la partie **Qui peut le voir** de son formulaire. Seuls les évènements qui ne sont pas restreints à un groupe peuvent être publics : un évènement limité à certains membres ne peut pas être montré à tout le monde. Les responsables de groupe, qui doivent choisir l'un de leurs groupes, ne peuvent pas publier d'évènements.

Lorsque les pages publiques sont activées, le menu des pages publiques reçoit une entrée `Évènements`, qui liste par mois les évènements publics à venir, avec leurs dates, ville, résumé et activités ; les réservations et les participants ne sont jamais montrés. Le bouton **Calendrier** les affiche plutôt dans un calendrier ; la page retient votre choix.

![La page des évènements publics](images/public_events.png)

Cliquez sur le nom d'un évènement pour en voir le détail : adresse complète avec un lien vers une carte, commentaire et activités. **Ajouter à mon agenda** télécharge l'évènement pour les applications d'agenda, sans aucun compte.

![Le détail d'un évènement public](images/public_event_details.png)

Le bouton **S'abonner** ajoute tous les évènements publics à une application d'agenda, et les tient à jour ; sa flèche permet de copier l'adresse d'abonnement, ou explique comment s'abonner. Cette adresse n'est pas personnelle : c'est la même pour tout le monde.

Qui peut voir cette page et son abonnement se règle avec les autres visibilités des pages publiques, depuis les préférences de Galette : le réglage **Évènements** suit la visibilité par défaut, sauf si vous le changez. Voir [qui peut voir les pages publiques](https://doc.galette.eu/en/master/usermanual/public_pages.html#who-can-see-them).
