<?php

/*
|--------------------------------------------------------------------------
| Country Registration Catalog
|--------------------------------------------------------------------------
|
| Drives the global "Registration Details" onboarding step. For the company's
| country of incorporation we surface the registration identifiers that apply,
| each with format validation and in-context help.
|
| Each field:
|   key             unique key (stored in registration_details JSON)
|   label           shown to the client
|   required        bool
|   types           which org categories it applies to: ['fi','corporate']
|   pattern         optional regex (anchored) for format validation
|   pattern_message message when the pattern fails
|   placeholder     example value
|   help            tooltip explanation
|
| NOTE: This is a curated starting dataset. Formats and which IDs are
| mandatory should be reviewed by compliance and are editable per-deployment
| (admin CRUD ships in a later phase). Countries without a specific override
| fall back to `default_fields`.
|
*/

return [

    // Generic fallback used for any country without a specific override.
    'default_fields' => [
        ['key' => 'company_reg_no', 'label' => 'Business Registration Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'The registration/incorporation number issued by your national company registry.'],
        ['key' => 'tax_id', 'label' => 'Tax Identification Number (TIN)', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Your company\'s tax/VAT identification number.'],
        ['key' => 'regulator_license', 'label' => 'Regulatory License Number', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'License or authorisation number issued by your national financial regulator or central bank.'],
    ],

    'overrides' => [

        'IN' => [
            ['key' => 'gstin', 'label' => 'GSTIN', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$', 'pattern_message' => 'Enter a valid 15-character GSTIN.', 'checksum' => 'gstin', 'placeholder' => '27AAPFU0939F1ZV', 'help' => '15-digit Goods & Services Tax Identification Number. GST registration is per state.'],
            ['key' => 'pan', 'label' => 'PAN', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[A-Z]{5}[0-9]{4}[A-Z]$', 'pattern_message' => 'Enter a valid 10-character PAN.', 'placeholder' => 'AAAAA0000A', 'help' => 'Permanent Account Number issued by the Income Tax Department.'],
            ['key' => 'cin', 'label' => 'CIN', 'required' => true, 'types' => ['corporate'], 'pattern' => '^[LU][0-9]{5}[A-Z]{2}[0-9]{4}[A-Z]{3}[0-9]{6}$', 'pattern_message' => 'Enter a valid 21-character CIN.', 'placeholder' => 'U12345MH2010PLC000000', 'help' => 'Corporate Identification Number issued by the MCA / Registrar of Companies.'],
            ['key' => 'rbi_reg', 'label' => 'RBI Registration Number', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Certificate of Registration number issued by the Reserve Bank of India.'],
        ],

        'US' => [
            ['key' => 'ein', 'label' => 'EIN', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{2}-?[0-9]{7}$', 'pattern_message' => 'Enter a valid 9-digit EIN.', 'placeholder' => '12-3456789', 'help' => 'Federal Employer Identification Number issued by the IRS.'],
            ['key' => 'state_reg', 'label' => 'State Registration / Incorporation Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Filing number from the Secretary of State where the entity is incorporated.'],
            ['key' => 'nmls', 'label' => 'NMLS ID / Charter Number', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'NMLS unique identifier or federal/state charter number for the institution.'],
        ],

        'GB' => [
            ['key' => 'crn', 'label' => 'Company Registration Number (CRN)', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[A-Z0-9]{8}$', 'pattern_message' => 'Enter a valid 8-character CRN.', 'placeholder' => '12345678', 'help' => 'Companies House registration number.'],
            ['key' => 'vat', 'label' => 'VAT Number', 'required' => false, 'types' => ['fi', 'corporate'], 'pattern' => '^GB[0-9]{9}([0-9]{3})?$', 'pattern_message' => 'Enter a valid UK VAT number.', 'placeholder' => 'GB123456789', 'help' => 'UK Value Added Tax registration number, if registered.'],
            ['key' => 'fca_frn', 'label' => 'FCA Firm Reference Number', 'required' => true, 'types' => ['fi'], 'pattern' => '^[0-9]{6,7}$', 'pattern_message' => 'Enter a valid FCA reference number.', 'placeholder' => '123456', 'help' => 'Firm Reference Number on the FCA Financial Services Register.'],
        ],

        'AE' => [
            ['key' => 'trade_license', 'label' => 'Trade License Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Trade license issued by the relevant emirate / free zone authority.'],
            ['key' => 'trn', 'label' => 'Tax Registration Number (TRN)', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{15}$', 'pattern_message' => 'Enter a valid 15-digit TRN.', 'placeholder' => '100000000000003', 'help' => '15-digit VAT Tax Registration Number issued by the FTA.'],
            ['key' => 'cbuae', 'label' => 'Central Bank (CBUAE) License No.', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'License number issued by the Central Bank of the UAE.'],
        ],

        'SG' => [
            ['key' => 'uen', 'label' => 'UEN', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{8,9}[A-Z]$', 'pattern_message' => 'Enter a valid UEN.', 'placeholder' => '201912345A', 'help' => 'Unique Entity Number issued by ACRA.'],
            ['key' => 'gst', 'label' => 'GST Registration Number', 'required' => false, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'GST registration number, if registered.'],
            ['key' => 'mas', 'label' => 'MAS License Number', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'License/registration number from the Monetary Authority of Singapore.'],
        ],

        'AU' => [
            ['key' => 'abn', 'label' => 'ABN', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{2}\\s?[0-9]{3}\\s?[0-9]{3}\\s?[0-9]{3}$', 'pattern_message' => 'Enter a valid 11-digit ABN.', 'checksum' => 'abn', 'placeholder' => '51 824 753 556', 'help' => 'Australian Business Number.'],
            ['key' => 'acn', 'label' => 'ACN', 'required' => true, 'types' => ['corporate'], 'pattern' => '^[0-9]{3}\\s?[0-9]{3}\\s?[0-9]{3}$', 'pattern_message' => 'Enter a valid 9-digit ACN.', 'placeholder' => '123 456 789', 'help' => 'Australian Company Number issued by ASIC.'],
            ['key' => 'afsl', 'label' => 'AFSL Number', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Australian Financial Services Licence number.'],
        ],

        'CA' => [
            ['key' => 'bn', 'label' => 'Business Number (BN)', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{9}$', 'pattern_message' => 'Enter a valid 9-digit Business Number.', 'placeholder' => '123456789', 'help' => 'CRA Business Number.'],
            ['key' => 'corp_no', 'label' => 'Corporation Number', 'required' => false, 'types' => ['corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Federal or provincial corporation number.'],
        ],

        'DE' => [
            ['key' => 'hrb', 'label' => 'Commercial Register No. (Handelsregister)', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => 'HRB 12345', 'help' => 'Handelsregister number (e.g. HRB 12345).'],
            ['key' => 'vat', 'label' => 'VAT ID (USt-IdNr.)', 'required' => false, 'types' => ['fi', 'corporate'], 'pattern' => '^DE[0-9]{9}$', 'pattern_message' => 'Enter a valid German VAT ID.', 'placeholder' => 'DE123456789', 'help' => 'German VAT identification number.'],
            ['key' => 'bafin', 'label' => 'BaFin Registration', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Registration/authorisation number from BaFin.'],
        ],

        'FR' => [
            ['key' => 'siren', 'label' => 'SIREN', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{9}$', 'pattern_message' => 'Enter a valid 9-digit SIREN.', 'placeholder' => '123456789', 'help' => 'SIREN identifier from INSEE.'],
            ['key' => 'vat', 'label' => 'VAT (TVA)', 'required' => false, 'types' => ['fi', 'corporate'], 'pattern' => '^FR[0-9A-Z]{2}[0-9]{9}$', 'pattern_message' => 'Enter a valid French VAT number.', 'placeholder' => 'FR12345678901', 'help' => 'French VAT number.'],
            ['key' => 'acpr', 'label' => 'ACPR Registration', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Registration number with the ACPR.'],
        ],

        'NL' => [
            ['key' => 'kvk', 'label' => 'KvK Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{8}$', 'pattern_message' => 'Enter a valid 8-digit KvK number.', 'placeholder' => '12345678', 'help' => 'Chamber of Commerce (KvK) registration number.'],
            ['key' => 'vat', 'label' => 'VAT (BTW)', 'required' => false, 'types' => ['fi', 'corporate'], 'pattern' => '^NL[0-9]{9}B[0-9]{2}$', 'pattern_message' => 'Enter a valid Dutch VAT number.', 'placeholder' => 'NL123456789B01', 'help' => 'Dutch VAT identification number.'],
        ],

        'IE' => [
            ['key' => 'cro', 'label' => 'CRO Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{5,6}$', 'pattern_message' => 'Enter a valid CRO number.', 'placeholder' => '123456', 'help' => 'Companies Registration Office number.'],
            ['key' => 'vat', 'label' => 'VAT Number', 'required' => false, 'types' => ['fi', 'corporate'], 'pattern' => '^IE[0-9]{7}[A-Z]{1,2}$', 'pattern_message' => 'Enter a valid Irish VAT number.', 'placeholder' => 'IE1234567A', 'help' => 'Irish VAT number, if registered.'],
        ],

        'CH' => [
            ['key' => 'uid', 'label' => 'UID (CHE)', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^CHE-?[0-9]{3}\\.?[0-9]{3}\\.?[0-9]{3}$', 'pattern_message' => 'Enter a valid CHE UID.', 'placeholder' => 'CHE-123.456.789', 'help' => 'Swiss business identification number (UID).'],
            ['key' => 'finma', 'label' => 'FINMA Authorisation', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Authorisation/registration number from FINMA.'],
        ],

        'HK' => [
            ['key' => 'br', 'label' => 'Business Registration Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{8}$', 'pattern_message' => 'Enter a valid 8-digit BR number.', 'placeholder' => '12345678', 'help' => 'Business Registration number from the Inland Revenue Department.'],
            ['key' => 'sfc_ce', 'label' => 'SFC CE Number', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Central Entity number from the Securities and Futures Commission.'],
        ],

        'JP' => [
            ['key' => 'corporate_number', 'label' => 'Corporate Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{13}$', 'pattern_message' => 'Enter a valid 13-digit Corporate Number.', 'placeholder' => '1234567890123', 'help' => 'Corporate Number (houjin bangou) issued by the National Tax Agency.'],
            ['key' => 'fsa', 'label' => 'FSA Registration', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Registration number from the Financial Services Agency.'],
        ],

        'CN' => [
            ['key' => 'uscc', 'label' => 'Unified Social Credit Code', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9A-Z]{18}$', 'pattern_message' => 'Enter a valid 18-character USCC.', 'placeholder' => '91110000XXXXXXXXXX', 'help' => '18-character Unified Social Credit Code.'],
        ],

        'BR' => [
            ['key' => 'cnpj', 'label' => 'CNPJ', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{2}\\.?[0-9]{3}\\.?[0-9]{3}/?[0-9]{4}-?[0-9]{2}$', 'pattern_message' => 'Enter a valid 14-digit CNPJ.', 'checksum' => 'cnpj', 'placeholder' => '11.444.777/0001-61', 'help' => 'Cadastro Nacional da Pessoa Jurídica.'],
        ],

        'ZA' => [
            ['key' => 'reg_no', 'label' => 'Company Registration Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{4}/[0-9]{6}/[0-9]{2}$', 'pattern_message' => 'Enter a valid CIPC registration number.', 'placeholder' => '2010/123456/07', 'help' => 'CIPC company registration number.'],
            ['key' => 'vat', 'label' => 'VAT Number', 'required' => false, 'types' => ['fi', 'corporate'], 'pattern' => '^4[0-9]{9}$', 'pattern_message' => 'Enter a valid 10-digit VAT number.', 'placeholder' => '4123456789', 'help' => 'SARS VAT number, if registered.'],
        ],

        'NZ' => [
            ['key' => 'nzbn', 'label' => 'NZBN', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{13}$', 'pattern_message' => 'Enter a valid 13-digit NZBN.', 'placeholder' => '9429000000000', 'help' => 'New Zealand Business Number.'],
        ],

        'SA' => [
            ['key' => 'cr', 'label' => 'Commercial Registration (CR)', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{10}$', 'pattern_message' => 'Enter a valid 10-digit CR number.', 'placeholder' => '1010000000', 'help' => 'Commercial Registration number from the Ministry of Commerce.'],
            ['key' => 'vat', 'label' => 'VAT Number', 'required' => false, 'types' => ['fi', 'corporate'], 'pattern' => '^3[0-9]{14}$', 'pattern_message' => 'Enter a valid 15-digit VAT number.', 'placeholder' => '300000000000003', 'help' => 'ZATCA VAT number, if registered.'],
            ['key' => 'sama', 'label' => 'SAMA License', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'License number from the Saudi Central Bank (SAMA).'],
        ],

        'MY' => [
            ['key' => 'ssm', 'label' => 'SSM Registration Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '202001000000', 'help' => 'Companies Commission of Malaysia (SSM) number.'],
            ['key' => 'bnm', 'label' => 'Bank Negara License', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'License from Bank Negara Malaysia.'],
        ],

        'ID' => [
            ['key' => 'nib', 'label' => 'NIB (Business ID)', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{13}$', 'pattern_message' => 'Enter a valid 13-digit NIB.', 'placeholder' => '1234567890123', 'help' => 'Nomor Induk Berusaha (Business Identification Number).'],
            ['key' => 'npwp', 'label' => 'NPWP (Tax)', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Tax identification number (NPWP).'],
            ['key' => 'ojk', 'label' => 'OJK License', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'License from Otoritas Jasa Keuangan (OJK).'],
        ],

        'PH' => [
            ['key' => 'sec_reg', 'label' => 'SEC Registration Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Securities and Exchange Commission registration number.'],
            ['key' => 'tin', 'label' => 'TIN', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Taxpayer Identification Number.'],
            ['key' => 'bsp', 'label' => 'BSP License', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'License from Bangko Sentral ng Pilipinas.'],
        ],

        'TH' => [
            ['key' => 'reg_no', 'label' => 'Company Registration Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{13}$', 'pattern_message' => 'Enter a valid 13-digit registration number.', 'placeholder' => '0123456789012', 'help' => 'DBD company registration number.'],
        ],

        'MX' => [
            ['key' => 'rfc', 'label' => 'RFC', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$', 'pattern_message' => 'Enter a valid RFC.', 'placeholder' => 'ABC123456T12', 'help' => 'Registro Federal de Contribuyentes.'],
        ],

        'ES' => [
            ['key' => 'nif', 'label' => 'NIF / CIF', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[A-Z][0-9]{7}[A-Z0-9]$', 'pattern_message' => 'Enter a valid NIF/CIF.', 'placeholder' => 'A12345674', 'help' => 'Tax identification number for the entity.'],
            ['key' => 'vat', 'label' => 'VAT Number', 'required' => false, 'types' => ['fi', 'corporate'], 'pattern' => '^ES[A-Z0-9][0-9]{7}[A-Z0-9]$', 'pattern_message' => 'Enter a valid Spanish VAT number.', 'placeholder' => 'ESA12345674', 'help' => 'Spanish VAT number, if registered.'],
        ],

        'IT' => [
            ['key' => 'piva', 'label' => 'VAT (Partita IVA)', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^(IT)?[0-9]{11}$', 'pattern_message' => 'Enter a valid 11-digit Partita IVA.', 'placeholder' => '12345678901', 'help' => 'Italian VAT number (Partita IVA).'],
            ['key' => 'rea', 'label' => 'REA Number', 'required' => false, 'types' => ['corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Economic and Administrative Index (REA) number.'],
        ],

        'KR' => [
            ['key' => 'brn', 'label' => 'Business Registration Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[0-9]{3}-?[0-9]{2}-?[0-9]{5}$', 'pattern_message' => 'Enter a valid 10-digit BRN.', 'placeholder' => '123-45-67890', 'help' => 'Business Registration Number from the National Tax Service.'],
        ],

        'NG' => [
            ['key' => 'rc', 'label' => 'RC Number (CAC)', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => 'RC1234567', 'help' => 'Corporate Affairs Commission registration number.'],
            ['key' => 'tin', 'label' => 'TIN', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Taxpayer Identification Number.'],
            ['key' => 'cbn', 'label' => 'CBN License', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'License from the Central Bank of Nigeria.'],
        ],

        'KE' => [
            ['key' => 'reg_no', 'label' => 'Company Registration Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Registrar of Companies registration number.'],
            ['key' => 'kra_pin', 'label' => 'KRA PIN', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => '^[A-Z][0-9]{9}[A-Z]$', 'pattern_message' => 'Enter a valid KRA PIN.', 'placeholder' => 'P051234567X', 'help' => 'Kenya Revenue Authority Personal Identification Number.'],
        ],

        'QA' => [
            ['key' => 'cr', 'label' => 'Commercial Registration Number', 'required' => true, 'types' => ['fi', 'corporate'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'Commercial Registration from the Ministry of Commerce and Industry.'],
            ['key' => 'qcb', 'label' => 'Qatar Central Bank License', 'required' => true, 'types' => ['fi'], 'pattern' => null, 'pattern_message' => null, 'placeholder' => '', 'help' => 'License from the Qatar Central Bank.'],
        ],

    ],

    // Full ISO 3166-1 country list (code => name). Countries without a specific
    // override above use `default_fields`.
    'countries' => [
        'AF' => 'Afghanistan', 'AX' => 'Åland Islands', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa',
        'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina',
        'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
        'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan',
        'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil', 'BN' => 'Brunei',
        'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'CV' => 'Cabo Verde', 'KH' => 'Cambodia',
        'CM' => 'Cameroon', 'CA' => 'Canada', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad',
        'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo',
        'CD' => 'Congo (DRC)', 'CR' => 'Costa Rica', 'CI' => "Côte d'Ivoire", 'HR' => 'Croatia', 'CU' => 'Cuba',
        'CW' => 'Curaçao', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DK' => 'Denmark', 'DJ' => 'Djibouti',
        'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia', 'SZ' => 'Eswatini', 'ET' => 'Ethiopia',
        'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia',
        'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana',
        'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada', 'GU' => 'Guam',
        'GT' => 'Guatemala', 'GG' => 'Guernsey', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana',
        'HT' => 'Haiti', 'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland',
        'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland',
        'IM' => 'Isle of Man', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan',
        'JE' => 'Jersey', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati',
        'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon',
        'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
        'LU' => 'Luxembourg', 'MO' => 'Macao', 'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia',
        'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MR' => 'Mauritania',
        'MU' => 'Mauritius', 'MX' => 'Mexico', 'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco',
        'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar',
        'NA' => 'Namibia', 'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NC' => 'New Caledonia',
        'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'MK' => 'North Macedonia',
        'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestine',
        'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines',
        'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RO' => 'Romania',
        'RU' => 'Russia', 'RW' => 'Rwanda', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia', 'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'São Tomé and Príncipe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
        'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SK' => 'Slovakia',
        'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'KR' => 'South Korea',
        'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname',
        'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Türkiye', 'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands',
        'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom',
        'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VE' => 'Venezuela',
        'VN' => 'Vietnam', 'VG' => 'British Virgin Islands', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
    ],

];
