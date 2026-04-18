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
│   ├── includes/         # NEW - Shared teacher components
│   │   ├── init.php      # Shared auth + teacher data fetch
│   │   ├── header.php    # Reusable navbar
│   │   └── sidebar.php   # Reusable sidebar with active page support
│   ├── dashboard.php     # Main teacher dashboard (uses shared includes)
│   ├── dashboard.html    # REDIRECTS to dashboard.php (deprecated)
│   ├── create-exam.html  # Create/edit exams (to be migrated)
│   ├── question-bank.html # Manage question bank (to be migrated)
│   ├── results.html      # View exam results (to be migrated)
│   ├── students.html     # Manage students (to be migrated)
│   ├── register.html     # Teacher registration (refactored)
│   └── settings.html     # Teacher settings (API keys)
├── student/              # Student exam interface
│   ├── dashboard.php     # Student dashboard (POST forms for exam access)
│   ├── exam.php          # Take exam interface (POST-only access)
│   ├── exam.html         # REDIRECTS to dashboard.php (deprecated)
│   └── register.html     # Student registration (refactored)
├── css/
│   ├── style.css         # Global styles (includes modal + sidebar styles)
│   └── register.css      # Shared registration styles
├── js/
│   ├── api-client.js     # SHARED - Base API client wrapper
│   ├── student-api.js    # Student-specific API endpoints
│   ├── register-common.js # SHARED - Registration utilities
│   ├── exam-manager.js   # SHARED - Exam management logic
│   ├── exam.js           # Exam engine (timer, answers, submission)
│   ├── security.js       # Anti-cheat monitoring (attaches on exam start)
│   └── teacher-layout.js # NEW - Shared sidebar toggle for teacher pages
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

### 3. **teacher/includes/init.php** (NEW - Shared Initialization)

**Purpose**: Centralized session config, authentication, and teacher data fetching for all teacher pages

**Key Features**:

- Session cookie configuration (SameSite=Lax, path=/)
- Session start with proper checks
- `requireLogin('guru')` authentication
- Session timer refresh
- Database query for teacher data (full_name, gelar, subject)
- Builds `$teacherData` array with:
  - `full_name`, `gelar`, `full_name_with_gelar`, `subject`, `avatar_initial`
- Error logging with session fallback
- Sets default `$activePage` if not defined

**Usage Pattern**:

```php
<?php
require_once 'includes/init.php';
$activePage = 'dashboard'; // or 'exams', 'students', etc.
?>
```

---

### 4. **teacher/includes/header.php** (NEW - Reusable Navbar)

**Purpose**: Shared navbar component for all teacher pages

**Dependencies**: Requires `$teacherData` array from `init.php`

**Contents**:

- Navbar with brand, user avatar, name, and logout button
- Hamburger button for mobile (no JS - handled by teacher-layout.js)

---

### 5. **teacher/includes/sidebar.php** (NEW - Reusable Sidebar)

**Purpose**: Shared sidebar component with active page highlighting

**Dependencies**: Requires `$teacherData` array and `$activePage` variable from `init.php`

**Features**:

- Avatar section with teacher name
- Menu items with conditional `active` class based on `$activePage`
- Wraps main content opening tag (requires closing `</main></div>` in parent)

**Menu Items**:

- Dashboard (`dashboard`)
- Buat Ujian Baru (`create-exam`)
- Bank Soal (`question-bank`)
- Hasil Ujian (`results`)
- Data Siswa (`students`)
- Pengaturan (`settings`)

---

### 6. **js/teacher-layout.js** (NEW - Shared Sidebar Toggle)

**Purpose**: Centralized sidebar toggle functionality for all teacher pages

**Features**:

- Hamburger button click handler
- Sidebar overlay click handler
- Auto-close sidebar on mobile when menu link clicked
- Uses CSS classes: `sidebar-open`, `sidebar-overlay-visible`

**Integration**: Include after `exam-manager.js` in any teacher page

---

### 7. **teacher/dashboard.php** (UPDATED - Uses Shared Components)

