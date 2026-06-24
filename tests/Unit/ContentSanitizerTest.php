<?php

use Degrinthorst\CmsEditor\Support\ContentSanitizer;

beforeEach(function () {
    $this->sanitizer = new ContentSanitizer();
});

it('strips script blocks', function () {
    $dirty = '<p>Hello</p><script>alert(1)</script>';

    expect($this->sanitizer->clean($dirty))
        ->toBe('<p>Hello</p>');
});

it('strips inline event handlers', function () {
    $dirty = '<img src="x.jpg" onerror="alert(1)" alt="x">';

    expect($this->sanitizer->clean($dirty))
        ->not->toContain('onerror');
});

it('neutralises javascript urls', function () {
    $dirty = '<a href="javascript:alert(1)">x</a>';

    expect($this->sanitizer->clean($dirty))
        ->toContain('href="#"')
        ->not->toContain('javascript:');
});

it('keeps allowlisted inline styles and drops the rest', function () {
    config()->set('cms-editor.sanitize.allowed_styles', ['width', 'float']);

    $dirty = '<img style="width: 300px; position: absolute; float: left" alt="x">';

    $clean = $this->sanitizer->clean($dirty);

    expect($clean)
        ->toContain('width: 300px')
        ->toContain('float: left')
        ->not->toContain('position');
});

it('drops css expression and url(javascript:) tricks from styles', function () {
    config()->set('cms-editor.sanitize.allowed_styles', ['width', 'background']);

    $dirty = '<div style="width: expression(alert(1)); background: url(javascript:alert(1))">x</div>';

    $clean = $this->sanitizer->clean($dirty);

    expect($clean)
        ->not->toContain('expression')
        ->not->toContain('javascript:');
});

it('returns html untouched when sanitizing is disabled', function () {
    config()->set('cms-editor.sanitize.enabled', false);

    $dirty = '<script>alert(1)</script>';

    expect($this->sanitizer->clean($dirty))->toBe($dirty);
});
