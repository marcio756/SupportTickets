<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Handles the CRUD operations for Tags via API.
 * Exclusively restricted to users with admin or supporter privileges.
 * Architect Note: Wrapped in Redis Tags caching to avoid millions of repetitive DB hits 
 * when the frontend generates Ticket creation forms.
 */
class TagController extends Controller
{
    use ApiResponser;

    private const CACHE_TAG = 'tags_metadata';

    /**
     * Helper method to centralize the authorization logic (DRY principle).
     * Checks if the user is a Supporter or an Admin.
     *
     * @param Request $request
     * @return bool
     */
    private function _canManageTags(Request $request): bool
    {
        $user = $request->user();
        $isAdmin = method_exists($user, 'isAdmin') ? $user->isAdmin() : $user->role === 'admin';
        return $user->isSupporter() || $isAdmin;
    }

    /**
     * Display a listing of all available tags.
     * Includes search functionality and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->_canManageTags($request)) {
            return $this->errorResponse('Acesso restrito apenas a Admins e Supporters.', 403);
        }

        $search = $request->input('search', '');
        $page = $request->input('page', 1);
        $cacheKey = "tags_index_page_{$page}_search_" . md5($search);

        // Redis cache tagged layer to serve metadata instantly without DB hits
        $tags = Cache::tags([self::CACHE_TAG])->remember($cacheKey, 86400, function () use ($search) {
            $query = Tag::query();

            if (!empty($search)) {
                $query->where('name', 'like', "%{$search}%");
            }

            return $query->orderBy('name')->simplePaginate(15);
        });

        return $this->successResponse($tags, 'Tags listadas com sucesso.');
    }

    /**
     * Store a newly created tag in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->_canManageTags($request)) {
            return $this->errorResponse('Acesso restrito apenas a Admins e Supporters.', 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        $tag = Tag::create($validated);
        $this->flushCache();

        return $this->successResponse($tag, 'Tag criada com sucesso.', 201);
    }

    /**
     * Update the specified tag in storage.
     *
     * @param Request $request
     * @param Tag $tag
     * @return JsonResponse
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        if (!$this->_canManageTags($request)) {
            return $this->errorResponse('Acesso restrito apenas a Admins e Supporters.', 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name,' . $tag->id],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        $tag->update($validated);
        $this->flushCache();

        return $this->successResponse($tag, 'Tag atualizada com sucesso.');
    }

    /**
     * Remove the specified tag from storage.
     *
     * @param Request $request
     * @param Tag $tag
     * @return JsonResponse
     */
    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        if (!$this->_canManageTags($request)) {
            return $this->errorResponse('Acesso restrito apenas a Admins e Supporters.', 403);
        }

        $tag->delete();
        $this->flushCache();

        return $this->successResponse(null, 'Tag eliminada com sucesso.');
    }

    /**
     * Clears the tagged cache upon metadata modification.
     */
    private function flushCache(): void
    {
        Cache::tags([self::CACHE_TAG])->flush();
    }
}