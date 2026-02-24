<?php

use App\Http\Controllers\AdminPanel\AuthController;
use App\Http\Controllers\AdminPanel\ConditionalRuleController;
use App\Http\Controllers\AdminPanel\DashboardController;
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

    // User Onboardings
    Route::get('user-onboardings', [UserOnboardingController::class, 'index'])->name('user-onboardings.index');
    Route::get('user-onboardings/{userOnboarding}', [UserOnboardingController::class, 'show'])->name('user-onboardings.show');
    Route::post('user-onboardings/{userOnboarding}/steps/{step}/toggle', [UserOnboardingController::class, 'toggleStep'])->name('user-onboardings.steps.toggle');

    // Audit Logs
    Route::get('audit-logs', [UserOnboardingController::class, 'auditLogs'])->name('audit-logs.index');
});
