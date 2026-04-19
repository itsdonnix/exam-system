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

## Quick Reference: Common Tasks

| Task                    | Key Files                                                                     | Notes                                                                              |
| ----------------------- | ----------------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Create new teacher page | `teacher/includes/init.php`, `header.php`, `sidebar.php`, `teacher-layout.js` | Use the pattern in `teacher/settings.php` or `teacher/students.php`                |
| Add CSRF protection     | `includes/csrf.php`                                                           | Call `generateCSRFToken()` and `verifyCSRFToken($posted, $_SESSION['csrf_token'])` |
| Add rate limiting       | `includes/auth.php`                                                           | Use `checkExamRateLimit($examId)` and `clearExamRateLimit($examId)`                |
| Create API endpoint     | `php/exam_api.php`                                                            | Add action handler, check role, add CSRF if state-changing                         |
| Debug session issues    | `teacher/includes/init.php`, `includes/auth.php`                              | Check `$_SESSION['role']`, `user_id`, `login_time`                                 |
| Handle exam access      | `student/exam.php`, `student/dashboard.php`                                   | Always POST with CSRF, then redirect to GET                                        |
| Fetch static data       | Server-side PHP in teacher page                                               | Use server-side DB query instead of API call when data doesn't change frequently   |
| Show user notifications | Include `../js/toast.js`, call `showToast(message, type)`                     | Type: 'success' (default), 'error', 'info'                                         |

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
├── teacher/              # Teacher dashboard & pages
│   ├── includes/         # Shared teacher components (CRITICAL)
│   │   ├── init.php      # ★ Start here - Session + auth + teacher data
│   │   ├── header.php    # Reusable navbar (depends on init.php)
│   │   └── sidebar.php   # Reusable sidebar (depends on init.php + $activePage)
│   ├── dashboard.php     # Main teacher dashboard (uses shared includes)
│   ├── dashboard.html    # REDIRECTS to dashboard.php (deprecated)
│   ├── settings.php      # Teacher settings with CSRF (uses shared includes)
│   ├── settings.html     # REDIRECTS to settings.php (deprecated)
│   ├── students.php      # ★ Student list (server-side data, uses shared includes)
│   ├── students.html     # REDIRECTS to students.php (deprecated)
│   ├── create-exam.php   # Create/edit exams (migrated to PHP with CSRF uploads)
│   ├── question-bank.html # Manage question bank (to be migrated)
│   ├── results.html      # View exam results (to be migrated)
│   └── register.html     # Teacher registration (refactored)
├── student/              # Student exam interface
│   ├── dashboard.php     # Student dashboard (POST forms for exam access)
│   ├── exam.php          # ★ Take exam interface (POST-only access with CSRF)
│   ├── exam.html         # REDIRECTS to dashboard.php (deprecated)
│   └── register.html     # Student registration (refactored)
├── css/
│   ├── style.css         # Global styles (modal + sidebar included)
│   └── register.css      # Registration form styles (shared)
├── js/
│   ├── api-client.js     # ★ Base API client wrapper (use for all AJAX)
│   ├── student-api.js    # Student-specific API endpoints
│   ├── register-common.js # Shared registration utilities
│   ├── exam-manager.js   # Exam management logic (monitor modal, violations)
│   ├── exam.js           # Exam engine (timer, answers, submission)
│   ├── security.js       # Anti-cheat monitoring (attaches on exam start)
│   ├── toast.js          # ★ Toast notification system (use for all user feedback)
│   ├── utils.js          # ★ Shared utilities (escapeHtml, formatDate, formatTime)
│   ├── teacher-api.js    # ★ Teacher API layer (stats, violations)
│   ├── teacher-dashboard.js # ★ Dashboard controller (stats, violations, monitor)
│   └── teacher-layout.js # Sidebar toggle (include on all teacher pages)
├── php/
│   ├── db.php           # Database connection
│   ├── exam_api.php     # ★ Main API - exam operations for all roles (CSRF on state-change)
│   ├── admin_api.php    # Admin-specific API
│   ├── auth.php         # Authentication + rate limiting helpers
│   ├── logout.php       # Logout handler
│   ├── ai_import.php    # AI question extraction (Gemini)
│   ├── get_ai_settings.php # Fetch teacher AI settings
│   ├── save_ai_settings.php # Save teacher AI settings (CSRF protected)
│   ├── upload_media.php # ★ Media upload with CSRF, rate limiting, and image validation
│   ├── student_register.php # Student registration API
│   ├── register.php     # Teacher registration API
│   └── logs/            # Log files directory
│       ├── ai_import.log    # AI extraction logs
│       └── exam_actions.log # Exam actions (reset, delete, CSRF fails, etc.)
├── includes/            # Shared PHP utilities
│   ├── auth.php         # Authentication helpers + rate limiting
│   └── csrf.php         # ★ CSRF token generation & validation (2-arg verify)
├── uploads/             # Uploaded media files
│   └── .htaccess        # ★ Security: prevents PHP execution, sets headers
└── vendor/              # Composer dependencies
```

**★ = Critical files to understand first**

---

## Toast Notification System

**Purpose**: Unified user feedback across all pages (success, error, info messages)

**File**: `js/toast.js`

**Usage**:

```javascript
// Include the script
<script src="../js/toast.js"></script>;

