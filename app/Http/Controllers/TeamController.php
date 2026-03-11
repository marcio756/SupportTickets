<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * Renders the Teams management page.
     * * @return Response
     */
    public function index(): Response
    {
        return Inertia::render('Teams/Index');
    }
}