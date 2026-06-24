<?php

namespace Degrinthorst\CmsEditor\Concerns;

use Degrinthorst\CmsEditor\Support\ContentRenderer;

/**
 * Optional: keeps a cached rendered-HTML column in sync with the ProseMirror
 * JSON source of truth (ADR-003). Add this trait to your model only when you
 * configure `cms-editor.columns.html`.
 *
 * On save, when the JSON column is dirty, the HTML column is re-rendered (which
 * also re-resolves image src from MediaLibrary). Note the documented caveat:
 * if a Media record is renamed/replaced, the cached HTML only refreshes on the
 * next save of the model.
 */
trait SyncsEditorHtml
{
    public static function bootSyncsEditorHtml(): void
    {
        static::saving(function ($model): void {
            $htmlColumn = config('cms-editor.columns.html');

            if (! $htmlColumn) {
                return;
            }

            $jsonColumn = config('cms-editor.columns.json', 'body');

            // Only re-render when the document actually changed (cheap save).
            if (! $model->isDirty($jsonColumn)) {
                return;
            }

            $model->{$htmlColumn} = app(ContentRenderer::class)
                ->toHtml($model->{$jsonColumn} ?? []);
        });
    }
}
