# ExamSafe - Project Structure & Relationships Map

## Quick Overview

ExamSafe is a secure online exam platform (Admin, Teacher, Student roles).
**Tech**: PHP 7.4+, MySQL/MariaDB, HTML/CSS/JS, Quill.js (rich text), CSRF protection, Rate limiting, Session-based auth.

---

## Directory Structure (Essentials Only)

```
ExamSafe/
├── admin/ # Admin dashboard files
├── teacher/
│ ├── includes/ # Shared components (CRITICAL)
│ │ ├── init.php # ★ Session + auth + teacher data (required first)
│ │ ├── header.php # Navbar (needs init.php)
│ │ └── sidebar.php # Menu (needs init.php + $activePage)
│ ├── dashboard.php # ★ Main dashboard (pattern example)
│ ├── settings.php # Settings with API calls (pattern example)
│ ├── students.php # Student list, server-side data (pattern example)
│ ├── results.php # Results with API calls + chart.js (pattern example)
│ ├── create-exam.php # Exam creation/editing with uploads + Quill.js + draft support
│ └── \*.html # DEPRECATED - Redirect to .php versions
├── student/
│ ├── dashboard.php # Exam list with POST forms
│ ├── exam.php # ★ POST-only exam access (security critical)
│ └── register.html # Registration form
├── css/
│ ├── style.css # Global styles (modal + sidebar)
│ └── register.css # Registration styles
├── js/
│ ├── api-client.js # ★ API wrapper (use for all AJAX)
│ ├── toast.js # ★ Notifications (use showToast())
│ ├── utils.js # Shared utilities
│ ├── teacher-api.js # ★ Teacher API layer (all teacher endpoints)
│ ├── teacher-dashboard.js # Dashboard controller
│ ├── teacher-layout.js # Sidebar toggle (include on all teacher pages)
│ ├── draft-autosave.js # Auto-save to localStorage (30s debounce, Ctrl+S, beforeunload)
│ ├── draft-manager.js # Draft orchestrator (server save, recovery, dirty tracking)
│ ├── exam-manager.js # Exam management (renders draft cards with "Lanjutkan Edit")
│ ├── exam.js # Exam engine (timer, submission)
│ ├── security.js # Anti-cheat monitoring
│ └── register-common.js # Registration utilities
├── php/
│ ├── db.php # Database connection + sanitizeHTML()
│ ├── exam_api.php # ★ Main API (all roles)
│ ├── auth.php # Auth helpers + rate limiting
│ ├── upload_media.php # ★ Media upload with CSRF + rate limiting
│ ├── save_ai_settings.php # AI settings (CSRF protected)
│ ├── get_ai_settings.php # Fetch AI settings
│ ├── ai_import.php # AI extraction (Gemini)
│ ├── student_register.php # Student registration API
│ ├── register.php # Teacher registration API
│ ├── admin_api.php # Admin API
│ ├── logout.php # Logout handler
│ └── logs/ # exam_actions.log, ai_import.log
├── includes/
│ ├── csrf.php # ★ CSRF validation (2-arg function)
│ └── auth.php # Authentication + session helpers
├── uploads/
│ └── .htaccess # ★ Blocks PHP execution in upload directory
└── vendor/ # Composer dependencies
```

**★ = Start here (critical files)**

---

## Essential Functions Reference

### CSRF Protection (`includes/csrf.php`)

```php
$token = generateCSRFToken();           // Create/get token
verifyCSRFToken($posted, $session);     // Validate (2 args: posted token + $_SESSION['csrf_token'])
echo csrfField($token);                 // Hidden input HTML
```

**Key**: Token stored in `$_SESSION['csrf_token']`, regenerated after sensitive operations.

### Sanitization (`php/db.php`)

```php
sanitize($str);       // htmlspecialchars(strip_tags()) — for plain text (names, emails, types)
sanitizeHTML($str);   // strip_tags() with safe allowlist — for Quill rich text (question text, descriptions)
```

**Key**: Use `sanitize()` for plain text fields. Use `sanitizeHTML()` for content from Quill editors. Never use `sanitize()` on rich text — it destroys HTML tags.

### Authentication (`includes/auth.php`)

```php
requireLogin($role);              // Validate role (siswa/guru/admin), die if not
isLoggedIn();                      // Check if authenticated
checkExamRateLimit($examId);       // Enforce 3 attempts per 60 sec (per exam per student)
clearExamRateLimit($examId);       // Reset counter on success
```

### Teacher Page Template

Every teacher page follows this structure:

