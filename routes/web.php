<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\PdpController;
use App\Http\Controllers\PdpSkillController;
use App\Http\Controllers\PdpProgressController;
use App\Http\Controllers\UserProfessionalLevelController;
use App\Http\Controllers\CuratorController;
use App\Http\Controllers\AdminDashboardController;
use Inertia\Inertia as InertiaFacade;
use App\Http\Controllers\AdminEntriesController;
use App\Http\Controllers\AdminCuratorsController;
use App\Http\Controllers\AdminUsersController;
use App\Http\Controllers\AdminPdpsController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// PDP list page
Route::get('pdps', function () {
    return Inertia::render('pdps/Index');
})->middleware(['auth', 'verified'])->name('pdps.index');

// PDP templates page
Route::get('pdps/templates', function () {
    return Inertia::render('pdps/Templates');
})->middleware(['auth', 'verified'])->name('pdps.templates');

// Curator mentees page
Route::get('curator/mentees', function () {
    return Inertia::render('curator/Mentees');
})->middleware(['auth', 'verified'])->name('curator.mentees');

// JSON endpoints for PDPs and Skills (session-authenticated + CSRF)
// Allow selecting initial professional level BEFORE email verification
Route::middleware(['auth'])->group(function () {
    // User professional level (global, based on closed skills across all PDPs)
    // GET/POST are available for any authenticated (even unverified) user to set initial level right after registration
    Route::get('/profile/pro-level.json', [UserProfessionalLevelController::class, 'show']);
    Route::post('/profile/pro-level.json', [UserProfessionalLevelController::class, 'setSelf']);
});

