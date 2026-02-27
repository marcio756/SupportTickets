<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles the CRUD operations for Tags.
 * Exclusively restricted to users with supporter privileges.
 */
class TagController extends Controller
{
    /**
     * Display a listing of all available tags.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        if (!$request->user()->isSupporter()) {
            abort(403, 'Acesso restrito apenas a Supporters.');
        }

        $tags = Tag::orderBy('name')->get();

        return Inertia::render('Tags/Index', [
            'tags' => $tags,
        ]);
    }

    /**
     * Store a newly created tag in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        if (!$request->user()->isSupporter()) {
            abort(403, 'Acesso restrito apenas a Supporters.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        Tag::create($validated);

        return redirect()->back()->with('success', 'Tag criada com sucesso.');
    }

    /**
     * Update the specified tag in storage.
     *
     * @param Request $request
     * @param Tag $tag
     * @return RedirectResponse
     */
    public function update(Request $request, Tag $tag): RedirectResponse
    {
        if (!$request->user()->isSupporter()) {
            abort(403, 'Acesso restrito apenas a Supporters.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name,' . $tag->id],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        $tag->update($validated);

        return redirect()->back()->with('success', 'Tag atualizada com sucesso.');
    }

    /**
     * Remove the specified tag from storage.
     *
     * @param Request $request
     * @param Tag $tag
     * @return RedirectResponse
     */
    public function destroy(Request $request, Tag $tag): RedirectResponse
    {
        if (!$request->user()->isSupporter()) {
            abort(403, 'Acesso restrito apenas a Supporters.');
        }

        $tag->delete();

        return redirect()->back()->with('success', 'Tag eliminada com sucesso.');
    }
}