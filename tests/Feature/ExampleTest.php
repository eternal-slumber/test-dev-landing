<?php

use Inertia\Testing\AssertableInertia as Assert;

test('renders the landing page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page->component('welcome'),
        );
});