```php
<?php
require_once 'includes/init.php';
$activePage = 'dashboard'; // Match sidebar menu key
?>
<!DOCTYPE html>
<html>
<head>
    <title>Page Title</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <!-- Page content -->
    </main>
    </div>
    <script src="../js/teacher-layout.js"></script>
</body>
</html>
```

**Important**: `init.php` must be first include. It handles session config, auth, and fetches teacher data into `$teacherData` array.

---

## Two Page Patterns

### Pattern A: Read-Only Pages (fetch server-side)

**Use for**: Student lists, class data, read-only dashboards.
**Why**: No API call = instant load, no loading spinner.

```php
<?php
require_once 'includes/init.php';
$activePage = 'students';

// Fetch data server-side
$items = [];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM students WHERE class = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $items = $stmt->fetchAll();
} catch (Exception $e) {
    error_log($e->getMessage());
    $error = "Failed to load.";
}
?>
<!-- Render $items directly in HTML -->
<?php foreach ($items as $item): ?>
    <!-- Show item -->
<?php endforeach; ?>
```

**Sidebar active keys**: `dashboard`, `students`, `question-bank`, `results`, `create-exam`, `settings`

### Pattern B: Interactive Pages (fetch via API)

**Use for**: Forms that modify data, real-time updates, dynamic filtering.
**Why**: Can update without page refresh.

```php
<?php
require_once 'includes/init.php';
require_once '../includes/csrf.php';
$activePage = 'settings';
$csrf_token = generateCSRFToken();
?>
<!-- Render forms with CSRF token -->
<input type="hidden" id="csrf-token" value="<?php echo $csrf_token; ?>">

<script src="../js/api-client.js"></script>
<script src="../js/toast.js"></script>
<script src="../js/teacher-api.js"></script>
<script src="../js/teacher-layout.js"></script>
<script>
    // Use TeacherAPI methods (they throw errors, catch with try/catch + showToast)
    try {
        const data = await TeacherAPI.getExamInfo(examId);
        // handle data
    } catch (error) {
        showToast(error, 'error');
    }
</script>
```

---

## Critical Workflows

### Teacher Page Load

```
GET /teacher/students.php
  ↓ init.php: Session setup, teacher data fetch
  ↓ Set $activePage = 'students'
  ↓ Include header.php, sidebar.php
  ↓ Server-side query for student data
  ↓ Render HTML with data (no waiting for API)
```

### Exam Access (POST → Redirect → GET)

```
Click "Mulai Ujian" on student dashboard
  ↓ POST to exam.php with CSRF token + exam_id
  ↓ exam.php validates CSRF, checks rate limit
  ↓ Stores exam_id in $_SESSION['active_exam_id']
  ↓ Regenerates CSRF token (prevent replay)
  ↓ 302 redirect to exam.php (GET)
  ↓ GET exam.php retrieves exam_id from session
  ↓ Clears session variable (single-use)
  ↓ Shows exam (agreement → security → questions)
```

**Why**: Direct GET to exam.php is blocked. POST validates, then redirect ensures clean state.

### API Call with CSRF (e.g., save profile)

```
Click "Save" on settings form
  ↓ JavaScript reads CSRF token from hidden input
  ↓ POST to exam_api.php?action=update_profile
  ↓ Body: {csrf_token, name, email, ...}
  ↓ Server validates: verifyCSRFToken($token, $_SESSION['csrf_token'])
  ↓ If valid: Update DB, return success
  ↓ If invalid: Return 403 error
  ↓ JavaScript: showToast(response.message, response.success ? 'success' : 'error')
```

### Media Upload (with CSRF)

```
Select file on exam creation form
  ↓ JavaScript creates FormData
  ↓ Append file + csrf_token + exam_id
  ↓ POST to php/upload_media.php
  ↓ Server validates: CSRF token, file extension, MIME type, image dimensions
  ↓ If valid: Save to uploads/, return file path
  ↓ If invalid: Return error
  ↓ JavaScript: showToast() with result
```

### Rich Text (Quill.js) in create-exam.php

```
Teacher types question text in Quill editor
  ↓ Quill stores content as HTML in memory
  ↓ On publish/save: syncAllQuills() reads root.innerHTML from each instance
  ↓ POST to exam_api.php?action=create_exam with HTML in question text
  ↓ Server: sanitizeHTML() strips dangerous tags, keeps safe formatting
  ↓ Stored as HTML in DB (question_text, description)
  ↓ Student side: exam.js renders via innerHTML (pre-sanitized by server)
```

**Where Quill is used**: Exam description (Step 1), question text (Step 2), essay answer key (Step 2).

### Exam Draft Workflow

