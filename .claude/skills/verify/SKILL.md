---
name: verify
description: Build/launch/drive recipe for verifying changes in this repo (Laravel API + React SPA)
---

# Verifying changes in onboarding-eficyent

## Backend (Laravel 12, `backend/`)

- `composer install`, then `cp .env.example .env && php artisan key:generate` if `.env` missing.
- Local runtime env (edit `.env`): `DB_CONNECTION=sqlite` (comment the `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD` lines, `touch database/database.sqlite`), `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`, `SESSION_DRIVER=file`, `CACHE_STORE=file`.
- **Gotcha:** set `SANCTUM_STATEFUL_DOMAINS=` (empty). The default `localhost:3000` makes `statefulApi()` demand CSRF from the SPA → every POST from the frontend 419s. The SPA uses bearer tokens, not cookies.
- `php artisan migrate:fresh --seed` gives the full 11-step KYB flow + question catalog. No admin user is seeded by default seeder run order — create one via tinker if needed.
- Outbound mail lands in `storage/logs/laravel-YYYY-MM-DD.log` as quoted-printable MIME. Decode with `php -r 'echo quoted_printable_decode(file_get_contents(...));'` — grepping the raw log misses URLs/codes split by soft line breaks. OTP codes: last 6-digit match in the decoded log.
- Seed test data quickly with `php artisan tinker --execute='...'` (User, OnboardingService::initializeForUser, UserAnswer, NotificationService::createChangeRequest).
- File uploads locally: set `ONBOARDING_UPLOAD_DISK=public` (default `s3` 500s on signed-URL generation with no bucket) and run `php artisan storage:link`.
- AI document validation: set `DOCUMENT_VALIDATION_DRIVER=fake` for deterministic offline behavior — verdicts key off the uploaded filename (see `FakeDocumentIntelligence`): `articles-of-association.pdf` → type mismatch vs a certificate question, `expired-*` → expired, `stale-*` → stale, `unreadable-*` → needs_review.
- The `rules` driver (default) analyzes real content locally: needs `poppler` (pdftotext/pdftoppm) and `tesseract` (brew install) for full coverage; without them, scans go to needs_review. Test fixtures: `tests/Support/MakesPdfs.php` builds text PDFs (`makePdf`) and image-only scanned PDFs (`makeScannedPdf`) — drop into the browser via a JS `DataTransfer` drop event on `.file-upload-dropzone` (fetch fixture bytes same-origin from `frontend/public/`).
- Jump a user to a later onboarding step via tinker: mark earlier `$onboarding->steps()` completed, set the target step `in_progress`, and set `current_step_id`.

## Frontend (CRA, `frontend/`)

- `npm ci`, `npm run build` for a build check. Build has pre-existing eslint warnings (FileUploadField, QuestionsStep, conditionalEngine) — `CI=true` turns them into errors, so build without it.
- API base defaults to `http://localhost:8000/api` (override `REACT_APP_API_URL`).

## Running & driving

- `.claude/launch.json` defines `backend` (php artisan serve :8000) and `frontend` (npm start :3000) — start both with preview_start. CRA takes ~20s to compile.
- Login flow: enter email → OTP arrives in the backend log (see decode note above) → the 6 OTP boxes reject rapid synthetic typing; type ONE digit per `type` action (auto-advance) and it auto-submits on the 6th.
- **Clicking in modals:** `computer` clicks with `ref` coordinates have landed on the click-to-close overlay instead of the dialog. Take a screenshot and click by screenshot-pixel coordinates instead.
- Deep link under test: `/home?notification={id}` auto-opens the notification detail modal (handled in NotificationBell).
