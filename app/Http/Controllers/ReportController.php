<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\Expense;
use App\Models\Meal;
use App\Models\MonthSession;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        $sessions = MonthSession::orderBy('start_date', 'desc')->get();
        return Inertia::render('Reports/Index', ['sessions' => $sessions]);
    }

    public function show(MonthSession $monthSession): Response
    {
        $users    = User::where('is_approved', true)->orderBy('name')->get();
        $sid      = $monthSession->id;

        $meals    = Meal::where('month_session_id', $sid)->where('status', 'approved')->get();
        $expenses = Expense::where('month_session_id', $sid)->where('status', 'approved')->get();
        $deposits = Deposit::where('month_session_id', $sid)->where('status', 'approved')->get();

        $fixedCharges = \App\Models\FixedCharge::where('month_session_id', $monthSession->id)->get();
        $payables     = \App\Models\Payable::where('month_session_id', $monthSession->id)->get();

        // ── Aggregate food expenses (for meal rate) ──────────────────────────
        // Only consider raw and dry food for personal expenses
        $foodCost   = $expenses->whereIn('category', ['raw_food', 'dry_food'])->sum('amount');
        
        $totalMeals = $meals->sum('lunch') + $meals->sum('dinner') + $meals->sum('guest_lunch') + $meals->sum('guest_dinner');
        $mealRate   = $totalMeals > 0 ? round($foodCost / $totalMeals, 4) : 0;

        // ── Aggregate Utility Expenses ─────────────────────────────────────────
        $totalUtilityExpense = $expenses->where('category', 'utility')->sum('amount');
        $activeUserCount     = $users->count();
        $utilityShareGlobal  = $activeUserCount > 0 ? round($totalUtilityExpense / $activeUserCount, 2) : 0;

        // ── Per-member summary ──────────────────────────────────────────────
        $memberSummary = $users->map(function ($user) use ($meals, $expenses, $deposits, $mealRate, $fixedCharges, $utilityShareGlobal, $payables) {
            $userMeals     = $meals->where('user_id', $user->id);
            $totalLunch    = $userMeals->sum('lunch') + $userMeals->sum('guest_lunch');
            $totalDinner   = $userMeals->sum('dinner') + $userMeals->sum('guest_dinner');
            $totalMeals    = $totalLunch + $totalDinner;
            $mealCost      = round($totalMeals * $mealRate, 2);

            // ONLY raw and dry food count towards personal bazar
            $ownExpenses   = $expenses->where('user_id', $user->id)->whereIn('category', ['raw_food', 'dry_food'])->sum('amount');
            $ownRaw        = $expenses->where('user_id', $user->id)->where('category', 'raw_food')->sum('amount');
            $ownDry        = $expenses->where('user_id', $user->id)->where('category', 'dry_food')->sum('amount');
            $ownUtility    = $expenses->where('user_id', $user->id)->where('category', 'utility')->sum('amount');
            $totalDeposits = $deposits->where('user_id', $user->id)->sum('amount');

            // Get their specific fixed charges
            $charge = $fixedCharges->firstWhere('user_id', $user->id);
            $rentShare    = $charge ? (float)$charge->rent : 0;
            $waterShare   = $charge ? (float)$charge->water : 0;
            $servantShare = $charge ? (float)$charge->servant : 0;
            $gasShare     = $charge ? (float)$charge->gas_electricity_internet : 0;
            $fixedShare   = $rentShare + $waterShare + $servantShare + $gasShare;
            $utilityShare = $utilityShareGlobal;

            // Balance = own_expenses + ownUtility - meal_cost - fixed_share - utility_share
            $balance       = round($ownExpenses + $ownUtility - $mealCost - $fixedShare - $utilityShare, 2);

            return [
                'id'            => $user->id,
                'name'          => $user->name,
                'totalLunch'    => $totalLunch,
                'totalDinner'   => $totalDinner,
                'totalMeals'    => $totalMeals,
                'mealCost'      => $mealCost,
                'ownExpenses'   => round($ownExpenses, 2),
                'ownUtility'    => round($ownUtility, 2),
                'ownRaw'        => round($ownRaw, 2),
                'ownDry'        => round($ownDry, 2),
                'totalDeposits' => round($totalDeposits, 2),
                'fixedShare'    => round($fixedShare, 2),
                'rentShare'     => $rentShare,
                'waterShare'    => $waterShare,
                'servantShare'  => $servantShare,
                'gasShare'      => $gasShare,
                'utilityShare'  => round($utilityShare, 2),
                'balance'       => $balance,   // + = mess owes them, - = they owe mess
                'payable'       => $payables->firstWhere('user_id', $user->id) ? [
                    'id'          => $payables->firstWhere('user_id', $user->id)->id,
                    'amount'      => (float)$payables->firstWhere('user_id', $user->id)->amount,
                    'paid_amount' => (float)$payables->firstWhere('user_id', $user->id)->paid_amount,
                    'due_amount'  => (float)$payables->firstWhere('user_id', $user->id)->due_amount,
                    'status'      => $payables->firstWhere('user_id', $user->id)->status,
                ] : null,
                'items'         => $expenses->where('user_id', $user->id)->whereIn('category', ['raw_food', 'dry_food'])->values()->toArray(),
                'utilityItems'  => $expenses->where('user_id', $user->id)->where('category', 'utility')->values()->toArray(),
            ];
        });

        $fixedCost = $fixedCharges->sum('rent') + $fixedCharges->sum('water') + $fixedCharges->sum('servant') + $fixedCharges->sum('gas_electricity_internet');

        $nextMonthName = $monthSession->start_date
            ? \Carbon\Carbon::parse($monthSession->start_date)->addMonth()->format('F Y')
            : 'Next Month';

        return Inertia::render('Reports/Show', [
            'session'       => $monthSession,
            'nextMonthName' => $nextMonthName,
            'memberSummary' => $memberSummary,
            'totals' => [
                'foodCost'   => round($foodCost, 2),
                'fixedCost'  => round($fixedCost, 2),
                'grandCost'  => round($foodCost + $fixedCost, 2),
                'totalMeals' => $totalMeals,
                'mealRate'   => $mealRate,
                'totalDeposits' => round($deposits->sum('amount'), 2),
            ],
            'activeSession' => MonthSession::where('is_active', true)->first(),
        ]);
    }

    public function carryForward(Request $request, MonthSession $monthSession, User $user): RedirectResponse
    {
        $request->validate(['balance' => 'required|numeric']);

        $activeSession = MonthSession::where('is_active', true)->first();

        if (!$activeSession) {
            return back()->with('error', 'There is no active month to carry forward to.');
        }

        if ($activeSession->id === $monthSession->id) {
            return back()->with('error', 'You cannot carry forward to the same month.');
        }

        // Positive balance (পাবেন) = we credit them in the new month (+ Deposit)
        // Negative balance (দিবেন) = they owe us, so we debit them in the new month (- Deposit)
        Deposit::create([
            'month_session_id' => $activeSession->id,
            'user_id'          => $user->id,
            'date'             => now()->format('Y-m-d'),
            'amount'           => $request->balance,
            'description'      => 'Carry Forward from ' . $monthSession->name,
            'status'           => 'approved',
        ]);

        return back()->with('success', "Balance carried forward to {$activeSession->name}.");
    }
}
