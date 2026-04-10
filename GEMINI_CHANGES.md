# 🎓 ExamSafe — Gemini CLI Project Summary and Comprehensive Documentation

This document provides an exhaustive overview of the ExamSafe Online Examination System, detailing its architecture, core functionalities, technological stack, and all modifications and enhancements made during the interaction with Gemini CLI. This information is meticulously structured to serve as a comprehensive guide for any future AI or human developer working on this project.

---

## 📝 Project Overview

ExamSafe is a robust and secure online examination system specifically designed for high school (SMA) environments. Its primary goal is to facilitate the efficient creation, management, and monitoring of academic assessments by teachers, while providing a highly secure and fair testing environment for students. The system integrates advanced features such as dynamic question types, rich media support for questions, real-time exam monitoring, sophisticated anti-cheating mechanisms, and detailed result reporting with analytics capabilities.

---

## 🚀 Technologies and Libraries Used

The ExamSafe system is built using a conventional LAMP (Linux, Apache, MySQL, PHP) stack, complemented by modern frontend technologies. Key components include:

-   **Frontend:**
    -   **HTML5:** Structured content for all web pages.
    -   **CSS3:** Styling and visual presentation, heavily utilizing custom properties (CSS variables) for theme management. The [Poppins](https://fonts.google.com/specimen/Poppins) font is sourced from Google Fonts.
    -   **JavaScript (Vanilla JS):** Core interactivity, dynamic content loading, form handling, and client-side logic. Avoids heavy frameworks for maximum flexibility and performance.
    -   **Web APIs:** Extensive use of the `Fetch API` for asynchronous communication with the backend. Browser APIs such as `Fullscreen API`, `Visibility API`, and `beforeunload` event are critical for the anti-cheating features.
    -   **Chart.js:** A JavaScript charting library used in `teacher/results.html` for visualizing exam score distributions.

-   **Backend:**
    -   **PHP 8.0+:** Server-side scripting language for handling business logic, database interactions, and API endpoint management.
    -   **MySQL 8.0+:** Relational database management system for storing all application data (users, exams, questions, submissions, results, etc.).
    -   **PDO (PHP Data Objects):** A PHP extension that provides a lightweight, consistent interface for accessing databases, ensuring secure and efficient database interactions (e.g., prepared statements to prevent SQL injection).

-   **Other:**
    -   **`$_SESSION` (PHP):** Used for server-side session management to maintain user authentication and state across multiple requests.
    -   **Apache/Nginx (or XAMPP/WAMP):** Web server environment for serving the application.

---

## 📁 Detailed Project Structure and File Interdependencies

The project is organized into logical directories, each serving a specific purpose:

-   `index.html`
    -   **Function:** The single entry point for all users, acting as the universal login page. It presents options to log in as a student, teacher, or administrator.
    -   **Interdependencies:** Submits authentication data (role, username, password) to `php/login.php` via an HTML form POST request. Upon successful authentication, it redirects the user to their respective dashboard (`student/dashboard.html`, `teacher/dashboard.html`, or `admin/dashboard.html`).

-   `css/`
    -   `style.css`
        -   **Function:** Contains all global CSS rules, custom variables (e.g., `--primary`, `--success`), typography definitions (Poppins from Google Fonts), and styling for common UI components such as navigation bars, buttons, forms, cards, and layout grids. It defines the overall aesthetic and responsive behavior of the entire website.
        -   **Interdependencies:** Linked by all HTML files (`<link rel="stylesheet" href="../css/style.css">`) to apply consistent styling across the application.

-   `js/`
    -   `security.js`
        -   **Function:** Implements client-side anti-cheating measures during active exams. This includes enforcing fullscreen mode (`Fullscreen API`), blocking various keyboard shortcuts (e.g., `Ctrl+T`, `F12`, `Alt+Tab`), disabling copy-paste events, and detecting `VisibilityChange` events (tab switching or losing focus on the exam window). Each detected violation is reported.
        -   **Interdependencies:** Integrated into `student/exam.html`. It communicates with `php/notify_supervisor.php` to send violation reports to the backend. It stops its monitoring when the exam is submitted or forced to end (controlled by `js/exam.js`).
    -   `exam.js`
        -   **Function:** The core JavaScript engine for student exams. It initializes the exam based on `exam_id`, manages a countdown timer, shuffles question and option orders (if configured), renders questions dynamically (including media), handles student answer inputs (multiple choice, checkbox, essay, true/false), tracks answer progress, and submits the final answers to the backend. It also includes functions for navigation (`nextQuestion`, `prevQuestion`, `goToQuestion`) and a zoom feature for question media.
        -   **Interdependencies:** Integrated into `student/exam.html`. It fetches exam data (`get_exam`) and student profile (`get_profile`) from `php/exam_api.php`. It sends submitted answers and exam status (`submit_answers`) back to `php/exam_api.php`. It also manages the lifecycle of `js/security.js` (initiation and stopping).

-   `student/`
    -   `dashboard.html`
        -   **Function:** The main dashboard for students, displaying a list of exams they are enrolled in, their scores, and statuses. It allows students to start available exams.
        -   **Interdependencies:** Fetches exam data and student profile from `php/exam_api.php`.
    -   `exam.html`
        -   **Function:** The dedicated interface for students to take an exam. It fully leverages `js/exam.js` for dynamic question rendering and logic, and `js/security.js` for anti-cheating enforcement.
        -   **Interdependencies:** Directly loads `js/exam.js` and `js/security.js`. Interacts with `php/exam_api.php` for fetching exam details and submitting answers.
    -   `register.html`
        -   **Function:** (Hypothetical/Planned) A page for new students to register for an account. In current project context, student accounts might be provisioned by an admin or teacher.
        -   **Interdependencies:** Would likely send data to a PHP script (e.g., `php/student_register.php` or a general `php/register.php` with a student role parameter) for account creation.

-   `teacher/`
    -   `dashboard.html`
        -   **Function:** The central hub for teachers to manage their exams. It displays a summary of created exams, quick access links to create new exams or view results, and a list of recent cheating violations. It provides actions like activating, deactivating, duplicating, and deleting exams.
        -   **Interdependencies:** Calls `php/exam_api.php` for `get_exams`, `get_profile` (for dynamic navbar/welcome message), `get_recent_violations`, `activate_exam`, `deactivate_exam`, `duplicate_exam`, and `delete_exam`. The dynamic profile loading was a fix implemented by Gemini CLI.
    -   `create-exam.html`
        -   **Function:** A multi-step form interface for teachers to build and configure exams. It supports diverse question types (multiple choice, checkbox, essay, true/false), allows media uploads for questions and options, sets exam metadata (name, subject, class, duration, pass mark, violation limit), and provides a preview function.
        -   **Interdependencies:**
            -   **Frontend Logic:** Relies on its own embedded JavaScript for step navigation (`goStep`), dynamic question building (`addQuestion`, `renderMultipleOptions`, `renderEssayOptions`, `renderTrueFalseOptions`), media handling (`uploadQuestionMedia`, `removeMedia`, `uploadOptionMedia`, `removeOptionMedia`), and data management (`updateQuestionData`, `getQuestionById`).
            -   **API Calls:** Fetches subjects (`get_subjects`) and teacher profile (`get_profile`) from `php/exam_api.php`. Submits question media to `php/upload_media.php`. The core exam publication process (`publishExam`) sends structured exam data to `php/exam_api.php?action=create_exam`.
            -   **Gemini CLI Fixes:** This file received critical updates from Gemini CLI for dynamic navbar name display, fixing the unresponsive "Lanjut: Tambah Soal" button, enhancing the exam preview to mirror the student view, and resolving a connection error during exam publication by standardizing data sent to the backend.
    -   `results.html`
        -   **Function:** Presents detailed results for a selected exam, including student scores, pass/fail status, average scores, and analytics (e.g., score distribution chart via Chart.js, identification of difficult questions). It also allows manual grading of essay answers.
        -   **Interdependencies:** Fetches exam results (`get_results`) and individual submission details (`get_submission_detail`) from `php/exam_api.php`. Submits manual essay grades (`save_manual_grade`) to `php/exam_api.php`. Uses Chart.js for data visualization. The dynamic profile loading (navbar name) was a fix implemented by Gemini CLI.
    -   `register.html`
        -   **Function:** Provides a form for new teachers to create an account. This account typically requires administrator approval.
        -   **Interdependencies:** Submits registration data to `php/register.php`.
    -   `settings.html`
        -   **Function:** Allows a logged-in teacher to view and update their profile details (full name, email, phone number) and change their password.
        -   **Interdependencies:** Fetches current profile data (`get_profile`) and submits updates (`update_profile`) to `php/exam_api.php`. The dynamic profile loading (navbar name) was a fix implemented by Gemini CLI.
    -   `students.html`
        -   **Function:** (Likely) A page for teachers to view and manage the list of students associated with their classes or exams. It might include functionalities to add/remove students or view basic student profiles.
        -   **Interdependencies:** Would typically fetch student data from `php/exam_api.php` or a dedicated `php/admin_api.php` if teacher has elevated privileges.

-   `admin/`
    -   `dashboard.html`, `exams.html`, `reports.html`, `security-logs.html`, `settings.html`, `students.html`, `teachers.html`
        -   **Function:** These HTML files collectively form the administrative panel, providing functionalities for system-wide management. This includes approving teacher registrations, monitoring all exams, generating comprehensive reports, reviewing security violation logs, managing system settings, and overseeing all student and teacher accounts.
        -   **Interdependencies:** These pages would primarily interact with `php/admin_api.php` (if it exists) or specific administrative actions within `php/exam_api.php` (e.g., `get_all_teachers`, `approve_teacher`).

-   `php/`
    -   `db.php`
        -   **Function:** The foundational backend utility file. It defines database connection constants (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`), establishes a PDO database connection (`getDB()`), and provides essential helper functions:
            -   `jsonResponse($data, $status = 200)`: Standardizes API responses by converting PHP arrays to JSON and setting appropriate HTTP status codes.
            -   `getInput()`: Parses raw HTTP request body (e.g., JSON payload from `fetch` API) or traditional POST data into a PHP array.
            -   `sanitize($str)`: Cleans and escapes string inputs using `htmlspecialchars`, `strip_tags`, and `trim` to mitigate XSS and other injection vulnerabilities.
            -   `session_start()`: Initiates or resumes the PHP session, enabling the use of `$_SESSION` superglobal for state management.
        -   **Interdependencies:** `require_once`d by almost all other PHP scripts (`login.php`, `register.php`, `exam_api.php`, `upload_media.php`, `notify_supervisor.php`) to provide database access and common utilities.
    -   `login.php`
        -   **Function:** Handles user authentication. It receives `role`, `username`, and `password` from the frontend, queries the respective user table (`teachers`, `students`, or `admins`), verifies the password, and upon success, populates `$_SESSION` with `user_id`, `role`, `full_name` (and potentially `subject` for teachers, `class` for students). It then returns a success JSON response with a redirect URL.
        -   **Interdependencies:** Requires `db.php`. Called by `index.html`.
    -   `register.php`
        -   **Function:** Manages the registration of new teacher accounts. It validates input (e.g., email format, password strength), hashes the password, and inserts the new teacher's details into the `teachers` table, usually with a `pending` status for admin approval.
        -   **Interdependencies:** Requires `db.php`. Called by `teacher/register.html`.
    -   `exam_api.php`
        -   **Function:** The primary API endpoint for virtually all exam-related operations, serving both teacher and student functionalities. It acts as a request router, dispatching to specific functions based on the `action` parameter in the request.
        -   **Key Actions/Functions (examples):**
            -   `get_profile()`: Retrieves authenticated user's profile data.
            -   `update_profile()`: Updates authenticated user's profile details.
            -   `get_subjects()`: Fetches a list of available subjects.
            -   `createExam()`: **(Modified by Gemini CLI)** Inserts new exam details and all associated questions into the database. It now correctly handles JSON-encoded `options`, `correct_answer`, and `media_url` fields sent from the frontend.
            -   `get_exams()`: Retrieves a list of exams, typically filtered by the teacher's ID or for students.
            -   `get_exam()`: Fetches detailed information for a specific exam, including all its questions and options.
            -   `submit_answers()`: Processes a student's submitted answers, calculates score, and logs the submission.
            -   `report_violation()`: Logs security violations during an exam.
            -   `get_results()`: Retrieves aggregated results for an exam.
            -   `get_submission_detail()`: Fetches a specific student's submission details for manual grading.
            -   `save_manual_grade()`: Saves manual grades for essay questions.
            -   `activateExam()`, `deactivateExam()`: Changes the status of an exam.
            -   `deleteExam()`: Deletes an exam and its associated data (submissions, violations).
            -   `duplicateExam()`: Creates a copy of an existing exam and its questions.
            -   `unlockStudent()`: Resets a student's violation count and allows them to re-enter an exam.
            -   `get_recent_violations()`: Fetches a list of recent cheating incidents.
            -   `get_exam_monitor()`: Provides real-time monitoring data for an active exam.
        -   **Interdependencies:** Requires `db.php`. Interacts with various HTML pages (e.g., `teacher/create-exam.html`, `teacher/dashboard.html`, `student/exam.html`) via `fetch` API calls. Performs extensive database operations.
    -   `upload_media.php`
        -   **Function:** Handles the server-side processing of file uploads (e.g., images for questions). It receives a file via POST request, validates it, saves it to the `uploads/` directory, and returns a JSON response containing the public URL of the saved file.
        -   **Interdependencies:** Requires `db.php`. Called by `teacher/create-exam.html` (specifically by `uploadQuestionMedia` and `uploadOptionMedia` functions).
    -   `notify_supervisor.php`
        -   **Function:** Receives and logs data regarding student security violations detected by `js/security.js`. It records the type of violation, student ID, exam ID, and timestamp into the database.
        -   **Interdependencies:** Requires `db.php`. Called by `js/security.js` via `fetch` API.
    -   `admin_api.php`
        -   **Function:** (Potentially) Contains API endpoints specifically for administrator-level operations not covered in `exam_api.php`, such as user management (teachers, students), system configuration, or advanced reporting.
        -   **Interdependencies:** Requires `db.php`. Would be called by HTML pages within the `admin/` directory.

-   `uploads/`
    -   **Function:** A designated directory for storing all media files (images, audio, video) uploaded through the `create-exam.html` interface and processed by `php/upload_media.php`.
    -   **Interdependencies:** Files within this directory are referenced by `<img src="../uploads/...">` tags in HTML to display question and option media.

-   `examsafe.sql`
    -   **Function:** Contains the SQL DDL (Data Definition Language) statements to create all necessary tables for the ExamSafe database (e.g., `teachers`, `students`, `exams`, `questions`, `exam_submissions`, `violations`, `subjects`). It also includes DML (Data Manipulation Language) statements for initial seed data (e.g., default admin, teacher, student accounts, and sample subjects/exams).
    -   **Interdependencies:** Executed manually (e.g., via `mysql` command line or phpMyAdmin) during project setup to initialize the database schema and data.

-   `README.md`
    -   **Function:** The original project README, providing basic information about the system, setup instructions, and initial demo login credentials.
    -   **Interdependencies:** Standalone documentation.

-   `GEMINI_CHANGES.md` (This file)
    -   **Function:** This document, generated by Gemini CLI, serves as a comprehensive record of all changes, bug fixes, and feature enhancements implemented. It details the problems addressed, the analysis, the specific code modifications, and the impact of each contribution.
    -   **Interdependencies:** Standalone documentation for project history and future development guidance.

---

## 💡 Core Logic and Workflow

### 1. Authentication & Session Management

-   **Process:** Users begin at `index.html`, select a role, and submit credentials. `php/login.php` validates these against the database (`teachers`, `students`, `admins` tables). If successful, it establishes a server-side session using `$_SESSION` (initiated by `session_start()` in `php/db.php`), storing `user_id`, `role`, and `full_name`. The user is then redirected to their role-specific dashboard.
-   **Authorization:** Subsequent API calls and page loads check the `$_SESSION['role']` and `$_SESSION['user_id']` to ensure only authorized users can access specific functionalities or data. Unauthorized access attempts typically result in a `401 Unauthorized` HTTP response.

### 2. Teacher Workflow

-   **Dashboard Interaction:** Upon logging in, teachers land on `teacher/dashboard.html`. This page dynamically populates the teacher's name in the navbar (a fix by Gemini CLI) and welcome message by calling `php/exam_api.php?action=get_profile`. It also fetches a list of the teacher's exams (`get_exams`) and recent security violations (`get_recent_violations`) for an overview.

-   **Exam Creation Flow (`teacher/create-exam.html`):
    1.  **Exam Info (Step 1):** Teachers input basic exam details (name, subject, class, duration, pass mark, violation limit). An exam code can be generated (`generateCode()` function).
    2.  **Add Questions (Step 2):** Teachers add questions of various types (Multiple Choice, Checkbox, Essay, True/False). For each question:
        -   **Text & Media:** Question text is entered, and multiple images can be uploaded (`uploadQuestionMedia` calls `php/upload_media.php`).
        -   **Options:** For MC/Checkbox/TrueFalse, options are added. Option media can also be uploaded (`uploadOptionMedia` calls `php/upload_media.php`). Correct answers are marked.
        -   **Data Management:** All question data (text, type, media_url, options, correct_answer, points, difficulty) is managed in a client-side `questions` JavaScript array. Functions like `updateQuestionData` ensure this array remains synchronized with form inputs.
    3.  **Settings (Step 3):** Additional exam-wide settings.
    4.  **Preview (Step 4):** **(Enhanced by Gemini CLI)** The `renderPreview()` function displays all created questions in a format closely resembling the student's view, including media and options. Critically, for teachers, it highlights the correct answers for review.
    5.  **Publish (`publishExam()` -> `php/exam_api.php?action=create_exam`):** **(Fixed by Gemini CLI)** Before sending, the `questions` array is pre-processed (`processedQuestions`). `correct_answer` and `media_url` fields are carefully prepared (e.g., `JSON.stringify` for arrays/objects) to match the backend's expected string formats. This data is then sent to `php/exam_api.php` which, in a single database transaction, inserts the exam and all its associated questions into the `exams` and `questions` tables.

-   **Exam Management:** From the teacher dashboard, various actions are available through `php/exam_api.php`:
    -   `activateExam()`/`deactivateExam()`: Changes an exam's `status` in the `exams` table.
    -   `deleteExam()`: Removes an exam, along with all related `violations` and `exam_submissions` (using database cascade or explicit DELETE statements within a transaction).
    -   `duplicateExam()`: Creates a new `draft` exam by copying all details and questions from an existing one.
    -   `unlockStudent()`: Resets a student's anti-cheating `violations` for a specific exam and removes their `forced` submission status, allowing re-entry.

-   **Results & Grading (`teacher/results.html`):
    -   Fetches aggregated `get_results` from `php/exam_api.php` to show overall exam performance. These results are used to generate score distribution charts (`Chart.js`).
    -   Teachers can access individual student submissions (`get_submission_detail`) to manually grade essay questions and `save_manual_grade` via API, updating the student's final score.

-   **Profile Management (`teacher/settings.html`):** `fetchProfile()` retrieves the current teacher's data. `saveProfile()` submits updates to `php/exam_api.php?action=update_profile`.

### 3. Student Workflow

-   **Exam Enrollment:** Students can join exams typically via an exam code on `student/dashboard.html`, which uses `php/exam_api.php?action=join_exam`.

-   **Taking an Exam (`student/exam.html`):
    -   `ExamEngine.init()` fetches exam details and questions (`php/exam_api.php?action=get_exam`).
    -   `ExamEngine.renderQuestions()` dynamically displays questions, manages navigation, and handles student input for answers.
    -   `ExamEngine.startTimer()` initiates a countdown, automatically submitting the exam if time expires.
    -   `ExamSecurity.init()` (from `js/security.js`) starts monitoring for cheating behaviors.
    -   `ExamEngine.submitExam()` sends all collected answers to `php/exam_api.php?action=submit_answers`. The backend processes answers, calculates auto-scores (for objective questions), and logs the submission.

-   **Anti-Cheating:** During an exam, `js/security.js` constantly monitors student behavior. Detected violations (e.g., tab switching, developer tools opening) trigger `php/exam_api.php?action=report_violation` to log the incident. After a configured number of violations, `js/security.js` can force-stop the exam by calling `ExamEngine.submitExam(true, 'Dihentikan paksa karena pelanggaran')`.

---

## ⚙️ Gemini CLI's Contributions

During this session, Gemini CLI implemented several critical fixes and enhancements to significantly improve the functionality, reliability, and user experience of the ExamSafe system, particularly within the teacher's workflow.

### 1. Dynamic Navbar Name Display Across Teacher Pages

**Problem:** The teacher's name displayed in the navigation bar (e.g., "Pak Budi" or "Guru") on `teacher/create-exam.html`, `teacher/dashboard.html`, `teacher/results.html`, and `teacher/settings.html` was hardcoded. This resulted in an static, incorrect display if the logged-in teacher was different from the hardcoded value.

**Analysis:** It was identified that `fetchProfile()` JavaScript function, present in each of these pages, was already designed to asynchronously fetch `user.full_name` from `../php/exam_api.php?action=get_profile` and update the navbar's `nav-user span` and `nav-avatar` elements. The hardcoded text, however, was preventing this dynamic update from becoming visible.

**Solution:**
-   In `teacher/create-exam.html`, `teacher/dashboard.html`, `teacher/results.html`, and `teacher/settings.html`, the hardcoded text within the `<div class="nav-user">...<span>Hardcoded Name</span>...</div>` HTML structure was replaced with empty `<span>` tags. This allowed the existing `fetchProfile()` logic to correctly populate these elements.
-   In `teacher/dashboard.html`, the hardcoded welcome message "Selamat datang, Pak Budi Hartono — Matematika" in the `page-subtitle` element was similarly replaced with an empty `<div>` tag, enabling the JavaScript to dynamically insert the logged-in teacher's full name and subject.

**Impact:** This ensures that the teacher's name and avatar displayed in the navigation bar and welcome messages are consistently and dynamically populated based on the currently logged-in user's profile data, providing a correct and personalized user experience across all teacher-facing pages.

### 2. "Lanjut: Tambah Soal" Button Functionality Fix

**Problem:** The button labeled "Lanjut: Tambah Soal" on the first step of the exam creation form (`teacher/create-exam.html`), intended to navigate the teacher from the "Exam Info" step to the "Add Questions" step, was unresponsive when clicked.

**Analysis:** Upon inspection of the `teacher/create-exam.html` source, it was found that the `<button>` element with `id="go-step-2"` was missing its crucial `onclick="goStep(2)"` attribute. Without this attribute, the `goStep` JavaScript function, responsible for step navigation, was never invoked.

**Solution:** The `onclick="goStep(2)"` attribute was explicitly added to the `<button class="btn btn-primary" id="go-step-2">` element.

**Impact:** The exam creation workflow now functions as intended, allowing teachers to seamlessly proceed from the initial exam information setup to the subsequent step of adding questions, thereby unblocking a critical part of the application's core functionality.

### 3. Enhanced Teacher Exam Preview

**Problem:** The "Preview" page (Step 4) within the `teacher/create-exam.html` interface provided a generic and unformatted display of the created questions. This generic rendering did not accurately reflect how students would perceive the questions during an actual exam, making it difficult for teachers to thoroughly review and verify the exam's visual presentation and content before publication.

**Analysis:** To address this, a detailed review of the student-facing rendering logic within `js/exam.js` (specifically `renderQuestions`, `renderOptions`, and `renderEssay` functions) was conducted. The goal was to replicate this student-centric display within the teacher's preview to ensure consistency and accuracy.

**Solution:** The `renderPreview` JavaScript function in `teacher/create-exam.html` was extensively refactored to:
-   **Replicate Student UI:** Adopted a similar HTML structure and applied inline CSS styles consistent with the student's `exam.html` view for question cards, question text, and media containers.
-   **Media Display:** Enhanced to correctly parse and display `media_url` for questions, supporting the presentation of multiple images or other media associated with a single question.
-   **Option Rendering:** For multiple-choice, checkbox, and true/false questions, all defined options are now displayed. Crucially, for the teacher's review, the *correct answer(s)* are visually highlighted using a distinct green background and a "✓ BENAR" label, allowing for immediate verification.
-   **Essay Question Display:** Essay questions are clearly identified, and their associated "Kunci Jawaban/Rubrik" (answer key/rubric) is displayed.
-   **Safety Functions:** Incorporated `escapeHtml` and `escapeUrl` helper functions to safely render dynamic content, preventing potential cross-site scripting (XSS) vulnerabilities.

**Impact:** Teachers can now visualize their exams almost exactly as students will experience them. This significant enhancement improves the quality assurance process, allowing teachers to detect and correct layout issues, content errors, and incorrect answer markings more effectively before an exam is published, thereby enhancing the overall reliability and fairness of the assessments.

### 4. "Connection Error" on Exam Publication Fix

**Problem:** When a teacher attempted to publish an exam after setting up all its details and questions, the system consistently returned a "Terjadi Kesalahan Koneksi" (Connection Error) message. This error indicated a fundamental mismatch in the data contract between the frontend's `publishExam` function and the backend's `createExam` API endpoint (`php/exam_api.php`).

**Analysis:**
-   **Frontend Data Transmission:** The `publishExam` JavaScript function in `teacher/create-exam.html` was collecting complex data for `correct` (a single index for multiple-choice/true-false, or text for essay) and `correct_answers_checkbox` (an array of indices for checkbox questions). The `media_url` for questions was also managed as an array of URLs.
-   **Backend Data Expectation:** The `createExam` function within `php/exam_api.php`'s database `INSERT` statement for questions (`questions` table) expected `correct_answer` and `media_url` to be simple string types (e.g., `VARCHAR` or `TEXT`). Sending JavaScript arrays or objects directly resulted in PHP's `json_decode` failing or incorrect string conversion during database insertion, leading to the connection error.

**Solution:** A two-part solution was implemented to ensure data consistency:
-   **Frontend Modification (`teacher/create-exam.html` - `publishExam` function):**
    -   A new `processedQuestions` array was introduced. Before the `examData` object is sent, each question in the original `questions` array is iterated and transformed.
    -   `correct_answer` Field Standardization:
        -   For `'multiple'`, `'truefalse'`, and `'essay'` question types, the `q.correct` value (which is a single index or text string) is directly assigned to the `correct_answer` field.
        -   For `'checkbox'` questions, `q.correct_answers_checkbox` (an array of selected option indices) is `JSON.stringify`d into a single string. This allows the backend to store a JSON array as a string in a database text field.
    -   `media_url` Field Standardization: The array of media URLs for a question (`q.media_url`) is consistently `JSON.stringify`d into a single string. This ensures that the backend receives a valid string for database insertion, rather than an array.
    -   The `examData` object now sends this `processedQuestions` array to the backend.
-   **Backend Modification (`php/exam_api.php` - `createExam` function):**
    -   The loop that processes incoming questions was updated.
    -   `$correctAnswer = $q['correct_answer'] ?? '';`: This now directly retrieves the `correct_answer` string (which is already pre-formatted as a plain string or JSON string from the frontend). No further `sanitize` or `json_encode` is applied here for `correct_answer` as it's already prepared.
    -   `$mediaUrl = $q['media_url'] ?? '[]';`: Similarly, this directly retrieves the `media_url` JSON string from the frontend. It defaults to an empty JSON array string `[]` if no media is present.
    -   The `INSERT` statement parameters within the `createExam` function were updated to use these correctly prepared `$correctAnswer` and `$mediaUrl` variables.

**Impact:** This comprehensive fix ensures perfect alignment between the data format sent by the frontend and the data expected by the backend. As a result, exams can now be successfully published without encountering "Connection Error" messages, establishing a reliable data pipeline for all question types and associated media. This significantly improves the stability and core functionality of the exam creation process.

---

## 🤝 Collaboration Notes for Future AI / Developers

-   **Frontend-Backend Contract:** A strict adherence to the data contract between frontend (JavaScript `fetch` requests) and backend (PHP API endpoints) is paramount. Pay meticulous attention to data types: JavaScript arrays or objects intended for storage in `VARCHAR` or `TEXT` database fields *must* be `JSON.stringify`d on the frontend and `json_decode`d on the backend (when retrieving) to prevent data corruption or processing errors.
-   **Error Handling and Debugging:** Implement robust error logging on both the client-side (extensive `console.error` and user-friendly `alert` messages) and server-side (PHP error logs) to facilitate rapid diagnosis of issues. Tools like browser developer consoles (for network requests and JavaScript errors) and server access logs are indispensable.
-   **Dynamic Content Management:** When developing features that involve dynamic content population (e.g., user names in navbars, generated lists), ensure that the target HTML elements are initially empty or contain generic placeholders. JavaScript should then reliably select and update these elements, avoiding hardcoded values that might become stale.
-   **Code Consistency and Maintainability:** Strive for consistent naming conventions, coding styles, and modularization across the entire project. Clear function responsibilities and well-commented code are crucial for long-term maintainability and collaborative development.
-   **Security Best Practices:** Always prioritize security. All user inputs received on the backend *must* be sanitized (e.g., using `sanitize()` in `db.php`) before being used in database queries or displayed on the frontend, effectively guarding against common vulnerabilities such as SQL injection and Cross-Site Scripting (XSS). Parameterized queries (used with PDO) are essential for database interaction safety.

This updated `GEMINI_CHANGES.md` provides an in-depth understanding of the ExamSafe project's current state and the significant improvements made. It is designed to empower any future development efforts with clarity and actionable insights.
