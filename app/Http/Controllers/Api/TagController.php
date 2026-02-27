<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Handles the CRUD operations for Tags via API.
 * Exclusively restricted to users with supporter privileges.
 */
class TagController extends Controller
{
    use ApiResponser;

    /**
     * Display a listing of all available tags.
     * Includes search functionality and pagination.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isSupporter()) {
            return $this->errorResponse('Acesso restrito apenas a Supporters.', 403);
        }

        $query = Tag::query();

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $tags = $query->orderBy('name')->paginate(15);

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
        if (!$request->user()->isSupporter()) {
            return $this->errorResponse('Acesso restrito apenas a Supporters.', 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        $tag = Tag::create($validated);

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
        if (!$request->user()->isSupporter()) {
            return $this->errorResponse('Acesso restrito apenas a Supporters.', 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:tags,name,' . $tag->id],
            'color' => ['nullable', 'string', 'max:50'],
        ]);

        $tag->update($validated);

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
        if (!$request->user()->isSupporter()) {
            return $this->errorResponse('Acesso restrito apenas a Supporters.', 403);
        }

        $tag->delete();

        return $this->successResponse(null, 'Tag eliminada com sucesso.');
    }
}