<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\MealChartController;
use App\Http\Controllers\MealController;
use App\Http\Controllers\MonthSessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/pending-approval', function () {
    return Inertia::render('PendingApproval');
})->middleware(['auth'])->name('pending.approval');

Route::middleware(['auth', 'approved'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Month Sessions
    Route::post('/month-sessions', [MonthSessionController::class, 'store'])->name('month-sessions.store');
    Route::patch('/month-sessions/{monthSession}/close', [MonthSessionController::class, 'close'])->name('month-sessions.close');
    Route::patch('/month-sessions/{monthSession}/reopen', [MonthSessionController::class, 'reopen'])->name('month-sessions.reopen');

    // Meal Chart (page)
    Route::get('/meals', [MealChartController::class, 'index'])->name('meals.index');

    // Meal CRUD
    Route::post('/meals', [MealController::class, 'store'])->name('meals.store');
    Route::post('/meals/bulk', [MealController::class, 'bulkStore'])->name('meals.bulk-store');
    Route::patch('/meals/{meal}/approve', [MealController::class, 'approve'])->name('meals.approve');
    Route::patch('/meals/{meal}/reject', [MealController::class, 'reject'])->name('meals.reject');

    // Fixed Charges (Rent, Water, Servant)
    Route::get('/fixed-charges', [App\Http\Controllers\FixedChargeController::class, 'index'])->name('fixed-charges.index');
    Route::post('/fixed-charges/bulk', [App\Http\Controllers\FixedChargeController::class, 'bulkStore'])->name('fixed-charges.bulk-store');

    // Expenses (Bazar)
    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::patch('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::patch('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::patch('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    // Deposits (Cash)
    Route::get('/deposits', [DepositController::class, 'index'])->name('deposits.index');
    Route::post('/deposits', [DepositController::class, 'store'])->name('deposits.store');
    Route::patch('/deposits/{deposit}', [DepositController::class, 'update'])->name('deposits.update');
    Route::patch('/deposits/{deposit}/approve', [DepositController::class, 'approve'])->name('deposits.approve');
    Route::patch('/deposits/{deposit}/reject', [DepositController::class, 'reject'])->name('deposits.reject');
    Route::delete('/deposits/{deposit}', [DepositController::class, 'destroy'])->name('deposits.destroy');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{monthSession}', [ReportController::class, 'show'])->name('reports.show');
    Route::post('/reports/{monthSession}/carry-forward/{user}', [ReportController::class, 'carryForward'])->name('reports.carry-forward');

    // Payables & Payments
    Route::get('/payables', [\App\Http\Controllers\PayableController::class, 'index'])->name('payables.index');
    Route::post('/payables/generate/{monthSession}', [\App\Http\Controllers\PayableController::class, 'generate'])->name('payables.generate');
    Route::post('/payables/{payable}/payments', [\App\Http\Controllers\PayableController::class, 'storePayment'])->name('payables.payments.store');
    Route::delete('/payments/{payment}', [\App\Http\Controllers\PayableController::class, 'destroyPayment'])->name('payables.payments.destroy');

    // Bazar Rosters
    Route::get('/bazar-rosters', [\App\Http\Controllers\BazarRosterController::class, 'index'])->name('bazar-rosters.index');
    Route::post('/bazar-rosters', [\App\Http\Controllers\BazarRosterController::class, 'store'])->name('bazar-rosters.store');
    Route::delete('/bazar-rosters/{roster}', [\App\Http\Controllers\BazarRosterController::class, 'destroy'])->name('bazar-rosters.destroy');

    // User Management (admin/manager)
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}/approve', [UserController::class, 'approve'])->name('users.approve');
    Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.update-role');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/auth.php';