**Purpose**: Main teacher dashboard with server-side authentication and data injection

**Changes**:

- Now uses `require_once 'includes/init.php'` instead of inline session/auth/db code
- Sets `$activePage = 'dashboard'` before includes
- Uses `include 'includes/header.php'` and `include 'includes/sidebar.php'`
- Removed inline navbar and sidebar HTML (70+ lines removed)
- Page-specific styles remain (exam-card, quick-actions, skeleton loader)
- Includes `teacher-layout.js` for sidebar functionality
- Modal styles removed (already in style.css)

**Key Features** (unchanged):

- Database query to fetch teacher data (full_name, gelar, subject)
- UTF-8 multibyte support
- Error logging with fallbacks
- No `get_profile` API call

**Data Flow**:

```
Teacher accesses dashboard.php
  ↓
init.php: Session validation + DB fetch for teacher data
  ↓
dashboard.php: Sets $activePage = 'dashboard'
  ↓
Includes header.php and sidebar.php (use $teacherData)
  ↓
JavaScript: Loads stats, exams, violations
  ↓
All data displayed
```

---

### 8. **teacher/dashboard.html** (Redirector - Unchanged)

**Purpose**: Legacy file that redirects to new PHP version

**Behavior**:

- JavaScript redirect (primary) to `dashboard.php`
- Meta refresh fallback (0 seconds)
- Visual feedback with spinner animation

---

### 9. **student/exam.php** (POST-Only Access)

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

---

### 10. **student/dashboard.php** (POST Forms)

**Purpose**: Student dashboard with POST-based exam access

**Key Features**:

- Includes `csrf.php` and generates token via `generateCSRFToken()`
- All "Mulai Ujian" buttons converted to POST forms

---

### 11. **js/api-client.js** (Base API Client)

**Purpose**: Shared API wrapper for all AJAX calls

**Key Methods**:

- `ApiClient.request()` - Generic request handler
- `ApiClient.get()` / `ApiClient.post()` - HTTP method shortcuts

---

### 12. **js/register-common.js** (Registration Module)

**Purpose**: Shared utilities for registration pages

**Modules**: `RegisterUI`, `RegisterValidation`, `RegisterAPI`, `RegisterWizard`

---

### 13. **css/register.css** (Registration Styles)

**Purpose**: Shared styles for registration pages (utility-first, responsive)

---

### 14. **js/exam-manager.js** (SHARED MODULE)

**Purpose**: Centralized exam management for admin and teacher dashboards

**Key Methods**: `fetchExams()`, `activateExam()`, `deactivateExam()`, `deleteExam()`, `duplicateExam()`, `showMonitor()`, `resetStudentResult()`

---

### 15. **js/security.js** (Student Exam Security)

**Purpose**: Anti-cheat monitoring that ONLY activates when exam officially starts

**Key Behavior**: `init()` attaches no listeners; `start()` attaches all security listeners

---

### 16. **php/exam_api.php** (SHARED API)

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
| `get_profile` | Guru, Siswa | Fetch user profile data (still used by settings.html) |
| `get_recent_violations` | Guru | Fetch latest 10 violations for teacher's exams |
| `get_classes` | Public | Fetch list of classes |
| `get_subjects` | Public | Fetch list of subjects |

**Note**: `get_profile` endpoint is preserved for other pages (settings.html) but no longer used by dashboard.php

**Session Recovery**: API auto-recovers sessions when `$_SESSION['role']` missing but `user_id` exists

**Logging**: `logExamAction()` writes to `logs/exam_actions.log`

---

## Key Workflows

### 1. Teacher Dashboard Load Flow (UPDATED - Uses Shared Components)

```
Teacher accesses dashboard.php
  ↓
init.php: Session validation + DB fetch for teacher data
  ↓
dashboard.php: Sets $activePage = 'dashboard'
  ↓
header.php + sidebar.php: Render with injected teacher data
  ↓
JavaScript: ExamManager.fetchExams() → GET exam_api.php?action=get_exams
  ↓
JavaScript: Fetch teacher stats → GET exam_api.php?action=get_teacher_stats
  ↓
JavaScript: Fetch violations → GET exam_api.php?action=get_recent_violations
  ↓
All data displayed; NO get_profile API call
```

