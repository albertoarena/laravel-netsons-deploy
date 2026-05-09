<?php

declare(strict_types=1);

describe('netsons:check', function () {
    it('is registered as an artisan command', function () {
        $this->artisan('netsons:check')
            ->assertSuccessful();
    });

    it('displays the current strategy', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('Strategy')
            ->assertSuccessful();
    });

    it('lists required secrets', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('SSH_PRIVATE_KEY')
            ->assertSuccessful();
    });

    it('shows PHP version info', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('PHP')
            ->assertSuccessful();
    });

    it('checks deploy path configuration', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('public_html')
            ->assertSuccessful();
    });

    it('validates SSH port default', function () {
        $this->artisan('netsons:check')
            ->expectsOutputToContain('65100')
            ->assertSuccessful();
    });
});
