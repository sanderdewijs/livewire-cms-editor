# Architectuur — Livewire CMS Editor

> Een WordPress-classic-achtige rich text editor voor Laravel Livewire, met
> first-class image-insert en eigen image-properties, gebouwd op TipTap en
> Spatie Laravel MediaLibrary.

Status: **draft / v0** · Doel-target: **Livewire 4** (Livewire 3 later) ·
Vendor/namespace in dit skelet: `degrinthorst/livewire-cms-editor` →
`Degrinthorst\CmsEditor`

Dit document legt de belangrijkste ontwerpbeslissingen vast in ADR-stijl: per
beslissing de **context**, de **keuze**, en de **consequenties** (incl. wat het
op termijn lastiger te onderhouden maakt). Onderaan staat een aparte sectie
"Onderhoudsrisico's" met de dingen die ik tijdens het uitwerken tegenkwam.

---

## 1. Scope en uitgangspunten

De editor moet voelen als de WordPress classic editor voor klanten die daar
vandaan komen: één doorlopend bewerkbaar tekstveld, vertrouwde toolbar, en —
de kernfeature — afbeeldingen die je *middenin de tekst* plaatst en waarvan je
per plaatsing `width`, `height`, CSS-classes en inline `style` kunt instellen.

Opinionated keuzes die we bewust maken:

- **Spatie MediaLibrary is een harde dependency.** Afbeeldingen worden altijd
  als `Media` opgeslagen en gekoppeld aan een geconfigureerd artikel-model.
- **TipTap (ProseMirror) is de editor-engine.** Geen abstractielaag eroverheen.
- **De editor is classic, niet block-based** (zie ADR-002).
- **De source of truth is ProseMirror-JSON, niet HTML** (zie ADR-003).

Niet-doel voor v0: een volwaardige Gutenberg-block editor, collaboratieve
realtime editing, en Livewire 3-support (komt later als compat-laag).

---

## ADR-001 — Editor-engine: TipTap

**Context.** De kernfeature is een image-node die in de tekstflow zit en eigen,
getypeerde attributen draagt. We willen geen contenteditable-HTML handmatig
bijhouden, en we willen volledige controle over de toolbar en de uitvoer.

**Keuze.** We bouwen op **TipTap** (headless wrapper rond ProseMirror, MIT).

**Waarom niet de alternatieven.**

- *Quill* — moeilijk uit te breiden met rijke custom nodes met meerdere attrs.
- *CKEditor 5* — zwaar, en sommige plugins zitten achter een commerciële
  licentie; slecht passend bij een open, opinionated package.
- *flux:editor (huidige situatie)* — te arm; geen image-insert met properties.
- *Kale contenteditable* — geen schema, dus geen garantie op geldige structuur.

**Consequenties.**

- ProseMirror's schema **garandeert geldige documentstructuur**. Plakken uit
  Word/WordPress wordt geschoond naar het schema in plaats van rommel-HTML.
- Onze image wordt een custom node met `attrs`: `mediaId`, `src`, `alt`,
  `width`, `height`, `class`, `style`. Properties zijn dus eerste-rangs data,
  geen geparste HTML-strings.
