# Client Onboarding Application

A dynamic, fully configurable client onboarding system built with **React + Bootstrap** (frontend) and **Laravel** (backend API + admin panel).

## Repository Structure

```
/
├── frontend/          # React application
│   ├── src/
│   │   ├── api/              # Axios API client & endpoint modules
│   │   ├── components/
│   │   │   ├── auth/         # Login/OTP components
│   │   │   ├── common/       # Shared components (ProtectedRoute, StepIndicator)
│   │   │   ├── layout/       # AppLayout wrapper
│   │   │   └── onboarding/   # Step components (SelectType, Questions, KYC, Review)
│   │   ├── hooks/            # Custom React hooks
│   │   ├── pages/            # Page-level components (HomePage)
│   │   ├── store/            # Redux Toolkit store & slices
│   │   │   └── slices/       # authSlice, onboardingSlice
│   │   └── utils/            # Conditional rule engine
│   └── public/
│
├── backend/           # Laravel application
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Api/      # User-facing API controllers
│   │   │   │   └── Admin/    # Admin CRUD controllers
│   │   │   └── Requests/
│   │   │       ├── Api/      # API form request validation
│   │   │       └── Admin/    # Admin form request validation
│   │   ├── Mail/             # OTP Mailable
│   │   ├── Models/           # Eloquent models (14 models)
│   │   └── Services/         # Business logic services
│   │       ├── OtpService.php
│   │       ├── OnboardingService.php
│   │       ├── AnswerService.php
│   │       └── ConditionalRuleEngine.php
│   ├── database/
│   │   ├── migrations/       # 15 migration files
│   │   └── seeders/          # Sample data seeder
│   ├── routes/
│   │   └── api.php           # All API routes
│   └── resources/views/
│       └── emails/           # OTP email template
│
└── README.md
```

---

## Database Schema

### ER Diagram (Relationships)

```
users
  ├── 1:1 → user_onboardings
  ├── 1:N → user_answers
  └── 1:N → answer_audit_logs

otp_codes (email-indexed, standalone)

user_types
  └── 1:N → user_type_subcategories

question_groups
  └── 1:N → questions
              ├── 1:N → question_type_mappings → user_types / user_type_subcategories
              ├── 1:N → conditional_rules (self-referencing parent_question_id)
              └── 1:N → user_answers

onboarding_steps (master template)
  └── 1:N → user_onboarding_steps

user_onboardings
  ├── N:1 → users
  ├── N:1 → user_types
  ├── N:1 → user_type_subcategories
  ├── 1:N → user_onboarding_steps
  └── 1:N → user_answers

answer_audit_logs
  ├── N:1 → user_answers
  ├── N:1 → questions
  └── N:1 → users (edited_by)
```

### Tables

| Table | Purpose |
|-------|---------|
| `users` | User accounts (email-only login, soft deletes) |
| `otp_codes` | SHA-256 hashed OTP codes with expiration |
| `user_types` | Organization types (Financial Institution, Corporate, etc.) |
| `user_type_subcategories` | Optional subcategories per type |
| `question_groups` | Logical groupings of questions |
| `questions` | Question definitions (type, options, validation) |
| `question_type_mappings` | Maps questions to types/subcategories (reusability) |
| `conditional_rules` | Show/hide questions based on parent answers |
| `onboarding_steps` | Master template steps (app-level) |
| `user_onboardings` | Per-user onboarding state |
| `user_onboarding_steps` | Per-user copies of onboarding steps |
| `user_answers` | User responses to questions |
| `answer_audit_logs` | Edit history for all answer changes |

---

## API Endpoints

### Authentication (Public)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/send-otp` | Send OTP to email |
| POST | `/api/auth/verify-otp` | Verify OTP & get auth token |

### Authentication (Protected)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/auth/me` | Get current user profile |
| POST | `/api/auth/logout` | Revoke current token |

### User Onboarding

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/user-types` | List active user types with subcategories |
| GET | `/api/onboarding/status` | Get/initialize onboarding state |
| POST | `/api/onboarding/set-type` | Set user type & subcategory |
| GET | `/api/onboarding/questions` | Get questions for user's type |
| POST | `/api/onboarding/answers` | Save/update answers (with audit) |
| POST | `/api/onboarding/steps/{id}/complete` | Complete a step |

### Admin CRUD

| Resource | Endpoints |
|----------|-----------|
| User Types | `GET/POST /api/admin/user-types`, `GET/PUT/DELETE /api/admin/user-types/{id}` |
| Subcategories | `GET/POST /api/admin/user-types/{id}/subcategories`, `GET/PUT/DELETE .../{id}` |
| Question Groups | `GET/POST /api/admin/question-groups`, `GET/PUT/DELETE .../{id}` |
| Questions | `GET/POST /api/admin/questions`, `GET/PUT/DELETE .../{id}` |
| Conditional Rules | `GET/POST /api/admin/conditional-rules`, `GET/PUT/DELETE .../{id}` |
| Onboarding Steps | `GET/POST /api/admin/onboarding-steps`, `GET/PUT/DELETE .../{id}` |
| Step Reordering | `POST /api/admin/onboarding-steps/reorder` |
| User Onboardings | `GET /api/admin/user-onboardings`, `GET .../{id}` |
| User Step Reorder | `POST /api/admin/user-onboardings/{id}/reorder-steps` |
| Audit Logs | `GET /api/admin/audit-logs` |

### Example API Responses

**GET /api/user-types**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Financial Institution",
      "slug": "financial-institution",
      "description": "Banks, NBFCs, Insurance companies, etc.",
      "has_subcategories": true,
      "subcategories": [
        { "id": 1, "name": "Bank", "slug": "bank", "description": null },
        { "id": 2, "name": "NBFC", "slug": "nbfc", "description": null }
      ]
    },
    {
      "id": 2,
      "name": "Corporate",
      "slug": "corporate",
      "description": "Corporate entities and businesses.",
      "has_subcategories": false,
      "subcategories": []
    }
  ]
}
```