// Call the function
showToast("Pesan berhasil disimpan"); // success (green)
showToast("Terjadi kesalahan", "error"); // error (red)
showToast("Informasi untuk Anda", "info"); // info (blue)
```

**Features**:

- Auto-dismiss after 3 seconds
- Smooth fade-in/fade-out animations
- Stackable (multiple toasts appear stacked)
- Accessible (aria-live region)
- Consistent styling across all pages

**When to use**:

- ✅ API success/error responses
- ✅ Form validation feedback
- ✅ CSRF validation failures
- ✅ Rate limit exceeded messages
- ✅ Copy/paste operations
- ❌ NOT for critical errors that block page functionality (use inline error messages)

**Example in teacher page**:

```php
// In settings.php - include toast.js
<script src="../js/toast.js"></script>

// In JavaScript
fetch("../php/exam_api.php", {
    method: "POST",
    body: JSON.stringify(data)
})
.then(r => r.json())
.then(d => {
    if (d.success) {
        showToast(d.message);
    } else {
        showToast("Error: " + d.message, "error");
    }
});
```

---

## Core Files & Dependencies

### Authentication & Security Layer

#### **includes/csrf.php** (CSRF Protection)

**Purpose**: Prevent Cross-Site Request Forgery on all state-changing operations

**Key Functions**:
| Function | Arguments | Purpose |
|----------|-----------|---------|
| `generateCSRFToken()` | None | Creates/retrieves session-based token |
| `verifyCSRFToken($token, $sessionToken)` | 2 required | Validates token using hash_equals() |
| `csrfField($token)` | 1 | Generates hidden input HTML |

**Critical Points**:

- Tokens stored in `$_SESSION['csrf_token']`
- `verifyCSRFToken()` requires **exactly 2 arguments**: posted token + `$_SESSION['csrf_token']`
- Regenerated after successful exam access (prevents replay attacks)
- Token included in all forms that modify data (settings, profile, exam access)

**Example Usage**:

```php
$csrf_token = generateCSRFToken();
// In form: <input type="hidden" value="<?php echo $csrf_token; ?>">
// In PHP: if (!verifyCSRFToken($input['csrf_token'], $_SESSION['csrf_token'])) die("CSRF failed");
```

---

#### **includes/auth.php** (Authentication & Rate Limiting)

**Purpose**: Session management + exam access rate limiting

**Key Functions**:
| Function | Purpose |
|----------|---------|
| `isLoggedIn()` | Check if user has valid session |
| `requireLogin($role)` | Ensure user logged in with specific role (siswa/guru/admin) |
| `setSession($userId, $role)` | Create authenticated session |
| `clearSession()` | Destroy session (logout) |
| `isSessionExpired()` | Check if 2-hour timeout exceeded |
| `checkExamRateLimit($examId)` | Enforce 3 attempts per 60 seconds per exam per student |
| `clearExamRateLimit($examId)` | Reset counter on successful exam access |

**Rate Limiting Details**:

- Attempts 1-3 within 60 seconds: Allowed
- After 3 failures: 60-second block
- Block message stored in `$_SESSION['rate_limit_error']`
- Counter tracked per exam per student (different exams = separate counters)

---

#### **php/upload_media.php** (Secure Media Upload)

**Purpose**: Handle image uploads for exam questions with comprehensive security

**Security Features**:

- CSRF token validation
- Rate limiting (50 uploads per hour per user)
- File extension whitelist (.jpg, .jpeg, .png, .gif, .webp)
- MIME type validation using finfo_file() (not client-supplied)
- Image integrity check via getimagesize()
- Dimension limits (max 4096x4096 pixels)
- Optional EXIF metadata stripping (if GD extension available)
- Secure file permissions (0644)
- Detailed error logging

**Usage Example** (JavaScript):

```javascript
const formData = new FormData();
formData.append("file", file);
formData.append("csrf_token", csrfToken);
fetch("../php/upload_media.php", {
  method: "POST",
  body: formData,
  credentials: "include",
});
```

**Rate Limiting**: 50 uploads per hour per user (stored in session)

---

#### **uploads/.htaccess** (Upload Directory Security)

**Purpose**: Prevent script execution and add security headers to uploads directory

**Security Controls**:

- Blocks PHP, CGI, and other script execution
- Sets X-Content-Type-Options: nosniff
- Sets Content-Security-Policy for images only
- Disables directory browsing
- Only allows access to image files

---

### Teacher Page Initialization Layer

#### **teacher/includes/init.php** (Shared Teacher Initialization - CRITICAL)

**Purpose**: Centralized session setup, authentication, and teacher data for all teacher pages

**Must be first include** in every teacher page:

```php
<?php
require_once 'includes/init.php';
$activePage = 'dashboard'; // or 'create-exam', 'results', 'settings', 'students', etc.
// Then include header and sidebar
?>
```

**What it does**:

1. Configures session cookies (SameSite=Lax, path=/)
2. Validates teacher authentication
3. Checks session timeout (2 hours)
4. Fetches teacher data from database
5. Builds `$teacherData` array with: `full_name`, `gelar`, `full_name_with_gelar`, `subject`, `avatar_initial`
6. Sets default `$activePage = 'dashboard'` (can be overridden)

**Errors handled**:

- Session start failures
- Database connection issues (uses session fallback)
- Logs via `logDatabaseError()`

**Dependencies**:

- `../includes/db.php` - Database connection
- `../includes/auth.php` - Authentication helpers

---

#### **teacher/includes/header.php** (Reusable Navbar)

**Purpose**: Consistent navbar across all teacher pages

**Dependencies**:

- Requires `$teacherData` from `init.php`
- Requires `$_SESSION['role']` for logout link

**Contains**:

- Brand/logo area
- User avatar with teacher initials
- User name and dropdown
- Logout button
- Mobile hamburger menu (no JS - handled by teacher-layout.js)

---

#### **teacher/includes/sidebar.php** (Reusable Sidebar with Active Highlighting)

**Purpose**: Navigation menu with active page highlighting

**Dependencies**:

- Requires `$teacherData` from `init.php`
- Requires `$activePage` variable (set before include)

**Features**:

- Avatar section with teacher name
- Menu items: Dashboard, Buat Ujian, Bank Soal, Hasil Ujian, Data Siswa, Pengaturan
- Active item highlighted based on `$activePage` value
- Opens `<main class="main-content">` tag (you must close with `</main></div>`)

**Menu Item Keys** (use these for `$activePage`):

- `dashboard` → Dashboard
- `create-exam` → Buat Ujian Baru
- `question-bank` → Bank Soal
- `results` → Hasil Ujian
- `students` → Data Siswa
- `settings` → Pengaturan

**Note**: Links in sidebar should point to `.php` files (e.g., `students.php`, `settings.php`)

---

#### **js/teacher-layout.js** (Sidebar Toggle - Include on All Teacher Pages)

**Purpose**: Mobile sidebar toggle functionality

**Features**:

- Hamburger button click handler
- Sidebar overlay click to close
- Auto-close sidebar on menu link click (mobile)
- Uses CSS classes: `sidebar-open`, `sidebar-overlay-visible`

**Include at bottom** of all teacher pages:

```html
<script src="../js/teacher-layout.js"></script>
```

---

### Teacher Page Examples

#### **teacher/dashboard.php** (Uses Shared Components)

**Pattern to follow**:

- Line 1: `require_once 'includes/init.php'`
- Line 2: `$activePage = 'dashboard'`
- Lines 3-5: `require_once '../includes/csrf.php'` (if forms exist), `include 'includes/header.php'`, `include 'includes/sidebar.php'`
- Body: Page content inside `<main class="main-content">`
- Footer: `</main></div>` + `<script src="../js/teacher-layout.js"></script>`

**Key Changes from HTML**:

- Server-side authentication (no GET request to check login)
- Teacher data injected at page load (no API calls for static data)
- Shared navbar/sidebar (70+ lines of code removed)
- Page-specific styles retained

---

#### **teacher/settings.php** (Settings with CSRF)

**Purpose**: Teacher profile and AI settings management

**Key Features**:

- Uses `init.php` for session/auth/teacher data
- **Two separate CSRF tokens** (profile form + AI form)
- JavaScript fetches profile and AI settings via API
- Buttons trigger POST requests with CSRF tokens
- Uses `showToast()` for all user feedback

**CSRF Flow**:

```
Page loads → generateCSRFToken() creates token
            → Token injected as hidden input
            → JavaScript sends token in POST body
            → Server validates: verifyCSRFToken($token, $_SESSION['csrf_token'])
            → Token regenerated after successful save
            → showToast() displays result to user
