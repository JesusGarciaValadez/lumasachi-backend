<?php

declare(strict_types=1);

use Tests\TestCase;

pest()
    ->tia()
    ->always()
    ->locally();

pest()
    ->extend(TestCase::class)
    ->in('Unit', 'Feature');
