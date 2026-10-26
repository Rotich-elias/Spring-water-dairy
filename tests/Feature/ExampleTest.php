<?php

it('redirects the root URL to the frontend', function () {
    $response = $this->get('/');

    $response->assertRedirect(config('app.frontend_url'));
});
