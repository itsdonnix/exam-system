/**
 * Teacher API Layer
 * All teacher-related API calls using ApiClient
 * Depends on: api-client.js
 *
 * NOTE: All methods throw error messages (strings) on failure.
 * Caller should use try/catch and show appropriate UI feedback.
 */

const TeacherAPI = {
  /**
   * Get teacher statistics (total students, average score)
   * @returns {Promise<Object>} { success, total_students, average_score }
   * @throws {string} Error message
   */
  async getTeacherStats() {
    try {
      const result = await ApiClient.get(
        "../php/exam_api.php?action=get_teacher_stats"
      );
      if (!result.success)
        throw result.message || "Gagal memuat statistik guru";
      return result;
    } catch (error) {
      throw error.message || error;
    }
  },

  /**
   * Get recent violations
   * @returns {Promise<Object>} { success, violations }
   * @throws {string} Error message
   */
  async getRecentViolations() {
    try {
      const result = await ApiClient.get(
        "../php/exam_api.php?action=get_recent_violations"
      );
      if (!result.success)
        throw result.message || "Gagal memuat pelanggaran terbaru";
      return result;
    } catch (error) {
      throw error.message || error;
    }
  },

  /**
   * Fetch all dashboard data in parallel
   * @returns {Promise<Object>} { stats, violations }
   * @throws {string} Error message
   */
  async getDashboardData() {
    try {
      const [statsResult, violationsResult] = await Promise.all([
        this.getTeacherStats(),
        this.getRecentViolations(),
      ]);
      return {
        stats: statsResult,
        violations: violationsResult,
      };
    } catch (error) {
      throw error;
    }
  },

  /**
   * Get exam information by ID
   * @param {number} examId
   * @returns {Promise<Object>} { success, exam }
   * @throws {string} Error message
   */
  async getExamInfo(examId) {
    try {
      const result = await ApiClient.get(
        `../php/exam_api.php?action=get_exam_info&exam_id=${examId}`
      );
      if (!result.success)
        throw result.message || "Gagal memuat informasi ujian";
      return result;
    } catch (error) {
      throw error.message || error;
    }
  },

  /**
   * Get exam results with optional class filter
   * @param {number} examId
   * @param {string} classFilter - Optional class name filter
   * @returns {Promise<Object>} { success, results, stats }
   * @throws {string} Error message
   */
  async getResults(examId, classFilter = "") {
    try {
      let url = `../php/exam_api.php?action=get_results&exam_id=${examId}`;
      if (classFilter) {
        url += `&class=${encodeURIComponent(classFilter)}`;
      }
      const result = await ApiClient.get(url);
      if (!result.success) throw result.message || "Gagal memuat hasil ujian";
      return result;
    } catch (error) {
      throw error.message || error;
    }
  },

  /**
   * Get student violations for a specific exam
   * @param {number} studentId
   * @param {number} examId
   * @returns {Promise<Object>} { success, violations, total_count }
   * @throws {string} Error message
   */
  async getStudentViolations(studentId, examId) {
    try {
      const result = await ApiClient.get(
        `../php/exam_api.php?action=get_student_violations&student_id=${studentId}&exam_id=${examId}`
      );
      if (!result.success)
        throw result.message || "Gagal memuat detail pelanggaran";
      return result;
    } catch (error) {
      throw error.message || error;
    }
  },

  /**
   * Get submission detail for grading
   * @param {number} submissionId
   * @returns {Promise<Object>} { success, submission, questions }
   * @throws {string} Error message
   */
  async getSubmissionDetail(submissionId) {
    try {
      const result = await ApiClient.get(
        `../php/exam_api.php?action=get_submission_detail&id=${submissionId}`
      );
      if (!result.success)
        throw result.message || "Gagal memuat detail jawaban";
      return result;
    } catch (error) {
      throw error.message || error;
    }
  },

  /**
   * Save manual grading for essay questions
   * @param {number} submissionId
   * @param {number} manualScore
   * @param {string} csrfToken
   * @returns {Promise<Object>} { success, message, total_score }
   * @throws {string} Error message
   */
  async saveManualGrade(submissionId, manualScore, csrfToken) {
    try {
      const result = await ApiClient.post("../php/exam_api.php", {
        action: "save_manual_grade",
        submission_id: submissionId,
        manual_score: manualScore,
        csrf_token: csrfToken,
      });
      if (!result.success)
        throw result.message || "Gagal menyimpan nilai manual";
      return result;
    } catch (error) {
      throw error.message || error;
    }
  },
};

// Make globally available
window.TeacherAPI = TeacherAPI;
