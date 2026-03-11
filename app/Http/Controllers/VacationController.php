<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VacationController extends Controller
{
    /**
     * Renders the Vacation visual map and calendar page.
     * * @return Response
     */
    public function index(): Response
    {
        return Inertia::render('Vacations/Index');
    }
}