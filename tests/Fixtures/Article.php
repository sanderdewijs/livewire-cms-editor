<?php

namespace Degrinthorst\CmsEditor\Tests\Fixtures;

use Degrinthorst\CmsEditor\Concerns\AdoptsEditorMedia;
use Degrinthorst\CmsEditor\Concerns\InteractsWithEditorMedia;
use Degrinthorst\CmsEditor\Concerns\SyncsEditorHtml;
use Degrinthorst\CmsEditor\Contracts\HasEditorMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;

class Article extends Model implements HasEditorMedia
{
    use AdoptsEditorMedia;
    use InteractsWithEditorMedia;
    use InteractsWithMedia;
    use SyncsEditorHtml;

    protected $guarded = [];

    protected $casts = [
        'body' => 'array',
    ];

    public function registerMediaCollections(): void
    {
        $this->registerEditorMediaCollection();
    }
}