- Server-side rendering kan met het officiële PHP-pakket
  [`ueberdosis/tiptap-php`](https://github.com/ueberdosis/tiptap-php), dat
  ProseMirror-JSON ↔ HTML omzet. Dat maakt ADR-003 (JSON-opslag) praktisch.
- Kostprijs: ProseMirror heeft een leercurve. Custom nodes, node-views en de
  schema-API vergen begrip. Dit is geïsoleerd in `resources/js` en hoeft niet
  door consumenten begrepen te worden.

---

## ADR-002 — Classic editor, geen block editor

**Context.** Open vraag: maken we er een block editor (Gutenberg-achtig) van?

**Keuze.** **Classic single-document editor.** Eén bewerkbaar document dat
JSON/HTML oplevert. Geen losse, herordenbare blokken op top-level.

**Waarom.** De doelgroep mist de *classic* editor, niet Gutenberg. Een echte
block editor kost per-block state, drag-reorder, per-block toolbars en een veel
complexere Livewire-sync — zonder de kernfeature (rich images in tekst) beter
te bedienen.

**Het belangrijke inzicht.** TipTap vervaagt de grens al. Een afbeelding,
embed of callout is in ProseMirror een **node binnen de flow** — feitelijk een
"block" middenin de tekst. We krijgen het block-achtige gedrag waar het ertoe
doet zónder de architectuur van een block editor.

**Consequenties.**

- Eenvoudiger state-model en Livewire-bridge.
- Uitbreidbaar: meer node-types toevoegen (callout, embed, gallery) kan
  incrementeel. We timmeren niets dicht; een latere block-modus blijft mogelijk.
- We leveren geen top-level drag-reorder van blokken in v0.

---

## ADR-003 — Opslag: ProseMirror-JSON als source of truth

**Context.** We kunnen de inhoud opslaan als HTML (simpel) of als
ProseMirror-JSON (rijker). De image-node verwijst naar een `Media`-record.

**Keuze.** Sla **JSON** op als source of truth. Render naar HTML server-side
met `tiptap-php` (of client-side waar nodig).

**Waarom.**

- De `mediaId` blijft de bron. Wordt een afbeelding hernoemd, vervangen of
  geconverteerd in MediaLibrary, dan render je bij weergave altijd de **actuele
  URL** — geen dode `<img src>` in oude artikelen.
- JSON is veel makkelijker programmatisch te transformeren (migraties, search
  indexing, herrendering naar AMP/e-mail, etc.).
- Sanitisatie en re-resolving gebeuren centraal bij het renderen.

**Consequenties.**

- Render-stap nodig bij weergave. We cachen de gerenderde HTML per record
  (bijv. een `body_html`-kolom of cache-key) en invalideren bij save of bij
  wijziging van gekoppelde Media.
- HTML-opslag zou simpeler zijn maar bevriest de afbeeldingspaden en maakt
  latere transformaties pijnlijk. Bewust niet gekozen.

> Pragmatische uitweg als de render-stap te veel is voor een consument: we
> bieden een config-flag `storage => 'html'` waarmee de editor direct HTML
> opslaat. JSON blijft de aanbevolen default.

---

## ADR-004 — Image-properties: scheiding intrinsiek vs. presentatie

**Context.** Een afbeelding heeft data die *bij het bestand* hoort (alt,
originele afmetingen, focal point) en data die *bij déze plaatsing* hoort
(width/height/class/style in dit specifieke artikel).

**Keuze.** Splitsen:

- **Intrinsieke data → op het `Media`-model** als MediaLibrary custom
  properties (`alt`, `caption`, focal point, originele dimensies).
- **Presentatie-overrides → als node-`attrs` in het document** (`width`,
  `height`, `class`, `style` per insertie).

**Waarom.** Een WordPress-gebruiker verwacht dat dezelfde afbeelding in artikel
A 300px breed kan zijn en in artikel B full-width. Zouden width/height op het
Media-record staan, dan overschrijft de ene plaatsing de andere.

**Consequenties.**

- De picker toont en bewerkt intrinsieke data (alt, caption) → schrijft naar
  Media.
- Het "image properties"-paneel in de editor bewerkt de node-attrs → leeft in
  het document.
- Bij render combineren we: bron + alt uit Media, presentatie uit de node.

---

## ADR-005 — MediaLibrary-koppeling via een dedicated collection

**Context.** De picker moet alleen afbeeldingen tonen die via de editor aan het
geconfigureerde artikel-model zijn gekoppeld — niet de hele library. Het
oorspronkelijke idee was filteren op `model_type = Article`.

**Probleem met `model_type`.** Bij een **nieuw artikel dat nog niet bestaat** op
uploadmoment is er geen model-id om aan te koppelen.

**Keuze.** Gebruik een vaste **MediaLibrary-collection** (default:
`article_body`, instelbaar in config) op het geconfigureerde model. De picker
filtert op die collection over alle records van dat model:

```php
Media::query()
    ->where('model_type', config('cms-editor.article_model'))
    ->where('collection_name', config('cms-editor.collection'))
    ->latest()
    ->paginate(config('cms-editor.picker_per_page', 24));
```

**Nieuwe artikelen.** Drie ondersteunde strategieën (config
`upload_binding`):

1. `draft` (default) — bij eerste upload maakt de host een draft-record (of de
   editor krijgt een al-bestaand model mee). Veiligst en voorspelbaarst.
2. `temporary` — upload als losgekoppelde Media in de collection; bij save van
   het artikel koppelen we de gebruikte Media aan het model.
3. `model` — host geeft altijd een bestaand model mee (formulier werkt met een
   reeds-gepersisteerd record).

**Consequenties.**

- Native MediaLibrary-concept, geen eigen tabellen.
- Andere library-afbeeldingen blijven automatisch buiten beeld.
- We moeten "orphan" Media (geüpload maar nooit gebruikt) periodiek opruimen —
  zie Onderhoudsrisico's.

---

## ADR-006 — Livewire 4 + Alpine integratie

**Context.** Livewire 4 update de DOM via **morphing**. Onze editor houdt
complexe state in diezelfde DOM. Als Livewire daarin morpht, sloopt het de
editor-instance.

**Keuze — het bridge-patroon.**

1. **`wire:ignore` op het mount-element.** Exact waar `wire:ignore` voor is
   bedoeld: third-party JS-widgets. Livewire laat die subtree met rust.
2. **TipTap leeft in een Alpine-component.** `init()` bouwt de editor,
   `destroy()` roept `editor.destroy()` — cruciaal tegen leaks bij
   `wire:navigate`.
3. **Bewuste één-richting-sync.** Editor → Livewire continu (debounced) via
   `onUpdate`; Livewire → editor *alleen* bij expliciete externe set
   (record laden) via `editor.commands.setContent()`. De property morpht nooit
   terug de editor in.
4. **Livewire 4 meevaller.** Alpine's `$wire` deelt nu dezelfde reactive proxy
   als Livewire's data, dus de glue is schoner dan in v3. Let op de gewijzigde
   `wire:model`-semantiek in v4 (luistert alleen nog naar events direct op het
   element zelf, `.self`-gedrag) — relevant als je een hidden input als brug
   gebruikt; wij gebruiken liever `$wire.set()` expliciet.

**Consequenties.**

- Robuust tegen re-renders; de editor overleeft server-roundtrips.
- Livewire 3-support is een aparte compat-laag: het sync-patroon (geen gedeelde
  proxy, andere `wire:model`-semantiek) verschilt net genoeg om apart te houden.

---

## ADR-007 — Distributie en build

**Context.** Consumenten moeten dit kunnen installeren zonder een complexe
front-end build te draaien.

**Keuze.** Lever een **voorgecompileerde JS-bundle** mee (`dist/`), geladen via
de Blade-component. Daarnaast een bron-entry (`resources/js`) voor wie zelf wil
bundelen met Vite.

**Consequenties.**

- Lage drempel: `composer require` + asset publishen werkt out-of-the-box.
- We moeten de gebundelde `dist/` actueel houden bij releases (CI-stap).
- TipTap is niet piepklein; we documenteren de bundelgrootte en bieden een
  "bring your own bundle"-pad voor wie tree-shaking wil.

---

## ADR-008 — Security: sanitisatie van uitvoer

**Context.** Gebruikers stellen zelf `class` en inline `style` in op
afbeeldingen (en mogelijk op tekst). Dit is een distribueerbaar package dat in
vreemde projecten draait.

**Keuze.** **Sanitize bij het renderen naar HTML** met een allowlist die onze
image-attrs en een beperkte set inline styles toelaat (bijv. via
`mews/purifier`/HTMLPurifier of een eigen allowlist over de tiptap-php output).

**Consequenties.**

- Voorkomt een XSS-vector in elk consumentenproject (`style="..."` met
  `expression()`/`url(javascript:...)`, `onerror`-injectie, etc.).
- We onderhouden een allowlist; nieuwe node-types moeten hun toegestane attrs
  registreren.

---

## Componentenoverzicht

```
Blade <x-cms-editor /> ─┐
                        ▼
        Livewire\Editor (PHP)
          - houdt content (JSON) als property
          - opent/sluit de MediaPicker
          - valideert & sanitiseert bij save
                        │  emits/listeners
                        ▼
        Livewire\MediaPicker (PHP)
          - Media::... gefilterd op model + collection (ADR-005)
          - upload via Livewire file upload → addMedia naar collection
          - geeft gekozen mediaId + intrinsieke data terug aan de editor
                        │
   resources/js/editor.js (Alpine component, in wire:ignore)
          - TipTap-instance (init/destroy lifecycle, ADR-006)
          - MediaImage custom node (ADR-001/004)
          - debounced onUpdate → $wire.set('content', json)
                        │
   Support\ContentRenderer (tiptap-php)  → JSON → HTML
   Support\ContentSanitizer              → allowlist (ADR-008)
```

Interface voor het host-model:

```php
class Article extends Model implements HasMedia, HasEditorMedia
{
    use InteractsWithMedia;          // Spatie
    use InteractsWithEditorMedia;    // dit package: registreert de collection
}
```

---

## Onderhoudsrisico's (dingen om in de gaten te houden)

1. **ProseMirror/TipTap major upgrades.** Custom nodes en het schema kunnen
   breken bij TipTap-majors. Mitigatie: node-definities geïsoleerd houden in
   `resources/js/extensions/`, en pinnen op een minor-range met expliciete
   upgrade-tests.

2. **Orphan-Media.** Met `upload_binding=temporary` ontstaan geüploade maar
   nooit-gebruikte afbeeldingen. We hebben een opruim-commando nodig
   (`cms-editor:prune-orphans`) dat Media in de collection vergelijkt met de
   `mediaId`'s die daadwerkelijk in opgeslagen documenten voorkomen.

3. **JSON ↔ HTML render-drift.** De client (TipTap JS) en server (tiptap-php)
   moeten dezelfde HTML produceren, anders krijg je verspringende preview vs.
   opgeslagen output. Mitigatie: snapshot-tests die een set JSON-documenten
   door beide renderers halen en vergelijken.

4. **Livewire 3 vs 4 divergentie.** Het bridge-patroon verschilt (gedeelde
   proxy, `wire:model`-semantiek). Houd dit in een dunne compat-laag in plaats
   van `if (version)` door de codebase te strooien.

5. **Sanitisatie-allowlist als bottleneck.** Elke nieuwe node/attr moet
   geregistreerd worden in de allowlist, anders wordt 'ie stilletjes gestript.
   Maak de allowlist data-driven per node-extensie.

6. **Bundle-onderhoud.** `dist/` moet bij elke release herbouwd. Zonder
   CI-automatisering raakt de gebundelde JS achter op de bron.

7. **Gekoppelde Media verwijderd.** Als een `Media`-record wordt verwijderd
   terwijl het nog in een document staat, moet de renderer gracieus degraderen
   (placeholder of weglaten) i.p.v. een fatal. Render-laag moet `mediaId` →
   Media defensief opzoeken.

8. **Config-model resolutie.** `article_model` komt uit config; bij meerdere
   editable modellen (bijv. Article én Page) wil je dit per-instance kunnen
   overschrijven op de Blade-component i.p.v. één globale config-waarde.
```