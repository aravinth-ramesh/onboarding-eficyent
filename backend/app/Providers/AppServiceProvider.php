<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Services\DocumentIntelligence\ClaudeDocumentIntelligence;
use App\Services\DocumentIntelligence\DocumentIntelligenceContract;
use App\Services\DocumentIntelligence\FakeDocumentIntelligence;
use App\Services\DocumentIntelligence\Rules\RulesDocumentIntelligence;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentIntelligenceContract::class, function ($app) {
            return match (config('document_validation.driver')) {
                'fake' => new FakeDocumentIntelligence(),
                'claude' => new ClaudeDocumentIntelligence(
                    new AnthropicClient(apiKey: config('document_validation.anthropic_api_key')),
                ),
                default => $app->make(RulesDocumentIntelligence::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
    }
}
