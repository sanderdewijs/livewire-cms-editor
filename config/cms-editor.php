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
    | Images inserted through the editor live in this collection on the article
    | model. The media picker is restrained to this collection so the rest of
    | the media library never shows up.
    |
    */
    'collection' => 'article_body',

    /*
    |--------------------------------------------------------------------------
    | Upload binding strategy (ADR-005)
    |--------------------------------------------------------------------------
    |
    | How freshly-uploaded images are associated with the article model:
    |   - 'draft'     : host provides/creates a persisted (draft) record first
    |   - 'temporary' : upload unattached, link on article save
    |   - 'model'     : a persisted model is always supplied to the editor
    |
    */
    'upload_binding' => 'draft',

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
