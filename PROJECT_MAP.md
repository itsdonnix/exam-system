# ExamSafe - Project Structure & Relationships Map

## Project Overview

ExamSafe is a secure online exam platform with three user roles: Admin, Teacher, and Student.

## Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP 7.4+
- **Database**: MySQL/MariaDB
- **Authentication**: Session-based
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
│   └── exam.html         # Take exam interface
├── css/
│   └── style.css         # Global styles
├── js/
│   └── exam-manager.js   # SHARED - Exam management logic
├── php/
│   ├── db.php           # Database connection
│   ├── exam_api.php     # SHARED - Exam operations API
│   ├── admin_api.php    # Admin-specific API
│   ├── auth.php         # Authentication handling
│   ├── logout.php       # Logout handler
│   ├── ai_import.php    # AI question extraction
│   └── logs/            # Log files directory
│       ├── ai_import.log    # AI extraction logs
│       └── exam_actions.log # Exam action logs (reset, toleransi)
└── vendor/              # Composer dependencies
```

---

## Core Files & Their Relationships

### 1. **js/exam-manager.js** (SHARED MODULE)

**Purpose**: Centralized exam management for both admin and teacher dashboards

**Dependencies**:

- Used by: `admin/dashboard.html`, `teacher/dashboard.html`
- API Calls: `php/exam_api.php`

**Key Methods**:
| Method | Purpose | API Endpoint |
|--------|---------|--------------|
| `fetchExams()` | Load all exams | `get_exams` |
| `activateExam(id)` | Activate exam | `activate_exam` |
| `deactivateExam(id)` | Stop exam | `deactivate_exam` |
| `deleteExam(id)` | Delete exam | `delete_exam` |
| `duplicateExam(id)` | Copy exam | `duplicate_exam` |
| `showMonitor(id, name)` | Open monitor modal | `get_exam_monitor` |
| `grantTolerance(id, studentId, name)` | Unlock student | `unlock_student` |
| `resetStudentResult(id, studentId, name, score)` | Reset student result | `reset_student_result` |
| `filterExams()` | Search/filter exams | Local only |

**Role-Based Behavior**:

- `role: 'admin'` - Can manage ALL exams, sees teacher_name, NO "Hasil" button
- `role: 'teacher'` - Can manage OWN exams, sees "Hasil" button

---

### 2. **php/exam_api.php** (SHARED API)

**Purpose**: Handles all exam-related operations for all roles

**Authentication**:

- Session required for all non-public endpoints
- Supports roles: `siswa`, `guru`, `admin`

**Key Endpoints**:

| Action                  | Role Access | Description                                |
| ----------------------- | ----------- | ------------------------------------------ |
| `get_exams`             | All         | Fetch exams (filtered by role)             |
| `get_exam`              | All         | Fetch single exam with questions           |
| `activate_exam`         | Guru, Admin | Activate exam (admin can activate any)     |
| `deactivate_exam`       | Guru, Admin | Stop exam (admin can stop any)             |
| `delete_exam`           | Guru, Admin | Delete exam (admin can delete any)         |
| `duplicate_exam`        | Guru, Admin | Copy exam (admin preserves teacher_id)     |
| `get_exam_monitor`      | Guru, Admin | Get real-time exam status                  |
| `unlock_student`        | Guru, Admin | Delete submission + violations (Toleransi) |
| `reset_student_result`  | Guru, Admin | Delete submission ONLY (Reset Hasil)       |
| `get_recent_violations` | Guru        | Get violations for teacher's exams         |
| `submit_answers`        | Siswa       | Submit exam answers                        |
| `report_violation`      | Siswa       | Report security violation                  |
| `get_results`           | Guru, Admin | Get exam results with stats                |

**Special Admin Permissions**:

- Admin bypasses `teacher_id` checks
- Admin can act on any exam regardless of teacher
- Admin sees all exams in `get_exams`

**Logging**:

- Function: `logExamAction($level, $message, $context)`
- Log file: `logs/exam_actions.log`
- Logs: user_id, role, action, exam_id, student_id, scores

---

### 3. **php/admin_api.php** (ADMIN ONLY)

**Purpose**: Admin-specific operations (user management, approvals)

**Authentication**: Requires `role === 'admin'`

**Key Endpoints**:
| Action | Description |
|--------|-------------|
| `get_stats` | Dashboard statistics (teachers, students, exams, pending) |
| `get_pending_teachers` | List pending teacher registrations |
| `get_pending_students` | List pending student registrations |
| `approve_teacher` | Approve teacher registration |
| `reject_teacher` | Reject teacher registration |
| `approve_student` | Approve student registration |
| `reject_student` | Reject student registration |
| `get_all_exams` | Get all exams (alternative to exam_api) |
| `get_security_logs` | Get all violations across all exams |
| `get_teachers` | Get all teachers (CRUD) |
| `get_all_students` | Get all students (CRUD) |
| `add_teacher/update/delete` | Teacher CRUD operations |
| `add_student/update/delete` | Student CRUD operations |
| `get_classes/add/delete` | Class management |
| `get_subjects/add/delete` | Subject management |

---

### 4. **admin/dashboard.html**

**Purpose**: Main admin control panel

**Key Sections**:

1. **Stats Cards** - Total teachers, students, exams, pending approvals
2. **Approval Lists** - Pending teacher & student registrations
3. **Exam List** - Uses `exam-manager.js` with `role: 'admin'`
4. **Violations Section** - Shows all violations across all exams
5. **Monitor Modal** - Real-time exam monitoring

**Data Sources**:

- Stats & Approvals → `admin_api.php`
- Exams & Monitor → `exam_api.php` (via exam-manager.js)
- Violations → `admin_api.php` (get_security_logs)

**Initialization**:

```javascript
examManager = new ExamManager({
  containerId: "exam-list",
  searchInputId: "examSearch",
  role: "admin",
  onExamAction: () => fetchAdminStats(),
});
```

---

### 5. **teacher/dashboard.html**

**Purpose**: Teacher's main control panel

**Key Sections**:

1. **Stats Cards** - Total exams, active exams, total students, average score
2. **Quick Actions** - Create exam, bank soal, results, monitor, export
3. **Exam List** - Uses `exam-manager.js` with `role: 'teacher'`
4. **Violations Section** - Shows violations from teacher's exams only
5. **Monitor Modal** - Real-time monitoring (same as admin)

**Data Sources**:

- Exams & Monitor → `exam_api.php` (via exam-manager.js)
- Teacher Stats → `exam_api.php` (get_teacher_stats)
- Profile → `exam_api.php` (get_profile)
- Violations → `exam_api.php` (get_recent_violations)

**Initialization**:

```javascript
examManager = new ExamManager({
  containerId: "exam-list",
  searchInputId: "examSearch",
  role: "teacher",
  onExamAction: () => updateStats(examManager.allExams),
});
```

---

### 6. **php/ai_import.php**

**Purpose**: Extract questions from documents using Google Gemini API

**Features**:

- File upload (PDF, DOCX, TXT)
- Text pasting
- AI-powered question extraction
- Supports multiple question types (multiple, checkbox, truefalse, essay)

**Dependencies**:

- Guzzle HTTP Client
- smalot/pdfparser (PDF)
- PhpOffice/PhpWord (DOCX)

**Logging**: `logAIMessage()` → `logs/ai_import.log`

**Key Actions**:

- `action=extract` - Process text/file and extract questions
- `action=test` - Test Gemini API connection with diagnostics

---

## Database Schema (Key Tables)

### Users & Authentication

- `teachers` - id, full_name, gelar, nip, email, subject, password, approval_status, is_active
- `students` - id, full_name, nisn, username, class, password, approval_status, is_active
- `admins` - id, username, email, full_name, password, is_active

### Exams & Questions

- `exams` - id, teacher_id, name, subject, class, exam_code, start_time, end_time, duration_minutes, question_count, description, status, show_results_setting
- `questions` - id, exam_id, question_text, question_type, options, correct_answer, points, difficulty, media_url

### Submissions & Violations

- `exam_submissions` - id, exam_id, student_id, answers_json, score, manual_score, total_score, status, time_taken_seconds, is_forced, submitted_at
- `violations` - id, exam_id, student_id, reason, violation_count, created_at

### Support Tables

- `classes` - id, name
- `subjects` - id, name, category
- `question_bank` - id, teacher_id, question_text, question_type, options, correct_answer, points, difficulty, category, media_url
- `teacher_settings` - teacher_id, gemini_api_key, gemini_model

---

## Key Workflows

### 1. Exam Reset Flow (Admin/Teacher)

```
User clicks "Reset Hasil" in Monitor Modal
  ↓
