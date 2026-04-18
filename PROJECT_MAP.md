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
│   ├── dashboard.html    # Main teacher dashboard
│   ├── create-exam.html  # Create/edit exams
│   ├── question-bank.html # Manage question bank
│   ├── results.html      # View exam results
│   ├── students.html     # Manage students
│   └── settings.html     # Teacher settings (API keys)
├── student/              # Student exam interface
│   ├── dashboard.php     # Student dashboard (POST forms for exam access)
│   ├── exam.php          # Take exam interface (POST-only access)
│   └── exam.html         # REDIRECTS to dashboard.php (deprecated)
├── css/
│   └── style.css         # Global styles
├── js/
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

### 1. **includes/csrf.php** (NEW - Security Module)

**Purpose**: CSRF protection for all forms

**Key Functions**:
| Function | Purpose |
|----------|---------|
| `generateCSRFToken()` | Creates/retrieves session-based CSRF token |
| `verifyCSRFToken($token1, $token2)` | Validates token using hash_equals() |
| `csrfField($token)` | Generates hidden input HTML |

**Critical**: Tokens stored in `$_SESSION['csrf_token']`, regenerated after successful exam access to prevent replay attacks.

---

### 2. **includes/auth.php** (Updated - Added Rate Limiting)

**Purpose**: Authentication handling + exam access rate limiting

**Key Functions** (existing):

- `isLoggedIn()`, `requireLogin()`, `setSession()`, `clearSession()`, `isSessionExpired()`

**New Rate Limiting Functions**:
| Function | Purpose |
|----------|---------|
| `checkExamRateLimit($examId)` | Limits to 3 attempts per 1 minute per exam per student |
| `clearExamRateLimit($examId)` | Resets rate limit on successful access |

**Rate Limit Behavior**:

- Attempts 1-3: Allowed within 60-second window
- After 3 failures: 60-second block
- Block message stored in `$_SESSION['rate_limit_error']`

---

### 3. **student/exam.php** (Updated - POST-Only Access)

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
- Rate limiting prevents brute force attempts

---

### 4. **student/dashboard.php** (Updated - POST Forms)

**Purpose**: Student dashboard with POST-based exam access

**Changes**:

- Includes `csrf.php` and generates token via `generateCSRFToken()`
- All "Mulai Ujian" buttons converted to POST forms
- Join exam flow uses JavaScript POST form submission
- CSRF token passed as hidden field in all exam access forms

**Exam Link Format** (replaces anchor tags):

```html
<form method="POST" action="exam.php" onsubmit="return confirm('...')">
  <input type="hidden" name="exam_id" value="123" />
  <input type="hidden" name="csrf_token" value="..." />
  <button type="submit" class="btn btn-primary">Mulai Ujian →</button>
</form>
```

---

### 5. **student/exam.html** (Deprecated - Redirects to Dashboard)

**Current Behavior**: Immediately redirects to `dashboard.php`

**Purpose**: Handles legacy bookmarks/links - prevents broken access

**Note**: Will be removed after confirming no external links remain.

---

### 6. **js/exam-manager.js** (SHARED MODULE)

**Purpose**: Centralized exam management for both admin and teacher dashboards

**Key Methods**:
| Method | Purpose | API Endpoint |
|--------|---------|--------------|
| `fetchExams()` | Load all exams | `get_exams` |
| `activateExam(id)` | Activate exam | `activate_exam` |
| `deactivateExam(id)` | Stop exam | `deactivate_exam` |
| `deleteExam(id)` | Delete exam | `delete_exam` |
| `duplicateExam(id)` | Copy exam | `duplicate_exam` |
| `showMonitor(id, name)` | Open monitor modal with search & auto-refresh | `get_exam_monitor` |
| `resetStudentResult(id, studentId, name, score)` | Reset student result + violations | `reset_student_result` |
| `showViolationDetails()` | View violation history for a student | `get_student_violations` |
| `deleteViolation()` | Remove a specific violation record | `delete_violation` |

**Monitor Modal Features**:

- Search/filter students by name
- Auto-refresh every 30 seconds (indicator in footer)
- Violation badges (🟢 0, 🟡 1-2, 🔴 3+) clickable for details
- Reset button (🔄) always visible, clears both answers AND violations
- Status icons (✅ ⏳ 🟡 ❌) instead of text

---

### 7. **js/security.js** (Student Exam Security)

**Purpose**: Anti-cheat monitoring that only activates when exam officially starts

**Key Behavior**:

