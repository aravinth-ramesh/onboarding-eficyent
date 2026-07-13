<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Services\DocumentIntelligence\ClaudeDocumentIntelligence;
use App\Services\DocumentIntelligence\DocumentIntelligenceContract;
use App\Services\DocumentIntelligence\FakeDocumentIntelligence;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentIntelligenceContract::class, function () {
            if (config('document_validation.driver') === 'fake') {
                return new FakeDocumentIntelligence();
            }

            return new ClaudeDocumentIntelligence(
                new AnthropicClient(apiKey: config('document_validation.anthropic_api_key')),
            );
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
