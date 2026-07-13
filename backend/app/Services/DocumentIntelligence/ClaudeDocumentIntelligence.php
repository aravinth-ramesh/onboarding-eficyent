<?php

namespace App\Services\DocumentIntelligence;

use Anthropic\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ClaudeDocumentIntelligence implements DocumentIntelligenceContract
{
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function __construct(
        private Client $client,
    ) {}

    public function analyze(UploadedFile $file): ?DocumentAnalysis
    {
        $block = $this->contentBlockFor($file);
        if ($block === null) {
            // Format Claude can't ingest (e.g. docx) — leave to human review.
            return null;
        }

        try {
            $response = $this->client->messages->create(
                model: config('document_validation.model'),
                maxTokens: 1024,
                system: $this->systemPrompt(),
                messages: [[
                    'role' => 'user',
                    'content' => [
                        $block,
                        ['type' => 'text', 'text' => 'Classify this document and extract its dates.'],
                    ],
                ]],
                outputConfig: ['format' => ['type' => 'json_schema', 'schema' => $this->schema()]],
            );
        } catch (\Throwable $e) {
            Log::warning('Document analysis failed', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->stopReason === 'refusal') {
            return null;
        }

        foreach ($response->content as $contentBlock) {
            if ($contentBlock->type === 'text') {
                $data = json_decode($contentBlock->text, true);

                return is_array($data) ? DocumentAnalysis::fromModelOutput($data) : null;
            }
        }

        return null;
    }

    private function contentBlockFor(UploadedFile $file): ?array
    {
        if ($file->getSize() > config('document_validation.max_analyzable_bytes')) {
            return null;
        }

        $mime = strtolower((string) $file->getMimeType());
        $data = base64_encode((string) file_get_contents($file->getRealPath()));

        if ($mime === 'application/pdf') {
            return [
                'type' => 'document',
                'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $data],
            ];
        }

        if (in_array($mime, self::IMAGE_MIMES, true)) {
            return [
                'type' => 'image',
                'source' => ['type' => 'base64', 'mediaType' => $mime, 'data' => $data],
            ];
        }

        return null;
    }

    private function systemPrompt(): string
    {
        $typeLines = collect(config('document_validation.types'))
            ->map(fn (array $meta, string $key) => "- {$key}: {$meta['description']}")
            ->implode("\n");

        return <<<PROMPT
You are a KYC document analyst for a client-onboarding platform. Given one document (PDF or image), classify it and extract its dates.

Document types:
{$typeLines}

Rules:
- document_type must be one of the keys above. Use "other" only when nothing fits.
- issue_date is the date the document was issued, printed, or signed (statement end date for bank statements, bill date for utility bills). Use YYYY-MM-DD. Null if no date is visible.
- expiry_date is a printed validity/expiry date (common on IDs, licenses, certificates). Null if the document has none.
- confidence reflects how sure you are of the classification: high (clear, legible, unambiguous), medium (probable but partly illegible or unusual), low (guessing).
- summary is one sentence describing what the document is, for a human reviewer.
PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'document_type' => [
                    'type' => 'string',
                    'enum' => array_keys(config('document_validation.types')),
                ],
                'issue_date' => ['type' => ['string', 'null'], 'format' => 'date'],
                'expiry_date' => ['type' => ['string', 'null'], 'format' => 'date'],
                'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'summary' => ['type' => 'string'],
            ],
            'required' => ['document_type', 'issue_date', 'expiry_date', 'confidence', 'summary'],
            'additionalProperties' => false,
        ];
    }
}
