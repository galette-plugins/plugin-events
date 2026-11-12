---
title: Dokumentacija
description: Upravljanje dogodkov in rezervacij
---

Ta vtičnik ponuja:

* upravljanje dogodkov,
* povezovanje dejavnosti z dogodki,
* upravljanje rezervacij,
* koledar z dogodki za aplikacije koledarja,
* javna stran, na kateri so navedeni dogodki, ki jih objavite.

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

**Povzetek** dogodek predstavi z nekaj besedami, v seznamih in na javnih
straneh; če je prazen, se namesto njega uporabi začetek komentarja. **Komentar**
je v podrobnostih dogodka prikazan v celoti.

Razdelek **Kdaj** določa datume in ure dogodka. Za dogodek brez določenih ur
označite možnost **Ves dan** – polja za vnos časa bodo skrita, predhodno vneseni
podatki o času pa bodo prezrti. Sicer pa vnos časa tudi v drugih primerih ni
obvezen:

* **Brez začetnega časa**: dogodek traja ves dan, od začetnega do končnega
  datuma,
* **Samo začetni čas**: dogodek je prikazan ob svojem začetnem času, brez konca,
* **Čas začetka in konca**: znotraj istega dne čas konca ne more biti pred časom
  začetka.

Končni čas brez začetnega časa ni dovoljen. **Prikaz kot** med vnašanjem
prikazuje časovno obdobje tako, kot bo vidno na seznamih, v koledarju in na
javnih straneh.

> **Opomba** — Časi, povzetek in izbira »Kdo lahko vidi« so se pojavili v
> različici 2.4.0.

V razdelku **Kdo lahko to vidi** določite, kdo ima dostop do dogodka:

![Kdo lahko vidi dogodek](images/event_visibility.png)

* **Vsak član**: dogodek je na voljo vsem članom,
* **Skupina**: dostop bodo imeli samo člani in upravitelji izbrane skupine (in
  njenih podskupin),
