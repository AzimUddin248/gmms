<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $this->authorizeAdmin();

        $users = User::orderBy('is_approved')
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'role'        => $u->role,
                'is_approved' => $u->is_approved,
                'created_at'  => $u->created_at->format('d M Y'),
            ]);

        return Inertia::render('Users/Index', [
            'users'   => $users,
            'isAdmin' => auth()->user()->role === 'admin',
        ]);
    }

    /**
     * Admin creates a border/manager directly.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:admin,manager,border',
        ]);

        User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'role'        => $request->role,
            'is_approved' => true,   // admin-created accounts are auto-approved
        ]);

        return back()->with('success', "User '{$request->name}' created successfully.");
    }

    /**
     * Approve a pending border.
     */
    public function approve(User $user): RedirectResponse
    {
        $this->authorizeManage();

        $user->update(['is_approved' => true]);
        return redirect()->route('users.index')->with('success', "{$user->name} has been approved.");
    }

    /**
     * Change a user's role (Admin only).
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin();

        $request->validate(['role' => 'required|in:admin,manager,border']);
        $user->update(['role' => $request->role]);

        return redirect()->route('users.index')->with('success', "{$user->name}'s role updated to {$request->role}.");
    }

    /**
     * Delete a border (Admin only).
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'You cannot delete yourself.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', "User '{$user->name}' deleted.");
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    private function authorizeAdmin(): void
    {
        if (auth()->user()->role !== 'admin') abort(403);
    }

    private function authorizeManage(): void
    {
        if (!in_array(auth()->user()->role, ['admin', 'manager'])) abort(403);
    }
}
