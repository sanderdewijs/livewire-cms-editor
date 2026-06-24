<?php

use Degrinthorst\CmsEditor\Tests\Fixtures\Article;

function body(string $text): array
{
    return ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]],
    ]];
}

it('renders json into the html column on save when a html column is configured', function () {
    config()->set('cms-editor.columns.html', 'body_html');

    $article = Article::create(['title' => 'X', 'body' => body('Hello cache')]);

    expect($article->body_html)->toContain('<p>Hello cache</p>');
});

it('refreshes the html column when the json changes', function () {
    config()->set('cms-editor.columns.html', 'body_html');

    $article = Article::create(['title' => 'X', 'body' => body('First')]);
    $article->update(['body' => body('Second')]);

    expect($article->fresh()->body_html)
        ->toContain('Second')
        ->not->toContain('First');
});

it('leaves the html column null when no html column is configured', function () {
    config()->set('cms-editor.columns.html', null);

    $article = Article::create(['title' => 'X', 'body' => body('No cache')]);

    expect($article->body_html)->toBeNull();
});

it('does not re-render when the json column is unchanged', function () {
    config()->set('cms-editor.columns.html', 'body_html');

    $article = Article::create(['title' => 'X', 'body' => body('Stable')]);
    $article->body_html = 'MANUALLY SET';
    $article->update(['title' => 'Y']); // body not dirty

    expect($article->fresh()->body_html)->toBe('MANUALLY SET');
});
