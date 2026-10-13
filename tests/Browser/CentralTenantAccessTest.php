<?php

declare(strict_types=1);

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

uses(DuskTestCase::class);

test('central super administrator can enter and leave tenant support context', function (): void {
    $this->browse(function (Browser $browser): void {
        $this->centralAdministratorEntersDuskTenant($browser);

        $browser->assertSee('Dusk Company')
            ->assertPresent('@central-support-exit')
            ->click('@central-support-exit')
            ->waitForLocation('/central', 10)
            ->assertSee('Central administration');
    });
});
