<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Models\MonthSession;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class MealChartController extends Controller
{
    public function index(): Response
    {
        $activeSession = MonthSession::where('is_active', true)->first();
        $users         = User::where('is_approved', true)->orderBy('name')->get();
        $meals         = [];
        $pendingMeals  = [];

        if ($activeSession) {
            $meals = Meal::where('month_session_id', $activeSession->id)
                ->where('status', 'approved')
                ->get();

            $pendingMeals = Meal::with('user')
                ->where('month_session_id', $activeSession->id)
                ->where('status', 'pending')
                ->get();
        }

        $role = auth()->user()->role;

        return Inertia::render('Meals/Index', [
            'activeSession' => $activeSession,
            'users'         => $users,
            'meals'         => $meals,
            'pendingMeals'  => $pendingMeals,
            'today'         => Carbon::now()->format('Y-m-d'),
            'canManage'     => in_array($role, ['admin', 'manager']),
            'userRole'      => $role,
        ]);
    }
}