confirm() dialog shows warning
  ↓
examManager.resetStudentResult()
  ↓
POST to exam_api.php?action=reset_student_result
  ↓
resetStudentResult() function:
  - Verify permissions (admin OR teacher owns exam)
  - Get submission details for logging
  - DELETE FROM exam_submissions (keep violations)
  - Log to logs/exam_actions.log
  ↓
Return success to frontend
  ↓
Refresh monitor modal
```

### 2. Toleransi Flow (Unlock Student)

```
Similar to Reset, but:
  - DELETE FROM exam_submissions
  - DELETE FROM violations
  - Different confirmation message
  - Different button (green, only for forced submissions)
```

### 3. Exam Monitoring Flow

```
User clicks "Monitor" on exam card
  ↓
examManager.showMonitor(examId, examName)
  ↓
GET exam_api.php?action=get_exam_monitor&exam_id={id}
  ↓
getExamMonitor() function:
  - Get total students (by class)
  - Get finished submissions count
  - Get violations count
  - Get participant list with scores and status
  ↓
Render modal with stats table
  - Shows: student name, score, submit time, status
  - Shows buttons based on status (Toleransi/Reset)
```

### 4. AI Question Import Flow

```
Teacher uploads file or pastes text
  ↓
POST to ai_import.php?action=extract
  ↓