### 2. Future Teacher Page Creation Pattern

```php
<?php
require_once 'includes/init.php';
$activePage = 'page-name'; // Must match sidebar menu item
?>
<!DOCTYPE html>
<html>
<head>
    <!-- Page-specific meta, title, CSS -->
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <!-- Page content here -->
    </main>
    <script src="../js/teacher-layout.js"></script>
    <!-- Page-specific scripts -->
</body>
</html>
```

### 3. Student Registration Flow

```
Student loads register.html → RegisterAPI.fetchClasses() → Form validation → Submit → Success
```

### 4. Teacher Registration Flow (Step Wizard)

```
Teacher loads register.html → RegisterWizard.init(3) → Step 1-2-3 → Submit → Success
```

### 5. Student Exam Access Flow (POST-only with CSRF)

```
POST form to exam.php → CSRF + rate limit → Store exam_id in session → Redirect → Exam begins
```

### 6. Reset Student Result Flow

```
Teacher clicks Reset → Confirm → DELETE transactions → Log → Auto-refresh monitor
```

---

## Important Notes for Future Development

### Recent Changes Summary

**2026-04-19 (Night) - Teacher Layout Refactoring for Reusability**:

1. **Created shared initialization** (`teacher/includes/init.php`):

   - Centralized session config, auth, teacher data fetch
   - Returns `$teacherData` array and sets `$activePage`
   - Enables consistent teacher page setup

2. **Created reusable components**:

   - `teacher/includes/header.php` - Navbar component
   - `teacher/includes/sidebar.php` - Sidebar with active page highlighting
   - `js/teacher-layout.js` - Shared sidebar toggle logic

3. **Refactored dashboard.php**:

   - Replaced inline session/auth/db code with `init.php`
   - Removed navbar/sidebar HTML (70+ lines)
   - Now uses includes for header and sidebar
   - Modal styles moved to global style.css

4. **Why changed**: Enable code reuse across all teacher pages, reduce duplication, maintain consistent layout, simplify future teacher page creation

**2026-04-19 (Late Evening) - Teacher Dashboard Optimization**:

1. **Removed `get_profile` API call** from dashboard.php
2. **Added server-side teacher data fetching**:
   - Database query for `full_name`, `gelar`, `subject`
   - UTF-8 multibyte support (`mb_substr`, `mb_strtoupper`)
   - Error logging with fallbacks (never breaks UI)
3. **Direct HTML injection**:
   - Navbar avatar and name rendered server-side
   - Sidebar avatar and name rendered server-side
   - Welcome subtitle rendered server-side
4. **JavaScript cleanup**: Removed profile fetch block, added `escapeHtml()` helper for XSS prevention
5. **Why changed**: Eliminate unnecessary API call, improve perceived performance (no "Memuat..." flicker), reduce server load

**2026-04-19 (Evening) - Teacher Dashboard Migration to PHP**:

1. **New file** (`teacher/dashboard.php`) - PHP version with server-side authentication
2. **Updated file** (`teacher/dashboard.html`) - Redirects to PHP version
3. **Why changed**: Enable server-side authentication, consistent with student dashboard pattern

**2026-04-19 (Morning) - Registration Pages Refactoring**:

1. **New shared CSS** (`css/register.css`)
2. **New shared JS** (`js/register-common.js`)
3. **Refactored** registration pages - Zero inline CSS/JS

**2026-04-18 - Security Hardening**:

1. POST-only exam.php with CSRF protection
2. Rate limiting (3 attempts per minute)
3. Clean URL redirect pattern

**2026-04-16 - Previous Changes**:

1. Removed student blocking
2. Security activates ONLY on exam start
3. Reset result clears submissions AND violations
4. Monitor modal improvements
5. Violation management

### Critical Constraints

