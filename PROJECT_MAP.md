# ExamSafe - Project Structure & Relationships Map

## Project Overview

ExamSafe is a secure online exam platform with three user roles: Admin, Teacher, and Student.

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP 7.4+
- **Database**: MySQL/MariaDB
- **Authentication**: Session-based
- **Security**: CSRF tokens, Rate limiting, POST-only sensitive endpoints
- **File Upload**: PDF/DOCX/TXT parsing via Composer packages

---

## Directory Structure

```
ExamSafe/
├── admin/                 # Admin dashboard files
│   ├── dashboard.html    # Main admin dashboard
│   ├── teachers.html     # Teacher management
│   ├── students.html     # Student management
│   ├── exams.html        # All exams view
│   ├── reports.html      # Reports & analytics
│   ├── settings.html     # System settings
│   └── security-logs.html # Security violation logs
├── teacher/              # Teacher dashboard files
│   ├── dashboard.php     # Main teacher dashboard (PHP with auth)
│   ├── dashboard.html    # REDIRECTS to dashboard.php (deprecated)
│   ├── create-exam.html  # Create/edit exams
│   ├── question-bank.html # Manage question bank
│   ├── results.html      # View exam results
│   ├── students.html     # Manage students
│   ├── register.html     # Teacher registration (refactored)
│   └── settings.html     # Teacher settings (API keys)
├── student/              # Student exam interface
│   ├── dashboard.php     # Student dashboard (POST forms for exam access)
│   ├── exam.php          # Take exam interface (POST-only access)
│   ├── exam.html         # REDIRECTS to dashboard.php (deprecated)
│   └── register.html     # Student registration (refactored)
├── css/
│   ├── style.css         # Global styles
│   └── register.css      # Shared registration styles
├── js/
│   ├── api-client.js     # SHARED - Base API client wrapper
│   ├── student-api.js    # Student-specific API endpoints
│   ├── register-common.js # SHARED - Registration utilities
│   ├── exam-manager.js   # SHARED - Exam management logic
│   ├── exam.js           # Exam engine (timer, answers, submission)
│   └── security.js       # Anti-cheat monitoring (attaches on exam start)
├── php/
│   ├── db.php           # Database connection
│   ├── exam_api.php     # SHARED - Exam operations API
│   ├── admin_api.php    # Admin-specific API
│   ├── auth.php         # Authentication handling + rate limiting
│   ├── logout.php       # Logout handler
│   ├── ai_import.php    # AI question extraction
│   ├── student_register.php # Student registration API
│   ├── register.php     # Teacher registration API
│   └── logs/            # Log files directory
│       ├── ai_import.log    # AI extraction logs
│       └── exam_actions.log # Exam action logs (reset, toleransi, violations)
├── includes/            # Shared PHP utilities
│   ├── auth.php         # Authentication helpers
│   └── csrf.php         # CSRF token generation & validation
└── vendor/              # Composer dependencies
```

---

## Core Files & Their Relationships

### 1. **includes/csrf.php** (Security Module)

**Purpose**: CSRF protection for all forms

**Key Functions**:
| Function | Purpose |
|----------|---------|
| `generateCSRFToken()` | Creates/retrieves session-based CSRF token |
| `verifyCSRFToken($token1, $token2)` | Validates token using hash_equals() |
| `csrfField($token)` | Generates hidden input HTML |

**Critical**: Tokens stored in `$_SESSION['csrf_token']`, regenerated after successful exam access to prevent replay attacks.

---

### 2. **includes/auth.php** (Authentication & Rate Limiting)

**Purpose**: Authentication handling + exam access rate limiting

**Key Functions**:

- `isLoggedIn()`, `requireLogin($role)`, `setSession()`, `clearSession()`, `isSessionExpired()`
- `checkExamRateLimit($examId)` - Limits to 3 attempts per 1 minute per exam per student
- `clearExamRateLimit($examId)` - Resets rate limit on successful access

**Rate Limit Behavior**:

- Attempts 1-3: Allowed within 60-second window
- After 3 failures: 60-second block
- Block message stored in `$_SESSION['rate_limit_error']`

---

### 3. **teacher/dashboard.php** (NEW - PHP with Auth)

**Purpose**: Main teacher dashboard with server-side authentication

**Key Features**:

