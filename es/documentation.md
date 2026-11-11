---
title: Documentación
description: Event and booking management
---

Este complemento proporciona:

* gestión de eventos,
* asocie actividades con eventos,
* booking management,
* a calendar, with events for calendar applications,
* a public page listing the events you publish.

## Instalación

Antes que todo, descargue el complemento:

* [Get latest Events
  plugin!](https://github.com/galette-plugins/plugin-events/releases/latest)
* [Get Events plugin nightly
  build!](https://github.com/galette-plugins/plugin-events/releases/tag/nightly)

Extraer el archivo descargado en el directorio de Galette `plugins`. Por
ejemplo, bajo linux (reemplazar `{url}` y `{version}` con valores correctos):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-events-{version}.tar.bz2
```

## Inicio de la base de datos

Para funcionar, este plugin requiere varias tablas en la base de datos. Ver
[Interfaz de gestión de plugins
Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

Y esto está finalizado; el complemento de Eventos está instalado :)

## Plugin usage

Cuando es complemento se instala, se añade un grupo `Events` al menú Galette
cuando un usuario entre en el equipo. Hay varias posibilidades que cambiar
dependiendo del perfil del usuario (miembro simple, gestor de grupo,
administrador, ...).

### Actividades

Puede definir tantas actividades como desee, y asociarlos a un evento. Una
actividad puede ser un viaje organizado, una comida, una casa, ...

![The list of activities](images/list_activities.png)

Una actividad está compuesta con un nombre, un estado y comentario opcional.

Para añadir una activad nueva, tan solo pulse en el enlace «Crear actividad»:

![The form of a new activity](images/new_activity.png)

### Eventos

Los eventos son el objetivo principal del complemento. Puede definir varias
informaciones, como un nombre, fechas que comienzan y finalizan, lugares, ...

![The form of a new event](images/new_event.png)

Nombre, datos de comienzo y ciudad son obligatorios. Toda las demás
informaciones son completamente opcionales.

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

Puede adjuntar una o varias actividades por cada evento, y por cada uno fija si
está disponible, no disponible o incluso obligatorio. Elija la actividad para
añadir, y pulse el botón.

![The activities attached to an event](images/event_activities.png)

> **Warning**
> 
> Añadiendo o quitando actividad desde un evento recargará la página y le
> preguntará rellenar información obligatoria. Sin embargo (y esto está
> especificado cada vez), el evento **no será almacenado** durante esta
> operación.
> 
> Asegure que guarde el evento :)

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

### Reservas

Las reservas pueden ser registradas por cada evento. Tal como dijimos antes, los
miembros únicos y los gestores de grupos estarán limitados a sus eventos de
grupos, o para los eventos que no están restringidos a un grupo.

Añadir una reserva nueva puede ser logrado desde el menú «Reserva nueva» o desde
el índice de evento de reservas.

![The form of a new booking](images/new_booking.png)

Las reservas están cerradas una vez que el evento está mercado como cerrado, o
cuando la fecha de inicio está superada. Los administradores y los miembros del
personal siempre pueden agregar reservas nuevas.

El índice de actividades está revertido desde el evento; obligatoriamente unos
deben por supuesto ser revisado durante la reserva.

![The list of bookings](images/bookings_list.png)

Puede filtrar el índice de reservas por evento, tipo de remuneración o estado de
remuneración. Entonces puede enviar un correo a los miembros de la reserva,
utilizando el mecanismo de correo estándar de Galette.

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