```
Teacher creates/edits exam on create-exam.php
  ↓ DraftAutoSave: auto-saves to localStorage every 30s + on Ctrl+S/beforeunload
  ↓ "Simpan Draft" button → DraftManager.saveDraftToServer() → TeacherAPI.saveDraft()
  ↓ POST exam_api.php?action=save_draft (CSRF required)
  ↓ Server: INSERT (new) or UPDATE (existing draft with status='draft')
  ↓ Returns exam_id → URL updates to ?edit=ID via pushState
  ↓ "Publikasikan" button → DraftManager.publishExam() → TeacherAPI.publishDraft()
  ↓ POST exam_api.php?action=publish_draft (CSRF required)
  ↓ Server: sets status='active', validates required fields + questions

Draft recovery on page load:
  ↓ If ?edit=ID: load from server via get_draft, then check localStorage
  ↓ If no ?edit: check localStorage for auto-save data
  ↓ If newer auto-save found: show recovery banner ("Pulihkan?" / "Abaikan")

Dashboard draft cards:
  ↓ ExamManager renders status='draft' with "✏️ Lanjutkan Edit" button
  ↓ Links to create-exam.php?edit=ID
```

---

## API Endpoints (exam_api.php)

| Action                   | Roles              | CSRF    | Purpose                                  |
| ------------------------ | ------------------ | ------- | ---------------------------------------- |
| `get_profile`            | guru, siswa        | No      | Fetch user profile                       |
| `update_profile`         | guru               | **Yes** | Save profile changes                     |
| `delete_bank_question`   | guru               | **Yes** | Delete question from bank                |
| `get_students`           | guru               | No      | Fetch teacher's students                 |
| `get_exam_monitor`       | guru, admin        | No      | Get exam participants + violation counts |
| `reset_student_result`   | guru, admin        | No      | Delete submission + violations           |
| `report_violation`       | siswa              | No      | Log security violation                   |
| `join_exam`              | siswa              | No      | Validate exam code                       |
| `start_exam`             | siswa              | No      | Initialize exam session                  |
| `get_teacher_stats`      | guru               | No      | Fetch total students + avg score         |
| `get_recent_violations`  | guru               | No      | Fetch latest violations                  |
| `get_exam_info`          | guru               | No      | Fetch exam details by ID                 |
| `get_results`            | guru, admin        | No      | Fetch exam results + statistics          |
| `get_student_violations` | guru, admin, siswa | No      | Fetch violations per student             |
| `get_submission_detail`  | guru               | No      | Fetch submission with answers            |
| `save_manual_grade`      | guru               | **Yes** | Save essay manual grading                |
| `save_draft`             | guru               | **Yes** | Create or update exam draft              |
| `get_draft`              | guru               | No      | Fetch draft exam + questions for editing |
| `publish_draft`          | guru               | **Yes** | Publish draft (status → active)          |

**Note**: All state-changing endpoints should require CSRF (create, update, delete). Currently `update_profile`, `delete_bank_question`, `save_manual_grade`, `save_draft`, and `publish_draft` have it. TODO: Add CSRF to remaining endpoints.

---

## User Notifications

**Always use `toast.js`** instead of `alert()` or custom modals:

```javascript
// Include script
<script src="../js/toast.js"></script>;

// Call function
showToast("Success message"); // Green (default)
showToast("Error occurred", "error"); // Red
showToast("FYI", "info"); // Blue
```

**Auto-dismisses after 3 seconds, supports stacking.**

---

## Key Constraints

**Cannot Do**:

- ❌ Delete active exams (only drafts)
- ❌ Use GET directly on exam.php (must POST first)
- ❌ Hardcode navbar/sidebar HTML (use includes)
- ❌ Call `get_profile` on dashboard (use server-side data)
- ❌ Use toleransi function (removed, obsolete)
- ❌ Use `sanitize()` on Quill rich text content (destroys HTML; use `sanitizeHTML()`)

**Must Always Do**:

- ✅ Set `$activePage` before including header/sidebar
- ✅ Use `verifyCSRFToken($posted, $_SESSION['csrf_token'])` (2 args)
- ✅ Include CSRF token in all state-changing forms/API calls
- ✅ Use `htmlspecialchars()` when outputting user data
- ✅ Use `sanitizeHTML()` for Quill-sourced content (question text, descriptions)
- ✅ Use `mb_*` functions for UTF-8 text
- ✅ Include `teacher-layout.js` on all teacher pages
- ✅ Use `showToast()` for all user feedback
- ✅ Use `TeacherAPI` methods instead of direct `fetch()` calls
- ✅ Handle API errors with try/catch and `showToast(error, 'error')`
- ✅ Set session cookie params before `session_start()`:

