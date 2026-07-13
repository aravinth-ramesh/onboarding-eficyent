<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Document Validation
    |--------------------------------------------------------------------------
    |
    | When enabled, uploaded KYC documents are analyzed (type classification +
    | issue/expiry date extraction) and checked against the per-question policy
    | in the question's validation_rules:
    |
    |   expected_document : key from the 'types' map below
    |   max_age_months    : reject documents issued longer ago than this
    |   check_expiry      : reject documents whose expiry date has passed
    |
    | Analysis failures never block an upload — the file is accepted and
    | flagged 'needs_review' for the admin.
    |
    */

    'enabled' => env('DOCUMENT_VALIDATION_ENABLED', true),

    // 'claude' calls the Anthropic API; 'fake' is a deterministic driver for
    // local development and tests (behavior keyed off the filename).
    'driver' => env('DOCUMENT_VALIDATION_DRIVER', 'claude'),

    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),

    'model' => env('DOCUMENT_VALIDATION_MODEL', 'claude-opus-4-8'),

    // Files larger than this are accepted without analysis (needs_review).
    'max_analyzable_bytes' => 10 * 1024 * 1024,

    /*
    | Document types the classifier may return. 'description' is shown to the
    | model to sharpen classification; 'label' is shown to users and admins.
    */
    'types' => [
        'certificate_of_incorporation' => [
            'label' => 'Certificate of Incorporation',
            'description' => 'Official certificate issued by a company registrar confirming a company has been incorporated. Not the same as Articles/Memorandum of Association.',
        ],
        'articles_of_association' => [
            'label' => 'Articles / Memorandum of Association',
            'description' => 'Constitutional documents of a company (MOA/AOA, articles of incorporation, bylaws).',
        ],
        'proof_of_address' => [
            'label' => 'Proof of Address',
            'description' => 'Utility bill, bank letter, lease, or government correspondence showing a business or residential address. The issue date is the bill/statement/letter date.',
        ],
        'identity_document' => [
            'label' => 'Identity Document',
            'description' => 'Government-issued passport, national ID card, or driving licence for an individual.',
        ],
        'bank_statement' => [
            'label' => 'Bank Statement',
            'description' => 'Statement of a bank account showing transactions over a period. The issue date is the statement end date.',
        ],
        'license' => [
            'label' => 'License / Permit',
            'description' => 'Financial services license, business permit, or regulatory authorization.',
        ],
        'financial_statements' => [
            'label' => 'Financial Statements',
            'description' => 'Audited or management accounts: balance sheet, income statement, annual report.',
        ],
        'register_extract' => [
            'label' => 'Shareholder / Director Register',
            'description' => 'Company register extract listing shareholders, directors, or beneficial owners.',
        ],
        'board_resolution' => [
            'label' => 'Board Resolution',
            'description' => 'Signed resolution of a company board authorizing an action.',
        ],
        'tax_certificate' => [
            'label' => 'Tax Certificate',
            'description' => 'Tax registration or residency certificate issued by a tax authority.',
        ],
        'policy_document' => [
            'label' => 'Policy / Procedure Document',
            'description' => 'Internal policy or procedure document (AML/CTF policy, monitoring procedures, org charts, audit reports).',
        ],
        'other' => [
            'label' => 'Other Document',
            'description' => 'Anything that does not fit the categories above.',
        ],
    ],

    /*
    | Policies applied to seeded file questions, keyed by question label.
    | Read by OnboardingDataSeeder and the apply_document_policies migration
    | so both stay in sync. Admin-created questions get their policy via the
    | question form instead.
    */
    'question_policies' => [
        'Certificate of Incorporation' => ['expected_document' => 'certificate_of_incorporation'],
        'MOA/AOA' => ['expected_document' => 'articles_of_association'],
        'Proof of Business Address (dated <3 months)' => ['expected_document' => 'proof_of_address', 'max_age_months' => 3],
        'Valid ID and address proof for Directors and UBOs' => ['expected_document' => 'identity_document'],
        'Shareholder & Director Register' => ['expected_document' => 'register_extract'],
        'Board resolution' => ['expected_document' => 'board_resolution'],
        'Tax certificate' => ['expected_document' => 'tax_certificate'],
        '6-month bank statements' => ['expected_document' => 'bank_statement', 'max_age_months' => 7],
        'Bank statements (last 6 months)' => ['expected_document' => 'bank_statement', 'max_age_months' => 7],
        'Company licence' => ['expected_document' => 'license'],
        'Active Financial License(s)' => ['expected_document' => 'license'],
        'Copies of all valid financial licenses' => ['expected_document' => 'license'],
        'Latest audited financial statements' => ['expected_document' => 'financial_statements'],
    ],
];
