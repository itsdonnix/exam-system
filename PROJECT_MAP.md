# ExamSafe - Project Structure & Relationships Map

## Quick Overview

ExamSafe is a secure online exam platform (Admin, Teacher, Student roles).
**Tech**: PHP 7.4+, MySQL/MariaDB, HTML/CSS/JS, CSRF protection, Rate limiting, Session-based auth.

---

## Directory Structure (Essentials Only)

```
ExamSafe/
├── admin/                      # Admin dashboard files
├── teacher/
│   ├── includes/               # Shared components (CRITICAL)
│   │   ├── init.php           # ★ Session + auth + teacher data (required first)
│   │   ├── header.php         # Navbar (needs init.php)
│   │   └── sidebar.php        # Menu (needs init.php + $activePage)
│   ├── dashboard.php          # ★ Main dashboard (pattern example)
│   ├── settings.php           # Settings with API calls (pattern example)
│   ├── students.php           # Student list, server-side data (pattern example)
│   ├── create-exam.php        # Exam creation with uploads
│   └── *.html                 # DEPRECATED - Redirect to .php versions
├── student/
│   ├── dashboard.php          # Exam list with POST forms
│   ├── exam.php               # ★ POST-only exam access (security critical)
│   └── register.html          # Registration form
├── css/
│   ├── style.css              # Global styles (modal + sidebar)
│   └── register.css           # Registration styles
├── js/
│   ├── api-client.js          # ★ API wrapper (use for all AJAX)
│   ├── toast.js               # ★ Notifications (use showToast())
│   ├── utils.js               # Shared utilities
│   ├── teacher-api.js         # Teacher API layer
│   ├── teacher-dashboard.js   # Dashboard controller
│   ├── teacher-layout.js      # Sidebar toggle (include on all teacher pages)
│   ├── exam-manager.js        # Exam management
│   ├── exam.js                # Exam engine (timer, submission)
│   ├── security.js            # Anti-cheat monitoring
│   └── register-common.js     # Registration utilities
├── php/
│   ├── db.php                 # Database connection
│   ├── exam_api.php           # ★ Main API (all roles)
│   ├── auth.php               # Auth helpers + rate limiting
│   ├── upload_media.php       # ★ Media upload with CSRF + rate limiting
│   ├── save_ai_settings.php   # AI settings (CSRF protected)
│   ├── get_ai_settings.php    # Fetch AI settings
│   ├── ai_import.php          # AI extraction (Gemini)
│   ├── student_register.php   # Student registration API
│   ├── register.php           # Teacher registration API
│   ├── admin_api.php          # Admin API
│   ├── logout.php             # Logout handler
│   └── logs/                  # exam_actions.log, ai_import.log
├── includes/
│   ├── csrf.php               # ★ CSRF validation (2-arg function)
│   └── auth.php               # Authentication + session helpers
├── uploads/
│   └── .htaccess              # ★ Blocks PHP execution in upload directory
└── vendor/                    # Composer dependencies
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
<script src="../js/teacher-layout.js"></script>
<script>
    // Fetch data via API with CSRF tokens in POST requests
    // Use showToast(message, type) for user feedback (success/error/info)
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

---

## API Endpoints (exam_api.php)

| Action | Roles | CSRF | Purpose |
|--------|-------|------|---------|
| `get_profile` | guru, siswa | No | Fetch user profile |
| `update_profile` | guru | **Yes** | Save profile changes |
| `delete_bank_question` | guru | **Yes** | Delete question from bank |
| `get_students` | guru | No | Fetch teacher's students |
| `get_exam_monitor` | guru, admin | No | Get exam participants + violation counts |
| `reset_student_result` | guru, admin | No | Delete submission + violations |
| `report_violation` | siswa | No | Log security violation |
| `join_exam` | siswa | No | Validate exam code |
| `start_exam` | siswa | No | Initialize exam session |
| `get_teacher_stats` | guru | No | Fetch total students + avg score |
| `get_recent_violations` | guru | No | Fetch latest violations |

**Note**: All state-changing endpoints should require CSRF (create, update, delete). Currently only `update_profile` and `delete_bank_question` have it. TODO: Add CSRF to remaining endpoints.

---

## User Notifications

**Always use `toast.js`** instead of `alert()` or custom modals:

```javascript
// Include script
<script src="../js/toast.js"></script>

// Call function
showToast("Success message");                    // Green (default)
showToast("Error occurred", "error");            // Red
showToast("FYI", "info");                        // Blue
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

**Must Always Do**:
- ✅ Set `$activePage` before including header/sidebar
- ✅ Use `verifyCSRFToken($posted, $_SESSION['csrf_token'])` (2 args)
- ✅ Include CSRF token in all state-changing forms/API calls
- ✅ Use `htmlspecialchars()` when outputting user data
- ✅ Use `mb_*` functions for UTF-8 text
- ✅ Include `teacher-layout.js` on all teacher pages
- ✅ Use `showToast()` for all user feedback
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
    → includes/db.php
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
```

---

## Session Recovery Checklist

When resuming work, review in this order:

1. **`includes/csrf.php`** - CSRF token functions (2-arg verify)
2. **`includes/auth.php`** - Rate limiting + auth helpers
3. **`teacher/includes/init.php`** - Shared teacher initialization
4. **`php/exam_api.php`** - Main API endpoints
5. **`php/upload_media.php`** - Media upload with CSRF
6. **`js/toast.js`** - User notification system
7. **`teacher/students.php`** - Server-side data pattern
8. **`teacher/settings.php`** - API-based pattern with CSRF
9. **`student/exam.php`** - POST-only access pattern
10. **`student/dashboard.php`** - POST form pattern for exam access

---

## Recent Changes

**2026-04-20**: Fixed deleteBankQuestion ID retrieval + CSRF validation. Added media upload security. Extracted dashboard JS modules (toast, teacher-api, teacher-dashboard).

**2026-04-19**: Migrated students.php and settings.php to PHP with shared components. Added toast system. Refactored teacher layout.

**2026-04-18**: Security hardening - POST-only exam.php, CSRF tokens, rate limiting.

---

## Last Updated

**Date**: 2026-04-20  
**Focus**: Cleaner documentation, essential information only, straightforward patterns  
**Status**: Active development
