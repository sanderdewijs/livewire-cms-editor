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
php artisan vendor:publish --tag=cms-editor-config
php artisan vendor:publish --tag=cms-editor-assets   # pre-built JS into public/vendor/cms-editor
```

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

Set `article_model` in `config/cms-editor.php`.

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

## Roadmap

- Image-properties panel UI (the node + command already support it).
- `cms-editor:prune-orphans` command (Onderhoudsrisico #2).
- Livewire 3 compat layer.
- JSON↔HTML render snapshot tests (Onderhoudsrisico #3).

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the full reasoning.
