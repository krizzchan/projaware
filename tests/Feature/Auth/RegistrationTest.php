<?php

test('public registration screen is unavailable', function () {
    $this->get('/register')->assertNotFound();
});

test('public registration cannot create an operational account', function () {
    $this->post('/register', ['name' => 'Test User', 'email' => 'test@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'admin'])->assertNotFound();
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});
