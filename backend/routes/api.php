<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Auth Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/send-otp', [Api\AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [Api\AuthController::class, 'verifyOtp']);
});

/*
|--------------------------------------------------------------------------
| Authenticated User API Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/auth/me', [Api\AuthController::class, 'me']);
    Route::post('/auth/profile', [Api\AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [Api\AuthController::class, 'logout']);

    // User Types (public list for onboarding)
    Route::get('/user-types', [Api\UserTypeController::class, 'index']);

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [Api\NotificationController::class, 'index']);
        Route::get('/count', [Api\NotificationController::class, 'unreadCount']);
        Route::get('/{notification}', [Api\NotificationController::class, 'show']);
        Route::post('/{notification}/read', [Api\NotificationController::class, 'markAsRead']);
        Route::post('/{notification}/resolve', [Api\NotificationController::class, 'resolve']);
        Route::post('/{notification}/resolve-upload', [Api\NotificationController::class, 'resolveWithFile']);
    });

    // Onboarding
    Route::prefix('onboarding')->group(function () {
        Route::get('/status', [Api\OnboardingController::class, 'status']);
        Route::post('/set-type', [Api\OnboardingController::class, 'setUserType']);
        Route::get('/questions', [Api\OnboardingController::class, 'questions']);
        Route::get('/registration', [Api\OnboardingController::class, 'registrationCatalog']);
        Route::post('/registration', [Api\OnboardingController::class, 'saveRegistration']);
        Route::post('/answers', [Api\OnboardingController::class, 'saveAnswers']);
        Route::post('/answers/upload', [Api\OnboardingController::class, 'uploadFileAnswer']);
        Route::post('/reopen', [Api\OnboardingController::class, 'reopen']);
        Route::get('/download-pdf', [Api\OnboardingController::class, 'downloadPdf']);
        Route::get('/timeline', [Api\OnboardingController::class, 'timeline']);
        Route::get('/messages', [Api\MessageController::class, 'index']);
        Route::post('/messages', [Api\MessageController::class, 'store']);
        Route::get('/messages/unread-count', [Api\MessageController::class, 'unreadCount']);
        Route::post('/messages/read', [Api\MessageController::class, 'markRead']);
        Route::post('/steps/{step}/complete', [Api\OnboardingController::class, 'completeStep']);
        Route::post('/steps/{step}/previous', [Api\OnboardingController::class, 'previousStep']);
        Route::post('/steps/{step}/goto', [Api\OnboardingController::class, 'goToStep']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    // User Types
    Route::apiResource('user-types', Admin\UserTypeController::class);
    Route::apiResource('user-types.subcategories', Admin\SubcategoryController::class);

    // Questions
    Route::apiResource('question-groups', Admin\QuestionGroupController::class);
    Route::apiResource('questions', Admin\QuestionController::class);
    Route::apiResource('conditional-rules', Admin\ConditionalRuleController::class);

    // Onboarding Steps (Master Template)
    Route::apiResource('onboarding-steps', Admin\OnboardingStepController::class);
    Route::post('onboarding-steps/reorder', [Admin\OnboardingStepController::class, 'reorder']);

    // User Onboarding Management
    Route::get('user-onboardings', [Admin\UserOnboardingController::class, 'index']);
    Route::get('user-onboardings/{user_onboarding}', [Admin\UserOnboardingController::class, 'show']);
    Route::post('user-onboardings/{user_onboarding}/reorder-steps', [Admin\UserOnboardingController::class, 'reorderSteps']);
    Route::get('audit-logs', [Admin\UserOnboardingController::class, 'auditLogs']);
});
