<?php

namespace Degrinthorst\CmsEditor\Contracts;

use Spatie\MediaLibrary\HasMedia;

/**
 * Marks a model as an editable "article" whose body images are managed by the
 * CMS editor. Models implementing this should also use the
 * InteractsWithEditorMedia trait.
 */
interface HasEditorMedia extends HasMedia
{
    /**
     * The MediaLibrary collection name that holds editor-inserted images.
     */
    public function editorMediaCollection(): string;
}
