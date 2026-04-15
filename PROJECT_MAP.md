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
│   ├── exam-manager.js   # SHARED - Exam management logic
│   ├── exam.js           # Exam engine (timer, answers, submission)
│   └── security.js       # Anti-cheat monitoring (attaches on exam start)
├── php/
│   ├── db.php           # Database connection
│   ├── exam_api.php     # SHARED - Exam operations API
│   ├── admin_api.php    # Admin-specific API
│   ├── auth.php         # Authentication handling
│   ├── logout.php       # Logout handler
│   ├── ai_import.php    # AI question extraction
│   └── logs/            # Log files directory
│       ├── ai_import.log    # AI extraction logs
│       └── exam_actions.log # Exam action logs (reset, toleransi, violations)
└── vendor/              # Composer dependencies
```

---

## Core Files & Their Relationships

### 1. **js/exam-manager.js** (SHARED MODULE)

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

**Monitor Modal Features** (Updated):

- Search/filter students by name
- Auto-refresh every 30 seconds (indicator in footer)
- Violation badges (🟢 0, 🟡 1-2, 🔴 3+) clickable for details
- Reset button (🔄) always visible, clears both answers AND violations
- Status icons (✅ ⏳ 🟡 ❌) instead of text

---

### 2. **js/security.js** (Student Exam Security)

**Purpose**: Anti-cheat monitoring that only activates when exam officially starts

**Key Behavior**:

- `init()` - Sets up debug mode, attaches NO listeners
- `start()` - Called from `exam.html` when student clicks "Mulai Ujian" on fullscreen prompt
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

### 3. **js/exam.js** (Exam Engine)

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

### 4. **php/exam_api.php** (SHARED API)

**Purpose**: Handles all exam-related operations for all roles

**Key Endpoints** (Updated):

| Action                   | Role Access | Description                                                       |
| ------------------------ | ----------- | ----------------------------------------------------------------- |
| `get_exam_monitor`       | Guru, Admin | Returns participants with violation counts                        |
| `reset_student_result`   | Guru, Admin | **NEW**: Deletes submission AND all violations (transaction-safe) |
| `get_student_violations` | Guru, Admin | Fetch violation history for a specific student+exam               |
| `delete_violation`       | Guru, Admin | Delete a single violation record                                  |
| `report_violation`       | Siswa       | Log violation to database (no blocking)                           |
| `log_agreement`          | Siswa       | Record student agreement to exam rules                            |

**reset_student_result Changes**:

- Now deletes both `exam_submissions` AND `violations` records
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

### 5. **student/exam.html**

**Purpose**: Student exam taking interface

**Flow**:

1. Load page → Shows agreement modal with 14 rules checkboxes
2. Student checks all boxes → 10-second countdown
3. Countdown finishes → "Mulai Ujian" enabled
4. Click → Logs agreement, shows fullscreen prompt
5. Click "Mulai Ujian" on FS prompt → Calls `startExam()`
6. `startExam()` calls `ExamSecurity.start()` → **Security monitoring begins**
7. Calls `ExamEngine.init()` → Loads questions, starts timer

**Critical**: Security monitoring only starts AFTER fullscreen prompt button click.

---

### 6. **teacher/results.html**

**Purpose**: View exam results with violation tracking

**Features**:

- Results table with violation column (clickable badges)
- Filter: "Tampilkan hanya siswa dengan pelanggaran"
- Violation detail modal (reused from exam-manager)

---

### 7. **admin/dashboard.html**

**Purpose**: Main admin control panel

**Violations Section** (Enhanced):

- Clickable violation rows that open detail modal
- Delete violation button for admin
- Auto-refresh every 30 seconds
- Manual refresh button

---

## Database Schema Updates

### violations table (existing, no changes)

- `id` - Primary key
- `exam_id` - Foreign key to exams
- `student_id` - Foreign key to students
- `reason` - Violation description
- `violation_count` - Counter (legacy, not used for blocking)
- `created_at` - Timestamp

---

## Key Workflows (Updated)

### 1. Reset Student Result Flow (With Violation Clearing)

```
Teacher clicks "Reset Hasil" (🔄) in Monitor Modal
  ↓
confirm() dialog warns: answers AND violations will be deleted
  ↓
examManager.resetStudentResult()
  ↓
POST to exam_api.php?action=reset_student_result
  ↓
resetStudentResult() function (UPDATED):
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

### 2. Student Exam Start Flow (Security Activation)

```
Student loads exam.html
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

### 3. Violation Viewing Flow (Teacher/Admin)

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

### Recent Changes Summary (2026-04-16)

1. **Removed student blocking** - No more 3-strike force submit
2. **Security only activates on exam start** - Listeners attached only when student clicks final "Mulai Ujian"
3. **Reset result now clears violations** - Transaction-safe deletion of both submission and violations
4. **Monitor modal improvements**:
   - Search/filter by student name
   - Auto-refresh every 30s
   - Violation badges clickable for details
   - Reset button always visible
   - Removed Toleransi button (obsolete)
   - Status icons instead of text
5. **Violation management**:
   - Teachers can view violation history
   - Admin can delete any violation
   - Violation detail modal with timestamps
6. **Admin violations table** - Clickable rows with delete functionality

### Critical Constraints (Updated)

1. **Active exams cannot be deleted** (check in deleteExam function)
2. **Reset now deletes submissions AND violations** (changed from "keeps violations")
3. **Toleransi function is OBSOLETE** (students are no longer blocked/forced)
4. **Admin duplicates preserve original teacher_id**
5. **Session timeout: 2 hours**
6. **Security listeners attached ONLY after exam officially starts** (no violations during agreement)

### Log Files

- `logs/exam_actions.log` - All exam actions (activate, delete, reset with violation counts)
- `logs/ai_import.log` - AI extraction operations

---

## Last Updated

**Date**: 2026-04-16
**Developer**: Full-stack implementation with violation management and delayed security activation
**Status**: Active development

---

## Session Recovery Notes

When returning to this project, review:

1. `js/security.js` - Security starts ONLY on exam begin (critical for fair exams)
2. `js/exam-manager.js` - Monitor modal with search, auto-refresh, violation details
3. `php/exam_api.php` - resetStudentResult now clears violations with transaction
4. `student/exam.html` - Agreement modal before security activation
5. This PROJECT_MAP.md - For latest workflow understanding
