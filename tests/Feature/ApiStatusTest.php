<?php

test('the api status endpoint returns json', function () {
    $response = $this->getJson('/api/status');

    $response
        ->assertSuccessful()
        ->assertJson([
            'status' => 'ok',
            'name' => config('app.name'),
        ]);
});