- Session configuration matching `exam_api.php` (SameSite=Lax, path=/)
- `requireLogin('guru')` authentication check
- Session timer refresh on each load (prevents timeout during active use)
- All dashboard data fetched via AJAX from `exam_api.php` (same as HTML version)
- Sidebar menu updated to point to `dashboard.php` instead of `dashboard.html`

**Authentication Flow**:

```
User accesses dashboard.php
  ↓
Session cookie configured (matching API)
  ↓
requireLogin('guru') validates session
  ↓
If invalid → redirect to ../index.php with error
  ↓
If valid → refresh login_time, render dashboard
  ↓
JavaScript loads data via exam_api.php (session already valid)
```

**Critical**: Relies on API for session recovery; no duplicate recovery logic in dashboard.php

---

### 4. **teacher/dashboard.html** (UPDATED - Redirector)

**Purpose**: Legacy file that redirects to new PHP version

**Behavior**:

- JavaScript redirect (primary) to `dashboard.php`
- Meta refresh fallback (0 seconds)
- Visual feedback with spinner animation
- Manual link if redirect fails

**Deprecation Note**: Keep for backward compatibility; remove after confirming no bookmarks/external links remain.

---

### 5. **student/exam.php** (POST-Only Access)

**Purpose**: Student exam taking interface with enhanced security

**Security Flow**:

```
POST request (from dashboard form)
  ↓
Validate CSRF token (verifyCSRFToken)
  ↓
Check rate limit (checkExamRateLimit)
  ↓
Store exam_id in $_SESSION['active_exam_id']
  ↓
Regenerate CSRF token (prevent replay)
  ↓
Redirect 302 to clean URL (exam.php)
  ↓
GET request retrieves exam_id from session
  ↓
Clear session variable (prevent reuse)
  ↓
Normal exam flow (agreement → security → questions)
```

**Critical Changes**:

- **NO LONGER accepts GET parameter** - exam_id must come from session
- Direct access without POST → redirect to dashboard with error
- Session-based exam_id prevents bookmarking/sharing

---

### 6. **student/dashboard.php** (POST Forms)

**Purpose**: Student dashboard with POST-based exam access

**Key Features**:

- Includes `csrf.php` and generates token via `generateCSRFToken()`
- All "Mulai Ujian" buttons converted to POST forms
- Join exam flow uses JavaScript POST form submission

---

### 7. **js/api-client.js** (Base API Client)

**Purpose**: Shared API wrapper for all AJAX calls

**Key Methods**:

- `ApiClient.request({url, method, data, csrfToken})` - Generic request handler
- `ApiClient.get(url)` - GET request shortcut
- `ApiClient.post(url, data, csrfToken)` - POST request shortcut

**Features**: Automatic JSON serialization, credentials included, optional CSRF token support

---

### 8. **js/register-common.js** (Registration Module)

**Purpose**: Shared utilities for student/teacher registration pages

**Modules**:

- `RegisterUI` - Alert and loading state management
- `RegisterValidation` - Email, password, required field validation
- `RegisterAPI` - API calls for registration, fetching classes/subjects
- `RegisterWizard` - Generic step wizard controller

---

### 9. **css/register.css** (Registration Styles)

**Purpose**: Shared styles for registration pages (utility-first, responsive)

---

### 10. **js/exam-manager.js** (SHARED MODULE)

**Purpose**: Centralized exam management for both admin and teacher dashboards

**Key Methods**:

- `fetchExams()`, `activateExam()`, `deactivateExam()`, `deleteExam()`, `duplicateExam()`
- `showMonitor()` - Opens monitor modal with search & auto-refresh
- `resetStudentResult()` - Resets student result + violations (transaction-safe)
- `showViolationDetails()` - View violation history
- `deleteViolation()` - Remove a specific violation record

**Monitor Modal Features**: Search/filter, auto-refresh (30s), violation badges, reset button always visible

---

### 11. **js/security.js** (Student Exam Security)

**Purpose**: Anti-cheat monitoring that ONLY activates when exam officially starts

**Key Behavior**:

- `init()` - Sets up debug mode, attaches NO listeners
- `start()` - Called from `exam.php` when student clicks "Mulai Ujian"
- Attaches all security listeners ONLY after `start()` is called
- Logs violations without blocking/forcing submit

**Important**: No violations recorded before exam officially starts.

---

### 12. **php/exam_api.php** (SHARED API)

**Purpose**: Handles all exam-related operations for all roles

