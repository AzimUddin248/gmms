<?php

namespace App\Http\Controllers;

use App\Models\BazarRoster;
use App\Models\MonthSession;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BazarRosterController extends Controller
{
    public function index(): Response
    {
        $activeSession = MonthSession::where('is_active', true)->first();
        $rosters = [];
        $users = User::where('is_approved', true)->orderBy('name')->get();

        if ($activeSession) {
            $rosters = BazarRoster::with('user')
                ->where('month_session_id', $activeSession->id)
                ->orderBy('date', 'asc')
                ->get();
        }

        $role = auth()->user()->role;

        return Inertia::render('BazarRosters/Index', [
            'activeSession' => $activeSession,
            'rosters'       => $rosters,
            'users'         => $users,
            'canManage'     => in_array($role, ['admin', 'manager']),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'month_session_id' => 'required|exists:month_sessions,id',
            'user_id'          => 'required|exists:users,id',
            'date'             => 'required|date',
            'shopping_list'    => 'nullable|string',
        ]);

        if (!in_array(auth()->user()->role, ['admin', 'manager'])) abort(403);

        $roster = BazarRoster::updateOrCreate(
            [
                'month_session_id' => $request->month_session_id,
                'date'             => $request->date,
            ],
            [
                'user_id' => $request->user_id,
            ]
        );

        if ($request->has('shopping_list')) {
            $roster->update(['shopping_list' => $request->shopping_list]);
        }

        return back()->with('success', 'Roster updated.');
    }

    public function destroy(BazarRoster $roster)
    {
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) abort(403);
        $roster->delete();
        return redirect()->route('bazar-rosters.index')->with('success', 'Roster removed.');
    }
}