```

---

#### **teacher/students.php** (Student List with Server-Side Data - NEW)

**Purpose**: Display list of students in teacher's classes (read-only)

**Pattern**: Server-side data injection (no API call)

**Key Features**:

- Uses `init.php` for session/auth/teacher data
- **Server-side database query** for student data
- **No JavaScript required** for data loading (except sidebar toggle)
- **No CSRF needed** (read-only page)
- Follows optimization pattern from `dashboard.php`

**Data Flow**:

```
Teacher accesses students.php (GET)
  ↓
init.php: Session validation + teacher data fetch
  ↓
students.php: Sets $activePage = 'students'
  ↓
Direct database query:
  SELECT DISTINCT s.* FROM students s
  JOIN exams e ON e.class = s.class
  WHERE e.teacher_id = ?
  ↓
Includes header.php and sidebar.php
  ↓
Table renders with student data immediately
  ↓
No loading spinner, no API call
```

**Why server-side instead of API**:

- Student data doesn't change frequently during a session
- Eliminates loading flicker
- Follows dashboard.php optimization pattern
- Reduces network requests

**Error Handling**:

- Database errors logged to PHP error_log
- User-friendly error message displayed in table
- Empty state shows "Belum ada data siswa di kelas Anda"

**API Endpoints Called**: None (all data server-side)

---

### Student Exam Flow

#### **student/exam.php** (POST-Only Access - Security Critical)

**Purpose**: Take exam interface with enhanced security

**Security Flow** (MUST follow this pattern):

```
1. User clicks "Mulai Ujian" button on dashboard
2. POST to exam.php with:
   - csrf_token (generated on dashboard)
   - exam_id
   - exam_code