* **Javno**: dogodek je na voljo vsem članom in je objavljen tudi na javnih
  straneh platforme Galette; glejte [Javni dogodki](#public-events).

> **Opomba**
> 
> Vodje skupin te izbire nimajo: izbrati morajo eno od skupin, ki jih
> upravljajo, in ne morejo objavljati dogodkov.

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

Na seznamu dogodkov lahko urejate ali brišete vnose, dostopate do seznama
rezervacij, izvozite rezervacije v formatu CSV ali prenesete dogodek za uporabo
v koledarskih aplikacijah. Stolpec z datumom prikazuje celotno trajanje dogodka,
vključno s časovnimi podatki, če so ti navedeni.

![Seznam dogodkov](images/events_list.png)

### Koledar

Vnos `Koledar` v meniju prikazuje dogodke, ki so vam vidni – bodisi po mesecih
bodisi v obliki dnevnih, tedenskih in mesečnih seznamov. Za člane so prikazani
tudi pretekli in zaključeni dogodki, ki jih sicer seznam dogodkov ne vključuje.
Kliknite na dogodek za ogled podrobnosti:

![Dogodek v koledarju](images/calendar_event.png)

Od tam lahko dogodek uredite (če imate za to dovoljenje), dodate novo
rezervacijo ali dogodek prenesete.

### Koledarske aplikacije

Dogodke lahko dodate v koledarske aplikacije, kot so Thunderbird, Google
Koledar, Outlook ali koledar na vašem telefonu, z uporabo formata iCalendar
(datoteke `.ics`).

> **Opomba** — Dodano v različici 2.4.0.

#### Prenesi dogodek

Uporabite ikono za prenos na seznamu dogodkov ali gumb **Prenesi** v
podrobnostih dogodka v koledarju. Prejeli boste datoteko `.ics`, ki jo lahko
odprete ali uvozite v svojo koledarsko aplikacijo. Datoteka vsebuje datum in uro
dogodka, lokacijo, komentar, ponujene dejavnosti ter povezavo za rezervacijo.

Prenesen dogodek je kopija: če se dogodek v sistemu Galette spremeni, se v vaši
koledarski aplikaciji ne bo posodobil. Prenesite ga znova ali pa se raje
naročite na koledar.

#### Naročite se na dogodke

Naročnina samodejno poskrbi, da je vaša koledarska aplikacija vedno
posodobljena. Uporabite gumb **Naroči se** na strani koledarja; ta doda
naročnino v vašo privzeto koledarsko aplikacijo. Ob prvi uporabi se ustvari vaš
osebni naslov za naročnino.

Puščica ob gumbu ponuja druge možnosti:

![Možnosti naročnine](images/calendar_subscription.png)

* **Kopiraj moj naslov za naročnino**, da ga ročno dodaš kot naročnino na
  koledar (včasih imenovano »koledar iz interneta« ali »nov koledar iz URL-ja«)
  v svojo aplikacijo,
* **Sprememba naslova za naročnino…**, glejte spodaj,
* **Kako se naročiti?** pojasnjuje, kako se naročiti prek najpogostejših
  koledarskih aplikacij:

![Kako se naročiti](images/subscription_help.png)

Naročnina vključuje iste dogodke kot vaš koledar: dogodke, ki niso omejeni na
določeno skupino, dogodke vaših skupin ter dogodke, ki ste jih rezervirali
(vključno z zaključenimi). Dogodki, ki so se zaključili pred več kot enim letom,
so izpuščeni. Besedila so v vašem jeziku.

Koledarske aplikacije osvežujejo naročnine po lastnem urniku; Galette zahteva
osvežitev vsako uro, nekatere aplikacije pa to počnejo redkeje (pri Google
Koledarju lahko to traja tudi do en dan).

> **Opozorilo**
> 
> Naslov za naročanje je oseben: vsakdo, ki ga pozna, lahko vidi vaše dogodke,
> ne da bi se moral prijaviti. Če je prišlo do razkritja naslova, uporabite
> možnost **Spremeni moj naslov za naročanje …**: takoj ko potrdite, prejšnji
> naslov preneha delovati, vi pa se boste morali znova naročiti z uporabo novega
> naslova.

Ko je članski račun onemogočen, preneha delovati tudi naslov.
Superadministrator, ki ni član, nima naročnine.

#### Časi in časovni pas

Časi se koledarskim aplikacijam posredujejo v univerzalnem času (UTC),
preračunani iz časovnega pasu strežnika; vsaka aplikacija jih nato prikaže v
svojem časovnem pasu. Če so časi v koledarskih aplikacijah zamaknjeni, časovni
pas PHP na strežniku (nastavitev `date.timezone`) verjetno ne ustreza časovnemu
pasu vašega društva; prosite ponudnika gostovanja ali skrbnika, da to popravi.

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

### Javni dogodki

Dogodke je mogoče prikazati osebam, ki niso prijavljene – na javni strani, prek
naročnine na koledarske aplikacije, ki ne zahteva uporabniškega računa.

> **Opomba** — Dodano v različici 2.4.0.

Če želite dogodek objaviti, v obrazcu v razdelku **Kdo ga lahko vidi** izberite
možnost **Javno**. Javni so lahko le dogodki, ki niso omejeni na določeno
skupino; dogodka, ki je omejen na določene člane, ni mogoče prikazati nikomur.
Upravitelji skupin, ki morajo izbrati eno od svojih skupin, dogodkov ne morejo
objaviti.

Ko so omogočene javne strani, meni javnih strani vključuje postavko `Dogodki`,
ki po mesecih prikazuje prihajajoče javne dogodke, vključno z datumi, krajem,
povzetkom in dejavnostmi; podatki o rezervacijah in udeležencih niso nikoli
prikazani. Gumb **Koledar** te dogodke prikaže v obliki koledarja, pri čemer si
stran zapomni vašo izbiro.

![Stran z javnimi dogodki](images/public_events.png)

Kliknite na ime dogodka za ogled podrobnosti: polni naslov s povezavo do
zemljevida, komentar in dejavnosti. Z izbiro **Dodaj v moj koledar** lahko
dogodek prenesete v koledarske aplikacije, ne da bi za to potrebovali
uporabniški račun.

![Podrobnosti o javnem dogodku](images/public_event_details.png)

Gumb **Naroči se** doda vse javne dogodke v koledarsko aplikacijo in skrbi za
njihovo posodabljanje; puščica ob njem omogoča kopiranje naslova za naročanje
oziroma pojasnjuje postopek naročanja. Ta naslov ni oseben: za vse je enak.

Dostop do te strani in njene naročnine je določen v skladu z nastavitvami
vidnosti za druge javne strani v razdelku z nastavitvami sistema Galette:
nastavitev za **dogodke** (Events) privzeto sledi nastavitvi vidnosti, razen če
jo spremenite. Glejte [kdo lahko vidi javne
strani](https://doc.galette.eu/en/master/usermanual/public_pages.html#who-can-see-them).