**Key Endpoints**:
| Action | Role Access | Description |
|--------|-------------|-------------|
| `get_exam_monitor` | Guru, Admin | Returns participants with violation counts |
| `reset_student_result` | Guru, Admin | Deletes submission AND all violations |
| `get_student_violations` | Guru, Admin | Fetch violation history |
| `delete_violation` | Guru, Admin | Delete a single violation record |
| `report_violation` | Siswa | Log violation to database |
| `log_agreement` | Siswa | Record student agreement |
| `join_exam` | Siswa | Validate exam code and authorize access |
| `start_exam` | Siswa | Initialize exam session |
| `get_teacher_stats` | Guru | Fetch teacher's total students and average score |
| `get_profile` | Guru, Siswa | Fetch user profile data |
| `get_recent_violations` | Guru | Fetch latest 10 violations for teacher's exams |
| `get_classes` | Public | Fetch list of classes |
| `get_subjects` | Public | Fetch list of subjects |

**Session Recovery**: API auto-recovers sessions when `$_SESSION['role']` missing but `user_id` exists (lines 22-44 in exam_api.php)

**Logging**: `logExamAction()` writes to `logs/exam_actions.log`

---

## Key Workflows

### 1. Teacher Dashboard Load Flow (NEW)

```
Teacher accesses dashboard.php
  ↓
PHP: Session validation (requireLogin)
  ↓
PHP: Refresh session timer
  ↓
Render HTML with sidebar (active link points to dashboard.php)
  ↓
JavaScript: ExamManager.fetchExams() → GET exam_api.php?action=get_exams
  ↓
JavaScript: Fetch teacher stats → GET exam_api.php?action=get_teacher_stats
  ↓
JavaScript: Fetch profile → GET exam_api.php?action=get_profile
  ↓
JavaScript: Fetch violations → GET exam_api.php?action=get_recent_violations
  ↓
All data displayed; monitor modal, exam actions work via exam-manager.js
```

### 2. Student Registration Flow

```
Student loads register.html
  ↓
CSS loaded: style.css + register.css
JS loaded: api-client.js + register-common.js
  ↓
RegisterAPI.fetchClasses() populates class dropdown
  ↓
Form validation via RegisterValidation
  ↓
Submit → RegisterAPI.registerStudent()
  ↓
Success: Show success message with login link
```

### 3. Teacher Registration Flow (Step Wizard)

```
Teacher loads register.html
  ↓
RegisterWizard.init(3) initializes 3-step wizard
RegisterAPI.fetchSubjects() populates subject dropdown
  ↓
Step 1: Personal data → Step 2: Teaching data → Step 3: Password + agreement
  ↓
Final submission → RegisterAPI.registerTeacher()
```

### 4. Student Exam Access Flow (POST-only with CSRF)

```
Student clicks "Mulai Ujian" on dashboard
  ↓
POST form to exam.php with exam_id + csrf_token
  ↓
CSRF validation + rate limiting (3 attempts/min)
  ↓
Store exam_id in session, regenerate CSRF token
  ↓
Redirect 302 to exam.php (clean URL)
  ↓
GET request reads exam_id from session (single-use)
  ↓
Load agreement modal → security starts → exam begins
```

### 5. Reset Student Result Flow

```
Teacher clicks "Reset Hasil" in Monitor Modal
  ↓
confirm() dialog warns: answers AND violations will be deleted
  ↓
POST to exam_api.php?action=reset_student_result
  ↓
BEGIN TRANSACTION
  - DELETE FROM violations
  - DELETE FROM exam_submissions
  - COMMIT
  ↓
Log violation count cleared to exam_actions.log
  ↓
Monitor modal auto-refreshes (30s) → violation count = 0
```

---

## Important Notes for Future Development

### Recent Changes Summary

**2026-04-19 (Evening) - Teacher Dashboard Migration to PHP**:

1. **New file** (`teacher/dashboard.php`):

   - PHP version with server-side authentication
   - Session configuration matching `exam_api.php` (SameSite=Lax, path=/)
   - `requireLogin('guru')` check before rendering
   - Session timer refresh on each load
   - Sidebar menu updated to reference `dashboard.php`

2. **Updated file** (`teacher/dashboard.html`):

   - Now redirects to `dashboard.php` via JavaScript + meta refresh
   - Visual feedback with spinner animation
   - Manual link fallback

3. **Why changed**:

   - Enable server-side authentication validation
   - Prevent unauthorized access to teacher dashboard
   - Consistent with student dashboard pattern (`student/dashboard.php`)
   - Better session management and timeout handling

