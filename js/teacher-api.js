/**
 * Teacher API Layer
 * All teacher-related API calls using ApiClient
 * Depends on: api-client.js, toast.js
 */

const TeacherAPI = {
    /**
     * Get teacher statistics (total students, average score)
     * @returns {Promise<Object>} { success, total_students, average_score }
     */
    async getTeacherStats() {
        try {
            const result = await ApiClient.get('../php/exam_api.php?action=get_teacher_stats');
            if (!result.success) {
                Toast.error('Gagal memuat statistik: ' + (result.message || 'Unknown error'));
                return { success: false, total_students: 0, average_score: 0 };
            }
            return result;
        } catch (error) {
            console.error('TeacherAPI.getTeacherStats error:', error);
            Toast.error('Gagal memuat statistik guru');
            return { success: false, total_students: 0, average_score: 0 };
        }
    },

    /**
     * Get recent violations
     * @returns {Promise<Object>} { success, violations }
     */
    async getRecentViolations() {
        try {
            const result = await ApiClient.get('../php/exam_api.php?action=get_recent_violations');
            if (!result.success) {
                Toast.error('Gagal memuat pelanggaran: ' + (result.message || 'Unknown error'));
                return { success: false, violations: [] };
            }
            return result;
        } catch (error) {
            console.error('TeacherAPI.getRecentViolations error:', error);
            Toast.error('Gagal memuat data pelanggaran');
            return { success: false, violations: [] };
        }
    },

    /**
     * Fetch all dashboard data in parallel
     * @returns {Promise<Object>} { stats, violations }
     */
    async getDashboardData() {
        try {
            const [statsResult, violationsResult] = await Promise.all([
                this.getTeacherStats(),
                this.getRecentViolations()
            ]);
            return {
                stats: statsResult,
                violations: violationsResult
            };
        } catch (error) {
            console.error('TeacherAPI.getDashboardData error:', error);
            Toast.error('Gagal memuat data dashboard');
            return { stats: { success: false }, violations: { success: false, violations: [] } };
        }
    }
};

// Make globally available
window.TeacherAPI = TeacherAPI;