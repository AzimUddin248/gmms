<?php

namespace App\Http\Controllers;

use App\Models\FixedCharge;
use App\Models\MonthSession;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FixedChargeController extends Controller
{
    public function index()
    {
        $activeSession = MonthSession::where('is_active', true)->first();
        if (!$activeSession) {
            return redirect()->route('dashboard')->with('error', 'No active month session.');
        }

        $users = User::where('is_approved', true)->orderBy('name')->get();
        $charges = FixedCharge::where('month_session_id', $activeSession->id)->get();

        return Inertia::render('FixedCharges/Index', [
            'activeSession' => $activeSession,
            'users' => $users,
            'charges' => $charges
        ]);
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'month_session_id' => 'required|exists:month_sessions,id',
            'charges' => 'required|array',
            'charges.*.user_id' => 'required|exists:users,id',
            'charges.*.rent' => 'required|numeric|min:0',
            'charges.*.water' => 'required|numeric|min:0',
            'charges.*.servant' => 'required|numeric|min:0',
            'charges.*.gas_electricity_internet' => 'required|numeric|min:0',
        ]);

        foreach ($request->charges as $charge) {
            FixedCharge::updateOrCreate(
                [
                    'month_session_id' => $request->month_session_id,
                    'user_id' => $charge['user_id']
                ],
                [
                    'rent' => $charge['rent'],
                    'water' => $charge['water'],
                    'servant' => $charge['servant'],
                    'gas_electricity_internet' => $charge['gas_electricity_internet'],
                ]
            );
        }

        return redirect()->route('fixed-charges.index')->with('success', 'Fixed charges updated successfully.');
    }
}
