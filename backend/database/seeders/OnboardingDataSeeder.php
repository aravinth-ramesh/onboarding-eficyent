<?php

namespace Database\Seeders;

use App\Models\ConditionalRule;
use App\Models\OnboardingStep;
use App\Models\Question;
use App\Models\QuestionGroup;
use App\Models\QuestionTypeMapping;
use App\Models\UserType;
use App\Models\UserTypeSubcategory;
use Illuminate\Database\Seeder;

class OnboardingDataSeeder extends Seeder
{
    private UserType $fi;
    private UserType $corp;
    private UserTypeSubcategory $bank;
    private UserTypeSubcategory $nbfc;
    private UserTypeSubcategory $insurance;

    public function run(): void
    {
        $this->seedUserTypes();
        $this->seedQuestionGroups();
        $this->seedOnboardingSteps();
    }

    private function seedUserTypes(): void
    {
        $this->fi = UserType::create([
            'name' => 'Financial Institution',
            'slug' => 'financial-institution',
            'description' => 'Banks, NBFCs, Insurance companies, and other regulated financial entities.',
            'has_subcategories' => true,
            'order' => 1,
        ]);

        $this->bank = UserTypeSubcategory::create([
            'user_type_id' => $this->fi->id,
            'name' => 'Bank',
            'slug' => 'bank',
            'description' => 'Commercial and retail banking institutions.',
            'order' => 1,
        ]);

        $this->nbfc = UserTypeSubcategory::create([
            'user_type_id' => $this->fi->id,
            'name' => 'NBFC',
            'slug' => 'nbfc',
            'description' => 'Non-Banking Financial Companies.',
            'order' => 2,
        ]);

        $this->insurance = UserTypeSubcategory::create([
            'user_type_id' => $this->fi->id,
            'name' => 'Insurance',
            'slug' => 'insurance',
            'description' => 'Insurance companies and underwriters.',
            'order' => 3,
        ]);

        $this->corp = UserType::create([
            'name' => 'Corporate',
            'slug' => 'corporate',
            'description' => 'Corporate entities, startups, and businesses.',
            'has_subcategories' => false,
            'order' => 2,
        ]);
    }

    private function seedQuestionGroups(): void
    {
        $this->seedGroup1BasicInformation();
        $this->seedGroup2ContactInformation();
        $this->seedGroup3CompanyDetails();
        $this->seedGroup4FinancialInformation();
        $this->seedGroup5BusinessOperations();
        $this->seedGroup6ComplianceRegulatory();
        $this->seedGroup7TechnologyInfrastructure();
        $this->seedGroup8RiskManagement();
        $this->seedGroup9LegalGovernance();
        $this->seedGroup10BankingDetails();
        $this->seedGroup11ProductConfiguration();
        $this->seedGroup12AdditionalInformation();
    }

