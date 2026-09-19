---
title: Dokumentacija
description: Upravljanje dogodkov in rezervacij
---

Ta vtičnik ponuja:

* upravljanje dogodkov,
* povezovanje dejavnosti z dogodki,
* upravljanje rezervacij.

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

Dogodki, ki niso povezani s skupino, bodo na voljo vsem članom. Če je skupina
nastavljena, bodo imeli dostop le člani in upravitelji te skupine.

> **Opomba**
> 
> Ko upravitelj skupine ustvari nov dogodek, mora izbrati eno od skupin, katerih
> lastnik je!

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

Na seznamu dogodkov lahko urejate ali odstranjujete vnose, dostopate do seznama
rezervacij ali izvozite rezervacije kot CSV.

![Seznam dogodkov](images/events_list.png)

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