3. exam.php receives POST:
   - Validates CSRF: verifyCSRFToken($token, $_SESSION['csrf_token'])
   - Checks rate limit: checkExamRateLimit($examId)
   - Stores exam_id in $_SESSION['active_exam_id']
   - Regenerates CSRF token (prevents replay)
   - Sends 302 redirect to exam.php (GET)
4. Browser follows redirect to GET exam.php
5. exam.php (GET):
   - Retrieves exam_id from $_SESSION['active_exam_id']
   - Clears session variable (single-use)
   - Shows exam agreement/security/questions
6. Security listeners attach after official exam start
```

**Critical Constraints**:

- Direct GET access → redirects to dashboard (no exam access)
- POST must include valid CSRF token
- Rate limit: 3 failed attempts = 60 second block
- Each attempt uses 1 slot (even if same request)

---

#### **student/dashboard.php** (Student Dashboard - POST Forms)

**Purpose**: Student exam list with POST-based access

**Key Features**:

- All "Mulai Ujian" buttons are `<form method="POST">` (not links)
- Forms include hidden CSRF token: `generateCSRFToken()`
- Forms include hidden exam code

---

### API Layer

#### **php/exam_api.php** (Main API - All Roles)

**Purpose**: Centralized API for exam operations across all user roles

**Key Endpoints Reference**:
| Action | Roles | CSRF | Purpose | Returns |
|--------|-------|------|---------|---------|
| `get_exam_monitor` | guru, admin | No | Get exam participants + violation counts | JSON array |
| `reset_student_result` | guru, admin | No | Delete submission + all violations | success/error |
| `get_student_violations` | guru, admin | No | Fetch violation history for student | JSON array |
| `delete_violation` | guru, admin | No | Delete single violation | success/error |
| `report_violation` | siswa | No | Log security violation | success/error |
| `log_agreement` | siswa | No | Record student agreement acceptance | success/error |
| `join_exam` | siswa | No | Validate exam code + authorize access | exam data or error |
| `start_exam` | siswa | No | Initialize exam session | timer + questions |
| `get_teacher_stats` | guru | No | Fetch total students + avg score | JSON stats |
| `get_profile` | guru, siswa | No | Fetch user profile data | profile JSON |
| `update_profile` | guru | **Yes** | Update teacher name/email/phone/password | success/error |
| `get_students` | guru | No | Fetch students from teacher's classes | JSON array |
| `get_recent_violations` | guru | No | Fetch 10 latest violations | JSON array |
| `get_classes` | public | No | Fetch list of classes | JSON array |
| `get_subjects` | public | No | Fetch list of subjects | JSON array |

**Note**: `get_students` endpoint is still available for API use, but `students.php` now uses server-side fetching for better performance.

**Session Recovery** (Built-in):

- If `$_SESSION['role']` missing but `user_id` exists → auto-recover from database
- Prevents accidental logout due to session serialization issues

**Logging**:

- `logExamAction($action, $details)` writes to `logs/exam_actions.log`
- Logs: exam resets, violations, CSRF failures, administrative actions

**CSRF Protected Endpoints** (State-changing):

```php
// Only update_profile requires CSRF
if (!verifyCSRFToken($input['csrf_token'], $_SESSION['csrf_token'])) {
    http_response_code(403);
    die(json_encode(['error' => 'CSRF validation failed']));
}
```

---

#### **php/save_ai_settings.php** (AI Settings with CSRF)

**Purpose**: Save teacher's Gemini API settings

**CSRF Implementation**:

```php
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['csrf_token']) ||
    !verifyCSRFToken($input['csrf_token'], $_SESSION['csrf_token'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid CSRF token']));
}
```

**Features**:

- Validates model against allowed list
- Preserves existing API key if new key is empty
- Auto-creates `teacher_settings` table if missing
- Returns masked API key in response

---

#### **php/get_ai_settings.php** (Fetch AI Settings - Unchanged)

**Purpose**: Retrieve teacher's current AI configuration

**Returns**:

- `api_key_masked` - First 8 chars + asterisks
- `model` - Selected model name
- `has_key` - Boolean flag

---

#### **php/ai_import.php** (AI Question Extraction)

**Purpose**: Extract questions from text/files using Gemini API

**Actions**:

- `extract` - Process file/text, extract questions, return as JSON
- `test` - Diagnostic test (no CSRF required - read-only)

---

## Critical Workflows

### Teacher Students Page Load (Server-Side Data - NEW)

```
Browser requests students.php (GET)
  ↓
init.php executes:
  - Session cookie configuration (SameSite=Lax)
  - Session start
  - requireLogin('guru') validates session
  - Fetch teacher record from DB
  - Build $teacherData array
  ↓
students.php sets $activePage = 'students'
  ↓
Direct database query for students:
  - JOIN exams ON class to find teacher's classes
  - ORDER BY class, full_name
  ↓
Includes header.php and sidebar.php:
  - Use $teacherData for user info
  - sidebar.php highlights 'students' menu item
  ↓
Page HTML renders with student data already in table
  ↓
No loading spinner (data appears immediately)
  ↓
JavaScript only handles sidebar toggle (teacher-layout.js)
```

### Teacher Settings Page Load (API-Based Data)

```
Browser requests settings.php (GET)
  ↓
init.php executes: session validation + teacher data fetch
  ↓
settings.php sets $activePage = 'settings'
  ↓
Generates CSRF tokens
  ↓
Includes header.php and sidebar.php
  ↓
Page HTML renders with empty forms
  ↓
JavaScript (on page ready):
  - Fetch /php/exam_api.php?action=get_profile
  - Fetch /php/get_ai_settings.php
  ↓
Forms populate with data
  ↓
User saves → POST with CSRF token → showToast() on success/error
```

### Teacher Profile Update (CSRF Flow)

```
User submits profile form
  ↓
JavaScript:
  - Reads CSRF token from hidden input
  - Sends POST to exam_api.php?action=update_profile
  - Body includes: csrf_token, name, email, phone, password
  ↓
Server (exam_api.php):
  - Validates: verifyCSRFToken($input['csrf_token'], $_SESSION['csrf_token'])
  - If invalid → 403 error + log failure
  - If valid → Update database + return success
  ↓
JavaScript:
  - Shows success message via showToast()
  - Optionally regenerates CSRF token for next form
```

### Exam Access (POST → Redirect → GET Security Pattern)

```
Student on dashboard.php sees exam list
  ↓
Student clicks "Mulai Ujian" button
  ↓
Form submits POST to exam.php:
  - csrf_token
  - exam_id
  - exam_code
  ↓
exam.php (POST handler):
  - Check: session_status() == PHP_SESSION_ACTIVE
  - Validate: verifyCSRFToken(2 args)
  - Check: checkExamRateLimit($exam_id)
  - Set: $_SESSION['active_exam_id'] = $exam_id
  - Regenerate: generateCSRFToken()
  - Send: header("Location: exam.php", true, 302)
  ↓
Browser follows 302 redirect to GET exam.php
  ↓
exam.php (GET handler):
  - Retrieve: $exam_id = $_SESSION['active_exam_id']
  - Clear: unset($_SESSION['active_exam_id'])
  - Show: Agreement page → Security check → Exam questions
  ↓
After exam start, security listeners attach
  ↓
Prevent: Reuse of same POST request (session key cleared)
```

---

## Creating New Teacher Pages (Two Patterns)

### Pattern A: Read-Only Pages with Server-Side Data (like students.php)

Use when data doesn't change frequently and you want optimal performance.

```php
<?php
require_once 'includes/init.php';
$activePage = 'page-key';

// Fetch data server-side
$items = [];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM table WHERE teacher_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $items = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Page error: " . $e->getMessage());
    $error = "Gagal memuat data.";
}
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
        <!-- Display $items directly -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif (empty($items)): ?>
            <p>Tidak ada data.</p>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <!-- Render item -->
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
    </div>
    <script src="../js/teacher-layout.js"></script>
