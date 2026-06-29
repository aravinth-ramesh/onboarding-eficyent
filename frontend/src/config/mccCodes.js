// Merchant Category Codes for the industry-classification field, grouped by
// sector. A curated, representative set covering the most common businesses;
// extend as needed.
export const MCC_GROUPS = [
  {
    category: 'Agriculture & Construction',
    codes: [
      { code: '0742', label: 'Veterinary Services' },
      { code: '0763', label: 'Agricultural Cooperatives' },
      { code: '1520', label: 'General Contractors – Residential & Commercial' },
      { code: '1711', label: 'Heating, Plumbing & A/C Contractors' },
      { code: '1731', label: 'Electrical Contractors' },
    ],
  },
  {
    category: 'Retail & E-commerce',
    codes: [
      { code: '5200', label: 'Home Supply Warehouse Stores' },
      { code: '5311', label: 'Department Stores' },
      { code: '5411', label: 'Grocery Stores & Supermarkets' },
      { code: '5511', label: 'Car & Truck Dealers' },
      { code: '5651', label: 'Family Clothing Stores' },
      { code: '5712', label: 'Furniture & Home Furnishings' },
      { code: '5732', label: 'Electronics Stores' },
      { code: '5912', label: 'Drug Stores & Pharmacies' },
      { code: '5942', label: 'Book Stores' },
      { code: '5964', label: 'Direct Marketing – Catalog Merchant' },
      { code: '5999', label: 'Miscellaneous Retail' },
    ],
  },
  {
    category: 'Food & Beverage',
    codes: [
      { code: '5812', label: 'Eating Places & Restaurants' },
      { code: '5813', label: 'Bars, Taverns & Nightclubs' },
      { code: '5814', label: 'Fast Food Restaurants' },
      { code: '5921', label: 'Package Stores – Beer, Wine & Liquor' },
    ],
  },
  {
    category: 'Travel & Transport',
    codes: [
      { code: '4111', label: 'Local & Suburban Commuter Transport' },
      { code: '4121', label: 'Taxicabs & Limousines' },
      { code: '4214', label: 'Motor Freight Carriers & Trucking' },
      { code: '4411', label: 'Cruise Lines' },
      { code: '4511', label: 'Airlines & Air Carriers' },
      { code: '4722', label: 'Travel Agencies & Tour Operators' },
      { code: '4789', label: 'Transportation Services' },
      { code: '7011', label: 'Hotels, Motels & Resorts' },
      { code: '7512', label: 'Automobile Rental Agency' },
    ],
  },
  {
    category: 'Financial Services',
    codes: [
      { code: '6011', label: 'Financial Institutions – ATM' },
      { code: '6012', label: 'Financial Institutions – Merchandise & Services' },
      { code: '6051', label: 'Foreign Currency & Money Orders' },
      { code: '6211', label: 'Security Brokers & Dealers' },
      { code: '6300', label: 'Insurance – Sales & Underwriting' },
      { code: '6513', label: 'Real Estate Agents & Managers' },
    ],
  },
  {
    category: 'Technology & Telecom',
    codes: [
      { code: '4814', label: 'Telecommunication Services' },
      { code: '4816', label: 'Computer Network & Information Services' },
      { code: '4899', label: 'Cable, Satellite & Pay TV/Radio' },
      { code: '5045', label: 'Computers, Peripherals & Software' },
      { code: '5734', label: 'Computer Software Stores' },
      { code: '7372', label: 'Computer Programming & Data Processing' },
    ],
  },
  {
    category: 'Professional & Business Services',
    codes: [
      { code: '7311', label: 'Advertising Services' },
      { code: '7392', label: 'Management, Consulting & PR Services' },
      { code: '7399', label: 'Business Services' },
      { code: '8111', label: 'Legal Services & Attorneys' },
      { code: '8711', label: 'Engineering & Architectural Services' },
      { code: '8721', label: 'Accounting, Auditing & Bookkeeping' },
      { code: '8742', label: 'Management Consulting Services' },
      { code: '8999', label: 'Professional Services' },
    ],
  },
  {
    category: 'Healthcare',
    codes: [
      { code: '8011', label: 'Doctors & Physicians' },
      { code: '8021', label: 'Dentists & Orthodontists' },
      { code: '8062', label: 'Hospitals' },
      { code: '8071', label: 'Medical & Dental Labs' },
      { code: '8099', label: 'Medical Services & Health Practitioners' },
    ],
  },
  {
    category: 'Education & Non-profit',
    codes: [
      { code: '8211', label: 'Elementary & Secondary Schools' },
      { code: '8220', label: 'Colleges & Universities' },
      { code: '8249', label: 'Vocational & Trade Schools' },
      { code: '8398', label: 'Charitable & Social Service Organizations' },
      { code: '8661', label: 'Religious Organizations' },
    ],
  },
  {
    category: 'Manufacturing & Wholesale',
    codes: [
      { code: '2741', label: 'Miscellaneous Publishing & Printing' },
      { code: '5065', label: 'Electrical Parts & Equipment' },
      { code: '5111', label: 'Stationery & Office Supplies' },
      { code: '5172', label: 'Petroleum & Petroleum Products' },
      { code: '5211', label: 'Lumber & Building Materials' },
    ],
  },
  {
    category: 'Utilities & Other',
    codes: [
      { code: '4900', label: 'Utilities – Electric, Gas, Water, Sanitary' },
      { code: '7299', label: 'Miscellaneous Personal Services' },
      { code: '7997', label: 'Membership Clubs – Sports & Recreation' },
      { code: '7999', label: 'Recreation Services' },
    ],
  },
];

// Flat lookup: code -> label, for displaying a saved value.
export const MCC_LABELS = MCC_GROUPS.reduce((acc, g) => {
  g.codes.forEach((c) => { acc[c.code] = c.label; });
  return acc;
}, {});