1. **Active exams cannot be deleted**
2. **Reset deletes submissions AND violations**
3. **Toleransi function is OBSOLETE**
4. **Admin duplicates preserve original teacher_id**
5. **Session timeout: 2 hours** (refreshed on dashboard.php load)
6. **Security listeners attached ONLY after exam officially starts**
7. **exam.php requires POST then redirect** - Direct GET access → dashboard redirect
8. **CSRF tokens required** for all exam access forms
9. **Rate limiting active** - 3 failed attempts = 1 minute block
10. **Registration pages use shared CSS/JS** - No inline styles allowed
11. **Teacher dashboard now requires PHP** - dashboard.html redirects to dashboard.php
12. **Dashboard uses server-side teacher data** - No `get_profile` call on dashboard
13. **Teacher pages must use shared components** - New teacher pages should follow pattern with `init.php`, header/sidebar includes, and `teacher-layout.js`

### Coding Conventions

**For Teacher Pages**:

- Start with `require_once 'includes/init.php'`
- Set `$activePage` before any output
- Use `include 'includes/header.php'` and `include 'includes/sidebar.php'`
- Include `../js/teacher-layout.js` for sidebar functionality
- Page-specific styles go in `<style>` block (not inline)
- Use `$teacherData` array for teacher info (never fetch via API)

**For Registration Pages**:

- Use `css/register.css` and `js/register-common.js`
- Never use inline styles or inline scripts

**For API Client**:

- All AJAX calls must use `ApiClient`
- Include credentials for session-based auth

**For PHP Pages**:

- Call `session_set_cookie_params()` before `session_start()` with SameSite=Lax
- Include `require_once '../includes/auth.php'` and call `requireLogin($role)`
- Refresh `$_SESSION['login_time']` on authenticated page loads
- **Fetch user data server-side when possible** to reduce API calls
- Use `mb_*` functions for UTF-8 multibyte character handling
- Always use `htmlspecialchars()` for output escaping
- Log database errors but never expose to users

### Log Files

- `logs/exam_actions.log` - Exam actions (activate, delete, reset)
- `logs/ai_import.log` - AI extraction operations
- PHP error_log - Database query failures and fallback events

---

## Security Headers Implemented

**exam.php** includes:

- `X-Frame-Options: DENY`
- `Content-Security-Policy: frame-ancestors 'none'`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`

---

## Last Updated

**Date**: 2026-04-19 (Night)
**Developer**: Teacher layout refactoring - shared components architecture
**Status**: Active development

---

## Session Recovery Notes

When returning to this project, review:

1. **`includes/csrf.php`** - CSRF token generation and validation
2. **`includes/auth.php`** - Rate limiting and authentication helpers
3. **`teacher/includes/init.php`** - NEW - Shared teacher initialization
4. **`teacher/includes/header.php`** - NEW - Reusable navbar
5. **`teacher/includes/sidebar.php`** - NEW - Reusable sidebar
6. **`teacher/dashboard.php`** - UPDATED - Uses shared components
7. **`teacher/dashboard.html`** - Redirects to PHP version
8. **`js/teacher-layout.js`** - NEW - Shared sidebar toggle
9. **`student/exam.php`** - POST-only access pattern
10. **`student/dashboard.php`** - POST forms for exam access
11. **`js/security.js`** - Security starts ONLY on exam begin
12. **`js/exam-manager.js`** - Monitor modal with violation management
13. **`php/exam_api.php`** - API with session recovery (get_profile still available)
14. **`js/register-common.js`** - Registration utilities
15. **`css/register.css`** - Registration styles
16. **`css/style.css`** - Global styles (includes modal + sidebar)
17. This PROJECT_MAP.md - For latest workflow understanding

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
10. **Dashboard no longer calls `get_profile`** - Server-side data injection instead; settings.html still uses API
11. **New teacher pages MUST use shared components** - Include `init.php`, header/sidebar, and teacher-layout.js
12. **Teacher pages must set `$activePage`** - Matches sidebar menu item for highlighting
13. **Never hardcode navbar/sidebar HTML** - Always use includes from `teacher/includes/`
