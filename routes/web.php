<?php

use App\DashboardAnalytics;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AdvisoryController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\SmsBalanceController;
use App\Http\Controllers\UserAccessController;
use App\Models\Barangay;
use App\Models\Category;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public / Welcome
Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// Authenticated Application Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard/sms-balance', SmsBalanceController::class)->middleware(['can:administer', 'throttle:directory-reads'])->name('dashboard.sms-balance');

    // Main Dashboard
    Route::get('/dashboard', function () {
        $categories = Category::withCount([
            'contacts',
            'contacts as active_contacts_count' => fn (Builder $query) => $query->where('is_active', true),
        ])->orderBy('name')->get(['id', 'name']);
        $barangays = Barangay::withCount([
            'contacts',
            'contacts as active_contacts_count' => fn (Builder $query) => $query->where('is_active', true),
        ])->orderBy('name')->get(['id', 'name']);

        return Inertia::render('dashboard', [
            'stats' => [
                'totalContacts' => Contact::count(),
                'activeContacts' => Contact::where('is_active', true)->count(),
                'totalBarangays' => $barangays->count(),
                'coveredBarangays' => $barangays->where('active_contacts_count', '>', 0)->count(),
                'totalCategories' => $categories->count(),
            ],
            'categories' => $categories,
            'barangays' => $barangays,
            'recentContacts' => Contact::with(['category:id,name', 'barangay:id,name'])
                ->latest('id')->limit(5)->get(['id', 'first_name', 'last_name', 'is_active', 'category_id', 'barangay_id']),
            'updatedAt' => now()->toIso8601String(),
            'analytics' => app(DashboardAnalytics::class)->summary(),
        ]);
    })->name('dashboard');

    Route::middleware('can:operate')->group(function () {
        Route::prefix('menu')->name('menu.')->group(function () {
            Route::get('/contacts/export', [ContactController::class, 'export'])->middleware(['can:administer', 'throttle:exports'])->name('contacts.export');
            Route::get('/contacts/template', [ContactController::class, 'downloadTemplate'])->middleware(['can:administer', 'throttle:exports'])->name('contacts.template');
            Route::post('/contacts/import', [ContactController::class, 'importCsv'])->middleware(['can:administer', 'throttle:imports'])->name('contacts.import');
            Route::resource('contacts', ContactController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('throttle:mutations');
            Route::get('/textblast', [ContactController::class, 'textblast'])->middleware('throttle:directory-reads')->name('textblast');
            Route::post('/textblast', [ContactController::class, 'sendTextBlast'])->middleware('throttle:sms')->name('sendTextBlast');
            Route::resource('advisories', AdvisoryController::class)->only(['store', 'update', 'destroy'])->middleware('throttle:mutations');
            Route::get('/quick-advisory', [OperationsController::class, 'advisories'])->name('quick-advisory');
            Route::post('/quick-advisory', [OperationsController::class, 'dispatchAdvisory'])->middleware('throttle:sms')->name('advisories.dispatchQuick');
            Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::resource('categories', CategoryController::class)->only(['store', 'update', 'destroy'])->middleware(['can:administer', 'throttle:mutations']);
        });
        Route::get('/barangays', [OperationsController::class, 'barangays'])->name('barangays.index');
        Route::get('/hotlines', [OperationsController::class, 'hotlines'])->name('hotlines.index');
        Route::get('/reports/sms/export', [OperationsController::class, 'exportSms'])->middleware(['can:administer', 'throttle:exports'])->name('reports.sms.export');
        Route::get('/reports', [OperationsController::class, 'reports'])->name('reports.index');
        Route::post('/reports/sms/{recipient}/cancel', [OperationsController::class, 'cancelSms'])->middleware('throttle:mutations')->name('reports.sms.cancel');
        Route::post('/reports/broadcasts/{broadcast}/cancel', [OperationsController::class, 'cancelBroadcast'])->middleware('throttle:mutations')->name('reports.broadcasts.cancel');
        Route::post('/reports', [OperationsController::class, 'storeIncident'])->middleware('throttle:mutations')->name('reports.store');
        Route::patch('/reports/{incident}', [OperationsController::class, 'updateIncident'])->middleware('throttle:mutations')->name('reports.update');
        Route::middleware(['can:administer', 'throttle:mutations'])->group(function () {
            Route::post('/barangays', [OperationsController::class, 'storeBarangay'])->name('barangays.store');
            Route::put('/barangays/{barangay}', [OperationsController::class, 'updateBarangay'])->name('barangays.update');
            Route::delete('/barangays/{barangay}', [OperationsController::class, 'destroyBarangay'])->name('barangays.destroy');
            Route::post('/hotlines', [OperationsController::class, 'storeHotline'])->name('hotlines.store');
            Route::put('/hotlines/{hotline}', [OperationsController::class, 'updateHotline'])->name('hotlines.update');
            Route::delete('/hotlines/{hotline}', [OperationsController::class, 'destroyHotline'])->name('hotlines.destroy');
            Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('/users', [UserAccessController::class, 'index'])->name('users.index');
            Route::post('/users', [UserAccessController::class, 'store'])->name('users.store');
            Route::patch('/users/{user}/password', [UserAccessController::class, 'resetPassword'])->name('users.password.reset');
            Route::patch('/users/{user}', [UserAccessController::class, 'update'])->name('users.update');
        });
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
