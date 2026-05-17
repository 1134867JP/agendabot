<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Home');
    }

    public function precos(): Response
    {
        return Inertia::render('Precos', [
            'planos' => config('plans'),
        ]);
    }
}
