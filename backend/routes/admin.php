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
Route::middleware(['web', AdminAuth::class])->prefix('admin')->name('admin.')->group(function () {
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

    // User Onboardings
    Route::get('user-onboardings', [UserOnboardingController::class, 'index'])->name('user-onboardings.index');
    Route::get('user-onboardings/{userOnboarding}', [UserOnboardingController::class, 'show'])->name('user-onboardings.show');
    Route::post('user-onboardings/{userOnboarding}/steps/{step}/toggle', [UserOnboardingController::class, 'toggleStep'])->name('user-onboardings.steps.toggle');
    Route::get('user-onboardings/{userOnboarding}/export-pdf', [UserOnboardingController::class, 'exportPdf'])->name('user-onboardings.export-pdf');
    Route::post('user-onboardings/{userOnboarding}/approve', [UserOnboardingController::class, 'approve'])->name('user-onboardings.approve');
    Route::post('user-onboardings/{userOnboarding}/reject', [UserOnboardingController::class, 'reject'])->name('user-onboardings.reject');
    Route::get('user-onboardings/{userOnboarding}/answers/{answer}/history', [UserOnboardingController::class, 'answerHistory'])->name('user-onboardings.answers.history');
    Route::post('user-onboardings/{userOnboarding}/answers/{answer}/request-change', [UserOnboardingController::class, 'requestChange'])->name('user-onboardings.answers.request-change');
    Route::get('user-onboardings/{userOnboarding}/new-question', [UserOnboardingController::class, 'createQuestion'])->name('user-onboardings.new-question');
    Route::post('user-onboardings/{userOnboarding}/new-question', [UserOnboardingController::class, 'storeQuestion'])->name('user-onboardings.store-question');
    Route::post('send-email', [UserOnboardingController::class, 'sendEmail'])->name('send-email');

    // Audit Logs
    Route::get('audit-logs', [UserOnboardingController::class, 'auditLogs'])->name('audit-logs.index');

    // Document Reviews (AI validation queue)
    Route::get('document-reviews', [DocumentReviewController::class, 'index'])->name('document-reviews.index');
    Route::post('document-reviews/{file}/approve', [DocumentReviewController::class, 'approve'])->name('document-reviews.approve');
});