**GET /api/onboarding/questions**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Basic Information",
      "description": null,
      "order": 1,
      "questions": [
        {
          "id": 1,
          "label": "Company Name",
          "type": "text",
          "options": null,
          "is_required": true,
          "order": 1,
          "placeholder": "Enter your company name",
          "help_text": null,
          "answer": null,
          "conditional_rules": []
        },
        {
          "id": 7,
          "label": "Are you regulated by any financial authority?",
          "type": "radio",
          "options": [
            { "label": "Yes", "value": "yes" },
            { "label": "No", "value": "no" }
          ],
          "is_required": true,
          "conditional_rules": []
        },
        {
          "id": 8,
          "label": "Name of Regulatory Authority",
          "type": "text",
          "is_required": true,
          "conditional_rules": [
            {
              "parent_question_id": 7,
              "comparison_type": "equals",
              "trigger_value": "yes",
              "action": "show",
              "logical_operator": "and"
            }
          ]
        }
      ]
    }
  ]
}
```

---

## Architecture & Design Decisions

### Two-Level Onboarding

1. **App-Level (Master Template)** - Admin defines global steps in `onboarding_steps`
2. **User-Level** - When user starts onboarding, steps are copied to `user_onboarding_steps`, allowing per-user customization without affecting the template

### Question Reusability

Questions are decoupled from types via `question_type_mappings`. A single question can be mapped to multiple user types with different order/required overrides.

### Conditional Logic Engine

Both backend (`ConditionalRuleEngine.php`) and frontend (`conditionalEngine.js`) implement the same evaluation logic:
- Supports: `equals`, `not_equals`, `contains`, `not_contains`, `greater_than`, `less_than`, `in`, `not_in`, `is_empty`, `is_not_empty`
- Actions: `show` or `hide`
- Logical operators: `and` / `or` for combining multiple rules

### Audit Logging

Every answer edit is tracked in `answer_audit_logs` with: old value, new value, timestamp, and who made the edit (user or admin).

### Template Versioning

`onboarding_steps.version` and `user_onboardings.template_version` enable tracking which version of the onboarding flow a user was assigned.

---

## Setup Instructions

### Backend (Laravel)

```bash
cd backend
cp .env.example .env

# Configure database (MySQL)
# DB_CONNECTION=mysql
# DB_DATABASE=onboarding_eficyent

composer install
php artisan key:generate
php artisan migrate
php artisan db:seed   # Seeds sample types, questions, steps
php artisan serve     # Runs on http://localhost:8000
```

### Frontend (React)

```bash
cd frontend
cp .env.example .env
npm install
npm start             # Runs on http://localhost:3000
```

---

## Recommended Packages

| Purpose | Package | Status |
|---------|---------|--------|
| API Auth (tokens) | `laravel/sanctum` | Installed |
| Role Management | `spatie/laravel-permission` | Installed |
| State Management | `@reduxjs/toolkit` + `react-redux` | Installed |
| HTTP Client | `axios` | Installed |
| UI Framework | `react-bootstrap` + `bootstrap` | Installed |
| Routing | `react-router-dom` v6 | Installed |
| OTP Email | Laravel built-in `Mail` (queue-ready) | Built-in |

---

## Best Practices Implemented

- **Scalability**: Service layer pattern separates business logic from controllers
- **Validation**: Form Request classes on every endpoint; frontend validation mirrors backend
- **Conditional Logic**: Dual-engine (PHP + JS) ensures consistent behavior
- **Audit Logging**: Automatic on every answer edit with full change history
- **Soft Deletes**: On all admin-managed entities to prevent data loss
- **Template Versioning**: Track which onboarding version each user completed
- **OTP Security**: SHA-256 hashed codes, expiration, max attempts, rate limiting
- **Dynamic Rendering**: `StepRenderer` maps `component_key` to React components - adding new step types requires zero hardcoding changes in the flow
- **Question Reuse**: Questions exist independently and are mapped to types via a pivot table
