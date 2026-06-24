# Livewire CMS Editor

A WordPress-classic-style rich text editor for **Laravel Livewire 4**, with
first-class **image insertion and per-placement image properties**
(width/height/class/style), built on **TipTap** and **Spatie MediaLibrary**.

> This is a **v0 skeleton**. It contains a coherent, opinionated architecture and
> working-shaped code, but is not yet a published, fully-tested package. See
> [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for every design decision and
> the open maintenance risks.

## Why

Editors like `flux:editor` are fine but too thin for clients coming from
WordPress. The missing feature is *inserting an image into the body* and setting
its display properties. Here that image is a real TipTap node backed by a
MediaLibrary record, so it's reusable across articles via a built-in picker.

## Requirements

- PHP 8.2+
- Laravel 11/12, Livewire 4
- Spatie MediaLibrary 11
- `ueberdosis/tiptap-php` (server-side rendering)

## Install

```bash
composer require degrinthorst/livewire-cms-editor
php artisan cms-editor:install
```

The `cms-editor:install` command publishes the config, optionally publishes the
pre-built assets, asks which **model** and **column(s)** hold the content, writes
those choices to your `.env`, and scaffolds a migration for the column(s). It
never edits your model — it prints the trait/interface/cast you add yourself.

Prefer to do it by hand? The manual equivalents are:

```bash
php artisan vendor:publish --tag=cms-editor-config
php artisan vendor:publish --tag=cms-editor-assets   # pre-built JS into public/vendor/cms-editor
```

### Columns: JSON vs cached HTML (ADR-003)

The package is **column-agnostic for the JSON** — the editor pushes ProseMirror
JSON through `wire:model` and your form persists it to your own column. The only
package-managed column is the *optional* rendered-HTML cache:

```php
// config/cms-editor.php  (driven by .env)
'columns' => [
    'json' => env('CMS_EDITOR_JSON_COLUMN', 'body'),   // source of truth, cast to array
    'html' => env('CMS_EDITOR_HTML_COLUMN'),            // null = render on the fly
],
```

When `columns.html` is set, add the `SyncsEditorHtml` trait to your model and the
HTML column is re-rendered from the JSON on every save (image src is re-resolved
from MediaLibrary at that moment — a renamed/replaced image refreshes on the
next save).

Front-end — either use the pre-built bundle, or import the source in your `app.js`:

```js
import { registerCmsEditor } from '@degrinthorst/livewire-cms-editor'
document.addEventListener('alpine:init', () => registerCmsEditor(window.Alpine))
```

Include the base styles (optional): `resources/css/cms-editor.css`.

## Prepare your model

```php
use Degrinthorst\CmsEditor\Concerns\InteractsWithEditorMedia;
use Degrinthorst\CmsEditor\Contracts\HasEditorMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Article extends Model implements HasEditorMedia
{
    use InteractsWithMedia;
    use InteractsWithEditorMedia;

    public function registerMediaCollections(): void
    {
        $this->registerEditorMediaCollection();
    }

    protected $casts = ['body' => 'array']; // ProseMirror JSON (ADR-003)
}
```

Want the cached-HTML column? Also `use Degrinthorst\CmsEditor\Concerns\SyncsEditorHtml;`
and set `CMS_EDITOR_HTML_COLUMN` (the install command does both for you).

Set `article_model` in `config/cms-editor.php` (or `CMS_EDITOR_ARTICLE_MODEL` in
`.env`).

## Use it

```blade
{{-- inside a Livewire form component with a public array $body --}}
<x-cms-editor wire:model="body" :model="$article" />
```

Render the stored document to HTML on the front-end:

```php
app(\Degrinthorst\CmsEditor\Support\ContentRenderer::class)->toHtml($article->body);
```

## How it fits together

| Concern | Where |
|---|---|
| Editor engine | TipTap (`resources/js/editor.js`) inside `wire:ignore` |
| Image node | `resources/js/extensions/MediaImage.js` + `src/Extensions/MediaImage.php` |
| Media picker | `src/Livewire/MediaPicker.php` (restrained to model + collection) |
| Storage | ProseMirror JSON, rendered via `ContentRenderer` |
| Safety | `ContentSanitizer` allowlist (swap for HTMLPurifier in prod) |
| Livewire sync | one-way bridge, see ADR-006 |

## Hardening the sanitizer

The bundled `ContentSanitizer` is dependency-free and deliberately simple. For
production, bind HTMLPurifier instead:

```php
$this->app->bind(
    \Degrinthorst\CmsEditor\Support\ContentSanitizer::class,
    YourPurifierSanitizer::class,
);
```

## Distribution & maintenance

The pre-built `dist/cms-editor.js` is committed to the repo so `composer require`
works without a Node toolchain (Packagist serves the git-tag archive). Keep it
fresh: run `npm run build` and commit the result before tagging a release — CI
rebuilds and **fails if the committed bundle drifts from the source**.

Supply-chain hardening:

- `package-lock.json` is committed; CI uses `npm ci` (verifies integrity hashes).
- `renovate.json` enforces `minimumReleaseAge: "7 days"` — no dependency version
  younger than a week is adopted (the window in which most malicious releases get
  caught and yanked). Majors are gated behind the dependency dashboard. Requires
  enabling the Renovate GitHub app on the repo.
- `.npmrc` `save-exact` stops caret ranges from silently floating.
- CI gates on `npm audit` for production deps, pins GitHub Actions to commit
  SHAs, and runs with least-privilege `contents: read`.

## Roadmap

- Image-properties panel UI (the node + command already support it).
- `cms-editor:prune-orphans` command (Onderhoudsrisico #2).
- Livewire 3 compat layer.
- JSON↔HTML render snapshot tests (Onderhoudsrisico #3).

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the full reasoning.
