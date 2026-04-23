/**
 * ExamSafe Student API Layer
 * Wraps ApiClient for all student-facing endpoints.
 * Methods return raw JSON — callers check data.success and handle errors.
 *
 * Usage:
 *   const data = await StudentAPI.getExam(examId);
 *   if (!data.success) { showToast(data.message, 'error'); return; }
 */
const StudentAPI = {
  // ── Exam ──────────────────────────────────────────────

  async getExam(examId) {
    return ApiClient.get(
      `../php/exam_api.php?action=get_exam&exam_id=${examId}`
    );
  },

  async startExam(examId) {
    return ApiClient.post("../php/exam_api.php", {
      action: "start_exam",
      exam_id: examId,
    });
  },

  async submitAnswers({ examId, answers, forced = false, timeTaken }) {
    return ApiClient.post("../php/exam_api.php", {
      action: "submit_answers",
      exam_id: examId,
      answers,
      forced,
      time_taken: timeTaken,
    });
  },

  // ── Student Profile ───────────────────────────────────

  async getProfile() {
    return ApiClient.get("../php/exam_api.php?action=get_profile");
  },

  // ── Dashboard ─────────────────────────────────────────

  async getExams() {
    return ApiClient.get("../php/exam_api.php?action=get_exams");
  },

  async getHistory() {
    return ApiClient.get("../php/exam_api.php?action=get_student_history");
  },

  // ── Agreement ─────────────────────────────────────────

  async logAgreement(examId) {
    return ApiClient.post("../php/exam_api.php", {
      action: "log_agreement",
      exam_id: examId,
      timestamp: new Date().toISOString(),
    });
  },

  // ── Violations ────────────────────────────────────────

  async reportViolation(examId, reason) {
    return ApiClient.post("../php/exam_api.php", {
      action: "report_violation",
      exam_id: examId,
      reason,
      violation_count: 1,
    });
  },

  // ── Exam Join ─────────────────────────────────────────

  async joinExam(examCode) {
    return ApiClient.post("../php/exam_api.php", {
      action: "join_exam",
      exam_code: examCode,
    });
  },
};