```php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

---

## File Dependencies Quick Map

```
Teacher pages depend on:
  teacher/includes/init.php
    → ../includes/auth.php
    → ../includes/db.php

API endpoints depend on:
  php/exam_api.php
    → includes/db.php (sanitize + sanitizeHTML)
    → includes/auth.php
    → includes/csrf.php

Media uploads depend on:
  php/upload_media.php
    → includes/db.php
    → ../includes/csrf.php
    → Rate limiting via $_SESSION

Student exam depends on:
  student/exam.php
    → includes/csrf.php
    → includes/auth.php
  js/exam.js
    → Renders question_text as innerHTML (server pre-sanitized)

Teacher JS modules:
  teacher-api.js → api-client.js
  teacher-results.js (if exists) → teacher-api.js
  teacher-dashboard.js → teacher-api.js

Draft module chain:
  draft-manager.js → draft-autosave.js, teacher-api.js, toast.js
  draft-autosave.js → (standalone, zero app dependencies)

create-exam.php depends on:
  → Quill CDN (quilljs.com 1.3.7)
  → quillInstances Map for lifecycle management
  → syncAllQuills() before publish/draft/preview
  → draft-autosave.js + draft-manager.js
  → collectAllFormData() gathers Step 1 + Step 3 + questions
  → populateFormFromDraft() restores form from server/auto-save data
  → ?edit=ID query param loads existing draft via get_draft API
```

---

## Session Recovery Checklist

When resuming work, review in this order:

1. **`includes/csrf.php`** - CSRF token functions (2-arg verify)
2. **`includes/auth.php`** - Rate limiting + auth helpers
3. **`teacher/includes/init.php`** - Shared teacher initialization
4. **`php/db.php`** - DB connection, `sanitize()`, `sanitizeHTML()`
5. **`php/exam_api.php`** - Main API endpoints (uses `sanitizeHTML` for rich text)
6. **`php/upload_media.php`** - Media upload with CSRF
7. **`js/toast.js`** - User notification system
8. **`js/api-client.js`** - Base API wrapper
9. **`js/teacher-api.js`** - Teacher API layer (all teacher endpoints)
10. **`js/draft-autosave.js`** - Auto-save to localStorage (standalone)
11. **`js/draft-manager.js`** - Draft orchestrator (server save, recovery, dirty tracking)
12. **`js/exam.js`** - Renders question text via innerHTML (not escapeHtml)
13. **`teacher/create-exam.php`** - Quill.js + draft integration (?edit=ID mode)
14. **`teacher/students.php`** - Server-side data pattern
15. **`teacher/settings.php`** - API-based pattern with CSRF
16. **`teacher/results.php`** - API-based pattern with chart.js + manual grading
17. **`student/exam.php`** - POST-only access pattern
18. **`student/dashboard.php`** - POST form pattern for exam access

---

## Recent Changes

**2026-04-23**: Implemented complete exam draft feature. Added `save_draft`, `get_draft`, `publish_draft` API endpoints with CSRF. Created `draft-autosave.js` (localStorage auto-save, 30s debounce, Ctrl+S) and `draft-manager.js` (orchestrator with server save, recovery banner, dirty tracking, URL pushState). Updated `create-exam.php` with `?edit=ID` mode, `collectAllFormData()`, `populateFormFromDraft()`, recovery banner, last-saved indicator. Fixed `createExam()` and `duplicateExam()` to save `shuffle_questions`, `shuffle_options`, `passing_score`, `max_violations`, `security_settings`. Updated `exam-manager.js` to render draft cards with "✏️ Lanjutkan Edit" button. Added `sql/migration_draft.sql` for `security_settings` column.

**2026-04-22**: Integrated Quill.js rich text editor into create-exam.php, updated sanitizeHTML() to protect against XSS when rendering rich text content, and updated student/exam.js to safely render pre-sanitized question content via innerHTML.

**2026-04-21**: Refactored results.php to use TeacherAPI modular methods. Added getExamInfo, getResults, getStudentViolations, getSubmissionDetail, saveManualGrade to teacher-api.js. All TeacherAPI methods now throw errors for caller to handle with toast notifications.

**2026-04-20**: Fixed deleteBankQuestion ID retrieval + CSRF validation. Added media upload security. Extracted dashboard JS modules (toast, teacher-api, teacher-dashboard).

**2026-04-19**: Migrated students.php and settings.php to PHP with shared components. Added toast system. Refactored teacher layout.

**2026-04-18**: Security hardening - POST-only exam.php, CSRF tokens, rate limiting.

---

## Last Updated

**Date**: 2026-04-23  
**Focus**: Complete exam draft feature (server-side storage, auto-save, recovery, edit mode)  
**Status**: Active development