4. **Breaking change**: None - backward compatible redirect preserves all bookmarks

**2026-04-19 (Morning) - Registration Pages Refactoring**:

1. **New shared CSS** (`css/register.css`) - Extracted all inline styles
2. **New shared JS** (`js/register-common.js`) - Registration utilities
3. **Refactored** `student/register.html` and `teacher/register.html` - Zero inline CSS/JS

**2026-04-18 - Security Hardening**:

1. POST-only exam.php with CSRF protection
2. Rate limiting (3 attempts per minute)
3. Clean URL redirect pattern
4. exam.html deprecated (redirects to dashboard)

**2026-04-16 - Previous Changes**:

1. Removed student blocking (no 3-strike force submit)
2. Security activates ONLY on exam start
3. Reset result clears both submissions AND violations
4. Monitor modal: search, auto-refresh, violation badges
5. Violation management for teachers and admins

### Critical Constraints

1. **Active exams cannot be deleted** (check in deleteExam function)
2. **Reset now deletes submissions AND violations** (changed from "keeps violations")
3. **Toleransi function is OBSOLETE** (students are no longer blocked/forced)
4. **Admin duplicates preserve original teacher_id**
5. **Session timeout: 2 hours** (refreshed on dashboard.php load)
6. **Security listeners attached ONLY after exam officially starts**
7. **exam.php requires POST then redirect** - Direct GET access → dashboard redirect
8. **CSRF tokens required** for all exam access forms
9. **Rate limiting active** - 3 failed attempts = 1 minute block
10. **Registration pages use shared CSS/JS** - No inline styles allowed
11. **Teacher dashboard now requires PHP** - dashboard.html redirects to dashboard.php

### Coding Conventions

**For Registration Pages**:

- Use `css/register.css` for all styles
- Use `js/register-common.js` for all functionality
- Never use inline styles or inline scripts beyond initialization
- Use `RegisterUI`, `RegisterValidation`, `RegisterAPI`, `RegisterWizard`

**For API Client**:

- All AJAX calls must use `ApiClient` (not raw fetch)
- Include credentials for session-based auth

**For PHP Pages**:

- Always call `session_set_cookie_params()` before `session_start()` with SameSite=Lax
- Include `require_once '../includes/auth.php'` and call `requireLogin($role)`
- Refresh `$_SESSION['login_time']` on authenticated page loads

### Log Files

- `logs/exam_actions.log` - All exam actions (activate, delete, reset with violation counts)
- `logs/ai_import.log` - AI extraction operations

---

## Security Headers Implemented

**exam.php** includes:

- `X-Frame-Options: DENY`
- `Content-Security-Policy: frame-ancestors 'none'`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`

---

## Last Updated

**Date**: 2026-04-19 (Evening)
**Developer**: Teacher dashboard migration to PHP with authentication
**Status**: Active development

---

## Session Recovery Notes

When returning to this project, review:

1. **`includes/csrf.php`** - CSRF token generation and validation
2. **`includes/auth.php`** - Rate limiting and authentication helpers
3. **`teacher/dashboard.php`** - NEW - Teacher dashboard with PHP auth
4. **`teacher/dashboard.html`** - UPDATED - Redirects to PHP version
5. **`student/exam.php`** - POST-only access pattern
6. **`student/dashboard.php`** - POST forms for exam access
7. **`js/security.js`** - Security starts ONLY on exam begin
8. **`js/exam-manager.js`** - Monitor modal with violation management
9. **`php/exam_api.php`** - API with session recovery and transaction-safe resets
10. **`js/register-common.js`** - Registration utilities
11. **`css/register.css`** - Registration styles
12. This PROJECT_MAP.md - For latest workflow understanding

## Breaking Changes for Future Development

1. **Never use GET for exam.php** - Always POST with CSRF token
2. **Always include CSRF token** in any form that accesses exam.php
3. **Rate limit is per exam per student** - Different exams have separate counters
4. **exam.html is deprecated** - Remove after confirming no external links remain
5. **Session key `active_exam_id`** is single-use (cleared after reading)
6. **Registration pages require shared CSS/JS** - No inline styles or scripts
7. **New registration pages must follow pattern** - Use `register-common.js` and `register.css`
8. **Teacher dashboard now served via PHP** - dashboard.html redirects; update any direct links to use dashboard.php
9. **Session cookie configuration must be consistent** - Always use SameSite=Lax, path=/, before session_start()
