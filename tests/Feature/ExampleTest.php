<?php

it('sends the root to the public site', function () {
    $this->get('/')->assertRedirect(route('front.home'));
});

it('sends the root to an http public home url', function () {
    config(['app.public_home_url' => 'https://example.com']);

    $this->get('/')->assertRedirect('https://example.com');
});

it('ignores a public home url that is not http', function () {
    config(['app.public_home_url' => 'javascript:alert(1)']);

    $this->get('/')->assertRedirect(route('front.home'));
});
