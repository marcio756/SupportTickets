<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\User;
use App\Traits\ChecksWorkSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Enums\WorkSessionStatusEnum;

/**
 * Handles the CRUD operations for Tags.
 * Accessible by Supporters (within active sessions) and Administrators.
 */
class TagController extends Controller
{
    use ChecksWorkSession;

    /**
     * Display a listing of all available tags.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->isSupporter() && !$user->isAdmin()) {
            abort(403, 'Restricted access to Staff only.');
        }

        $workSessionStatus = $this->getWorkSessionStatus($user);

        if (!$user->isAdmin() && $workSessionStatus !== WorkSessionStatusEnum::ACTIVE->value) {
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
            'workSessionStatus' => $user->isAdmin() ? WorkSessionStatusEnum::ACTIVE->value : $workSessionStatus,
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
        $this->authorizeAction($request->user());

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
        $this->authorizeAction($request->user());

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
        $this->authorizeAction($request->user());

        $tag->delete();

        return redirect()->back()->with('success', 'Tag deleted successfully.');
    }

    /**
     * Ensure the user has permission to modify tags.
     * Admins are always allowed. Supporters require an active work session.
     * * @param User $user
     * @return void
     */
    private function authorizeAction(User $user): void
    {
        if (!$user->isSupporter() && !$user->isAdmin()) {
            abort(403, 'Restricted access to Staff only.');
        }

        if (!$user->isAdmin() && $this->getWorkSessionStatus($user) !== WorkSessionStatusEnum::ACTIVE->value) {
            abort(403, 'Action requires an active work session.');
        }
    }
}