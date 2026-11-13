<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\MonthSession;
use App\Models\User;
use App\Models\Deposit;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function index(): Response
    {
        $activeSession = MonthSession::where('is_active', true)->first();
        $users         = User::where('is_approved', true)->orderBy('name')->get();
        $expenses      = [];

        if ($activeSession) {
            $expenses = Expense::with('user')
                ->where('month_session_id', $activeSession->id)
                ->orderBy('date', 'desc')
                ->get();

            $deposits = Deposit::where('month_session_id', $activeSession->id)
                ->where('status', 'approved')
                ->get();
            
            $users->transform(function ($user) use ($deposits, $expenses) {
                $userDeposits = $deposits->where('user_id', $user->id)->sum('amount');
                $userDryFood  = $expenses->where('user_id', $user->id)
                                         ->where('status', 'approved')
                                         ->where('category', 'dry_food')->sum('amount');
                $user->available_deposit = round($userDeposits - $userDryFood, 2);
                return $user;
            });
        }

        return Inertia::render('Expenses/Index', [
            'activeSession' => $activeSession,
            'users'         => $users,
            'expenses'      => $expenses,
            'canManage'     => in_array(auth()->user()->role, ['admin', 'manager']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'month_session_id' => 'required|exists:month_sessions,id',
            'user_id'          => 'required|exists:users,id',
            'date'             => 'required|date',
            'amount'           => 'required|numeric|min:0.01',
            'category'         => 'required|in:raw_food,dry_food,utility,rent',
            'description'      => 'nullable|string|max:255',
        ]);

        $role   = auth()->user()->role;
        $status = in_array($role, ['admin', 'manager']) ? 'approved' : 'pending';

        // Borders can only submit for themselves
        if ($role === 'border' && $request->user_id != auth()->id()) {
            abort(403);
        }

        if ($request->category === 'dry_food') {
            if (in_array($role, ['admin', 'manager'])) {
                // Admin/Manager: Check if user has enough available deposit
                $deposits = Deposit::where('month_session_id', $request->month_session_id)
                                   ->where('user_id', $request->user_id)
                                   ->where('status', 'approved')
                                   ->sum('amount');
                                   
                $dryFoodExpenses = Expense::where('month_session_id', $request->month_session_id)
                                          ->where('user_id', $request->user_id)
                                          ->where('category', 'dry_food')
                                          ->whereIn('status', ['approved', 'pending'])
                                          ->sum('amount');
                                          
                $available = $deposits - $dryFoodExpenses;

                if ($request->amount > $available) {
                    return back()->with('error', 'Insufficient available deposit (Available: ৳' . $available . '). Please make a deposit first.');
                }

                Expense::create([
                    'month_session_id' => $request->month_session_id,
                    'user_id'          => $request->user_id,
                    'date'             => $request->date,
                    'amount'           => $request->amount,
                    'category'         => $request->category,
                    'description'      => $request->description,
                    'status'           => $status,
                ]);
            } else {
                // Border: Adding dry food from pocket. Create both expense and deposit.
                DB::transaction(function () use ($request, $status) {
                    Expense::create([
                        'month_session_id' => $request->month_session_id,
                        'user_id'          => $request->user_id,
                        'date'             => $request->date,
                        'amount'           => $request->amount,
                        'category'         => $request->category,
                        'description'      => $request->description,
                        'status'           => $status,
                    ]);

                    Deposit::create([
                        'month_session_id' => $request->month_session_id,
                        'user_id'          => $request->user_id,
                        'date'             => $request->date,
                        'amount'           => $request->amount,
                        'description'      => 'Auto-Deposit (Self-bought Dry Food): ' . $request->description,
                        'status'           => $status,
                    ]);
                });
                
                return back()->with('success', 'Dry Food expense and matching deposit request placed successfully.');
            }
        } else {
            // Normal expense
            Expense::create([
                'month_session_id' => $request->month_session_id,
                'user_id'          => $request->user_id,
                'date'             => $request->date,
                'amount'           => $request->amount,
                'category'         => $request->category,
                'description'      => $request->description,
                'status'           => $status,
            ]);
        }

        return back()->with('success', 'Expense added successfully.');
    }
    public function update(Request $request, Expense $expense): RedirectResponse
    {
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
            return redirect()->route('expenses.index')->with('error', 'Unauthorized.');
        }

        $request->validate([
            'date'        => 'required|date',
            'amount'      => 'required|numeric|min:0',
            'category'    => 'required|in:raw_food,dry_food,utility,rent',
            'description' => 'nullable|string|max:255',
        ]);

        $expense->update([
            'date'        => $request->date,
            'amount'      => $request->amount,
            'category'    => $request->category,
            'description' => $request->description,
        ]);

        return redirect()->route('expenses.index')->with('success', 'Expense updated successfully.');
    }

    public function approve(Expense $expense): RedirectResponse
    {
        $expense->update(['status' => 'approved']);
        return redirect()->route('expenses.index')->with('success', 'Expense approved.');
    }

    public function reject(Expense $expense): RedirectResponse
    {
        $expense->update(['status' => 'rejected']);
        return redirect()->route('expenses.index')->with('success', 'Expense rejected.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        // Only admin/manager can delete
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
            abort(403);
        }

        if ($expense->category === 'dry_food') {
            // Find if there's a matching auto-deposit created by Border transaction
            $matchingDeposit = Deposit::where('user_id', $expense->user_id)
                ->where('month_session_id', $expense->month_session_id)
                ->where('date', $expense->date)
                ->where('amount', $expense->amount)
                ->where('description', 'like', 'Auto-Deposit (Self-bought Dry Food)%')
                ->first();

            if ($matchingDeposit) {
                $matchingDeposit->delete();
            }
        }

        $expense->delete();
        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }
}