- `init()` - Sets up debug mode, attaches NO listeners
- `start()` - Called from `exam.php` when student clicks "Mulai Ujian" on fullscreen prompt
- Attaches all security listeners ONLY after `start()` is called
- Logs violations to database without blocking/forcing submit
- Shows toast notifications for blocked actions

**Security Features**:

- Blocks keyboard shortcuts (Ctrl+C, Ctrl+V, F12, Alt+Tab, etc.)
- Blocks copy/paste, right-click
- Monitors tab switching, window blur, fullscreen exit
- Detects Developer Tools
- Prevents navigation (back button, refresh, close)

**Important**: No violations are recorded before exam officially starts (agreement modal, countdown, FS prompt are violation-free).

---

### 8. **js/exam.js** (Exam Engine)

**Purpose**: Manages exam taking experience for students

**Key Methods**:
| Method | Purpose |
|--------|---------|
| `init(examId)` | Load exam questions, start timer, notify security |
| `submitExam()` | Submit answers to server |
| `logAgreement(examId)` | Record student agreement to rules |
| `renderQuestions()` | Display questions with media support |

**Integration with Security**:

- Calls `ExamSecurity.setExamId()` after loading exam data
- Calls `ExamSecurity.stop()` after submission

---

### 9. **php/exam_api.php** (SHARED API)

**Purpose**: Handles all exam-related operations for all roles

**Key Endpoints**:

| Action                   | Role Access | Description                                              |
| ------------------------ | ----------- | -------------------------------------------------------- |
| `get_exam_monitor`       | Guru, Admin | Returns participants with violation counts               |
| `reset_student_result`   | Guru, Admin | Deletes submission AND all violations (transaction-safe) |
| `get_student_violations` | Guru, Admin | Fetch violation history for a specific student+exam      |
| `delete_violation`       | Guru, Admin | Delete a single violation record                         |
| `report_violation`       | Siswa       | Log violation to database (no blocking)                  |
| `log_agreement`          | Siswa       | Record student agreement to exam rules                   |
| `join_exam`              | Siswa       | Validate exam code and authorize access                  |
| `start_exam`             | Siswa       | Initialize exam session in database                      |

**reset_student_result**:

- Deletes both `exam_submissions` AND `violations` records
- Wrapped in database transaction for atomicity
- Logs number of violations cleared to `exam_actions.log`

**Special Admin Permissions**:

- Admin bypasses `teacher_id` checks
- Admin can delete any violation, reset any student result

**Logging**:

- Function: `logExamAction($level, $message, $context)`
- Log file: `logs/exam_actions.log`
- Logs include: user_id, role, action, exam_id, student_id, scores, violation counts

---

## Key Workflows (Updated)

### 1. Student Exam Access Flow (POST-only with CSRF)

```
Student clicks "Mulai Ujian" on dashboard
  ↓
POST form submitted to exam.php with:
  - exam_id (hidden field)
  - csrf_token (hidden field)
  ↓
exam.php validates CSRF token (verifyCSRFToken)
  ↓
checkExamRateLimit() → 3 attempts per minute max
  ↓
Store exam_id in $_SESSION['active_exam_id']
  ↓
Regenerate CSRF token (generateCSRFToken)
  ↓
Redirect 302 to exam.php (clean URL)
  ↓
GET request reads exam_id from session
  ↓
Clear $_SESSION['active_exam_id']
  ↓
Load agreement modal → security starts → exam begins
```

### 2. Reset Student Result Flow (With Violation Clearing)

```
Teacher clicks "Reset Hasil" (🔄) in Monitor Modal
  ↓
confirm() dialog warns: answers AND violations will be deleted
  ↓
examManager.resetStudentResult()
  ↓
POST to exam_api.php?action=reset_student_result
  ↓
resetStudentResult() function:
  - Verify permissions (admin OR teacher owns exam)
  - Get submission & violation details for logging
  - BEGIN TRANSACTION
  - DELETE FROM violations WHERE exam_id = ? AND student_id = ?
  - DELETE FROM exam_submissions WHERE exam_id = ? AND student_id = ?
  - COMMIT
  - Log to logs/exam_actions.log with violation count cleared
  ↓
Return success to frontend
  ↓
Monitor modal auto-refreshes (30s) → shows violation count as 0
```

### 3. Student Exam Start Flow (Security Activation)

