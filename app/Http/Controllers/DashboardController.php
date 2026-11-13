<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\Expense;
use App\Models\Meal;
use App\Models\MonthSession;
use App\Models\User;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $activeSession     = MonthSession::where('is_active', true)->first();
        $users             = User::where('is_approved', true)->orderBy('name')->get();
        $pendingMealsCount = 0;
        $pendingExpCount   = 0;
        $pendingDepCount   = 0;
        $stats             = [];

        if ($activeSession) {
            $sid = $activeSession->id;

            $pendingMealsCount = Meal::where('month_session_id', $sid)->where('status', 'pending')->count();
            $pendingExpCount   = Expense::where('month_session_id', $sid)->where('status', 'pending')->count();
            $pendingDepCount   = Deposit::where('month_session_id', $sid)->where('status', 'pending')->count();

            // Approved totals for stats
            $approvedMeals    = Meal::where('month_session_id', $sid)->where('status', 'approved')->get();
            $approvedExpenses  = Expense::where('month_session_id', $sid)->where('status', 'approved')->get();
            $approvedDeposits  = Deposit::where('month_session_id', $sid)->where('status', 'approved')->get();

            $totalMeals     = $approvedMeals->sum('lunch') + $approvedMeals->sum('dinner') + $approvedMeals->sum('guest_lunch') + $approvedMeals->sum('guest_dinner');
            $foodCost       = $approvedExpenses->whereIn('category', ['raw_food', 'dry_food'])->sum('amount');
            $totalDeposits  = $approvedDeposits->sum('amount');
            $totalExpenses  = $approvedExpenses->sum('amount');
            $mealRate       = $totalMeals > 0 ? round($foodCost / $totalMeals, 2) : 0;

            $stats = [
                'totalMembers'  => $users->count(),
                'totalMeals'    => $totalMeals,
                'mealRate'      => $mealRate,
                'foodCost'      => $foodCost,
                'totalExpenses' => $totalExpenses,
                'totalDeposits' => $totalDeposits,
                'expensesBreakdown' => [
                    'rent'      => $approvedExpenses->where('category', 'rent')->sum('amount'),
                    'utility'   => $approvedExpenses->where('category', 'utility')->sum('amount'),
                    'raw_food'  => $approvedExpenses->where('category', 'raw_food')->sum('amount'),
                    'dry_food'  => $approvedExpenses->where('category', 'dry_food')->sum('amount'),
                ]
            ];

            $myBazarRoster = [];
            $myFixedCharges = null;
            $myExpenses = [];
            if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
                $myBazarRoster = \App\Models\BazarRoster::where('month_session_id', $sid)
                    ->where('user_id', auth()->id())
                    ->orderBy('date')
                    ->get();

                $fixedCharge = \App\Models\FixedCharge::where('month_session_id', $sid)
                    ->where('user_id', auth()->id())
                    ->first();

                if ($fixedCharge) {
                    $myFixedCharges = [
                        'rent'                     => (float)$fixedCharge->rent,
                        'water'                    => (float)$fixedCharge->water,
                        'servant'                  => (float)$fixedCharge->servant,
                        'gas_electricity_internet' => (float)$fixedCharge->gas_electricity_internet,
                        'total'                    => (float)($fixedCharge->rent + $fixedCharge->water + $fixedCharge->servant + $fixedCharge->gas_electricity_internet),
                    ];
                }

                $myExpenses = Expense::where('month_session_id', $sid)
                    ->where('user_id', auth()->id())
                    ->where('status', 'approved')
                    ->orderBy('date', 'desc')
                    ->get();
            }

            $upcomingBazar = \App\Models\BazarRoster::where('month_session_id', $sid)
                ->where('user_id', auth()->id())
                ->whereIn('date', [Carbon::today()->format('Y-m-d'), Carbon::tomorrow()->format('Y-m-d')])
                ->orderBy('date')
                ->get();

            $memberStats = [];
            foreach ($users as $u) {
                if (!in_array(auth()->user()->role, ['admin', 'manager']) && $u->id !== auth()->id()) {
                    continue;
                }
                $uId = $u->id;
                
                $mUser = $approvedMeals->where('user_id', $uId);
                $uMeals = $mUser->sum('lunch') + $mUser->sum('dinner') + $mUser->sum('guest_lunch') + $mUser->sum('guest_dinner');
                
                $eUser = $approvedExpenses->where('user_id', $uId);
                $uRaw = (float)$eUser->where('category', 'raw_food')->sum('amount');
                $uDry = (float)$eUser->where('category', 'dry_food')->sum('amount');
                $uUtil = (float)$eUser->where('category', 'utility')->sum('amount');

                $dUser = $approvedDeposits->where('user_id', $uId);
                $uDeposit = (float)$dUser->sum('amount');
                $uDepositBalance = $uDeposit - $uDry;
                
                $uMealCost = $uMeals * $mealRate;
                
                $memberStats[] = [
                    'id'             => $uId,
                    'name'           => $u->name,
                    'meals'          => $uMeals,
                    'mealCost'       => $uMealCost,
                    'rawFood'        => $uRaw,
                    'dryFood'        => $uDry,
                    'utility'        => $uUtil,
                    'deposit'        => $uDeposit,
                    'depositBalance' => $uDepositBalance,
                ];
            }
        }

        $pendingUsers = User::where('is_approved', false)->count();
        $role         = auth()->user()->role;

        return Inertia::render('Dashboard', [
            'activeSession'     => $activeSession,
            'stats'             => $stats,
            'memberStats'       => $memberStats ?? [],
            'upcomingBazar'     => $upcomingBazar ?? [],
            'myBazarRoster'     => $myBazarRoster ?? [],
            'myFixedCharges'    => $myFixedCharges ?? null,
            'myExpenses'        => $myExpenses ?? [],
            'today'             => Carbon::now()->format('Y-m-d'),
            'pendingMealsCount' => $pendingMealsCount,
            'pendingExpCount'   => $pendingExpCount,
            'pendingDepCount'   => $pendingDepCount,
            'pendingUsers'      => $pendingUsers,
            'canManage'         => in_array($role, ['admin', 'manager']),
            'isAdmin'           => $role === 'admin',
        ]);
    }
}
