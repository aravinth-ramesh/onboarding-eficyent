<?php

/*
|--------------------------------------------------------------------------
| Merchant Category Codes (industry classification)
|--------------------------------------------------------------------------
|
| Mirrored from the client's picker so the admin panel can render the
| industry NAME rather than the stored code. Seeded onto the `mcc` question's
| options, which both sides then read, so the two can't drift.
|
*/

return [
    'Agriculture & Construction' => [
        '0742' => 'Veterinary Services',
        '0763' => 'Agricultural Cooperatives',
        '1520' => 'General Contractors – Residential & Commercial',
        '1711' => 'Heating, Plumbing & A/C Contractors',
        '1731' => 'Electrical Contractors',
    ],
    'Retail & E-commerce' => [
        '5200' => 'Home Supply Warehouse Stores',
        '5311' => 'Department Stores',
        '5411' => 'Grocery Stores & Supermarkets',
        '5511' => 'Car & Truck Dealers',
        '5651' => 'Family Clothing Stores',
        '5712' => 'Furniture & Home Furnishings',
        '5732' => 'Electronics Stores',
        '5912' => 'Drug Stores & Pharmacies',
        '5942' => 'Book Stores',
        '5964' => 'Direct Marketing – Catalog Merchant',
        '5999' => 'Miscellaneous Retail',
    ],
    'Food & Beverage' => [
        '5812' => 'Eating Places & Restaurants',
        '5813' => 'Bars, Taverns & Nightclubs',
        '5814' => 'Fast Food Restaurants',
        '5921' => 'Package Stores – Beer, Wine & Liquor',
    ],
    'Travel & Transport' => [
        '4111' => 'Local & Suburban Commuter Transport',
        '4121' => 'Taxicabs & Limousines',
        '4214' => 'Motor Freight Carriers & Trucking',
        '4411' => 'Cruise Lines',
        '4511' => 'Airlines & Air Carriers',
        '4722' => 'Travel Agencies & Tour Operators',
        '4789' => 'Transportation Services',
        '7011' => 'Hotels, Motels & Resorts',
        '7512' => 'Automobile Rental Agency',
    ],
    'Financial Services' => [
        '6011' => 'Financial Institutions – ATM',
        '6012' => 'Financial Institutions – Merchandise & Services',
        '6051' => 'Foreign Currency & Money Orders',
        '6211' => 'Security Brokers & Dealers',
        '6300' => 'Insurance – Sales & Underwriting',
        '6513' => 'Real Estate Agents & Managers',
    ],
    'Technology & Telecom' => [
        '4814' => 'Telecommunication Services',
        '4816' => 'Computer Network & Information Services',
        '4899' => 'Cable, Satellite & Pay TV/Radio',
        '5045' => 'Computers, Peripherals & Software',
        '5734' => 'Computer Software Stores',
        '7372' => 'Computer Programming & Data Processing',
    ],
    'Professional & Business Services' => [
        '7311' => 'Advertising Services',
        '7392' => 'Management, Consulting & PR Services',
        '7399' => 'Business Services',
        '8111' => 'Legal Services & Attorneys',
        '8711' => 'Engineering & Architectural Services',
        '8721' => 'Accounting, Auditing & Bookkeeping',
        '8742' => 'Management Consulting Services',
        '8999' => 'Professional Services',
    ],
    'Healthcare' => [
        '8011' => 'Doctors & Physicians',
        '8021' => 'Dentists & Orthodontists',
        '8062' => 'Hospitals',
        '8071' => 'Medical & Dental Labs',
        '8099' => 'Medical Services & Health Practitioners',
    ],
    'Education & Non-profit' => [
        '8211' => 'Elementary & Secondary Schools',
        '8220' => 'Colleges & Universities',
        '8249' => 'Vocational & Trade Schools',
        '8398' => 'Charitable & Social Service Organizations',
        '8661' => 'Religious Organizations',
    ],
    'Manufacturing & Wholesale' => [
        '2741' => 'Miscellaneous Publishing & Printing',
        '5065' => 'Electrical Parts & Equipment',
        '5111' => 'Stationery & Office Supplies',
        '5172' => 'Petroleum & Petroleum Products',
        '5211' => 'Lumber & Building Materials',
    ],
    'Utilities & Other' => [
        '4900' => 'Utilities – Electric, Gas, Water, Sanitary',
        '7299' => 'Miscellaneous Personal Services',
        '7997' => 'Membership Clubs – Sports & Recreation',
        '7999' => 'Recreation Services',
    ],
];
