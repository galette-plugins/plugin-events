---
title: Documentazione
description: Event and booking management
---

Questo componente aggiuntivo fornisce:

* gestione eventi,
* attività associate agli eventi,
* booking management,
* a calendar, with events for calendar applications,
* a public page listing the events you publish.

## Installazione

Prima di tutto, scaricare il plugin:

* [Get latest Events
  plugin!](https://github.com/galette-plugins/plugin-events/releases/latest)
* [Get Events plugin nightly
  build!](https://github.com/galette-plugins/plugin-events/releases/tag/nightly)

Estrarre l'archivio scaricato nella directory `plugins` di Galette. Ad esempio,
su Linux (sostituendo `{url}` e `{version}` con i valori corretti):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-events-{version}.tar.bz2
```

## Inizializzazione database

Per funzionare, questo plugin richiede diverse tabelle nel database. Vedere
[Interfaccia di gestione dei plugin
Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

E questo è finito; il plugin Eventi è stato installato :)

## Plugin usage

Quando il plugin è installato, un gruppo `Eventi` viene aggiunto al menu Galette
quando un utente è connesso. Ci sono varie possibilità che cambiano a seconda
del profilo utente (socio semplice, gestore del gruppo, amministratore, ...).

### Attività

Si può definire tutte le attività che si vuole e associarle a un evento.
Un'attività può essere un viaggio organizzato, un pasto, un alloggio, ...

![The list of activities](images/list_activities.png)

Un'attività è composta da un nome, uno stato e un commento facoltativo.

Per aggiungere una nuova attività, basta fare clic sul collegamento "Nuova
attività":

![The form of a new activity](images/new_activity.png)

### Eventi

Gli eventi sono l'obiettivo principale del plugin. È possibile definire diverse
informazioni, come un nome, le date di inizio e di fine, la posizione, ...

![The form of a new event](images/new_event.png)

Nome, data di inizio e città sono obbligatori. Tutte le altre informazioni sono
completamente facoltative.

The **Summary** presents the event in a few words, in lists and on public pages;
when it is empty, the beginning of the comment is used instead. The **Comment**
is shown in full in the event details.

The **When** part sets the dates and times of the event. Check **All day** for
an event without times: time fields are then hidden, and times typed before are
ignored. Otherwise, times are optional as well:

* **No begin time**: the event lasts all day long, from its begin date to its
  end date,
* **Begin time only**: the event is shown at its begin time, without end,
* **Begin and end times**: on a single day, the end time cannot be before the
  begin time.

An end time without begin time is refused. **Displayed as** shows, while you
type, the period as it will appear in lists, in the calendar and on public
pages.

> **Note** — Times, summary and the "Who can see it" choice appeared in version
> 2.4.0.

The **Who can see it** part sets who has access to the event:

![Who can see an event](images/event_visibility.png)

* **Every member**: the event is available for all members,
* **A group**: only members and managers of the chosen group (and of its
  subgroups) will have access,
* **Public**: the event is available for all members, and is also published on
  Galette public pages, see [Public events](#public-events).

> **Note**
> 
> Group managers do not get this choice: they must choose one of the groups they
> own, and cannot publish events.

È possibile allegare una o più attività a ogni evento, e per ognuna impostare se
è disponibile, non disponibile o anche obbligatoria. Scegliere l'attività da
aggiungere e fare clic sul pulsante.

![The activities attached to an event](images/event_activities.png)

> **Warning**
> 
> L'aggiunta o la rimozione di attività da un evento ricaricherà la pagina e
> provocherà la richiesta di inserire le necessarie informazioni. Tuttavia (e
> questo viene di volta in volta specificato), l'evento **non verrà
> memorizzato** durante questa operazione.
> 
> Assicurarsi di salvare l'evento :)

From Events list, you can edit or remove entries, access to booking list, export
bookings as CSV, or download the event for calendar applications. The date
column shows the whole period of the event, with its times when it has some.

![The list of events](images/events_list.png)

### Calendar

The `Calendar` entry of the menu displays the events you can see, by month or as
daily, weekly and monthly lists. For members, it also shows past and closed
events, that the events list leaves out. Click on an event to get its details:

![An event in the calendar](images/calendar_event.png)

From there, you can edit the event (if you are allowed to), add a new booking,
or download the event.

### Calendar applications

Events can be added to calendar applications, such as Thunderbird, Google
Calendar, Outlook or the calendar of your phone, using the iCalendar format
(`.ics` files).

> **Note** — Added in version 2.4.0.

#### Download an event

Use the download icon of the events list, or the **Download** button of the
event details in the calendar. You get a `.ics` file to open or import in your
calendar application. It contains the dates and times of the event, its
location, its comment, the activities it offers, and a link to book it.

A downloaded event is a copy: it will not change in your calendar application if
the event is changed in Galette. Download it again, or subscribe instead.

#### Subscribe to events

A subscription keeps your calendar application up to date by itself. Use the
**Subscribe** button of the calendar page: it adds the subscription in your
default calendar application. The first time, it creates your personal
subscription address.

The arrow next to the button gives other options:

![Subscription options](images/calendar_subscription.png)

* **Copy my subscription address**, to add it by hand as a calendar subscription
  (sometimes named "calendar from the Internet", or "new calendar from URL") in
  your application,
* **Change my subscription address…**, see below,
* **How to subscribe?** explains how to subscribe from the most common calendar
  applications:

![How to subscribe](images/subscription_help.png)

The subscription contains the same events as your calendar: events that are not
restricted to a group, the ones of your groups, and the ones you have booked,
closed ones included. Events ended for more than a year are left out. Texts are
in your language.

Calendar applications refresh subscriptions on their own schedule; Galette asks
for every hour, but some applications refresh less often (Google Calendar may
take up to a day).

> **Warning**
> 
> The subscription address is personal: anyone who knows it can see your events,
> without login. If it has been disclosed, use **Change my subscription
> address…**: once you confirm, the previous one stops working at once, and you
> will have to subscribe again with the new one.

The address stops working as well when the member account is disabled. Super
administrator, who is not a member, has no subscription.

#### Times and time zone

Times are given to calendar applications in universal time (UTC), converted from
the time zone of the server: each application then displays them in its own time
zone. If times are shifted in calendar applications, the PHP time zone
(`date.timezone` setting) of the server is probably not the one of your
association; ask your host or administrator to fix it.

### Prenotazioni

Per ogni evento è possibile registrare le prenotazioni. Come detto prima, i
membri semplici e i gestori dei gruppi saranno limitati agli eventi dei loro
gruppi, oppure agli eventi che non sono limitati a un gruppo.

L'aggiunta di una nuova prenotazione può essere effettuata dal menu "Nuova
prenotazione" o dall'elenco delle prenotazioni degli eventi.

![The form of a new booking](images/new_booking.png)

Le prenotazioni vengono chiuse una volta che l'evento viene contrassegnato come
chiuso o al termine della data di inizio. Gli amministratori e i membri del
personale possono sempre aggiungere nuove prenotazioni.

L'elenco attività viene recuperato dall'evento; quelle obbligatorie vanno
ovviamente verificate in fase di prenotazione.

![The list of bookings](images/bookings_list.png)

Si può filtrare l'elenco delle prenotazioni per evento, tipo di pagamento o
stato del pagamento. Poi si potrà inviare una mail ai membri prenotati,
utilizzando il meccanismo dell'invio postale standard di Galette.

### Public events

Events can be shown to people who are not logged in, on a public page, with a
subscription for calendar applications that requires no account.

> **Note** — Added in version 2.4.0.

To publish an event, choose **Public** in the **Who can see it** part of its
form. Only events that are not restricted to a group can be public: an event
limited to some members cannot be shown to anyone. Group managers, who must
choose one of their groups, cannot publish events.

When public pages are enabled, the public pages menu gets an `Events` entry,
that lists upcoming public events by month, with their dates, town, summary and
activities; bookings and attendees are never shown. The **Calendar** button
displays them in a calendar instead; the page remembers your choice.

![Public events page](images/public_events.png)

Click on an event name to get its details: full address with a link to a map,
comment, and activities. **Add to my calendar** downloads the event for calendar
applications, without any account.

![Public event details](images/public_event_details.png)

The **Subscribe** button adds all public events to a calendar application, and
keeps them up to date; its arrow lets you copy the subscription address, or
explains how to subscribe. This address is not personal: it is the same for
everyone.

Who can see this page and its subscription is set with the other public pages
visibilities, from Galette settings: the **Events** setting follows the default
visibility unless you change it. See [who can see public
pages](https://doc.galette.eu/en/master/usermanual/public_pages.html#who-can-see-them).