Route::middleware(['auth', 'verified'])->group(function () {
    // PDPs
    Route::get('/pdps.json', [PdpController::class, 'index']);
    Route::get('/pdps.shared.json', [PdpController::class, 'shared']);
    Route::get('/pdps/templates.json', [PdpController::class, 'templates']);
    Route::post('/pdps/templates.json', [PdpController::class, 'createTemplate']);
    Route::get('/pdps/templates/{key}.json', [PdpController::class, 'getTemplate']);
    Route::put('/pdps/templates/{key}.json', [PdpController::class, 'updateTemplate']);
    Route::delete('/pdps/templates/{key}.json', [PdpController::class, 'deleteTemplate']);
    Route::post('/pdps/templates/{key}/assign.json', [PdpController::class, 'assignTemplate']);
    // Assign template skills into an existing PDP (compose PDP from skill templates)
    Route::post('/pdps/{pdp}/templates/{key}/assign.json', [PdpController::class, 'assignTemplateToPdp']);
    // Manual sync trigger for templates (owner only)
    Route::post('/pdps/templates/{key}/sync.json', [PdpController::class, 'syncTemplate']);
    Route::post('/pdps.json', [PdpController::class, 'store']);
    Route::post('/pdps/{pdp}/assign-curator.json', [PdpController::class, 'assignCurator']);
    Route::get('/pdps/{pdp}/curators.json', [PdpController::class, 'curators']);
    Route::delete('/pdps/{pdp}/curators/{user}.json', [PdpController::class, 'removeCurator']);
    Route::get('/pdps/{pdp}.json', [PdpController::class, 'show']);
    Route::get('/pdps/{pdp}/export.json', [PdpController::class, 'export']);
    Route::post('/pdps/{pdp}/transfer.json', [PdpController::class, 'transfer']);
    Route::post('/pdps/import.json', [PdpController::class, 'import']);
    Route::put('/pdps/{pdp}.json', [PdpController::class, 'update']);
    Route::delete('/pdps/{pdp}.json', [PdpController::class, 'destroy']);

    // Users search for assignment dropdown
    Route::get('/users.search.json', [PdpController::class, 'usersSearch']);

    // PDP Skills
    Route::get('/pdps/{pdp}/skills.json', [PdpSkillController::class, 'index']);
    Route::post('/pdps/{pdp}/skills.json', [PdpSkillController::class, 'store']);
    Route::put('/pdps/{pdp}/skills/{skill}.json', [PdpSkillController::class, 'update']);
    Route::patch('/pdps/{pdp}/skills/{skill}/criteria/{index}.json', [PdpSkillController::class, 'updateCriterionComment'])->whereNumber('index'); // legacy single comment support
    Route::patch('/pdps/{pdp}/skills/{skill}/criteria/{index}/done.json', [PdpSkillController::class, 'updateCriterionDone'])->whereNumber('index');
    Route::get('/pdps/{pdp}/skills/{skill}/criteria/{index}/progress.json', [PdpSkillController::class, 'listProgress'])->whereNumber('index');
    Route::post('/pdps/{pdp}/skills/{skill}/criteria/{index}/progress.json', [PdpSkillController::class, 'addProgress'])->whereNumber('index');
    Route::patch('/pdps/{pdp}/skills/{skill}/criteria/{index}/progress/{entry}.json', [PdpSkillController::class, 'updateProgressNote'])->whereNumber('index')->whereNumber('entry');
    Route::patch('/pdps/{pdp}/skills/{skill}/criteria/{index}/progress/{entry}/comment.json', [PdpSkillController::class, 'commentProgress'])->whereNumber('index')->whereNumber('entry');
    Route::post('/pdps/{pdp}/skills/{skill}/criteria/{index}/progress/{entry}/approve.json', [PdpSkillController::class, 'approveProgress'])->whereNumber('index')->whereNumber('entry');
    Route::delete('/pdps/{pdp}/skills/{skill}/criteria/{index}/progress/{entry}.json', [PdpSkillController::class, 'deleteProgress'])->whereNumber('index')->whereNumber('entry');

    // Annex JSON (document-like view)
    Route::get('/pdps/{pdp}/annex.json', [PdpSkillController::class, 'annex']);

    // Dashboard: pending approvals for current curator
    Route::get('/dashboard/pending-approvals.json', [PdpSkillController::class, 'pendingApprovals']);
    // Dashboard: PDP summary (KPI tiles + per-skill breakdown)
    Route::get('/dashboard/pdps/{pdp}/summary.json', [PdpSkillController::class, 'summary']);
    // Dashboard: My PDPs snapshot overview
    Route::get('/dashboard/pdps/overview.json', [PdpController::class, 'overview']);

    // Curator: My Mentees
    Route::get('/curator/mentees.json', [CuratorController::class, 'mentees']);
    Route::get('/curator/mentees/{user}/pending-approvals.json', [CuratorController::class, 'menteePendingApprovals']);
    Route::get('/curator/mentees/pending-approvals/count.json', [CuratorController::class, 'pendingApprovalsCount']);

    // PDP progress by closed skills (Done)
    Route::get('/pdps/{pdp}/progress.json', [PdpProgressController::class, 'show']);

    // Moderator can adjust level for any user (keep verified requirement)
    Route::put('/users/{user}/pro-level.json', [UserProfessionalLevelController::class, 'setForUser']);

    Route::delete('/pdps/{pdp}/skills/{skill}.json', [PdpSkillController::class, 'destroy']);


    Route::get('/notifications.unread.json', [NotificationController::class, 'unread']);
    Route::get('/notifications.json', [NotificationController::class, 'all']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// Admin area (Super Admin only)
Route::prefix('admin')
    ->middleware(['auth', 'verified', 'super_admin'])
    ->group(function () {
        // Inertia page for Admin Dashboard
        Route::get('/', function () {
            return InertiaFacade::render('admin/Dashboard');
        })->name('admin.dashboard');

        // API endpoint for global overview metrics
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        // Drill-down pages (Inertia)
        Route::get('/entries', function () { return InertiaFacade::render('admin/AdminEntriesList'); });
        Route::get('/curators', function () { return InertiaFacade::render('admin/AdminCuratorsList'); });
        Route::get('/users', function () { return InertiaFacade::render('admin/AdminUsersList'); });
        Route::get('/pdps', function () { return InertiaFacade::render('admin/AdminPdpsList'); });

        // Drill-down API endpoints (JSON)
        Route::get('/entries.json', [AdminEntriesController::class, 'index']);
        Route::get('/curators.json', [AdminCuratorsController::class, 'index']);
        Route::post('/curators/{user}/nudge.json', [AdminCuratorsController::class, 'nudge'])->whereNumber('user');
        Route::get('/users.json', [AdminUsersController::class, 'index']);
        Route::get('/pdps.json', [AdminPdpsController::class, 'index']);
    });
