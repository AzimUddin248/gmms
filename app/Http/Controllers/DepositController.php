<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Models\MonthSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    public function index(): Response
    {
        $activeSession = MonthSession::where('is_active', true)->first();
        $users         = User::where('is_approved', true)->orderBy('name')->get();
        $deposits      = [];

        if ($activeSession) {
            $deposits = Deposit::with('user')
                ->where('month_session_id', $activeSession->id)
                ->orderBy('date', 'desc')
                ->get();
        }

        return Inertia::render('Deposits/Index', [
            'activeSession' => $activeSession,
            'users'         => $users,
            'deposits'      => $deposits,
            'canManage'     => in_array(auth()->user()->role, ['admin', 'manager']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'month_session_id' => 'required|exists:month_sessions,id',
            'user_id'          => 'required|exists:users,id',
            'date'             => 'required|date',
            'amount'           => 'required|numeric', // Removed min:0.01 to allow negative
            'description'      => 'nullable|string|max:255',
        ]);

        $role   = auth()->user()->role;
        $status = in_array($role, ['admin', 'manager']) ? 'approved' : 'pending';

        // Borders can only submit for themselves
        if ($role === 'border' && $request->user_id != auth()->id()) {
            abort(403);
        }

        Deposit::create([
            'month_session_id' => $request->month_session_id,
            'user_id'          => $request->user_id,
            'date'             => $request->date,
            'amount'           => $request->amount,
            'description'      => $request->description,
            'status'           => $status,
        ]);

        return back()->with('success', 'Deposit recorded successfully.');
    }
    public function update(Request $request, Deposit $deposit): RedirectResponse
    {
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
            return redirect()->route('deposits.index')->with('error', 'Unauthorized.');
        }

        $request->validate([
            'date'        => 'required|date',
            'amount'      => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        $deposit->update([
            'date'        => $request->date,
            'amount'      => $request->amount,
            'description' => $request->description,
        ]);

        return redirect()->route('deposits.index')->with('success', 'Deposit updated successfully.');
    }

    public function approve(Deposit $deposit): RedirectResponse
    {
        $deposit->update(['status' => 'approved']);
        return redirect()->route('deposits.index')->with('success', 'Deposit approved.');
    }

    public function reject(Deposit $deposit): RedirectResponse
    {
        $deposit->update(['status' => 'rejected']);
        return redirect()->route('deposits.index')->with('success', 'Deposit rejected.');
    }

    public function destroy(Deposit $deposit): RedirectResponse
    {
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) {
            abort(403);
        }
        $deposit->delete();
        return redirect()->route('deposits.index')->with('success', 'Deposit deleted.');
    }
}
