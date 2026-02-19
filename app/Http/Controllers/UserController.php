<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): Response
    {
        // Security check: Only supporters can access User Management
        if (! $request->user()->isSupporter()) {
            abort(403, 'Unauthorized access.');
        }

        $users = User::latest()->paginate(10);

        return Inertia::render('Users/Index', [
            'users' => $users,
        ]);
    }
}