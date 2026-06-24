<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    foreach (glob(database_path('migrations/*_add_cms_editor_columns_to_*_table.php')) as $file) {
        @unlink($file);
    }
});

it('scaffolds a migration with both columns and exits cleanly', function () {
    File::ensureDirectoryExists(database_path('migrations'));

    $this->artisan('cms-editor:install')
        ->expectsConfirmation('Publish the pre-built JS/CSS assets to public/vendor/cms-editor?', 'no')
        ->expectsQuestion('Which Eloquent model holds the editable content?', 'App\\Models\\Article')
        ->expectsQuestion('Column for the ProseMirror JSON document?', 'body')
        ->expectsConfirmation('Also store a cached rendered-HTML column? (faster front-end reads)', 'yes')
        ->expectsQuestion('Column for the cached HTML?', 'body_html')
        ->expectsQuestion('Which database table gets these columns?', 'articles')
        ->assertExitCode(0);

    $created = glob(database_path('migrations/*_add_cms_editor_columns_to_articles_table.php'));
    expect($created)->not->toBeEmpty();

    $contents = file_get_contents($created[0]);
    expect($contents)
        ->toContain("Schema::table('articles'")
        ->toContain("\$table->json('body')->nullable();")
        ->toContain("\$table->longText('body_html')->nullable();")
        ->toContain("hasColumn('articles', 'body')");
});

it('omits the html column when the user declines it', function () {
    File::ensureDirectoryExists(database_path('migrations'));

    $this->artisan('cms-editor:install')
        ->expectsConfirmation('Publish the pre-built JS/CSS assets to public/vendor/cms-editor?', 'no')
        ->expectsQuestion('Which Eloquent model holds the editable content?', 'App\\Models\\Page')
        ->expectsQuestion('Column for the ProseMirror JSON document?', 'content')
        ->expectsConfirmation('Also store a cached rendered-HTML column? (faster front-end reads)', 'no')
        ->expectsQuestion('Which database table gets these columns?', 'pages')
        ->assertExitCode(0);

    $created = glob(database_path('migrations/*_add_cms_editor_columns_to_pages_table.php'));
    $contents = file_get_contents($created[0]);

    expect($contents)
        ->toContain("\$table->json('content')->nullable();")
        ->not->toContain('longText');
});
