<?php

use App\Core\Front\FrontComponentsCss;

it('refuses forbidden tokens in any case, with whitespace, or split by a comment', function (string $css) {
    expect(app(FrontComponentsCss::class)->problem($css))->toStartWith('components.css may not contain');
})->with([
    'expression with a space' => ['.a { width: expression (alert(1)); }'],
    'expression with a newline and mixed case' => [".a { width: ExPrEsSiOn\n(alert(1)); }"],
    'javascript with a space before the colon' => ['.a { background: url("assets/x.svg"); content: "javascript :alert(1)"; }'],
    'behavior with a tab' => [".a { behavior\t: url(assets/x.htc); }"],
    'image-set with a space' => ['.a { background: image-set ("assets/x.png" 1x); }'],
    'webkit image-set' => ['.a { background: -webkit-image-set("assets/x.png" 1x); }'],
    'image() with a remote url' => ['.a { background: image("https://evil.example/x.png"); }'],
    'image() with a space' => ['.a { background: IMAGE ("https://evil.example/x.png"); }'],
    'moz-binding' => ['.a { -MOZ-binding: url(assets/x.xml); }'],
    'import split by a comment' => ['@imp/**/ort "https://evil.example/x.css";'],
    'javascript split by a comment' => ['.a { content: "java/* x */script:alert(1)"; }'],
    'import spliced around a removed token' => ['@imexpression(port "https://evil.example/x.css";'],
    'import spliced around a spaced token' => ['@imjavascript :port "https://evil.example/x.css";'],
    'charset in caps' => ['@CHARSET "utf-8";'],
    'namespace' => ['@namespace svg url(assets/x);'],
]);

it('accepts plain component CSS with asset urls and comments', function () {
    $css = "/* Cards */\n.front-card { background: url(\"assets/hero-art.svg\"); border-image: none; }\n.front-cta:hover { color: red; }\n";

    expect(app(FrontComponentsCss::class)->problem($css))->toBeNull();
});

it('strips spliced and spaced tokens when inlining until nothing forbidden is left', function (string $css) {
    $inlined = app(FrontComponentsCss::class)->inline($css, fn (string $reference): string => 'https://site.test/'.$reference);

    expect($inlined)->not->toMatch('/@\s*import|expression\s*\(|image(?:-set)?\s*\(|javascript\s*:|behavior\s*:|-moz-binding/i')
        ->not->toMatch('/url\(\s*["\']?https?:/i');
})->with([
    'import spliced around expression(' => ['@imexpression(port "https://evil.example/x.css";'],
    'import spliced around a spaced javascript:' => ['@imjavascript :port "https://evil.example/x.css";'],
    'import spliced around a spaced image-set(' => ['@imimage-set (port "https://evil.example/x.css";'],
    'import spliced around another import' => ['@imp@import "a";ort "https://evil.example/x.css";'],
    'import spliced around <' => ['@imp<ort "https://evil.example/x.css";'],
    'javascript spliced around itself' => ['.a { content: "javajava script:script :alert(1)"; }'],
    'image() with a remote url' => ['.a { background: image("https://evil.example/x.css"); }'],
]);
