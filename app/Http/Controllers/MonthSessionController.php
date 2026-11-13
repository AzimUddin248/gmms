<?php

namespace App\Http\Controllers;

use App\Models\MonthSession;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class MonthSessionController extends Controller
{
    /**
     * Store a new month session (Admin/Manager only).
     * Only one session can be active at a time.
     */
    public function store(Request $request): RedirectResponse
    {
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) abort(403);

        $request->validate([
            'name'       => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        // Deactivate any existing active session
        MonthSession::where('is_active', true)->update(['is_active' => false]);

        MonthSession::create([
            'name'       => $request->name,
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
            'is_active'  => true,
        ]);

        return redirect()->route('dashboard')->with('success', "Month session '{$request->name}' started successfully!");
    }

    /**
     * Close the active month session.
     */
    public function close(MonthSession $monthSession): RedirectResponse
    {
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) abort(403);
        $monthSession->update(['is_active' => false]);
        return redirect()->route('dashboard')->with('success', "Month '{$monthSession->name}' has been closed.");
    }

    /**
     * Reopen a closed month session.
     */
    public function reopen(MonthSession $monthSession): RedirectResponse
    {
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) abort(403);
        
        // Deactivate any existing active session
        MonthSession::where('is_active', true)->update(['is_active' => false]);
        
        // Wake up this session
        $monthSession->update(['is_active' => true]);
        
        return back()->with('success', "Month '{$monthSession->name}' is now ACTIVE again.");
    }
}