</body>
</html>
```

### Pattern B: Interactive Pages with API Calls (like settings.php)

Use when data changes frequently or requires real-time updates.

```php
<?php
require_once 'includes/init.php';
$activePage = 'page-key';
require_once '../includes/csrf.php';
$csrf_token = generateCSRFToken();
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
        <input type="hidden" id="csrf-token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <!-- Forms and dynamic content -->
    </main>
    </div>
    <script src="../js/api-client.js"></script>
    <script src="../js/toast.js"></script>
    <script src="../js/teacher-layout.js"></script>
    <script>
        // Fetch data via API, include CSRF token in POST requests
        // Use showToast() for user feedback
    </script>
</body>
</html>
```

---

## Log Files Reference

| Log File           | Purpose                                                  | Location       |
| ------------------ | -------------------------------------------------------- | -------------- |
| `exam_actions.log` | Exam resets, deletions, CSRF failures, rate limit blocks | `php/logs/`    |
| `ai_import.log`    | AI extraction operations, Gemini API calls, errors       | `php/logs/`    |
| PHP error_log      | Database connection errors, query failures               | System default |

**Log Entry Format**:

```
[2026-04-19 14:30:45] USER_ID:123 ACTION:reset_exam EXAM_ID:5 STATUS:success
[2026-04-19 14:31:02] USER_ID:456 ACTION:csrf_fail ENDPOINT:update_profile REASON:token_invalid
```

---

## Security Headers (exam.php)

These headers prevent clickjacking and MIME-type sniffing:

```php
header('X-Frame-Options: DENY');
header('Content-Security-Policy: frame-ancestors \'none\'');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
```

---

## Critical Constraints & Rules

### Cannot Be Done

1. ❌ Delete active exams (only draft exams can be deleted)
2. ❌ Use GET request directly on exam.php (must POST first)
3. ❌ Hardcode navbar/sidebar HTML (always use includes)
4. ❌ Call `get_profile` on teacher dashboard (use server-side data)
5. ❌ Use toleransi function (obsolete - removed)
6. ❌ Modify CSRF token validation signature (always 2 args)

### Must Always Do

1. ✅ Set `$activePage` before including header/sidebar
2. ✅ Include CSRF token in all forms that modify data
3. ✅ Call `verifyCSRFToken($posted, $_SESSION['csrf_token'])` (2 args)
4. ✅ Use `requireLogin($role)` to validate session
5. ✅ Use `mb_*` functions for UTF-8 text handling
6. ✅ Use `htmlspecialchars()` when outputting user data
7. ✅ Include `teacher-layout.js` on all teacher pages
8. ✅ Set session cookie params before `session_start()`
9. ✅ Log errors but never expose to users
10. ✅ **Use server-side data fetching for read-only pages** (like students.php)
11. ✅ **Include `toast.js` and use `showToast()` for all user feedback**
12. ✅ **Include CSRF token in all upload requests to `upload_media.php`**

### Session Configuration (Always This Way)

```php
<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'samesite' => 'Lax'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