Extract text (PDF/DOCX/TXT)
  ↓
Call Gemini API with structured prompt
  ↓
Parse JSON response
  ↓
Return question array to frontend
  ↓
Teacher reviews and saves to question bank or exam
```

---

## Important Notes for Future Development

### Role-Based Access Summary

| Feature              | Admin             | Teacher                 | Student            |
| -------------------- | ----------------- | ----------------------- | ------------------ |
| View all exams       | ✅ (all teachers) | ✅ (own only)           | ✅ (eligible only) |
| Create exam          | ❌                | ✅                      | ❌                 |
| Edit exam            | ❌                | ✅ (own)                | ❌                 |
| Delete exam          | ✅ (any)          | ✅ (own, if not active) | ❌                 |
| Activate/Stop exam   | ✅ (any)          | ✅ (own)                | ❌                 |
| Monitor exam         | ✅ (any)          | ✅ (own)                | ❌                 |
| Reset student result | ✅ (any)          | ✅ (own exam)           | ❌                 |
| Grant toleransi      | ✅ (any)          | ✅ (own exam)           | ❌                 |
| View violations      | ✅ (all)          | ✅ (own exam)           | ❌                 |
| Manage users         | ✅                | ❌                      | ❌                 |
| Take exam            | ❌                | ❌                      | ✅                 |

### Critical Constraints

1. **Active exams cannot be deleted** (check in deleteExam function)
2. **Reset only deletes submissions, keeps violations**
3. **Toleransi deletes both submissions AND violations**
4. **Admin duplicates preserve original teacher_id**
5. **Session timeout: 2 hours**

### Log Files

- `logs/exam_actions.log` - All exam actions (activate, delete, reset, toleransi)
- `logs/ai_import.log` - AI extraction operations

### CSS Class Naming Conventions

- `.exam-card` - Exam list item container
- `.exam-card-info` - Exam details section
- `.exam-card-actions` - Action buttons container
- `.badge-*` - Status badges (success, danger, warning, secondary)
- `.btn-*` - Button styles (primary, success, danger, warning, outline)
- `.modal-overlay` - Modal background
- `.skeleton-loader` - Loading animation

---

## Common Debugging Points

### If exams don't load:

1. Check session: `var_dump($_SESSION)` in exam_api.php
2. Check role: Should be 'admin' or 'guru'
3. Check database connection in db.php

### If buttons don't appear:

1. Verify `exam-manager.js` is loaded
2. Check `this.role` value in ExamManager
3. Check exam status (active/draft/ended)
4. For reset button: status must be 'graded' or 'pending'

### If API returns 401:

1. User not logged in
2. Session expired (>2 hours)
3. Role mismatch (e.g., teacher accessing admin endpoint)

### If monitor modal doesn't open:

1. Check element IDs: 'monitor-modal', 'monitor-tbody'
2. Verify modal HTML exists in the page
3. Check for JavaScript errors in console

---

## Quick Reference: Most Common Modifications

### Add a new exam action button:

1. Add method to `exam-manager.js`
2. Add API endpoint in `exam_api.php`
3. Update `renderExamCard()` to show button (conditional by role)
4. Add logging in API function

### Add a new admin feature:

1. Add endpoint in `admin_api.php`
2. Add UI in `admin/dashboard.html`
3. Call API from page-specific JavaScript (not exam-manager.js)

### Modify button permissions:

1. Edit `renderExamCard()` in exam-manager.js (lines ~130-160)
2. Or edit `showMonitor()` for monitor modal buttons (lines ~300-350)

---

## Last Updated

**Date**: 2026-01-15
**Developer**: Full-stack implementation with role-based exam management
**Status**: Active development

---

## Session Recovery Notes

When returning to this project, review:

1. `js/exam-manager.js` - Most complex logic
2. `php/exam_api.php` - Critical API with role checks
3. `admin/dashboard.html` - Admin UI with exam-manager integration
4. This PROJECT_MAP.md - For relationship understanding
