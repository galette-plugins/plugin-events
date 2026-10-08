---
title: Documentation
description: Event and booking management
---

This plugin provides:

* events management,
* associate activities with events,
* booking management.

## Installation

First of all, download the plugin:

* [Get latest Events
  plugin!](https://github.com/galette-plugins/plugin-events/releases/latest)
* [Get Events plugin nightly
  build!](https://github.com/galette-plugins/plugin-events/releases/tag/nightly)

Extract the downloaded archive in Galette `plugins` directory. For example,
under linux (replacing `{url}` and `{version}` with correct values):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-events-{version}.tar.bz2
```

## Database initialisation

In order to work, this plugin requires several tables in the database. See
[Galette plugins management
interface](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

And this is finished; Events plugin is installed :)

## Plugin usage

When the plugin is installed, an `Events` group is added to Galette menu when a
user is logged in. There are various possibilities that change depending on user
profile (simple member, group manager, administrator, ...).

### Activities

You can define as much activities as you want, and associate them to an event.
An activity may be an organized trip, a meal, a housing, ...

![The list of activities](images/list_activities.png)

An activity is composed with a name, a status and an optional comment.

To add a new activity, just click on "New activity" link:

![The form of a new activity](images/new_activity.png)

### Events

Events are the main goal of the plugin. You can define several information, like
a name, begin and ending dates, location, ...

![The form of a new event](images/new_event.png)

Name, begin date and town are mandatory. All other information are entirely
optional.

Events that are not linked to a group will be available for all members. If a
group is set, only members and managers of this group will have access.

> **Note**
> 
> When a group manager creates a new event, he must choose one of the groups he
> owns!

You can attach one or several activities to each event, and for each one set if
it is available, not available or even mandatory. Choose the activity to add,
and click the button.

![The activities attached to an event](images/event_activities.png)

> **Warning**
> 
> Adding or removing activity from an event will reload the page and ask you to
> fill mandatory information. Nonetheless (and this is specified each time), the
> event **will not be stored** during this operation.
> 
> Ensure you save the event :)

From Events list, you can edit or remove entries, access to booking list or
export bookings as CSV.

![The list of events](images/events_list.png)

### Bookings

Bookings can be registered for each event. As we said before, simple members and
groups managers will be limited to their groups events, or to the events that
are not restricted to a group.

Adding a new booking can be achieved from the menu "New booking" or from the
event bookings list.

![The form of a new booking](images/new_booking.png)

Bookings are closed once the event is marked as close, or when the begin date is
over. Administrators and staff members can always add new bookings.

Activities list is retrieved from the event; mandatory ones must of course be
checked during booking.

![The list of bookings](images/bookings_list.png)

You can filter bookings list per event, payment type or payment status. You can
then send a mailing to booked members, using the standard Galette mailing
mechanism.
