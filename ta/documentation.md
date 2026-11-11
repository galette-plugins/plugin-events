---
title: ஆவணமாக்கல்
description: Event and booking management
---

இந்த சொருகி வழங்குகிறது:

* நிகழ்வுகள் மேலாண்மை,
* நிகழ்வுகளுடன் தொடர்புடைய நடவடிக்கைகள்,
* booking management,
* a calendar, with events for calendar applications,
* a public page listing the events you publish.

## நிறுவல்

முதலில், சொருகி பதிவிறக்கவும்:

* [Get latest Events
  plugin!](https://github.com/galette-plugins/plugin-events/releases/latest)
* [Get Events plugin nightly
  build!](https://github.com/galette-plugins/plugin-events/releases/tag/nightly)

பதிவிறக்கம் செய்யப்பட்ட காப்பகத்தைக் கேலட் `செருகுநிரல்கள்` கோப்பகத்தில்
பிரித்தெடுக்கவும். எடுத்துக்காட்டாக, லினக்சின் கீழ் (`{url}` மற்றும் `{version}`
ஆகியவற்றை சரியான மதிப்புகளுடன் மாற்றுகிறது):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-events-{version}.tar.bz2
```

## தரவுத்தள துவக்கம்

வேலை செய்ய, இந்தச் சொருகி தரவுத்தளத்தில் பல அட்டவணைகள் தேவை. காண்க [கேலட்
செருகுநிரல்கள் மேலாண்மை
இடைமுகம்](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

இது முடிந்தது; நிகழ்வுகள் சொருகி நிறுவப்பட்டுள்ளது :)

## சொருகி பயன்பாடு

சொருகி நிறுவப்பட்டால், ஒரு பயனர் உள்நுழையும்போது `நிகழ்வுகள்` குழு கேலட்
பட்டியலில் சேர்க்கப்படுகிறது. பயனர் சுயவிவரத்தைப் பொறுத்து மாறுபடும் பல்வேறு
சாத்தியங்கள் உள்ளன (எளிய உறுப்பினர், குழு மேலாளர், நிர்வாகி, ...).

### செயல்பாடுகள்

நீங்கள் விரும்பும் அளவுக்கு செயல்பாடுகளை நீங்கள் வரையறுக்கலாம், மேலும் அவற்றை
ஒரு நிகழ்வோடு தொடர்புபடுத்தலாம். ஒரு செயல்பாடு ஒரு ஒழுங்கமைக்கப்பட்ட பயணம்,
உணவு, ஒரு வீட்டுவசதி, ...

![The list of activities](images/list_activities.png)

ஒரு செயல்பாடு ஒரு பெயர், நிலை மற்றும் விருப்பமான கருத்துடன் இயற்றப்பட்டுள்ளது.

புதிய செயல்பாட்டைச் சேர்க்க, "புதிய செயல்பாடு" இணைப்பைக் சொடுக்கு செய்க:

![The form of a new activity](images/new_activity.png)

### நிகழ்வுகள்

நிகழ்வுகள் சொருகி முக்கிய குறிக்கோள். ஒரு பெயர் போன்ற பல தகவல்களை நீங்கள்
வரையறுக்கலாம், தேதிகள், இடம், இருப்பிடம், ...

![The form of a new event](images/new_event.png)

பெயர், தொடக்க தேதி மற்றும் நகரம் கட்டாயமாகும். மற்ற எல்லா தகவல்களும் முற்றிலும்
விருப்பமானவை.

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

ஒவ்வொரு நிகழ்விற்கும் நீங்கள் ஒன்று அல்லது பல செயல்பாடுகளை இணைக்கலாம், மேலும்
ஒவ்வொன்றிற்கும் அது கிடைத்தால் அது கிடைக்கவில்லை அல்லது கட்டாயமாக இல்லை. சேர்க்க
செயல்பாட்டைத் தேர்வுசெய்து, பொத்தானைக் சொடுக்கு செய்க.

![The activities attached to an event](images/event_activities.png)

> **Warning**
> 
> ஒரு நிகழ்விலிருந்து செயல்பாட்டைச் சேர்ப்பது அல்லது அகற்றுவது பக்கத்தை மீண்டும்
> ஏற்றி, கட்டாய தகவல்களை நிரப்பும்படி கேட்கும். ஆயினும்கூட (இது ஒவ்வொரு முறையும்
> குறிப்பிடப்பட்டுள்ளது), இந்தச் செயல்பாட்டின்போது நிகழ்வு **சேமிக்கப்படாது**.
> 
> நிகழ்வை சேமிப்பதை உறுதிசெய்க :)

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

### முன்பதிவுகள்

ஒவ்வொரு நிகழ்விற்கும் முன்பதிவு பதிவு செய்யலாம். நாங்கள் முன்பு கூறியது போல்,
எளிய உறுப்பினர்கள் மற்றும் குழுக்கள் மேலாளர்கள் தங்கள் குழுக்களின்
நிகழ்வுகளுக்கு மட்டுப்படுத்தப்படுவார்கள், ஒரு குழுவிற்கு கட்டுப்படுத்தப்படாத
நிகழ்வுகளுக்கு ஓயு.

புதிய முன்பதிவைச் சேர்ப்பது மெனுவிலிருந்து "புதிய முன்பதிவு" அல்லது நிகழ்வு
முன்பதிவு பட்டியலிலிருந்து அடையலாம்.

![The form of a new booking](images/new_booking.png)

நிகழ்வு நெருக்கமாக குறிக்கப்பட்டவுடன் அல்லது தொடக்க தேதி முடிந்ததும் முன்பதிவு
மூடப்படும். நிர்வாகிகள் மற்றும் பணியாளர்கள் எப்போதும் புதிய முன்பதிவுகளைச்
சேர்க்கலாம்.

செயல்பாடுகள் பட்டியல் நிகழ்விலிருந்து மீட்டெடுக்கப்படுகிறது; முன்பதிவின் போது
கட்டாயமாக சரிபார்க்கப்பட வேண்டும்.

![The list of bookings](images/bookings_list.png)

ஒரு நிகழ்வுக்கு முன்பதிவு பட்டியலை வடிகட்டலாம், கட்டண வகை அல்லது கட்டண நிலை.
நிலையான கேலட் அஞ்சல் பொறிமுறையைப் பயன்படுத்தி முன்பதிவு செய்யப்பட்ட
உறுப்பினர்களுக்கு நீங்கள் ஒரு அஞ்சலை அனுப்பலாம்.

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
