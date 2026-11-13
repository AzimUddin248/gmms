<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\Expense;
use App\Models\FixedCharge;
use App\Models\Meal;
use App\Models\MonthSession;
use App\Models\Payable;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class PayableController extends Controller
{
    /**
     * Compute member balances for a given session.
     */
    public static function calculateMemberBalances(MonthSession $monthSession): array
    {
        $users    = User::where('is_approved', true)->orderBy('name')->get();
        $sid      = $monthSession->id;

        $meals    = Meal::where('month_session_id', $sid)->where('status', 'approved')->get();
        $expenses = Expense::where('month_session_id', $sid)->where('status', 'approved')->get();
        $deposits = Deposit::where('month_session_id', $sid)->where('status', 'approved')->get();
        $fixedCharges = FixedCharge::where('month_session_id', $sid)->get();

        $foodCost   = $expenses->whereIn('category', ['raw_food', 'dry_food'])->sum('amount');
        $totalMeals = $meals->sum('lunch') + $meals->sum('dinner') + $meals->sum('guest_lunch') + $meals->sum('guest_dinner');
        $mealRate   = $totalMeals > 0 ? round($foodCost / $totalMeals, 4) : 0;

        $totalUtilityExpense = $expenses->where('category', 'utility')->sum('amount');
        $activeUserCount     = $users->count();
        $utilityShareGlobal  = $activeUserCount > 0 ? round($totalUtilityExpense / $activeUserCount, 2) : 0;

        $balances = [];
        foreach ($users as $user) {
            $userMeals     = $meals->where('user_id', $user->id);
            $totalLunch    = $userMeals->sum('lunch') + $userMeals->sum('guest_lunch');
            $totalDinner   = $userMeals->sum('dinner') + $userMeals->sum('guest_dinner');
            $uTotalMeals   = $totalLunch + $totalDinner;
            $mealCost      = round($uTotalMeals * $mealRate, 2);

            $ownExpenses   = $expenses->where('user_id', $user->id)->whereIn('category', ['raw_food', 'dry_food'])->sum('amount');
            $ownUtility    = $expenses->where('user_id', $user->id)->where('category', 'utility')->sum('amount');

            $charge        = $fixedCharges->firstWhere('user_id', $user->id);
            $rentShare     = $charge ? (float)$charge->rent : 0;
            $waterShare    = $charge ? (float)$charge->water : 0;
            $servantShare  = $charge ? (float)$charge->servant : 0;
            $gasShare      = $charge ? (float)$charge->gas_electricity_internet : 0;
            $fixedShare    = $rentShare + $waterShare + $servantShare + $gasShare;
            $utilityShare  = $utilityShareGlobal;

            // Balance = ownExpenses + ownUtility - mealCost - fixedShare - utilityShare
            $balance = round($ownExpenses + $ownUtility - $mealCost - $fixedShare - $utilityShare, 2);

            $balances[$user->id] = [
                'user'     => $user,
                'balance'  => $balance,
                'due'      => $balance < 0 ? abs($balance) : 0,
                'surplus'  => $balance > 0 ? $balance : 0,
            ];
        }

        return $balances;
    }

    /**
     * List payables & payments.
     */
    public function index(Request $request): Response
    {
        $role = auth()->user()->role;
        $isManager = in_array($role, ['admin', 'manager']);

        $sessions = MonthSession::orderBy('start_date', 'desc')->get();
        $activeSession = MonthSession::where('is_active', true)->first();
        $users = User::where('is_approved', true)->orderBy('name')->get();

        $defaultSessionId = $activeSession ? $activeSession->id : ($sessions->first()?->id ?? null);
        $sessionId = $request->input('session_id', $defaultSessionId);
        $userId = $isManager ? $request->input('user_id', 'all') : auth()->id();
        $status = $request->input('status', 'all');

        $query = Payable::with(['user', 'monthSession', 'payments.recorder']);

        if ($sessionId && $sessionId !== 'all') {
            $query->where('month_session_id', $sessionId);
        }

        if ($userId && $userId !== 'all') {
            $query->where('user_id', $userId);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $payables = $query->orderBy('created_at', 'desc')->get();

        // Calculate summary totals
        $totalPayable = (float)$payables->sum('amount');
        $totalPaid    = (float)$payables->sum('paid_amount');
        $totalDue     = (float)$payables->sum('due_amount');

        return Inertia::render('Payables/Index', [
            'payables'      => $payables,
            'sessions'      => $sessions,
            'users'         => $users,
            'filters'       => [
                'session_id' => $sessionId,
                'user_id'    => (string)$userId,
                'status'     => $status,
            ],
            'totals'        => [
                'payable' => $totalPayable,
                'paid'    => $totalPaid,
                'due'     => $totalDue,
            ],
            'canManage'     => $isManager,
            'activeSession' => $activeSession,
        ]);
    }

    /**
     * Generate payables for a session (single or all members).
     */
    public function generate(Request $request, MonthSession $monthSession): RedirectResponse
    {
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
            abort(403);
        }

        $balances = self::calculateMemberBalances($monthSession);
        $targetUserId = $request->input('user_id');

        $processed = 0;
        foreach ($balances as $userId => $info) {
            if ($targetUserId && $targetUserId !== 'all' && (int)$targetUserId !== (int)$userId) {
                continue;
            }

            // Only generate payable if member owes money ($balance < 0)
            if ($info['balance'] < 0) {
                $payableAmount = abs($info['balance']);

                $payable = Payable::where('month_session_id', $monthSession->id)
                    ->where('user_id', $userId)
                    ->first();

                if ($payable) {
                    $payable->update([
                        'amount' => $payableAmount,
                    ]);
                    $payable->recalculate();
                } else {
                    Payable::create([
                        'month_session_id' => $monthSession->id,
                        'user_id'          => $userId,
                        'amount'           => $payableAmount,
                        'paid_amount'      => 0,
                        'due_amount'       => $payableAmount,
                        'status'           => 'unpaid',
                        'notes'            => "Payable amount for the closing month of {$monthSession->name}",
                    ]);
                }
                $processed++;
            }
        }

        $msg = $processed > 0
            ? "Successfully generated/updated {$processed} payable record(s) for {$monthSession->name}."
            : "No negative balance found to generate payables for.";

        return back()->with('success', $msg);
    }

    /**
     * Record a payment against a payable (supports installments).
     */
    public function storePayment(Request $request, Payable $payable): RedirectResponse
    {
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
            abort(403);
        }

        $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'date'           => 'required|date',
            'payment_method' => 'required|string|max:50',
            'notes'          => 'nullable|string|max:255',
        ]);

        Payment::create([
            'payable_id'       => $payable->id,
            'month_session_id' => $payable->month_session_id,
            'user_id'          => $payable->user_id,
            'amount'           => $request->amount,
            'date'             => $request->date,
            'payment_method'   => $request->payment_method,
            'notes'            => $request->notes,
            'recorded_by'      => auth()->id(),
        ]);

        $payable->recalculate();

        return back()->with('success', "Payment of ৳{$request->amount} recorded for {$payable->user->name}.");
    }

    /**
     * Delete a payment record and update the payable.
     */
    public function destroyPayment(Payment $payment): RedirectResponse
    {
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
            abort(403);
        }

        $payable = $payment->payable;
        $payment->delete();
        $payable->recalculate();

        return back()->with('success', 'Payment deleted and payable balance updated.');
    }
}
