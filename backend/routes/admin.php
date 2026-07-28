<?php

use App\Http\Controllers\AdminPanel\AuthController;
use App\Http\Controllers\AdminPanel\ConditionalRuleController;
use App\Http\Controllers\AdminPanel\CountryRegistrationController;
use App\Http\Controllers\AdminPanel\DashboardController;
use App\Http\Controllers\AdminPanel\DocumentReviewController;
use App\Http\Controllers\AdminPanel\OnboardingStepController;
use App\Http\Controllers\AdminPanel\QuestionController;
use App\Http\Controllers\AdminPanel\QuestionGroupController;
use App\Http\Controllers\AdminPanel\SubcategoryController;
use App\Http\Controllers\AdminPanel\UserOnboardingController;
use App\Http\Controllers\AdminPanel\UserTypeController;
use App\Enums\Ability;
use App\Http\Middleware\AdminAuth;
use Illuminate\Support\Facades\Route;

// Admin Auth (guest)
Route::middleware('web')->prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');
});

// Admin Protected Routes
Route::middleware(['web', AdminAuth::class, \App\Http\Middleware\LogAdminActivity::class])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Personal settings
    Route::patch('settings/pin-shortcut', [\App\Http\Controllers\AdminPanel\AdminSettingsController::class, 'updatePinShortcut'])->name('settings.pin-shortcut');
    Route::post('settings/reset-preset-customizations', [\App\Http\Controllers\AdminPanel\AdminSettingsController::class, 'resetPresetCustomizations'])->name('settings.reset-preset-customizations');
    Route::get('settings/preset-history', [\App\Http\Controllers\AdminPanel\AdminSettingsController::class, 'presetHistory'])->name('settings.preset-history');
    Route::get('settings/preset-history/export', [\App\Http\Controllers\AdminPanel\AdminSettingsController::class, 'exportPresetHistory'])->name('settings.preset-history.export');
    Route::post('settings/preset-history/clear', [\App\Http\Controllers\AdminPanel\AdminSettingsController::class, 'clearPresetHistory'])->name('settings.preset-history.clear');
    Route::post('settings/preset-history/restore', [\App\Http\Controllers\AdminPanel\AdminSettingsController::class, 'restorePresetHistory'])->name('settings.preset-history.restore');
    Route::post('settings/preset-history/bulk-pin', [\App\Http\Controllers\AdminPanel\AdminSettingsController::class, 'bulkPinHistory'])->name('settings.preset-history.bulk-pin');
    Route::post('settings/preset-history/unpin-all', [\App\Http\Controllers\AdminPanel\AdminSettingsController::class, 'unpinAllHistory'])->name('settings.preset-history.unpin-all');
    Route::post('settings/preset-history/{log}/pin', [\App\Http\Controllers\AdminPanel\AdminSettingsController::class, 'toggleHistoryPin'])->name('settings.preset-history.pin');

    // Platform configuration — templates & rules (Admin / Super Admin only)
    Route::middleware('ability:' . Ability::MANAGE_TEMPLATES)->group(function () {
        Route::resource('user-types', UserTypeController::class)->except(['show']);
        Route::resource('user-types.subcategories', SubcategoryController::class)->except(['show']);
        Route::resource('question-groups', QuestionGroupController::class)->except(['show']);
        Route::resource('questions', QuestionController::class)->except(['show']);
        Route::resource('conditional-rules', ConditionalRuleController::class)->except(['show']);
        Route::resource('onboarding-steps', OnboardingStepController::class)->except(['show']);
        Route::resource('country-registrations', CountryRegistrationController::class)->except(['show']);
    });

    // Scheduled Emails (broadcast tooling — Manager / Admin+)
    Route::middleware('ability:' . Ability::MANAGE_EMAILS)->group(function () {
    Route::get('scheduled-emails', [\App\Http\Controllers\AdminPanel\ScheduledEmailController::class, 'index'])->name('scheduled-emails.index');
    // Before the {scheduledEmail} wildcards below.
    Route::get('scheduled-emails/export-csv', [\App\Http\Controllers\AdminPanel\ScheduledEmailController::class, 'exportCsv'])->name('scheduled-emails.export-csv');
    Route::post('scheduled-emails/bulk-cancel', [\App\Http\Controllers\AdminPanel\ScheduledEmailController::class, 'bulkCancel'])->name('scheduled-emails.bulk-cancel');
    Route::post('scheduled-emails/{scheduledEmail}/cancel', [\App\Http\Controllers\AdminPanel\ScheduledEmailController::class, 'cancel'])->name('scheduled-emails.cancel');
    Route::post('scheduled-emails/{scheduledEmail}/restore', [\App\Http\Controllers\AdminPanel\ScheduledEmailController::class, 'restore'])->name('scheduled-emails.restore');
    Route::post('scheduled-emails/{scheduledEmail}/duplicate', [\App\Http\Controllers\AdminPanel\ScheduledEmailController::class, 'duplicate'])->name('scheduled-emails.duplicate');
    Route::get('scheduled-emails/{scheduledEmail}/preview', [\App\Http\Controllers\AdminPanel\ScheduledEmailController::class, 'preview'])->name('scheduled-emails.preview');
    });

    // Filter Presets (saved views on list pages; {context} names the page)
    Route::get('filter-presets/{context}/export', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'export'])->name('filter-presets.export');
    Route::post('filter-presets/{context}/import', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'import'])->name('filter-presets.import');
    Route::post('filter-presets/{context}/reorder', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'reorder'])->name('filter-presets.reorder');
    Route::post('filter-presets/{context}/reset-order', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'resetOrder'])->name('filter-presets.reset-order');
    Route::post('filter-presets/{context}/bulk-pin', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'bulkPin'])->name('filter-presets.bulk-pin');
    Route::post('filter-presets/{context}/unpin-all', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'unpinAll'])->name('filter-presets.unpin-all');
    Route::post('filter-presets/{context}', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'store'])->name('filter-presets.store');
    Route::post('filter-presets/{context}/{preset}/duplicate', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'duplicate'])->name('filter-presets.duplicate');
    Route::post('filter-presets/{context}/{preset}/pin', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'togglePin'])->name('filter-presets.pin');
    Route::patch('filter-presets/{context}/{preset}', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'rename'])->name('filter-presets.rename');
    Route::delete('filter-presets/{context}/{preset}', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'destroy'])->name('filter-presets.destroy');
    Route::delete('filter-presets/{context}', [\App\Http\Controllers\AdminPanel\FilterPresetController::class, 'destroyAll'])->name('filter-presets.destroy-all');

    // Email Templates (configuration — Admin+)
    Route::middleware('ability:' . Ability::MANAGE_TEMPLATES)->group(function () {
        Route::get('email-templates', [\App\Http\Controllers\AdminPanel\EmailTemplateController::class, 'index'])->name('email-templates.index');
        Route::get('email-templates/{key}/edit', [\App\Http\Controllers\AdminPanel\EmailTemplateController::class, 'edit'])->name('email-templates.edit');
        Route::put('email-templates/{key}', [\App\Http\Controllers\AdminPanel\EmailTemplateController::class, 'update'])->name('email-templates.update');
        Route::post('email-templates/{key}/reset', [\App\Http\Controllers\AdminPanel\EmailTemplateController::class, 'reset'])->name('email-templates.reset');
    });

    // User Onboardings
    Route::get('user-onboardings', [UserOnboardingController::class, 'index'])->name('user-onboardings.index');
    // Must precede the {userOnboarding} wildcard.
    Route::get('user-onboardings/export-csv', [UserOnboardingController::class, 'exportCsv'])->name('user-onboardings.export-csv');
    Route::post('user-onboardings/bulk-decision', [UserOnboardingController::class, 'bulkDecision'])->name('user-onboardings.bulk-decision')->middleware('ability:' . Ability::APPROVE_ONBOARDING);
    Route::post('user-onboardings/bulk-assign', [UserOnboardingController::class, 'bulkAssign'])->name('user-onboardings.bulk-assign')->middleware('ability:' . Ability::ASSIGN_ONBOARDING);
    Route::post('user-onboardings/bulk-email', [UserOnboardingController::class, 'bulkEmail'])->name('user-onboardings.bulk-email')->middleware('ability:' . Ability::MANAGE_EMAILS);
    Route::get('user-onboardings/{userOnboarding}', [UserOnboardingController::class, 'show'])->name('user-onboardings.show');
    Route::post('user-onboardings/{userOnboarding}/steps/{step}/toggle', [UserOnboardingController::class, 'toggleStep'])->name('user-onboardings.steps.toggle');
    Route::get('user-onboardings/{userOnboarding}/export-pdf', [UserOnboardingController::class, 'exportPdf'])->name('user-onboardings.export-pdf');
    Route::post('notifications/{notification}/check', [UserOnboardingController::class, 'checkResponse'])->name('notifications.check');
    Route::post('user-onboardings/{userOnboarding}/archive', [UserOnboardingController::class, 'archive'])->name('user-onboardings.archive');
    Route::post('user-onboardings/{userOnboarding}/unarchive', [UserOnboardingController::class, 'unarchive'])->name('user-onboardings.unarchive');
    Route::post('user-onboardings/{userOnboarding}/assign', [UserOnboardingController::class, 'assign'])->name('user-onboardings.assign')->middleware('ability:' . Ability::ASSIGN_ONBOARDING);
    Route::post('user-onboardings/{userOnboarding}/messages', [UserOnboardingController::class, 'replyMessage'])->name('user-onboardings.messages.reply');
    Route::post('user-onboardings/{userOnboarding}/notes', [\App\Http\Controllers\AdminPanel\OnboardingNoteController::class, 'store'])->name('user-onboardings.notes.store');
    Route::delete('user-onboardings/{userOnboarding}/notes/{note}', [\App\Http\Controllers\AdminPanel\OnboardingNoteController::class, 'destroy'])->name('user-onboardings.notes.destroy');
    Route::post('user-onboardings/{userOnboarding}/approve', [UserOnboardingController::class, 'approve'])->name('user-onboardings.approve')->middleware('ability:' . Ability::APPROVE_ONBOARDING);
    Route::post('user-onboardings/{userOnboarding}/reject', [UserOnboardingController::class, 'reject'])->name('user-onboardings.reject')->middleware('ability:' . Ability::REJECT_ONBOARDING);
    Route::get('user-onboardings/{userOnboarding}/answers/{answer}/history', [UserOnboardingController::class, 'answerHistory'])->name('user-onboardings.answers.history');
    Route::post('user-onboardings/{userOnboarding}/answers/{answer}/request-change', [UserOnboardingController::class, 'requestChange'])->name('user-onboardings.answers.request-change');
    Route::get('user-onboardings/{userOnboarding}/new-question', [UserOnboardingController::class, 'createQuestion'])->name('user-onboardings.new-question');
    Route::post('user-onboardings/{userOnboarding}/new-question', [UserOnboardingController::class, 'storeQuestion'])->name('user-onboardings.store-question');
    Route::post('send-email', [UserOnboardingController::class, 'sendEmail'])->name('send-email');

    // Staff / user management (Admin / Super Admin)
    Route::middleware('ability:' . Ability::MANAGE_USERS)->group(function () {
        Route::get('users', [\App\Http\Controllers\AdminPanel\AdminUserController::class, 'index'])->name('users.index');
        Route::get('users/create', [\App\Http\Controllers\AdminPanel\AdminUserController::class, 'create'])->name('users.create');
        Route::post('users', [\App\Http\Controllers\AdminPanel\AdminUserController::class, 'store'])->name('users.store');
        Route::get('users/{admin}/edit', [\App\Http\Controllers\AdminPanel\AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('users/{admin}', [\App\Http\Controllers\AdminPanel\AdminUserController::class, 'update'])->name('users.update');
        Route::post('users/{admin}/toggle', [\App\Http\Controllers\AdminPanel\AdminUserController::class, 'toggleStatus'])->name('users.toggle');
    });

    // Monitoring — audit trails (Compliance / Admin+)
    Route::middleware('ability:' . Ability::VIEW_ACTIVITY_LOG)->group(function () {
        Route::get('audit-logs', [UserOnboardingController::class, 'auditLogs'])->name('audit-logs.index');
        Route::get('admin-activity', [\App\Http\Controllers\AdminPanel\AdminActivityLogController::class, 'index'])->name('admin-activity.index');
    });

    // Document Reviews (validation queue + rules tuning — Compliance / Admin+)
    Route::middleware('ability:' . Ability::TUNE_DOCUMENT_POLICY)->group(function () {
        Route::get('document-reviews', [DocumentReviewController::class, 'index'])->name('document-reviews.index');
        Route::post('document-reviews/{file}/approve', [DocumentReviewController::class, 'approve'])->name('document-reviews.approve');
    });
});