### Rate Limiting (Per Exam Per Student)

```php
// Check before allowing exam access
if (!checkExamRateLimit($exam_id)) {
    die(json_encode(['error' => $_SESSION['rate_limit_error']]));
}

// Clear after successful access
clearExamRateLimit($exam_id);
```

---

## Important Notes for Future Development

### Recent Changes Summary

**2026-04-20 - Media Upload Security Hardening**:

- Refactored `php/upload_media.php` with comprehensive security measures
- Added CSRF token validation for all upload requests
- Implemented rate limiting (50 uploads per hour per user)
- Added strict file validation (extension, MIME type, image integrity)
- Added dimension limits (max 4096x4096 pixels)
- Added optional EXIF metadata stripping via GD
- Created `uploads/.htaccess` to prevent script execution
- Updated `teacher/create-exam.php` to include CSRF tokens in uploads
- Updated `.gitignore` to track `.htaccess` while ignoring uploaded files

**2026-04-20 - Dashboard API Extraction**:

- Created `js/utils.js` with shared utilities (escapeHtml, formatDate, formatTime)
- Created `js/teacher-api.js` with TeacherAPI class for stats and violations
- Created `js/teacher-dashboard.js` with TeacherDashboard controller
- Updated `teacher/dashboard.php` to use extracted modules
- Added proper initialization flow with `examManager.fetchExams()`

