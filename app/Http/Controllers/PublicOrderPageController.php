<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

final class PublicOrderPageController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Orders/Track');
    }
}
