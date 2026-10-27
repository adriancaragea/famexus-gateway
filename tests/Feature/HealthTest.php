<?php

it('reports healthy on the framework health route', function () {
    $this->get('/up')->assertOk();
});

it('answers the v1 ping endpoint', function () {
    $this->getJson('/api/v1/ping')
        ->assertOk()
        ->assertJson(['service' => 'gateway', 'status' => 'ok']);
});