```
Student loads exam.php (after POST redirect)
  ↓
ExamSecurity.init() runs → NO listeners attached
  ↓
Agreement modal shown → Student checks rules → 10s countdown
  ↓
Click "Mulai Ujian" on agreement → shows FS prompt
  ↓
Click "Mulai Ujian" on FS prompt → calls startExam()
  ↓
startExam() calls ExamSecurity.start() → ATTACHES ALL LISTENERS
  ↓
ExamEngine.init() loads questions, starts timer
  ↓
Security monitoring ACTIVE for entire exam
  ↓
Violations logged to database (no blocking/force submit)
```

### 4. Join Exam with Code Flow (Updated)

```
Student enters 8-character code in dashboard
  ↓
JavaScript POST to exam_api.php?action=join_exam
  ↓
Server validates code & exam status
  ↓
On success: create POST form dynamically
  ↓
Submit POST to exam.php (same flow as regular exam access)
```

### 5. Violation Viewing Flow (Teacher/Admin)

```
Teacher clicks violation badge (⚠️ 2) in Monitor Modal or Results page
  ↓
showViolationDetails(studentId, studentName, examId)
  ↓
GET exam_api.php?action=get_student_violations
  ↓
Modal shows:
  - Student name
  - Total violation count
  - List of violations with timestamps and reasons
  - Delete button for each violation (admin/teacher)
  ↓
Teacher can delete individual violations
  ↓
Monitor modal auto-refreshes with updated count
```

---

## Important Notes for Future Development

### Recent Changes Summary (2026-04-18)

**Security Hardening - POST-only Exam Access**:

1. **POST-only exam.php** - No longer accepts GET parameters, uses session-stored exam_id
2. **CSRF protection** - All exam access forms include tokens validated via `includes/csrf.php`
3. **Rate limiting** - 3 attempts per 1 minute per exam per student (prevents brute force)
4. **Clean URL redirect** - POST → redirect → GET pattern prevents resubmission on refresh
5. **Token regeneration** - CSRF token regenerated after successful exam access (replay prevention)
6. **Dashboard converted** - All "Mulai Ujian" links replaced with POST forms
7. **Join exam updated** - Uses JavaScript POST form submission instead of GET redirect
8. **exam.html deprecated** - Now redirects to dashboard (handles legacy bookmarks)

**Previous Changes (2026-04-16)**:

1. Removed student blocking - No more 3-strike force submit
2. Security only activates on exam start - Listeners attached only when student clicks final "Mulai Ujian"
3. Reset result now clears violations - Transaction-safe deletion of both submission and violations
4. Monitor modal improvements: search, auto-refresh, violation badges, reset button always visible
5. Violation management: Teachers can view violation history, Admin can delete any violation
6. Admin violations table - Clickable rows with delete functionality

### Critical Constraints (Updated)

1. **Active exams cannot be deleted** (check in deleteExam function)
2. **Reset now deletes submissions AND violations** (changed from "keeps violations")
3. **Toleransi function is OBSOLETE** (students are no longer blocked/forced)
4. **Admin duplicates preserve original teacher_id**
5. **Session timeout: 2 hours**
6. **Security listeners attached ONLY after exam officially starts** (no violations during agreement)
7. **exam.php requires POST then redirect** - Direct GET access without session → dashboard redirect
8. **CSRF tokens required** for all exam access forms (dashboard, join exam)
9. **Rate limiting active** - 3 failed attempts = 1 minute block

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

**Date**: 2026-04-18
**Developer**: Security hardening - POST-only exam access with CSRF + rate limiting
**Status**: Active development

---

## Session Recovery Notes

When returning to this project, review:

1. **`includes/csrf.php`** - CSRF token generation and validation (critical for all forms)
2. **`includes/auth.php`** - Rate limiting functions `checkExamRateLimit()` and `clearExamRateLimit()`
3. **`student/exam.php`** - POST-only access pattern with session-based exam_id storage
4. **`student/dashboard.php`** - POST forms for exam access, CSRF token integration
5. **`js/security.js`** - Security starts ONLY on exam begin (critical for fair exams)
6. **`js/exam-manager.js`** - Monitor modal with search, auto-refresh, violation details
7. **`php/exam_api.php`** - resetStudentResult now clears violations with transaction
8. This PROJECT_MAP.md - For latest workflow understanding

## Breaking Changes for Future Development

1. **Never use GET for exam.php** - Always POST with CSRF token
2. **Always include CSRF token** in any form that accesses exam.php
3. **Rate limit is per exam per student** - Different exams have separate counters
4. **exam.html is deprecated** - Remove after confirming no external links remain
5. **Session key `active_exam_id`** is single-use (cleared after reading)
