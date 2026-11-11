---
title: Dokumentacija
description: Upravljanje dogodkov in rezervacij
---

Ta vtičnik ponuja:

* upravljanje dogodkov,
* povezovanje dejavnosti z dogodki,
* booking management,
* a calendar, with events for calendar applications,
* a public page listing the events you publish.

## Namestitev

Najprej prenesite vtičnik:

* [Pridobite najnovejši vtičnik za
  dogodke!](https://github.com/galette-plugins/plugin-events/releases/latest)
* [Pridobite nočno gradnjo vtičnika za
  dogodke!](https://github.com/galette-plugins/plugin-events/releases/tag/nightly)

Razširite prenesen arhiv v imenik Galette `plugins`. Na primer v Linuxu
(zamenjajte `{url}` in `{version}` s pravilnimi vrednostmi):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-events-{version}.tar.bz2
```

## Inicializacija baze podatkov

Za delovanje ta vtičnik potrebuje več tabel v bazi podatkov. Glejte [Vmesnik za
upravljanje vtičnikov
Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

In to je končano; vtičnik Events je nameščen :)

## Uporaba vtičnika

Ko je vtičnik nameščen, se skupina `Dogodki` doda v meni Galette, ko je
uporabnik prijavljen. Obstajajo različne možnosti, ki se spreminjajo glede na
profil uporabnika (preprost član, vodja skupine, administrator, ...).

### Dejavnosti

Določite lahko poljubno število aktivnosti in jih povežete z dogodkom. Aktivnost
je lahko organiziran izlet, obrok, nastanitev, ...

![Seznam dejavnosti](images/list_activities.png)

Dejavnost je sestavljena iz imena, stanja in neobveznega komentarja.

Če želite dodati novo dejavnost, preprosto kliknite povezavo »Nova dejavnost«:

![Oblika nove dejavnosti](images/new_activity.png)

### Dogodki

Dogodki so glavni cilj vtičnika. Določite lahko več informacij, kot so ime,
začetni in končni datum, lokacija, ...

![Oblika novega dogodka](images/new_event.png)

Ime, datum začetka in kraj so obvezni. Vsi drugi podatki so popolnoma neobvezni.

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

> **Opomba**
> 
> Group managers do not get this choice: they must choose one of the groups they
> own, and cannot publish events.

Vsakemu dogodku lahko dodate eno ali več dejavnosti in za vsako nastavite, ali
je na voljo, ni na voljo ali je celo obvezna. Izberite dejavnost, ki jo želite
dodati, in kliknite gumb.

![Dejavnosti, priložene dogodku](images/event_activities.png)

> **Opozorilo**
> 
> Če dodate ali odstranite aktivnost iz dogodka, se stran ponovno naloži in vas
> pozove, da izpolnite obvezne podatke. Kljub temu (in to je vsakič določeno)
> dogodek med tem postopkom **ne bo shranjen**.
> 
> Poskrbite, da boste dogodek shranili :)

From Events list, you can edit or remove entries, access to booking list, export
bookings as CSV, or download the event for calendar applications. The date
column shows the whole period of the event, with its times when it has some.

![Seznam dogodkov](images/events_list.png)

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

> **Opozorilo**
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

### Rezervacije

Rezervacije je mogoče registrirati za vsak dogodek posebej. Kot smo že omenili,
bodo preprosti člani in upravitelji skupin omejeni na dogodke svojih skupin
oziroma na dogodke, ki niso omejeni na skupino.

Novo rezervacijo lahko dodate v meniju »Nova rezervacija« ali na seznamu
rezervacij dogodkov.

![Obrazec nove rezervacije](images/new_booking.png)

Rezervacije so zaprte, ko je dogodek označen kot zaprt ali ko je datum začetka
potekel. Administratorji in člani osebja lahko vedno dodajo nove rezervacije.

Seznam aktivnosti je pridobljen z dogodka; obvezne aktivnosti je seveda treba
preveriti med rezervacijo.

![Seznam rezervacij](images/bookings_list.png)

Seznam rezervacij lahko filtrirate po dogodku, vrsti plačila ali statusu
plačila. Nato lahko rezerviranim članom pošljete pošto z uporabo standardnega
poštnega mehanizma Galette.

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
