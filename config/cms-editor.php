<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Article model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that editor images are attached to. This model MUST
    | implement Spatie\MediaLibrary\HasMedia and use the package's
    | InteractsWithEditorMedia trait.
    |
    | Can be overridden per editor instance via the Blade component's
    | :model="$article" / :model-class="..." props.
    |
    */
    'article_model' => env('CMS_EDITOR_ARTICLE_MODEL', \App\Models\Article::class),

    /*
    |--------------------------------------------------------------------------
    | MediaLibrary collection (ADR-005)
    |--------------------------------------------------------------------------
    |
    | Images inserted through the editor live in this collection. The media
    | picker is restrained to this collection so the rest of the media library
    | never shows up.
    |
    */
    'collection' => 'article_body',

    /*
    |--------------------------------------------------------------------------
    | Upload binding strategy (ADR-009)
    |--------------------------------------------------------------------------
    |
    | How freshly-uploaded images are owned before the host article is saved:
    |   - 'bucket' (default) : attach to the package upload bucket; the optional
    |                          AdoptsEditorMedia trait re-parents them onto the
    |                          host on save. Works identically for new + existing
    |                          articles — no draft record required.
    |   - 'model'            : always attach directly to the supplied :model
    |                          instance (host must pass a persisted model; no
    |                          bucket, no adoption).
    |
    | Any other value is treated as 'bucket'.
    |
    */
    'upload_binding' => env('CMS_EDITOR_UPLOAD_BINDING', 'bucket'),

    /*
    |--------------------------------------------------------------------------
    | Upload bucket (ADR-009)
    |--------------------------------------------------------------------------
    |
    | The package-owned model that owns not-yet-adopted uploads, and how its
    | rows are scoped:
    |   - 'user' (default) : one bucket per authenticated user; pending uploads
    |                        stay private to the uploader until adopted.
    |   - 'singleton'      : one shared bucket for everyone.
    |   - callable(): ?string : compute your own scope (e.g. per tenant).
    |
    */
    'upload_bucket' => [
        'model' => \Degrinthorst\CmsEditor\Models\EditorUpload::class,
        'scope' => env('CMS_EDITOR_UPLOAD_BUCKET_SCOPE', 'user'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Orphan pruning (ADR-009)
    |--------------------------------------------------------------------------
    |
    | `cms-editor:prune-orphans` deletes editor media no document references.
    |   - 'sources'         : [model, json-column] pairs to scan for live
    |                         references. Empty => the configured article model
    |                         + json column.
    |   - 'bucket_ttl_days' : grace window before un-inserted bucket uploads are
    |                         pruned (null = never prune bucket uploads by age).
    |
    */
    'prune' => [
        'sources' => [],
        'bucket_ttl_days' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage format (ADR-003)
    |--------------------------------------------------------------------------
    |
    | 'json' (recommended) stores the ProseMirror document as source of truth
    | and renders to HTML on demand. 'html' stores rendered HTML directly.
    |
    */
    'storage' => 'json',

    /*
    |--------------------------------------------------------------------------
    | Content columns (ADR-003)
    |--------------------------------------------------------------------------
    |
    | The package never owns your article table — these tell the optional
    | SyncsEditorHtml trait which columns to read from and write to:
    |
    |   - 'json' : the column holding the ProseMirror JSON document (cast to
    |              array on your model). This is the source of truth.
    |   - 'html' : an OPTIONAL column that caches the rendered HTML. null =
    |              disabled (render on the fly via ContentRenderer instead).
    |              When set, add the SyncsEditorHtml trait to your model and it
    |              re-renders this column from the JSON on every save.
    |
    | `php artisan cms-editor:install` scaffolds a migration for these.
    |
    */
    'columns' => [
        'json' => env('CMS_EDITOR_JSON_COLUMN', 'body'),
        'html' => env('CMS_EDITOR_HTML_COLUMN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media picker
    |--------------------------------------------------------------------------
    */
    'picker_per_page' => 24,

    /*
    |--------------------------------------------------------------------------
    | Conversions (optional)
    |--------------------------------------------------------------------------
    |
    | Default MediaLibrary conversion used for the src of inserted images.
    | Leave null to use the original.
    |
    */
    'default_conversion' => null,

    /*
    |--------------------------------------------------------------------------
    | Sanitization allowlist (ADR-008)
    |--------------------------------------------------------------------------
    |
    | Attributes and inline style properties permitted on rendered output.
    | Anything outside this list is stripped on render.
    |
    */
    'sanitize' => [
        'enabled' => true,
        'allowed_classes' => null, // null = allow any class string; or an array allowlist
        'allowed_styles' => [
            'width', 'height', 'max-width', 'margin', 'margin-left', 'margin-right',
            'float', 'text-align', 'border-radius',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Toolbar
    |--------------------------------------------------------------------------
    |
    | Enabled toolbar buttons, in order. Mirrors the WordPress classic toolbar.
    |
    */
    'toolbar' => [
        'bold', 'italic', 'underline', 'strike',
        '|', 'h2', 'h3', 'paragraph',
        '|', 'bulletList', 'orderedList', 'blockquote',
        '|', 'link', 'image',
        '|', 'undo', 'redo',
    ],
];
