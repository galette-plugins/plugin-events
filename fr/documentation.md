---
title: Documentation
description: Gestion d'évènements et des réservations
---

Ce plugin fournit :

* gestion d'évènements,
* association d'activités avec des évènements,
* gestion des réservations.

## Installation

Tout d'abord, téléchargez le plugin :

* [Obtenir le dernier plugin Events
  !](https://github.com/galette-plugins/plugin-events/releases/latest)
* [Obtenir la nightly du plugin Events
  !](https://github.com/galette-plugins/plugin-events/releases/tag/nightly)

Extrayez l'archive téléchargée dans le dossier `plugins` de Galette. Par
exemple, sous linux (en remplaçant `{url}` et `{version}` par les valeurs
adéquates) :

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-events-{version}.tar.bz2
```

## Initialisation de la base de données

Pour fonctionner, ce plugin requiert des tables dans la base de données.
Référez-vous [à l'interface de gestion des plugins de
Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

Et c'est terminé, le plugin Events est installé :)

## Utilisation du plugin

Lorsque le plugin est installé, un groupe `Évènements` est ajouté au menu de
Galette lorsqu'un utilisateur est connecté. Il y a plusieurs possibilités qui
diffèrent en fonction du profil de l'utilisateur (simple adhérent, responsable
de groupe, administrateur, ...).

### Activités

Vous pouvez définir autant d'activités que vous le souhaitez, et les associer à
un évènement. Une activité peut être un voyage organisé, un repas, une sortie,
un hébergement, ...

![La liste des activités](images/list_activities.png)

Una activité se compose d'un nom, d'un statut et éventuellement d'un
commentaire.

Pour ajouter une nouvelle activité, cliquez sur le lien « Nouvelle activité » :

![La forme d'une nouvelle activité](images/new_activity.png)

### Évènements

Les évènements sont le cœur du plugin. Vous pouvez définir diverses
informations, comme un nom, des dates de début et de fin, un lieu, ...

![La forme d'un nouvel événement](images/new_event.png)

Les nom, date de début et ville sont requis. Toutes les autres informations sont
optionnelles.

Les évènements qui ne sont pas liés à un groupe seront accessible pour tous les
adhérents ; Si un groupe est défini, seuls les membres et responsable de ce
groupe y auront accès.

> **Note**
> 
> Lorsqu'un responsable de groupe crée un nouvel évènement, il doit choisir l'un
> des groupes qu'il gère !

Vous pouvez attacher une ou plusieurs activités à chaque évènement, et pour
chacune d'entre elles si elle est disponible, non disponible ou encore
obligatoire. Choisissez l'activité à ajouter et cliquez sur le bouton.

![Les activités liées à un événement](images/event_activities.png)

> **Attention**
> 
> Ajouter ou supprimer une activité d'un évènement rechargera la page et vous
> demandera d'enregistrer les informations requises. Néanmoins (et c'est précisé
> à chaque fois), l'évènement **ne sera pas enregistré** pendant cette
> opération.
> 
> Assurez-vous de sauvegarder l'évènement :)

Depuis la liste des évènements, vous pouvez modifier ou supprimer des entrées,
accéder à la liste des réservations ou exporter ces dernières en CSV.

![La liste des événements](images/events_list.png)

### Réservations

Les réservations peuvent être enregistrées sur chaque évènement. Comme dit
précédemment, les simples membres et responsables de groupes seront limités aux
évènements liés aux groupes auxquels ils appartiennent, ou à ceux qui ne sont
pas restreints à un groupe.

Ajouter une nouvelle réservation se fait depuis l'entrée de menu « Nouvelle
réservation » ou depuis la liste des réservations.

![Le formulaire de nouvelle réservation](images/new_booking.png)

Les réservations sont closes dès que l'évènement est marqué comme fermé, ou si
la date de début est dépassée. Les administrateurs et membres du bureau peuvent
toujours ajouter de nouvelles réservations.

La liste des activités est récupérée depuis l'évènement ; celles qui sont
obligatoires doivent bien sur être cochées lors de la réservation.

![La liste des réservations](images/bookings_list.png)

Vous pouvez filtrer la liste des évènements par évènement, type ou statut de
paiement. Vous pouvez envoyer des mailings aux membres qui ont réservé, en
utilisant le mécanisme de mailing de Galette.
