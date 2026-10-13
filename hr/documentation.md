---
title: Dokumentacija
description: Upravljanje događajima i rezervacijama
---

Dodatak omogućuje:

* upravljanje događajima,
* povezivanje aktivnosti s događajima,
* upravljanje rezervacijama.

## Instalacija

Najprije preuzmite dodatak:

* [Preuzmite najnoviji dodatak
  Events!](https://github.com/galette-plugins/plugin-events/releases/latest)
* [Preuzmite noćnu razvojnu verziju dodatka
  Events!](https://github.com/galette-plugins/plugin-events/releases/tag/nightly)

Raspakirajte preuzetu arhivu u Galetteovu mapu `plugins`. Primjerice, na Linuxu
(zamijenite `{url}` i `{version}` odgovarajućim vrijednostima):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-events-{version}.tar.bz2
```

## Inicijalizacija baze podataka

Dodatak zahtijeva nekoliko tablica u bazi. Pogledajte [sučelje za upravljanje
dodacima
Galettea](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

Gotovo; dodatak Events je instaliran :)

## Uporaba dodatka

Nakon instalacije u izborniku prijavljenog korisnika pojavljuje se odjeljak
`Događaji`. Dostupne mogućnosti ovise o korisnikovoj ulozi: obični član,
voditelj grupe, administrator i slično.

### Aktivnosti

Možete definirati proizvoljan broj aktivnosti i povezati ih s događajem.
Aktivnost može biti organizirani izlet, obrok, smještaj i slično.

![Popis aktivnosti](images/list_activities.png)

Aktivnost sadrži naziv, status i neobveznu napomenu.

Za dodavanje aktivnosti kliknite poveznicu „Nova aktivnost”:

![Obrazac nove aktivnosti](images/new_activity.png)

### Događaji

Događaji su središnja funkcija dodatka. Možete zadati naziv, datume početka i
završetka, lokaciju i druge podatke.

![Obrazac novog događaja](images/new_event.png)

Naziv, datum početka i mjesto obvezni su. Ostali podaci nisu obvezni.

Događaji koji nisu povezani s grupom dostupni su svim članovima. Ako je zadana
grupa, pristup imaju samo njezini članovi i voditelji.

> **Napomena**
> 
> Kad voditelj grupe dodaje događaj, mora odabrati jednu od svojih grupa!

Događaju možete pridružiti jednu ili više aktivnosti i za svaku odrediti je li
dostupna, nedostupna ili obvezna. Odaberite aktivnost i kliknite gumb.

![Aktivnosti povezane s događajem](images/event_activities.png)

> **Upozorenje**
> 
> Dodavanje ili uklanjanje aktivnosti ponovno učitava stranicu i traži unos
> obveznih podataka. Međutim, događaj se tijekom toga **ne sprema**, na što se
> svaki put upozorava.
> 
> Ne zaboravite spremiti događaj :)

U popisu događaja možete uređivati i brisati zapise, otvoriti popis rezervacija
ili ih izvesti u CSV.

![Popis događaja](images/events_list.png)

### Rezervacije

Za svaki događaj možete bilježiti rezervacije. Kao što je navedeno, obični
članovi i voditelji grupa imaju pristup događajima svojih grupa i događajima bez
ograničenja na grupu.

Novu rezervaciju možete dodati putem stavke „Nova rezervacija” ili popisa
rezervacija događaja.

![Obrazac nove rezervacije](images/new_booking.png)

Rezervacije se zatvaraju kad je događaj označen kao zatvoren ili kad prođe datum
početka. Administratori i uprava uvijek mogu dodati rezervaciju.

Popis aktivnosti preuzima se iz događaja. Obvezne aktivnosti morate označiti pri
rezervaciji.

![Popis rezervacija](images/bookings_list.png)

Rezervacije možete filtrirati prema događaju, načinu plaćanja ili statusu
plaćanja. Zatim možete poslati skupnu poruku članovima s rezervacijom putem
uobičajenog Galetteova sustava.
