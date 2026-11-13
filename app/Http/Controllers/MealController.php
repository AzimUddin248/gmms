<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Models\MonthSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class MealController extends Controller
{
    /**
     * Save or update a meal entry.
     * Borders submit → status = pending.
     * Managers/Admins submit → status = approved directly.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'user_id'          => 'required|exists:users,id',
            'month_session_id' => 'required|exists:month_sessions,id',
            'date'             => 'required|date',
            'lunch'            => 'required|numeric',
            'dinner'           => 'required|numeric',
            'guest_lunch'      => 'nullable|numeric|min:0',
            'guest_dinner'     => 'nullable|numeric|min:0',
        ]);

        $role = auth()->user()->role;
        $status = in_array($role, ['admin', 'manager']) ? 'approved' : 'pending';

        // If border, they can only submit for themselves
        if ($role === 'border' && $request->user_id != auth()->id()) {
            abort(403, 'You can only submit meals for yourself.');
        }

        Meal::updateOrCreate(
            [
                'user_id'          => $request->user_id,
                'month_session_id' => $request->month_session_id,
                'date'             => $request->date,
            ],
            [
                'lunch'        => $request->lunch,
                'dinner'       => $request->dinner,
                'guest_lunch'  => $request->guest_lunch ?? 0,
                'guest_dinner' => $request->guest_dinner ?? 0,
                'status'       => $status,
            ]
        );

        return back()->with('success', 'Meal saved successfully.');
    }

    /**
     * Approve a pending meal entry (Manager/Admin only).
     */
    public function approve(Meal $meal): RedirectResponse
    {
        $meal->update(['status' => 'approved']);
        return redirect()->route('meals.index')->with('success', 'Meal approved.');
    }

    /**
     * Reject a pending meal entry (Manager/Admin only).
     */
    public function reject(Meal $meal): RedirectResponse
    {
        $meal->update(['status' => 'rejected']);
        return redirect()->route('meals.index')->with('success', 'Meal rejected.');
    }

    /**
     * Bulk upsert meals (used by the grid for quick admin/manager edits).
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $request->validate([
            'month_session_id' => 'required|exists:month_sessions,id',
            'meals'            => 'required|array',
            'meals.*.user_id'  => 'required|exists:users,id',
            'meals.*.date'     => 'required|date',
            'meals.*.lunch'    => 'required|in:0,0.5,1',
            'meals.*.dinner'   => 'required|in:0,0.5,1',
        ]);

        $role = auth()->user()->role;
        $status = in_array($role, ['admin', 'manager']) ? 'approved' : 'pending';

        foreach ($request->meals as $mealData) {
            Meal::updateOrCreate(
                [
                    'user_id'          => $mealData['user_id'],
                    'month_session_id' => $request->month_session_id,
                    'date'             => $mealData['date'],
                ],
                [
                    'lunch'  => $mealData['lunch'],
                    'dinner' => $mealData['dinner'],
                    'status' => $status,
                ]
            );
        }

        return back()->with('success', 'Meals saved successfully.');
    }
}
