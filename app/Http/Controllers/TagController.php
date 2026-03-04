<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Traits\ChecksWorkSession;
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
    use ChecksWorkSession;

    /**
     * Display a listing of all available tags.
     * Includes search functionality and pagination.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->isSupporter()) {
            abort(403, 'Restricted access to Supporters only.');
        }

        $workSessionStatus = $this->getWorkSessionStatus($user);

        // Se o supporter não tiver a sessão ativa, não carregamos a listagem de tags
        if ($workSessionStatus !== \App\Enums\WorkSessionStatusEnum::ACTIVE->value) {
            return Inertia::render('Tags/Index', [
                'tags' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                ],
                'filters' => $request->only('search'),
                'workSessionStatus' => $workSessionStatus,
            ]);
        }

        $query = Tag::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $tags = $query->orderBy('name')->paginate(15)->withQueryString();

        return Inertia::render('Tags/Index', [
            'tags' => $tags,
            'filters' => $request->only('search'),
            'workSessionStatus' => $workSessionStatus,
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
            abort(403, 'Restricted access to Supporters only.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        Tag::create($validated);

        return redirect()->back()->with('success', 'Tag created successfully.');
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
            abort(403, 'Restricted access to Supporters only.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name,' . $tag->id],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        $tag->update($validated);

        return redirect()->back()->with('success', 'Tag updated successfully.');
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
            abort(403, 'Restricted access to Supporters only.');
        }

        $tag->delete();

        return redirect()->back()->with('success', 'Tag deleted successfully.');
    }
}