    // =====================================================================
    // Group 1: Basic Information (15 questions) — ALL types
    // =====================================================================
    private function seedGroup1BasicInformation(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Basic Information',
            'slug' => 'basic-information',
            'description' => 'General information about your organization.',
            'order' => 1,
        ]);

        $questions = [
            ['label' => 'Legal Entity Name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'placeholder' => 'Enter full legal name of the entity'],
            ['label' => 'Trading / Brand Name', 'type' => 'text', 'is_required' => false, 'order' => 2, 'placeholder' => 'Enter brand name if different from legal name'],
            ['label' => 'Date of Incorporation', 'type' => 'date', 'is_required' => true, 'order' => 3],
            ['label' => 'Country of Incorporation', 'type' => 'select', 'is_required' => true, 'order' => 4, 'options' => [
                ['label' => 'India', 'value' => 'IN'], ['label' => 'United States', 'value' => 'US'],
                ['label' => 'United Kingdom', 'value' => 'UK'], ['label' => 'Singapore', 'value' => 'SG'],
                ['label' => 'United Arab Emirates', 'value' => 'AE'], ['label' => 'Hong Kong', 'value' => 'HK'],
                ['label' => 'Australia', 'value' => 'AU'], ['label' => 'Canada', 'value' => 'CA'],
                ['label' => 'Germany', 'value' => 'DE'], ['label' => 'Other', 'value' => 'other'],
            ]],
            ['label' => 'State / Province', 'type' => 'text', 'is_required' => true, 'order' => 5, 'placeholder' => 'e.g., Maharashtra, California'],
            ['label' => 'City', 'type' => 'text', 'is_required' => true, 'order' => 6, 'placeholder' => 'e.g., Mumbai, New York'],
            ['label' => 'Registration / CIN Number', 'type' => 'text', 'is_required' => true, 'order' => 7, 'placeholder' => 'Corporate Identification Number', 'help_text' => 'Enter your company registration or CIN number.'],
            ['label' => 'Tax Identification Number (TIN / PAN)', 'type' => 'text', 'is_required' => true, 'order' => 8, 'placeholder' => 'e.g., ABCDE1234F'],
            ['label' => 'GST Number', 'type' => 'text', 'is_required' => false, 'order' => 9, 'placeholder' => 'e.g., 27AABCU9603R1ZM', 'help_text' => 'Required for Indian entities.'],
            ['label' => 'Entity Type', 'type' => 'select', 'is_required' => true, 'order' => 10, 'options' => [
                ['label' => 'Public Limited Company', 'value' => 'public_limited'],
                ['label' => 'Private Limited Company', 'value' => 'private_limited'],
                ['label' => 'Limited Liability Partnership', 'value' => 'llp'],
                ['label' => 'Partnership Firm', 'value' => 'partnership'],
                ['label' => 'Sole Proprietorship', 'value' => 'sole_proprietorship'],
                ['label' => 'Trust / Society', 'value' => 'trust'],
                ['label' => 'Government Entity', 'value' => 'government'],
            ]],
            ['label' => 'Company Website', 'type' => 'text', 'is_required' => false, 'order' => 11, 'placeholder' => 'https://www.example.com'],
            ['label' => 'Number of Years in Operation', 'type' => 'number', 'is_required' => true, 'order' => 12, 'placeholder' => 'e.g., 5'],
            ['label' => 'Brief Description of Business', 'type' => 'textarea', 'is_required' => true, 'order' => 13, 'placeholder' => 'Describe your core business activities in 2-3 sentences.'],
            ['label' => 'Registered Address', 'type' => 'textarea', 'is_required' => true, 'order' => 14, 'placeholder' => 'Full registered office address including PIN code.'],
            ['label' => 'Company Logo', 'type' => 'file', 'is_required' => false, 'order' => 15, 'help_text' => 'Upload PNG or JPG, max 2MB.'],
        ];

        $this->createQuestionsForAll($group, $questions);
    }

    // =====================================================================
    // Group 2: Contact Information (10 questions) — ALL types
    // =====================================================================
    private function seedGroup2ContactInformation(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Contact Information',
            'slug' => 'contact-information',
            'description' => 'Primary and secondary contact details.',
            'order' => 2,
        ]);

        $questions = [
            ['label' => 'Primary Contact Full Name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'placeholder' => 'First and Last Name'],
            ['label' => 'Primary Contact Designation', 'type' => 'text', 'is_required' => true, 'order' => 2, 'placeholder' => 'e.g., CEO, CFO, Compliance Head'],
            ['label' => 'Primary Contact Email', 'type' => 'text', 'is_required' => true, 'order' => 3, 'placeholder' => 'name@company.com'],
            ['label' => 'Primary Contact Phone', 'type' => 'text', 'is_required' => true, 'order' => 4, 'placeholder' => '+91 98765 43210'],
            ['label' => 'Secondary Contact Full Name', 'type' => 'text', 'is_required' => false, 'order' => 5, 'placeholder' => 'First and Last Name'],
            ['label' => 'Secondary Contact Email', 'type' => 'text', 'is_required' => false, 'order' => 6, 'placeholder' => 'name@company.com'],
            ['label' => 'Secondary Contact Phone', 'type' => 'text', 'is_required' => false, 'order' => 7, 'placeholder' => '+91 98765 43210'],
            ['label' => 'Preferred Mode of Communication', 'type' => 'radio', 'is_required' => true, 'order' => 8, 'options' => [
                ['label' => 'Email', 'value' => 'email'], ['label' => 'Phone', 'value' => 'phone'],
                ['label' => 'WhatsApp', 'value' => 'whatsapp'], ['label' => 'Video Call', 'value' => 'video_call'],
            ]],
            ['label' => 'Preferred Language', 'type' => 'select', 'is_required' => true, 'order' => 9, 'options' => [
                ['label' => 'English', 'value' => 'en'], ['label' => 'Hindi', 'value' => 'hi'],
                ['label' => 'Tamil', 'value' => 'ta'], ['label' => 'Telugu', 'value' => 'te'],
                ['label' => 'Kannada', 'value' => 'kn'], ['label' => 'Other', 'value' => 'other'],
            ]],
            ['label' => 'Timezone', 'type' => 'select', 'is_required' => true, 'order' => 10, 'options' => [
                ['label' => 'IST (UTC+5:30)', 'value' => 'Asia/Kolkata'],
                ['label' => 'EST (UTC-5)', 'value' => 'America/New_York'],
                ['label' => 'PST (UTC-8)', 'value' => 'America/Los_Angeles'],
                ['label' => 'GMT (UTC+0)', 'value' => 'Europe/London'],
                ['label' => 'SGT (UTC+8)', 'value' => 'Asia/Singapore'],
                ['label' => 'GST (UTC+4)', 'value' => 'Asia/Dubai'],
            ]],
        ];

        $this->createQuestionsForAll($group, $questions);
    }

    // =====================================================================
    // Group 3: Company Details (12 questions) — ALL types, some conditional
    // =====================================================================
    private function seedGroup3CompanyDetails(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Company Details',
            'slug' => 'company-details',
            'description' => 'Detailed information about your organization structure.',
            'order' => 3,
        ]);

        $q1 = $this->createQuestion($group, ['label' => 'Industry Sector', 'type' => 'select', 'is_required' => true, 'order' => 1, 'options' => [
            ['label' => 'Banking & Finance', 'value' => 'banking_finance'],
            ['label' => 'Insurance', 'value' => 'insurance'],
            ['label' => 'Technology', 'value' => 'technology'],
            ['label' => 'Manufacturing', 'value' => 'manufacturing'],
            ['label' => 'Healthcare', 'value' => 'healthcare'],
            ['label' => 'Real Estate', 'value' => 'real_estate'],
            ['label' => 'Retail & E-commerce', 'value' => 'retail'],
            ['label' => 'Education', 'value' => 'education'],
            ['label' => 'Other', 'value' => 'other'],
        ]]);
        $this->mapToAll($q1);

        $q2 = $this->createQuestion($group, ['label' => 'Specify Industry Sector', 'type' => 'text', 'is_required' => true, 'order' => 2, 'placeholder' => 'Please specify your industry']);
        $this->mapToAll($q2);
        // Conditional: show only if "Other" selected
        ConditionalRule::create(['question_id' => $q2->id, 'parent_question_id' => $q1->id, 'comparison_type' => 'equals', 'trigger_value' => 'other', 'action' => 'show']);

        $q3 = $this->createQuestion($group, ['label' => 'Number of Employees', 'type' => 'radio', 'is_required' => true, 'order' => 3, 'options' => [
            ['label' => '1 – 10', 'value' => '1-10'], ['label' => '11 – 50', 'value' => '11-50'],
            ['label' => '51 – 200', 'value' => '51-200'], ['label' => '201 – 1000', 'value' => '201-1000'],
            ['label' => '1000+', 'value' => '1000+'],
        ]]);
        $this->mapToAll($q3);

        $q4 = $this->createQuestion($group, ['label' => 'Head Office Address', 'type' => 'textarea', 'is_required' => true, 'order' => 4, 'placeholder' => 'Full head office address if different from registered address.']);
        $this->mapToAll($q4);

        $q5 = $this->createQuestion($group, ['label' => 'Number of Branches / Offices', 'type' => 'number', 'is_required' => true, 'order' => 5, 'placeholder' => 'e.g., 25']);
        $this->mapToAll($q5);

        $q6 = $this->createQuestion($group, ['label' => 'Geographic Presence', 'type' => 'multi_select', 'is_required' => true, 'order' => 6, 'options' => [
            ['label' => 'India', 'value' => 'india'], ['label' => 'North America', 'value' => 'north_america'],
            ['label' => 'Europe', 'value' => 'europe'], ['label' => 'Middle East', 'value' => 'middle_east'],
            ['label' => 'South East Asia', 'value' => 'southeast_asia'], ['label' => 'Africa', 'value' => 'africa'],
            ['label' => 'South America', 'value' => 'south_america'], ['label' => 'Australia / Oceania', 'value' => 'oceania'],
        ]]);
        $this->mapToAll($q6);

        $q7 = $this->createQuestion($group, ['label' => 'Is the company part of a group / conglomerate?', 'type' => 'radio', 'is_required' => true, 'order' => 7, 'options' => [
            ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'],
        ]]);
        $this->mapToAll($q7);

        $q8 = $this->createQuestion($group, ['label' => 'Parent / Holding Company Name', 'type' => 'text', 'is_required' => true, 'order' => 8, 'placeholder' => 'Name of the parent or holding company']);
        $this->mapToAll($q8);
        ConditionalRule::create(['question_id' => $q8->id, 'parent_question_id' => $q7->id, 'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show']);

        $q9 = $this->createQuestion($group, ['label' => 'Subsidiary Companies', 'type' => 'textarea', 'is_required' => false, 'order' => 9, 'placeholder' => 'List subsidiary or associate companies, if any.']);
        $this->mapToAll($q9);
        ConditionalRule::create(['question_id' => $q9->id, 'parent_question_id' => $q7->id, 'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show']);

        $q10 = $this->createQuestion($group, ['label' => 'Key Products / Services Offered', 'type' => 'textarea', 'is_required' => true, 'order' => 10, 'placeholder' => 'Describe key products and services.']);
        $this->mapToAll($q10);

        $q11 = $this->createQuestion($group, ['label' => 'Target Customer Segments', 'type' => 'multi_select', 'is_required' => true, 'order' => 11, 'options' => [
            ['label' => 'Retail / Individual', 'value' => 'retail'], ['label' => 'SME', 'value' => 'sme'],
            ['label' => 'Mid-Market', 'value' => 'mid_market'], ['label' => 'Large Corporate', 'value' => 'large_corporate'],
            ['label' => 'Government / PSU', 'value' => 'government'], ['label' => 'MSME', 'value' => 'msme'],
        ]]);
        $this->mapToAll($q11);

        $q12 = $this->createQuestion($group, ['label' => 'Organization Structure Chart', 'type' => 'file', 'is_required' => false, 'order' => 12, 'help_text' => 'Upload org chart (PDF/PNG/JPG, max 5MB).']);
        $this->mapToAll($q12);
    }

    // =====================================================================
    // Group 4: Financial Information (13 questions) — ALL types
    // =====================================================================
    private function seedGroup4FinancialInformation(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Financial Information',
            'slug' => 'financial-information',
            'description' => 'Financial health and performance details.',
            'order' => 4,
        ]);

        $questions = [
            ['label' => 'Financial Year End Month', 'type' => 'select', 'is_required' => true, 'order' => 1, 'options' => [
                ['label' => 'March', 'value' => '03'], ['label' => 'June', 'value' => '06'],
                ['label' => 'September', 'value' => '09'], ['label' => 'December', 'value' => '12'],
            ]],
            ['label' => 'Currency of Reporting', 'type' => 'select', 'is_required' => true, 'order' => 2, 'options' => [
                ['label' => 'INR - Indian Rupee', 'value' => 'INR'], ['label' => 'USD - US Dollar', 'value' => 'USD'],
                ['label' => 'EUR - Euro', 'value' => 'EUR'], ['label' => 'GBP - British Pound', 'value' => 'GBP'],
                ['label' => 'SGD - Singapore Dollar', 'value' => 'SGD'], ['label' => 'AED - UAE Dirham', 'value' => 'AED'],
            ]],
            ['label' => 'Annual Revenue (Last FY)', 'type' => 'number', 'is_required' => true, 'order' => 3, 'placeholder' => 'In reporting currency'],
            ['label' => 'Net Profit / Loss (Last FY)', 'type' => 'number', 'is_required' => true, 'order' => 4, 'placeholder' => 'Enter negative for loss'],
            ['label' => 'Total Assets', 'type' => 'number', 'is_required' => true, 'order' => 5, 'placeholder' => 'As per last audited balance sheet'],
            ['label' => 'Net Worth', 'type' => 'number', 'is_required' => true, 'order' => 6, 'placeholder' => 'Total equity / net worth'],
            ['label' => 'Authorized Capital', 'type' => 'number', 'is_required' => false, 'order' => 7, 'placeholder' => 'As per Memorandum of Association'],
            ['label' => 'Paid-up Capital', 'type' => 'number', 'is_required' => true, 'order' => 8, 'placeholder' => 'Actual capital paid by shareholders'],
            ['label' => 'Name of Statutory Auditor', 'type' => 'text', 'is_required' => true, 'order' => 9, 'placeholder' => 'e.g., Deloitte, PwC, local firm name'],
            ['label' => 'Last Audited Financial Year', 'type' => 'text', 'is_required' => true, 'order' => 10, 'placeholder' => 'e.g., 2024-25'],
            ['label' => 'Source of Funds', 'type' => 'multi_select', 'is_required' => true, 'order' => 11, 'options' => [
                ['label' => 'Equity Capital', 'value' => 'equity'], ['label' => 'Debt / Loans', 'value' => 'debt'],
                ['label' => 'Venture Capital / PE', 'value' => 'vc_pe'], ['label' => 'Retained Earnings', 'value' => 'retained_earnings'],
                ['label' => 'Government Grants', 'value' => 'grants'], ['label' => 'Public Deposits', 'value' => 'public_deposits'],
            ]],
            ['label' => 'Credit Rating (if any)', 'type' => 'text', 'is_required' => false, 'order' => 12, 'placeholder' => 'e.g., CRISIL AA+, ICRA A1+', 'help_text' => 'Provide rating agency name and grade.'],
            ['label' => 'Audited Financial Statements', 'type' => 'file', 'is_required' => true, 'order' => 13, 'help_text' => 'Upload last 2 years audited financials (PDF, max 10MB).'],
        ];

        $this->createQuestionsForAll($group, $questions);
    }

    // =====================================================================
    // Group 5: Business Operations (11 questions) — ALL types
    // =====================================================================
    private function seedGroup5BusinessOperations(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Business Operations',
            'slug' => 'business-operations',
            'description' => 'Operational details and business model information.',
            'order' => 5,
        ]);

        $questions = [
            ['label' => 'Primary Business Activity', 'type' => 'textarea', 'is_required' => true, 'order' => 1, 'placeholder' => 'Describe primary business activity in detail.'],
            ['label' => 'Operational Since', 'type' => 'date', 'is_required' => true, 'order' => 2, 'help_text' => 'When did you start commercial operations?'],
            ['label' => 'Business Model', 'type' => 'select', 'is_required' => true, 'order' => 3, 'options' => [
                ['label' => 'B2B (Business to Business)', 'value' => 'b2b'],
                ['label' => 'B2C (Business to Consumer)', 'value' => 'b2c'],
                ['label' => 'B2B2C', 'value' => 'b2b2c'],
                ['label' => 'B2G (Business to Government)', 'value' => 'b2g'],
                ['label' => 'Marketplace / Platform', 'value' => 'marketplace'],
            ]],
            ['label' => 'Monthly Active Users / Clients', 'type' => 'number', 'is_required' => false, 'order' => 4, 'placeholder' => 'Approximate number'],
            ['label' => 'Expected Monthly Transaction Volume', 'type' => 'number', 'is_required' => true, 'order' => 5, 'placeholder' => 'Number of transactions per month'],
            ['label' => 'Expected Monthly Transaction Value', 'type' => 'number', 'is_required' => true, 'order' => 6, 'placeholder' => 'Total value in reporting currency'],
            ['label' => 'Peak Transaction Period', 'type' => 'select', 'is_required' => false, 'order' => 7, 'options' => [
                ['label' => 'Quarter End (Mar, Jun, Sep, Dec)', 'value' => 'quarter_end'],
                ['label' => 'Month End', 'value' => 'month_end'],
                ['label' => 'Festival / Holiday Seasons', 'value' => 'festivals'],
                ['label' => 'No Specific Peak', 'value' => 'no_peak'],
            ]],
            ['label' => 'Countries of Operation', 'type' => 'multi_select', 'is_required' => true, 'order' => 8, 'options' => [
                ['label' => 'India', 'value' => 'IN'], ['label' => 'United States', 'value' => 'US'],
                ['label' => 'United Kingdom', 'value' => 'UK'], ['label' => 'Singapore', 'value' => 'SG'],
                ['label' => 'UAE', 'value' => 'AE'], ['label' => 'Other', 'value' => 'other'],
            ]],
            ['label' => 'Do you deal with cross-border transactions?', 'type' => 'radio', 'is_required' => true, 'order' => 9, 'options' => [
                ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'],
            ]],
            ['label' => 'Key Partnerships / Alliances', 'type' => 'textarea', 'is_required' => false, 'order' => 10, 'placeholder' => 'List key business partnerships or strategic alliances.'],
            ['label' => 'Operational License / Certificate', 'type' => 'file', 'is_required' => false, 'order' => 11, 'help_text' => 'Upload relevant business or operational license.'],
        ];

        $this->createQuestionsForAll($group, $questions);
    }

    // =====================================================================
    // Group 6: Compliance & Regulatory (14 questions) — FI-focused, with conditionals
    // =====================================================================
    private function seedGroup6ComplianceRegulatory(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Compliance & Regulatory',
            'slug' => 'compliance-regulatory',
            'description' => 'Regulatory status, compliance certifications, and policies.',
            'order' => 6,
        ]);

        // Q1: Regulated? — ALL types
        $regulated = $this->createQuestion($group, ['label' => 'Is your entity regulated by a financial authority?', 'type' => 'radio', 'is_required' => true, 'order' => 1, 'options' => [
            ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'],
        ]]);
        $this->mapToAll($regulated);

        // Q2-Q4: Conditional on regulated=yes — ALL types
        $regName = $this->createQuestion($group, ['label' => 'Name of Regulatory Authority', 'type' => 'text', 'is_required' => true, 'order' => 2, 'placeholder' => 'e.g., RBI, SEBI, IRDAI, SEC, FCA']);
        $this->mapToAll($regName);
        ConditionalRule::create(['question_id' => $regName->id, 'parent_question_id' => $regulated->id, 'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show']);

        $licNum = $this->createQuestion($group, ['label' => 'Regulatory License Number', 'type' => 'text', 'is_required' => true, 'order' => 3, 'placeholder' => 'License / Registration number']);
        $this->mapToAll($licNum);
        ConditionalRule::create(['question_id' => $licNum->id, 'parent_question_id' => $regulated->id, 'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show']);

        $licExpiry = $this->createQuestion($group, ['label' => 'License Expiry Date', 'type' => 'date', 'is_required' => false, 'order' => 4, 'help_text' => 'Leave blank if perpetual license.']);
        $this->mapToAll($licExpiry);
        ConditionalRule::create(['question_id' => $licExpiry->id, 'parent_question_id' => $regulated->id, 'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show']);

        // Q5-Q6: AML/KYC — ALL types
        $aml = $this->createQuestion($group, ['label' => 'Do you have an AML / KYC policy in place?', 'type' => 'radio', 'is_required' => true, 'order' => 5, 'options' => [
            ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'], ['label' => 'In Progress', 'value' => 'in_progress'],
        ]]);
        $this->mapToAll($aml);

        $amlDoc = $this->createQuestion($group, ['label' => 'Upload AML / KYC Policy Document', 'type' => 'file', 'is_required' => false, 'order' => 6, 'help_text' => 'PDF, max 5MB.']);
        $this->mapToAll($amlDoc);
        ConditionalRule::create(['question_id' => $amlDoc->id, 'parent_question_id' => $aml->id, 'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show']);

        // Q7: Data protection — ALL types
        $this->createQuestionsForAll($group, [
            ['label' => 'Data Protection Compliance', 'type' => 'multi_select', 'is_required' => true, 'order' => 7, 'options' => [
                ['label' => 'DPDP Act (India)', 'value' => 'dpdp'], ['label' => 'GDPR (EU)', 'value' => 'gdpr'],
                ['label' => 'CCPA (California)', 'value' => 'ccpa'], ['label' => 'PDPA (Singapore)', 'value' => 'pdpa'],
                ['label' => 'Not Applicable', 'value' => 'na'],
            ]],
        ]);

        // Q8-Q10: Compliance officer — ALL types
        $this->createQuestionsForAll($group, [
            ['label' => 'Compliance Officer Name', 'type' => 'text', 'is_required' => true, 'order' => 8, 'placeholder' => 'Full name of compliance officer'],
            ['label' => 'Compliance Officer Email', 'type' => 'text', 'is_required' => true, 'order' => 9, 'placeholder' => 'compliance@company.com'],
            ['label' => 'Last Compliance Audit Date', 'type' => 'date', 'is_required' => false, 'order' => 10],
        ]);

        // Q11: Sanctions screening — FI only
        $sanctions = $this->createQuestion($group, ['label' => 'Do you perform sanctions screening?', 'type' => 'radio', 'is_required' => true, 'order' => 11, 'options' => [
            ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'],
        ]]);
        $this->mapToType($sanctions, $this->fi->id);

        // Q12: PEP screening — FI only
        $pep = $this->createQuestion($group, ['label' => 'Do you perform PEP (Politically Exposed Persons) screening?', 'type' => 'radio', 'is_required' => true, 'order' => 12, 'options' => [
            ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'],
        ]]);
        $this->mapToType($pep, $this->fi->id);

        // Q13: Compliance certifications — FI only
        $certs = $this->createQuestion($group, ['label' => 'Compliance Certifications Held', 'type' => 'multi_select', 'is_required' => false, 'order' => 13, 'options' => [
            ['label' => 'ISO 27001', 'value' => 'iso27001'], ['label' => 'SOC 2 Type II', 'value' => 'soc2'],
            ['label' => 'PCI DSS', 'value' => 'pcidss'], ['label' => 'ISO 22301', 'value' => 'iso22301'],
            ['label' => 'None', 'value' => 'none'],
        ]]);
        $this->mapToType($certs, $this->fi->id);

        // Q14: Compliance audit report — ALL types
        $this->createQuestionsForAll($group, [
            ['label' => 'Compliance Audit Report', 'type' => 'file', 'is_required' => false, 'order' => 14, 'help_text' => 'Upload latest compliance audit report (PDF).'],
        ]);
    }

    // =====================================================================
    // Group 7: Technology & Infrastructure (12 questions) — ALL types
    // =====================================================================
    private function seedGroup7TechnologyInfrastructure(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Technology & Infrastructure',
            'slug' => 'technology-infrastructure',
            'description' => 'IT infrastructure, integration, and security details.',
            'order' => 7,
        ]);

        $q1 = $this->createQuestion($group, ['label' => 'Primary Technology Platform / Core System', 'type' => 'text', 'is_required' => true, 'order' => 1, 'placeholder' => 'e.g., SAP, Oracle, Salesforce, Custom built']);
        $this->mapToAll($q1);

        $q2 = $this->createQuestion($group, ['label' => 'Cloud Provider', 'type' => 'select', 'is_required' => true, 'order' => 2, 'options' => [
            ['label' => 'AWS', 'value' => 'aws'], ['label' => 'Microsoft Azure', 'value' => 'azure'],
            ['label' => 'Google Cloud', 'value' => 'gcp'], ['label' => 'On-Premise', 'value' => 'on_premise'],
            ['label' => 'Hybrid', 'value' => 'hybrid'], ['label' => 'Other', 'value' => 'other'],
        ]]);
        $this->mapToAll($q2);

        $q3 = $this->createQuestion($group, ['label' => 'Do you require API integration?', 'type' => 'radio', 'is_required' => true, 'order' => 3, 'options' => [
            ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'], ['label' => 'Maybe Later', 'value' => 'later'],
        ]]);
        $this->mapToAll($q3);

        $q4 = $this->createQuestion($group, ['label' => 'Preferred Integration Method', 'type' => 'select', 'is_required' => true, 'order' => 4, 'options' => [
            ['label' => 'REST API', 'value' => 'rest'], ['label' => 'SOAP/XML', 'value' => 'soap'],
            ['label' => 'SFTP', 'value' => 'sftp'], ['label' => 'Webhooks', 'value' => 'webhooks'],
            ['label' => 'SDK', 'value' => 'sdk'],
        ]]);
        $this->mapToAll($q4);
        ConditionalRule::create(['question_id' => $q4->id, 'parent_question_id' => $q3->id, 'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show']);

        $q5 = $this->createQuestion($group, ['label' => 'Data Hosting Preference', 'type' => 'radio', 'is_required' => true, 'order' => 5, 'options' => [
            ['label' => 'India Only', 'value' => 'india'], ['label' => 'Any Region', 'value' => 'any'],
            ['label' => 'Specific Region', 'value' => 'specific'],
        ]]);
        $this->mapToAll($q5);

        $this->createQuestionsForAll($group, [
            ['label' => 'Describe Current IT Infrastructure', 'type' => 'textarea', 'is_required' => false, 'order' => 6, 'placeholder' => 'Brief overview of your IT setup and architecture.'],
            ['label' => 'Cybersecurity Certifications', 'type' => 'multi_select', 'is_required' => false, 'order' => 7, 'options' => [
                ['label' => 'ISO 27001', 'value' => 'iso27001'], ['label' => 'SOC 2', 'value' => 'soc2'],
                ['label' => 'PCI DSS', 'value' => 'pcidss'], ['label' => 'CERT-In Empanelled', 'value' => 'certin'],
                ['label' => 'None', 'value' => 'none'],
            ]],
            ['label' => 'Data Encryption Standard', 'type' => 'select', 'is_required' => true, 'order' => 8, 'options' => [
                ['label' => 'AES-256', 'value' => 'aes256'], ['label' => 'AES-128', 'value' => 'aes128'],
                ['label' => 'RSA', 'value' => 'rsa'], ['label' => 'TLS 1.3', 'value' => 'tls13'],
                ['label' => 'Not Sure', 'value' => 'not_sure'],
            ]],
            ['label' => 'Do you have a Disaster Recovery Plan?', 'type' => 'radio', 'is_required' => true, 'order' => 9, 'options' => [
                ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'], ['label' => 'In Progress', 'value' => 'in_progress'],
            ]],
            ['label' => 'System Uptime Requirement', 'type' => 'select', 'is_required' => true, 'order' => 10, 'options' => [
                ['label' => '99.99% (Four Nines)', 'value' => '99.99'], ['label' => '99.9% (Three Nines)', 'value' => '99.9'],
                ['label' => '99.5%', 'value' => '99.5'], ['label' => '99%', 'value' => '99'],
            ]],
            ['label' => 'Estimated Data Volume (GB/month)', 'type' => 'number', 'is_required' => false, 'order' => 11, 'placeholder' => 'Approximate monthly data volume'],
            ['label' => 'Technical Architecture Document', 'type' => 'file', 'is_required' => false, 'order' => 12, 'help_text' => 'Upload your system architecture diagram (PDF/PNG).'],
        ]);
    }

    // =====================================================================
    // Group 8: Risk Management (10 questions) — FI only
    // =====================================================================
    private function seedGroup8RiskManagement(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Risk Management',
            'slug' => 'risk-management',
            'description' => 'Risk assessment framework and insurance coverage.',
            'order' => 8,
        ]);

        $questions = [
            ['label' => 'Do you have a formal Risk Management Framework?', 'type' => 'radio', 'is_required' => true, 'order' => 1, 'options' => [
                ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'], ['label' => 'In Development', 'value' => 'in_dev'],
            ]],
            ['label' => 'Chief Risk Officer Name', 'type' => 'text', 'is_required' => true, 'order' => 2, 'placeholder' => 'Full name of CRO / Head of Risk'],
            ['label' => 'Chief Risk Officer Email', 'type' => 'text', 'is_required' => true, 'order' => 3, 'placeholder' => 'risk@company.com'],
            ['label' => 'Key Risk Categories Managed', 'type' => 'multi_select', 'is_required' => true, 'order' => 4, 'options' => [
                ['label' => 'Credit Risk', 'value' => 'credit'], ['label' => 'Market Risk', 'value' => 'market'],
                ['label' => 'Operational Risk', 'value' => 'operational'], ['label' => 'Liquidity Risk', 'value' => 'liquidity'],
                ['label' => 'Cyber Risk', 'value' => 'cyber'], ['label' => 'Compliance Risk', 'value' => 'compliance'],
                ['label' => 'Reputational Risk', 'value' => 'reputational'],
            ]],
            ['label' => 'Risk Appetite Statement', 'type' => 'textarea', 'is_required' => false, 'order' => 5, 'placeholder' => 'Describe your risk appetite and tolerance levels.'],
            ['label' => 'Insurance Coverage Types', 'type' => 'multi_select', 'is_required' => true, 'order' => 6, 'options' => [
                ['label' => 'Directors & Officers (D&O)', 'value' => 'dno'], ['label' => 'Professional Indemnity', 'value' => 'pi'],
                ['label' => 'Cyber Liability', 'value' => 'cyber'], ['label' => 'Errors & Omissions (E&O)', 'value' => 'eo'],
                ['label' => 'General Liability', 'value' => 'gl'], ['label' => 'None', 'value' => 'none'],
            ]],
            ['label' => 'Total Insurance Coverage Amount', 'type' => 'number', 'is_required' => false, 'order' => 7, 'placeholder' => 'In reporting currency'],
            ['label' => 'Risk Assessment Frequency', 'type' => 'select', 'is_required' => true, 'order' => 8, 'options' => [
                ['label' => 'Monthly', 'value' => 'monthly'], ['label' => 'Quarterly', 'value' => 'quarterly'],
                ['label' => 'Semi-Annual', 'value' => 'semi_annual'], ['label' => 'Annual', 'value' => 'annual'],
            ]],
            ['label' => 'Last Risk Assessment Date', 'type' => 'date', 'is_required' => false, 'order' => 9],
            ['label' => 'Risk Assessment Report', 'type' => 'file', 'is_required' => false, 'order' => 10, 'help_text' => 'Upload latest risk assessment report (PDF).'],
        ];

        foreach ($questions as $data) {
            $q = $this->createQuestion($group, $data);
            $this->mapToType($q, $this->fi->id);
        }
    }

    // =====================================================================
    // Group 9: Legal & Governance (13 questions) — ALL types, some conditional
    // =====================================================================
    private function seedGroup9LegalGovernance(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Legal & Governance',
            'slug' => 'legal-governance',
            'description' => 'Corporate governance, directors, and legal matters.',
            'order' => 9,
        ]);

        $this->createQuestionsForAll($group, [
            ['label' => 'Number of Board Members', 'type' => 'number', 'is_required' => true, 'order' => 1, 'placeholder' => 'Total board of directors'],
            ['label' => 'Number of Independent Directors', 'type' => 'number', 'is_required' => false, 'order' => 2],
            ['label' => 'Board Composition Details', 'type' => 'textarea', 'is_required' => true, 'order' => 3, 'placeholder' => 'List names and designations of board members.'],
            ['label' => 'Key Shareholders (>5% holding)', 'type' => 'textarea', 'is_required' => true, 'order' => 4, 'placeholder' => 'Name, nationality, and % holding for each shareholder.'],
            ['label' => 'Ultimate Beneficial Owner (UBO)', 'type' => 'textarea', 'is_required' => true, 'order' => 5, 'placeholder' => 'Name and details of individuals with >25% beneficial ownership.', 'help_text' => 'As per AML/KYC regulations, provide UBO details.'],
            ['label' => 'Legal Counsel / Law Firm Name', 'type' => 'text', 'is_required' => false, 'order' => 6, 'placeholder' => 'Name of legal counsel or law firm'],
            ['label' => 'Legal Counsel Contact Email', 'type' => 'text', 'is_required' => false, 'order' => 7, 'placeholder' => 'legal@lawfirm.com'],
        ]);

        $pending = $this->createQuestion($group, ['label' => 'Any pending litigation or regulatory action?', 'type' => 'radio', 'is_required' => true, 'order' => 8, 'options' => [
            ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'],
        ]]);
        $this->mapToAll($pending);

        $litDetails = $this->createQuestion($group, ['label' => 'Litigation / Regulatory Action Details', 'type' => 'textarea', 'is_required' => true, 'order' => 9, 'placeholder' => 'Provide details of pending litigation or regulatory proceedings.']);
        $this->mapToAll($litDetails);
        ConditionalRule::create(['question_id' => $litDetails->id, 'parent_question_id' => $pending->id, 'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show']);

        $this->createQuestionsForAll($group, [
            ['label' => 'Authorized Signatory Name', 'type' => 'text', 'is_required' => true, 'order' => 10, 'placeholder' => 'Person authorized to sign on behalf of entity'],
            ['label' => 'Authorized Signatory Designation', 'type' => 'text', 'is_required' => true, 'order' => 11, 'placeholder' => 'e.g., Director, CEO, Company Secretary'],
            ['label' => 'Certificate of Incorporation', 'type' => 'file', 'is_required' => true, 'order' => 12, 'help_text' => 'Upload certified copy (PDF).'],
            ['label' => 'Memorandum & Articles of Association', 'type' => 'file', 'is_required' => false, 'order' => 13, 'help_text' => 'Upload MOA and AOA (PDF).'],
        ]);
    }

    // =====================================================================
    // Group 10: Banking Details (11 questions) — ALL types
    // =====================================================================
    private function seedGroup10BankingDetails(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Banking & Settlement Details',
            'slug' => 'banking-settlement',
            'description' => 'Bank account and settlement preferences.',
            'order' => 10,
        ]);

        $questions = [
            ['label' => 'Primary Bank Name', 'type' => 'text', 'is_required' => true, 'order' => 1, 'placeholder' => 'e.g., HDFC Bank, SBI, Citibank'],
            ['label' => 'Bank Account Number', 'type' => 'text', 'is_required' => true, 'order' => 2, 'placeholder' => 'Enter bank account number'],
            ['label' => 'Bank Branch', 'type' => 'text', 'is_required' => true, 'order' => 3, 'placeholder' => 'Branch name and city'],
            ['label' => 'IFSC Code', 'type' => 'text', 'is_required' => true, 'order' => 4, 'placeholder' => 'e.g., HDFC0001234'],
            ['label' => 'SWIFT / BIC Code', 'type' => 'text', 'is_required' => false, 'order' => 5, 'placeholder' => 'Required for international transactions', 'help_text' => 'Needed for cross-border settlements.'],
            ['label' => 'Account Type', 'type' => 'select', 'is_required' => true, 'order' => 6, 'options' => [
                ['label' => 'Current Account', 'value' => 'current'], ['label' => 'Savings Account', 'value' => 'savings'],
                ['label' => 'Escrow Account', 'value' => 'escrow'], ['label' => 'Nodal Account', 'value' => 'nodal'],
            ]],
            ['label' => 'Account Currency', 'type' => 'select', 'is_required' => true, 'order' => 7, 'options' => [
                ['label' => 'INR', 'value' => 'INR'], ['label' => 'USD', 'value' => 'USD'],
                ['label' => 'EUR', 'value' => 'EUR'], ['label' => 'GBP', 'value' => 'GBP'],
            ]],
            ['label' => 'Average Monthly Balance', 'type' => 'number', 'is_required' => false, 'order' => 8, 'placeholder' => 'Approximate monthly balance'],
            ['label' => 'Settlement Frequency Preference', 'type' => 'radio', 'is_required' => true, 'order' => 9, 'options' => [
                ['label' => 'T+0 (Same Day)', 'value' => 't0'], ['label' => 'T+1 (Next Day)', 'value' => 't1'],
                ['label' => 'T+2', 'value' => 't2'], ['label' => 'Weekly', 'value' => 'weekly'],
            ]],
            ['label' => 'Do you need multi-currency settlement?', 'type' => 'radio', 'is_required' => true, 'order' => 10, 'options' => [
                ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'],
            ]],
            ['label' => 'Cancelled Cheque / Bank Verification Letter', 'type' => 'file', 'is_required' => true, 'order' => 11, 'help_text' => 'Upload cancelled cheque or bank letter on letterhead.'],
        ];

        $this->createQuestionsForAll($group, $questions);
    }

    // =====================================================================
    // Group 11: Product Configuration (12 questions) — FI only, subcategory-specific
    // =====================================================================
    private function seedGroup11ProductConfiguration(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Product Configuration',
            'slug' => 'product-configuration',
            'description' => 'Product-specific configuration for financial institutions.',
            'order' => 11,
        ]);

        // Q1: Products offered — FI all subcategories
        $products = $this->createQuestion($group, ['label' => 'Products / Services Offered', 'type' => 'multi_select', 'is_required' => true, 'order' => 1, 'options' => [
            ['label' => 'Lending / Loans', 'value' => 'lending'], ['label' => 'Payments / Transfers', 'value' => 'payments'],
            ['label' => 'Deposits', 'value' => 'deposits'], ['label' => 'Insurance', 'value' => 'insurance'],
            ['label' => 'Investments / Wealth', 'value' => 'investments'], ['label' => 'Cards (Credit/Debit)', 'value' => 'cards'],
            ['label' => 'Trade Finance', 'value' => 'trade_finance'], ['label' => 'Treasury', 'value' => 'treasury'],
        ]]);
        $this->mapToType($products, $this->fi->id);

        // Q2-Q3: Lending — conditional on products containing lending
        $lendingTypes = $this->createQuestion($group, ['label' => 'Types of Lending Products', 'type' => 'multi_select', 'is_required' => true, 'order' => 2, 'options' => [
            ['label' => 'Personal Loans', 'value' => 'personal'], ['label' => 'Business Loans', 'value' => 'business'],
            ['label' => 'Home Loans', 'value' => 'home'], ['label' => 'Vehicle Loans', 'value' => 'vehicle'],
            ['label' => 'Gold Loans', 'value' => 'gold'], ['label' => 'Microfinance', 'value' => 'microfinance'],
        ]]);
        $this->mapToType($lendingTypes, $this->fi->id);
        ConditionalRule::create(['question_id' => $lendingTypes->id, 'parent_question_id' => $products->id, 'comparison_type' => 'contains', 'trigger_value' => 'lending', 'action' => 'show']);

        $avgTicket = $this->createQuestion($group, ['label' => 'Average Loan Ticket Size', 'type' => 'number', 'is_required' => false, 'order' => 3, 'placeholder' => 'In reporting currency']);
        $this->mapToType($avgTicket, $this->fi->id);
        ConditionalRule::create(['question_id' => $avgTicket->id, 'parent_question_id' => $products->id, 'comparison_type' => 'contains', 'trigger_value' => 'lending', 'action' => 'show']);

        // Q4-Q5: Insurance — conditional on products containing insurance, and Insurance subcategory
        $insTypes = $this->createQuestion($group, ['label' => 'Types of Insurance Products', 'type' => 'multi_select', 'is_required' => true, 'order' => 4, 'options' => [
            ['label' => 'Life Insurance', 'value' => 'life'], ['label' => 'Health Insurance', 'value' => 'health'],
            ['label' => 'Motor Insurance', 'value' => 'motor'], ['label' => 'Property Insurance', 'value' => 'property'],
            ['label' => 'Liability Insurance', 'value' => 'liability'], ['label' => 'Marine Insurance', 'value' => 'marine'],
        ]]);
        $this->mapToSubcategory($insTypes, $this->fi->id, $this->insurance->id);
        ConditionalRule::create(['question_id' => $insTypes->id, 'parent_question_id' => $products->id, 'comparison_type' => 'contains', 'trigger_value' => 'insurance', 'action' => 'show']);

        $gwp = $this->createQuestion($group, ['label' => 'Gross Written Premium (Last FY)', 'type' => 'number', 'is_required' => false, 'order' => 5, 'placeholder' => 'Total GWP in reporting currency']);
        $this->mapToSubcategory($gwp, $this->fi->id, $this->insurance->id);

        // Q6: Payment methods — FI all
        $this->createAndMapToType($group, ['label' => 'Payment Methods Supported', 'type' => 'multi_select', 'is_required' => true, 'order' => 6, 'options' => [
            ['label' => 'UPI', 'value' => 'upi'], ['label' => 'NEFT / RTGS', 'value' => 'neft_rtgs'],
            ['label' => 'IMPS', 'value' => 'imps'], ['label' => 'Credit / Debit Card', 'value' => 'cards'],
            ['label' => 'Net Banking', 'value' => 'netbanking'], ['label' => 'SWIFT', 'value' => 'swift'],
            ['label' => 'Cheque / DD', 'value' => 'cheque'],
        ]], $this->fi->id);

        // Q7-Q8: Transaction limits — FI all
        $this->createAndMapToType($group, ['label' => 'Per Transaction Limit', 'type' => 'number', 'is_required' => true, 'order' => 7, 'placeholder' => 'Maximum per-transaction amount'], $this->fi->id);
        $this->createAndMapToType($group, ['label' => 'Daily Transaction Limit', 'type' => 'number', 'is_required' => true, 'order' => 8, 'placeholder' => 'Maximum daily transaction amount'], $this->fi->id);

        // Q9: KYC method — FI all
        $this->createAndMapToType($group, ['label' => 'Customer KYC Verification Method', 'type' => 'select', 'is_required' => true, 'order' => 9, 'options' => [
            ['label' => 'Video KYC', 'value' => 'video_kyc'], ['label' => 'eKYC (Aadhaar)', 'value' => 'ekyc'],
            ['label' => 'Physical KYC', 'value' => 'physical'], ['label' => 'CKYC', 'value' => 'ckyc'],
            ['label' => 'Digi Locker', 'value' => 'digilocker'],
        ]], $this->fi->id);

        // Q10-Q11: Bank-specific questions
        $npa = $this->createQuestion($group, ['label' => 'Net NPA Ratio (%)', 'type' => 'number', 'is_required' => true, 'order' => 10, 'placeholder' => 'e.g., 1.5', 'help_text' => 'As per last quarterly filing.']);
        $this->mapToSubcategory($npa, $this->fi->id, $this->bank->id);

        $car = $this->createQuestion($group, ['label' => 'Capital Adequacy Ratio (CAR) %', 'type' => 'number', 'is_required' => true, 'order' => 11, 'placeholder' => 'e.g., 15.5']);
        $this->mapToSubcategory($car, $this->fi->id, $this->bank->id);

        // Q12: Product documentation — FI all
        $this->createAndMapToType($group, ['label' => 'Product Documentation / Brochure', 'type' => 'file', 'is_required' => false, 'order' => 12, 'help_text' => 'Upload product details document (PDF).'], $this->fi->id);
    }

    // =====================================================================
    // Group 12: Additional Information (10 questions) — ALL types, some conditional
    // =====================================================================
    private function seedGroup12AdditionalInformation(): void
    {
        $group = QuestionGroup::create([
            'name' => 'Additional Information',
            'slug' => 'additional-information',
            'description' => 'Partnership preferences and final remarks.',
            'order' => 12,
        ]);

        $this->createQuestionsForAll($group, [
            ['label' => 'How did you hear about Eficyent?', 'type' => 'select', 'is_required' => true, 'order' => 1, 'options' => [
                ['label' => 'Website', 'value' => 'website'], ['label' => 'Referral', 'value' => 'referral'],
                ['label' => 'LinkedIn', 'value' => 'linkedin'], ['label' => 'Conference / Event', 'value' => 'event'],
                ['label' => 'Industry Publication', 'value' => 'publication'], ['label' => 'Other', 'value' => 'other'],
            ]],
        ]);

        $refSource = Question::where('label', 'How did you hear about Eficyent?')->first();

        $refCode = $this->createQuestion($group, ['label' => 'Referral Code / Referrer Name', 'type' => 'text', 'is_required' => false, 'order' => 2, 'placeholder' => 'Enter referral code or name of referrer']);
        $this->mapToAll($refCode);
        ConditionalRule::create(['question_id' => $refCode->id, 'parent_question_id' => $refSource->id, 'comparison_type' => 'equals', 'trigger_value' => 'referral', 'action' => 'show']);

        $this->createQuestionsForAll($group, [
            ['label' => 'Expected Go-Live Date', 'type' => 'date', 'is_required' => true, 'order' => 3, 'help_text' => 'When do you expect to start operations with us?'],
            ['label' => 'Partnership Type', 'type' => 'select', 'is_required' => true, 'order' => 4, 'options' => [
                ['label' => 'Direct Integration', 'value' => 'direct'], ['label' => 'White Label', 'value' => 'white_label'],
                ['label' => 'Reseller', 'value' => 'reseller'], ['label' => 'Consulting', 'value' => 'consulting'],
            ]],
            ['label' => 'Implementation Timeline', 'type' => 'select', 'is_required' => true, 'order' => 5, 'options' => [
                ['label' => 'Immediate (< 1 month)', 'value' => 'immediate'], ['label' => '1 – 3 months', 'value' => '1-3_months'],
                ['label' => '3 – 6 months', 'value' => '3-6_months'], ['label' => '6+ months', 'value' => '6+_months'],
            ]],
        ]);

        $training = $this->createQuestion($group, ['label' => 'Do you require training?', 'type' => 'radio', 'is_required' => true, 'order' => 6, 'options' => [
            ['label' => 'Yes', 'value' => 'yes'], ['label' => 'No', 'value' => 'no'],
        ]]);
        $this->mapToAll($training);

        $trainingUsers = $this->createQuestion($group, ['label' => 'Number of Users to be Trained', 'type' => 'number', 'is_required' => true, 'order' => 7, 'placeholder' => 'e.g., 10']);
        $this->mapToAll($trainingUsers);
        ConditionalRule::create(['question_id' => $trainingUsers->id, 'parent_question_id' => $training->id, 'comparison_type' => 'equals', 'trigger_value' => 'yes', 'action' => 'show']);

        $this->createQuestionsForAll($group, [
            ['label' => 'Special Requirements or Customizations', 'type' => 'textarea', 'is_required' => false, 'order' => 8, 'placeholder' => 'Describe any special requirements, SLAs, or customizations needed.'],
            ['label' => 'Additional Comments or Remarks', 'type' => 'textarea', 'is_required' => false, 'order' => 9, 'placeholder' => 'Any other information you would like to share.'],
            ['label' => 'Supporting Documents', 'type' => 'file', 'is_required' => false, 'order' => 10, 'help_text' => 'Upload any additional supporting documents.'],
        ]);
    }

    // =====================================================================
    // Onboarding Steps
    // =====================================================================
    private function seedOnboardingSteps(): void
    {
        OnboardingStep::create(['name' => 'Select Type', 'slug' => 'select-type', 'description' => 'Choose your organization type.', 'component_key' => 'select_type', 'order' => 1]);
        OnboardingStep::create(['name' => 'Questions', 'slug' => 'questions', 'description' => 'Answer onboarding questions.', 'component_key' => 'questions', 'order' => 2]);
        OnboardingStep::create(['name' => 'KYC', 'slug' => 'kyc', 'description' => 'Upload KYC documents.', 'component_key' => 'kyc', 'order' => 3]);
        OnboardingStep::create(['name' => 'Review', 'slug' => 'review', 'description' => 'Review and submit your application.', 'component_key' => 'review', 'order' => 4]);
    }

    // =====================================================================
    // Helper Methods
    // =====================================================================

    private function createQuestion(QuestionGroup $group, array $data): Question
    {
        return Question::create(array_merge([
            'question_group_id' => $group->id,
            'is_required' => false,
            'is_active' => true,
        ], $data));
    }

    private function createQuestionsForAll(QuestionGroup $group, array $questionsData): void
    {
        foreach ($questionsData as $data) {
            $q = $this->createQuestion($group, $data);
            $this->mapToAll($q);
        }
    }

    private function mapToAll(Question $question): void
    {
        QuestionTypeMapping::create(['question_id' => $question->id, 'user_type_id' => $this->fi->id, 'order' => $question->order]);
        QuestionTypeMapping::create(['question_id' => $question->id, 'user_type_id' => $this->corp->id, 'order' => $question->order]);
    }

    private function mapToType(Question $question, int $userTypeId): void
    {
        QuestionTypeMapping::create(['question_id' => $question->id, 'user_type_id' => $userTypeId, 'order' => $question->order]);
    }

    private function mapToSubcategory(Question $question, int $userTypeId, int $subcategoryId): void
    {
        QuestionTypeMapping::create([
            'question_id' => $question->id,
            'user_type_id' => $userTypeId,
            'user_type_subcategory_id' => $subcategoryId,
            'order' => $question->order,
        ]);
    }

    private function createAndMapToType(QuestionGroup $group, array $data, int $userTypeId): Question
    {
        $q = $this->createQuestion($group, $data);
        $this->mapToType($q, $userTypeId);

        return $q;
    }
}