**2026-04-20 - Toast System Documentation**:

- Added comprehensive toast notification system documentation
- Updated all workflows to reference `showToast()` usage
- Added toast.js to required includes in Pattern B
- Added toast system to Quick Reference table

**2026-04-19 - Teacher Students Page Migration to PHP**:

- New file: `teacher/students.php` (migrated from HTML)
- **Server-side data injection** (no API call, no loading spinner)
- Uses shared components (init.php, header, sidebar)
- Read-only display (no edit/delete)
- `teacher/students.html` now redirects to `.php` version
- Updated sidebar link from `students.html` to `students.php`

**Why server-side instead of API**:

- Student data is static during session
- Eliminates "Memuat..." flicker
- Follows optimization pattern from dashboard.php
- Reduces network requests

**2026-04-19 - Teacher Settings Migration to PHP with CSRF**:

- New file: `teacher/settings.php` (migrated from HTML)
- Uses shared components (init.php, header, sidebar)
- Added CSRF protection for profile and AI settings forms
- `teacher/settings.html` now redirects to `.php` version

**2026-04-19 - Teacher Layout Refactoring**:

- Created `teacher/includes/init.php` (shared initialization)
- Created `teacher/includes/header.php` and `sidebar.php` (reusable components)
- Created `js/teacher-layout.js` (sidebar toggle)
- Refactored `dashboard.php` to use shared components

**2026-04-19 - Dashboard Optimization**:

- Removed `get_profile` API call from dashboard.php
- Added server-side teacher data fetching

**2026-04-18 - Security Hardening**:

- Implemented POST-only exam.php access
- Added CSRF token requirement for exam forms
- Implemented rate limiting (3 attempts per 60 sec)
- Added security violation logging

---

## Session Recovery Checklist (When Resuming Project)

When coming back to this project, verify these 7 files first:

1. **`includes/csrf.php`** - CSRF generation/validation (2-arg verify required)
2. **`includes/auth.php`** - Rate limiting + authentication helpers
3. **`teacher/includes/init.php`** - Shared teacher initialization (CRITICAL)
4. **`php/exam_api.php`** - Main API with CSRF on state-change endpoints
5. **`php/save_ai_settings.php`** - AI settings with CSRF protection
6. **`php/upload_media.php`** - Secure media upload with CSRF and rate limiting
7. **`js/toast.js`** - Toast notification system (use for all user feedback)

Then review these files for latest patterns:

8. **`teacher/students.php`** - Server-side data pattern
9. **`teacher/settings.php`** - API-based pattern with CSRF
10. **`teacher/dashboard.php`** - Uses shared components with extracted JS modules
11. **`teacher/create-exam.php`** - Exam creation with CSRF-protected uploads
12. **`js/teacher-api.js`** - Teacher API layer
13. **`js/teacher-dashboard.js`** - Dashboard controller
14. **`js/utils.js`** - Shared utilities
15. **`student/exam.php`** - POST-only access pattern with CSRF
16. **`student/dashboard.php`** - POST forms for exam access
17. **`js/teacher-layout.js`** - Sidebar toggle (include on all teacher pages)

