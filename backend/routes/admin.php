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

    // User Types
    Route::resource('user-types', UserTypeController::class)->except(['show']);

    // Subcategories (nested under user types)
    Route::resource('user-types.subcategories', SubcategoryController::class)->except(['show']);

    // Question Groups
    Route::resource('question-groups', QuestionGroupController::class)->except(['show']);

    // Questions
    Route::resource('questions', QuestionController::class)->except(['show']);

    // Conditional Rules
    Route::resource('conditional-rules', ConditionalRuleController::class)->except(['show']);

    // Onboarding Steps
    Route::resource('onboarding-steps', OnboardingStepController::class)->except(['show']);

    // Country Registrations
    Route::resource('country-registrations', CountryRegistrationController::class)->except(['show']);

    // Scheduled Emails
    Route::get('scheduled-emails', [\App\Http\Controllers\AdminPanel\ScheduledEmailController::class, 'index'])->name('scheduled-emails.index');
    Route::post('scheduled-emails/{scheduledEmail}/cancel', [\App\Http\Controllers\AdminPanel\ScheduledEmailController::class, 'cancel'])->name('scheduled-emails.cancel');
    Route::post('scheduled-emails/{scheduledEmail}/duplicate', [\App\Http\Controllers\AdminPanel\ScheduledEmailController::class, 'duplicate'])->name('scheduled-emails.duplicate');

    // Email Templates
    Route::get('email-templates', [\App\Http\Controllers\AdminPanel\EmailTemplateController::class, 'index'])->name('email-templates.index');
    Route::get('email-templates/{key}/edit', [\App\Http\Controllers\AdminPanel\EmailTemplateController::class, 'edit'])->name('email-templates.edit');
    Route::put('email-templates/{key}', [\App\Http\Controllers\AdminPanel\EmailTemplateController::class, 'update'])->name('email-templates.update');
    Route::post('email-templates/{key}/reset', [\App\Http\Controllers\AdminPanel\EmailTemplateController::class, 'reset'])->name('email-templates.reset');

    // User Onboardings
    Route::get('user-onboardings', [UserOnboardingController::class, 'index'])->name('user-onboardings.index');
    // Must precede the {userOnboarding} wildcard.
    Route::get('user-onboardings/export-csv', [UserOnboardingController::class, 'exportCsv'])->name('user-onboardings.export-csv');
    Route::post('user-onboardings/bulk-decision', [UserOnboardingController::class, 'bulkDecision'])->name('user-onboardings.bulk-decision');
    Route::post('user-onboardings/bulk-email', [UserOnboardingController::class, 'bulkEmail'])->name('user-onboardings.bulk-email');
    Route::get('user-onboardings/{userOnboarding}', [UserOnboardingController::class, 'show'])->name('user-onboardings.show');
    Route::post('user-onboardings/{userOnboarding}/steps/{step}/toggle', [UserOnboardingController::class, 'toggleStep'])->name('user-onboardings.steps.toggle');
    Route::get('user-onboardings/{userOnboarding}/export-pdf', [UserOnboardingController::class, 'exportPdf'])->name('user-onboardings.export-pdf');
    Route::post('notifications/{notification}/check', [UserOnboardingController::class, 'checkResponse'])->name('notifications.check');
    Route::post('user-onboardings/{userOnboarding}/archive', [UserOnboardingController::class, 'archive'])->name('user-onboardings.archive');
    Route::post('user-onboardings/{userOnboarding}/unarchive', [UserOnboardingController::class, 'unarchive'])->name('user-onboardings.unarchive');
    Route::post('user-onboardings/{userOnboarding}/assign', [UserOnboardingController::class, 'assign'])->name('user-onboardings.assign');
    Route::post('user-onboardings/{userOnboarding}/messages', [UserOnboardingController::class, 'replyMessage'])->name('user-onboardings.messages.reply');
    Route::post('user-onboardings/{userOnboarding}/notes', [\App\Http\Controllers\AdminPanel\OnboardingNoteController::class, 'store'])->name('user-onboardings.notes.store');
    Route::delete('user-onboardings/{userOnboarding}/notes/{note}', [\App\Http\Controllers\AdminPanel\OnboardingNoteController::class, 'destroy'])->name('user-onboardings.notes.destroy');
    Route::post('user-onboardings/{userOnboarding}/approve', [UserOnboardingController::class, 'approve'])->name('user-onboardings.approve');
    Route::post('user-onboardings/{userOnboarding}/reject', [UserOnboardingController::class, 'reject'])->name('user-onboardings.reject');
    Route::get('user-onboardings/{userOnboarding}/answers/{answer}/history', [UserOnboardingController::class, 'answerHistory'])->name('user-onboardings.answers.history');
    Route::post('user-onboardings/{userOnboarding}/answers/{answer}/request-change', [UserOnboardingController::class, 'requestChange'])->name('user-onboardings.answers.request-change');
    Route::get('user-onboardings/{userOnboarding}/new-question', [UserOnboardingController::class, 'createQuestion'])->name('user-onboardings.new-question');
    Route::post('user-onboardings/{userOnboarding}/new-question', [UserOnboardingController::class, 'storeQuestion'])->name('user-onboardings.store-question');
    Route::post('send-email', [UserOnboardingController::class, 'sendEmail'])->name('send-email');

    // Audit Logs
    Route::get('audit-logs', [UserOnboardingController::class, 'auditLogs'])->name('audit-logs.index');

    // Admin Activity (append-only audit of admin actions)
    Route::get('admin-activity', [\App\Http\Controllers\AdminPanel\AdminActivityLogController::class, 'index'])->name('admin-activity.index');

    // Document Reviews (AI validation queue)
    Route::get('document-reviews', [DocumentReviewController::class, 'index'])->name('document-reviews.index');
    Route::post('document-reviews/{file}/approve', [DocumentReviewController::class, 'approve'])->name('document-reviews.approve');
});
