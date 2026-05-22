<?php

it('serves the apple app site association for password autofill', function () {
    config(['services.apple.app_id' => 'TEAMID123.com.example.app']);

    $this->getJson('/.well-known/apple-app-site-association')
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('webcredentials.apps.0', 'TEAMID123.com.example.app');
});
