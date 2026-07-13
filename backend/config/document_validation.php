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

    // 'rules'  — local text extraction + keyword/date heuristics; no data
    //            leaves the server (poppler/pdfparser + MRZ parsing).
    // 'claude' — Anthropic API (vision + classification).
    // 'fake'   — deterministic driver for tests (keyed off the filename).
    'driver' => env('DOCUMENT_VALIDATION_DRIVER', 'rules'),

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
    /*
    |--------------------------------------------------------------------------
    | Rules driver (no AI)
    |--------------------------------------------------------------------------
    |
    | The 'rules' driver extracts text locally (poppler pdftotext with a
    | pure-PHP pdfparser fallback; DOCX via ZipArchive) and classifies the
    | document by scoring anchor phrases. Dates are only trusted when found
    | within `label_window` characters after a known label. Identity documents
    | are detected via their MRZ (machine-readable zone), whose expiry date
    | carries ICAO 9303 check digits — fully deterministic.
    |
    | Scanned/image documents have no text layer and fall back to
    | needs_review in Phase 1 (no OCR).
    |
    */
    'rules' => [
        // Documents whose extracted text is shorter than this are treated as
        // unreadable (scans/images) and routed to human review.
        'min_text_length' => 120,

        // Explicit pdftotext binary path; null = auto-detect common locations.
        'pdftotext_path' => env('PDFTOTEXT_PATH'),

        /*
        | OCR branch (Phase 2): scanned PDFs and images are rasterized with
        | poppler's pdftoppm and read with Tesseract. Disabled automatically
        | when the binaries are missing — those documents then fall back to
        | human review exactly as in Phase 1.
        */
        'ocr' => [
            'enabled' => env('DOCUMENT_OCR_ENABLED', true),
            'tesseract_path' => env('TESSERACT_PATH'),
            'pdftoppm_path' => env('PDFTOPPM_PATH'),
            'languages' => env('DOCUMENT_OCR_LANGUAGES', 'eng'),
            'dpi' => 300,
            'max_pages' => 3,
            // Mean Tesseract word confidence below this → treat the document
            // as unreadable (needs_review). At or above 'high_confidence' the
            // OCR text is trusted like a native text layer.
            'min_mean_confidence' => 60,
            'high_confidence' => 80,
            'timeout_seconds' => 60,
        ],

        'classification' => [
            'min_score' => 8,             // below → 'other' (needs_review)
            'high_confidence_score' => 14, // at/above → high confidence
        ],

        // How far (in characters) after a label a date may appear.
        'label_window' => 80,

        'date_labels' => [
            'expiry' => [
                'date of expiry', 'expiry date', 'expiration date', 'expires on',
                'expires', 'valid until', 'valid till', 'valid to', 'valid through',
                'validity period ends', 'renewal due',
            ],
            'issue' => [
                'date of issue', 'issue date', 'issued on', 'date of issuance',
                'statement date', 'bill date', 'invoice date', 'period ending',
                'as at', 'as of', 'dated this', 'dated',
            ],
        ],

        // Anchor phrases per document type: matched on word boundaries,
        // case-insensitive, each counted once. Negative weights let sibling
        // documents (certificate vs articles) push each other apart.
        'anchors' => [
            'certificate_of_incorporation' => [
                'certificate of incorporation' => 10,
                'certify that' => 6,
                'registrar of companies' => 6,
                'is incorporated' => 5,
                'companies act' => 4,
                'company number' => 4,
                'articles of association' => -10,
                'memorandum of association' => -8,
            ],
            'articles_of_association' => [
                'articles of association' => 10,
                'memorandum of association' => 10,
                'articles of incorporation' => 8,
                'bylaws' => 6,
                'share capital' => 4,
                'objects of the company' => 4,
                'certificate of incorporation' => -10,
            ],
            'proof_of_address' => [
                'billing address' => 6,
                'service address' => 6,
                'amount due' => 5,
                'kwh' => 5,
                'utility' => 5,
                'council tax' => 5,
                'tenancy agreement' => 5,
                'meter reading' => 5,
                'account holder' => 3,
            ],
            'identity_document' => [
                'passport' => 6,
                'national id' => 6,
                'identity card' => 6,
                'driving licence' => 5,
                "driver's license" => 5,
                'date of birth' => 4,
                'place of birth' => 4,
                'nationality' => 3,
            ],
            'bank_statement' => [
                'statement period' => 8,
                'opening balance' => 7,
                'closing balance' => 7,
                'sort code' => 6,
                'iban' => 5,
                'statement date' => 4,
                'account number' => 3,
                'withdrawals' => 3,
                'deposits' => 3,
            ],
            'license' => [
                'authorised and regulated' => 8,
                'authorized and regulated' => 8,
                'licence number' => 7,
                'license number' => 7,
                'financial services' => 5,
                'regulatory authority' => 5,
                'permission to carry on' => 5,
                'licence' => 3,
                'license' => 3,
            ],
            'financial_statements' => [
                'statement of financial position' => 8,
                'balance sheet' => 8,
                'income statement' => 7,
                "auditor's report" => 7,
                'profit and loss' => 6,
                'cash flow statement' => 6,
                'retained earnings' => 4,
            ],
            'register_extract' => [
                'register of members' => 8,
                'register of directors' => 8,
                'share register' => 7,
                'shareholder' => 5,
                'beneficial owner' => 5,
                'shareholding' => 4,
            ],
            'board_resolution' => [
                'board resolution' => 10,
                'resolved that' => 8,
                'it was resolved' => 8,
                'board of directors' => 5,
                'duly convened' => 5,
                'minutes of the meeting' => 4,
                'quorum' => 3,
            ],
            'tax_certificate' => [
                'tax residency certificate' => 10,
                'certificate of tax' => 8,
                'tax registration' => 7,
                'taxpayer identification' => 6,
                'vat registration' => 6,
                'tax authority' => 4,
            ],
            'policy_document' => [
                'anti-money laundering' => 7,
                'aml' => 5,
                'counter-terrorist financing' => 6,
                'policy' => 4,
                'procedure' => 4,
                'compliance officer' => 4,
                'risk assessment' => 3,
            ],
        ],
    ],

    'question_policies' => [
        'Certificate of Incorporation' => ['expected_document' => 'certificate_of_incorporation'],
        'MOA/AOA' => ['expected_document' => 'articles_of_association'],
        // A recent utility bill or bank statement are both acceptable address proof.
        'Proof of Business Address (dated <3 months)' => ['expected_document' => ['proof_of_address', 'bank_statement'], 'max_age_months' => 3],
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