---

## Breaking Changes to Remember

1. **Never use GET for exam access** - Always POST with CSRF then redirect
2. **Never hardcode navbar/sidebar** - Always use shared includes
3. **Always use 2 arguments for verifyCSRFToken()** - Posted token + `$_SESSION['csrf_token']`
4. **Session timeout is 2 hours** - Refreshed on authenticated page loads
5. **Rate limit is per exam per student** - Different exams = separate counters
6. **Dashboard data is server-side** - No `get_profile` API call needed
7. **Settings data is API-based** - Profile still fetched dynamically (real-time sync)
8. **Students data is server-side** - No API call for student list (static during session)
9. **Teacher pages must use init.php** - Start every teacher page with this include
10. **Active exams cannot be deleted** - Only draft exams can be removed
11. **Toleransi is obsolete** - Function removed, do not use
12. **Sidebar links must point to .php files** - Update when migrating pages
13. **Always use toast system for user feedback** - Include `toast.js` and call `showToast()`
14. **Always include CSRF token in upload requests** - Uploads to `upload_media.php` require `csrf_token` field

---

## File Dependencies at a Glance

```
teacher/students.php (Server-side data)
  ├─ includes/init.php
  │   ├─ includes/auth.php
  │   ├─ ../includes/db.php
  │   ├─ ../includes/csrf.php
  ├─ includes/header.php (depends on init.php)
  ├─ includes/sidebar.php (depends on init.php + $activePage)
  └─ js/teacher-layout.js

teacher/settings.php (API-based with CSRF)
  ├─ includes/init.php
  ├─ includes/header.php
  ├─ includes/sidebar.php
  ├─ js/api-client.js
  ├─ js/toast.js
  ├─ js/teacher-layout.js
  └─ (API calls to exam_api.php, get_ai_settings.php, save_ai_settings.php)

teacher/dashboard.php (Mixed - server-side + API)
  ├─ includes/init.php
  ├─ includes/header.php
  ├─ includes/sidebar.php
  ├─ js/utils.js
  ├─ js/api-client.js
  ├─ js/toast.js
  ├─ js/teacher-api.js
  ├─ js/exam-manager.js
  ├─ js/teacher-dashboard.js
  ├─ js/teacher-layout.js
  └─ (API calls via TeacherAPI, examManager.fetchExams)

teacher/create-exam.php (Exam creation with uploads)
  ├─ includes/init.php
  ├─ includes/header.php
  ├─ includes/sidebar.php
  ├─ ../includes/csrf.php (for $csrf_token)
  ├─ js/toast.js
  ├─ js/teacher-layout.js
  ├─ js/ai-import.js
  └─ (Uploads to php/upload_media.php with CSRF token)

student/exam.php
  ├─ includes/csrf.php
  ├─ includes/auth.php
  └─ js/exam.js

php/exam_api.php
  ├─ includes/db.php
  ├─ includes/auth.php
  ├─ includes/csrf.php
  └─ logs/exam_actions.log (write)

php/upload_media.php (Secure upload endpoint)
  ├─ includes/db.php
  ├─ ../includes/csrf.php
  └─ (Rate limiting via session, image validation)

php/save_ai_settings.php
  ├─ includes/db.php
  ├─ includes/csrf.php
  └─ logs/ (optional)

uploads/.htaccess (Security configuration)
  └─ (Blocks PHP execution, sets security headers)

js/toast.js
  └─ Global showToast() function

js/teacher-api.js
  ├─ depends on api-client.js
  └─ depends on toast.js

js/teacher-dashboard.js
  ├─ depends on utils.js
  ├─ depends on teacher-api.js
  ├─ depends on toast.js
  └─ depends on exam-manager.js

js/utils.js
  └─ Independent shared utilities
```

---

## Last Updated

**Date**: 2026-04-20
**Latest Change**: Security hardening of media upload endpoint (CSRF, rate limiting, image validation, .htaccess)
**Status**: Active development
**Maintainer Notes**: Use toast system (showToast) for all user notifications instead of alert() or custom modals. Always include CSRF token in upload requests to `upload_media.php`.
