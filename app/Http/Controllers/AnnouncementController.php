<?php

namespace App\Http\Controllers;

use App\Enums\RoleEnum;
use App\Jobs\SendAnnouncementEmailJob;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Handles the creation and dispatching of system announcements to customers.
 */
class AnnouncementController extends Controller
{
    /**
     * Displays the announcement creation form with the customer selection list.
     * Architect Note: The massive get() query that loaded millions of users into memory
     * has been completely removed to prevent fatal crashes. 
     *
     * @param Request $request
     * @return \Inertia\Response
     */
    public function create(Request $request)
    {
        $this->authorizeAccess($request);

        // Architect Note: Sent as an empty array. The frontend CustomerSelector
        // relies on async API calls to fetch data, providing a lightweight, scalable render.
        $customers = [];

        return Inertia::render('Announcements/Create', [
            'customers' => $customers
        ]);
    }

    /**
     * Validates input and dispatches background jobs to send the announcements.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $this->authorizeAccess($request);

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'exists:users,id',
        ]);

        $customers = User::whereIn('id', $validated['customer_ids'])->get();

        foreach ($customers as $customer) {
            SendAnnouncementEmailJob::dispatch(
                $customer->email,
                $validated['subject'],
                $validated['content']
            );
        }

        return redirect()->back()->with('success', __('announcements.sent_successfully'));
    }

    /**
     * Ensures only Admins and Supporters can access announcement features.
     *
     * @param Request $request
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function authorizeAccess(Request $request): void
    {
        $role = $request->user()->getAttribute('role');
        $roleValue = $role instanceof RoleEnum ? $role->value : $role;

        if (!in_array($roleValue, [RoleEnum::ADMIN->value, RoleEnum::SUPPORTER->value])) {
            abort(403, 'Acesso não autorizado a esta funcionalidade.');
        }
    }
}