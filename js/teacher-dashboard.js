/**
 * Teacher Dashboard Controller
 * Manages dashboard UI updates, stats, and violations table
 * Depends on: utils.js, teacher-api.js, toast.js, exam-manager.js
 */

const TeacherDashboard = {
    examManager: null,
    statsElements: null,

    /**
     * Initialize dashboard with exam manager reference
     * @param {ExamManager} manager - The exam manager instance
     */
    init(manager) {
        this.examManager = manager;
        this.cacheStatsElements();
        this.loadDashboardData();
    },

    /**
     * Cache DOM elements for stats cards
     */
    cacheStatsElements() {
        const statValues = document.querySelectorAll('.stat-value');
        this.statsElements = {
            totalExams: statValues[0],
            activeExams: statValues[1],
            totalStudents: statValues[2],
            averageScore: statValues[3]
        };
    },

    /**
     * Load all dashboard data
     */
    async loadDashboardData() {
        try {
            // Update stats from exams if available
            if (this.examManager && this.examManager.allExams) {
                this.updateStatsFromExams(this.examManager.allExams);
            }

            // Fetch teacher stats and violations via API
            const { stats, violations } = await TeacherAPI.getDashboardData();

            if (stats.success) {
                this.updateStatsCards(stats.total_students || 0, stats.average_score || 0);
            }

            if (violations.success) {
                this.renderViolationsTable(violations.violations || []);
            }
        } catch (error) {
            console.error('TeacherDashboard.loadDashboardData error:', error);
            Toast.error('Gagal memuat data dashboard');
        }
    },

    /**
     * Update stats cards from exam data
     * @param {Array} exams - List of exams
     */
    updateStatsFromExams(exams) {
        if (!exams) return;
        const total = exams.length;
        const active = exams.filter(e => e.status === 'active').length;
        
        if (this.statsElements.totalExams) {
            this.statsElements.totalExams.textContent = total;
        }
        if (this.statsElements.activeExams) {
            this.statsElements.activeExams.textContent = active;
        }
    },

    /**
     * Update stats cards with student count and average score
     * @param {number} totalStudents - Total students count
     * @param {number} averageScore - Average score
     */
    updateStatsCards(totalStudents, averageScore) {
        if (this.statsElements.totalStudents) {
            this.statsElements.totalStudents.textContent = totalStudents || 0;
        }
        if (this.statsElements.averageScore) {
            this.statsElements.averageScore.textContent = averageScore || 0;
        }
    },

    /**
     * Render violations table
     * @param {Array} violations - List of violations
     */
    renderViolationsTable(violations) {
        const tbody = document.getElementById('violations-tbody');
        if (!tbody) return;

        if (!violations || violations.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:20px">Tidak ada pelanggaran terdeteksi.</td></tr>';
            return;
        }

        tbody.innerHTML = violations.map(v => `
            <tr>
                <td><b>${escapeHtml(v.student_name)}</b></td>
                <td>${escapeHtml(v.exam_name)}</td>
                <td>${escapeHtml(v.reason)}</td>
                <td>${formatTime(v.created_at)}</td>
                <td><span class="badge badge-${v.violation_count >= 3 ? 'danger' : 'warning'}">${v.violation_count >= 3 ? 'Dihentikan' : 'Peringatan'}</span></td>
            </tr>
        `).join('');
    },

    /**
     * Show monitor for first available exam
     */
    showMonitorForFirstExam() {
        if (!this.examManager) {
            Toast.error('Exam manager belum siap');
            return;
        }

        const exams = this.examManager.allExams;
        if (!exams || exams.length === 0) {
            Toast.info('Belum ada ujian yang tersedia');
            return;
        }

        const activeExam = exams.find(e => e.status === 'active');
        const targetExam = activeExam || exams[0];
        
        this.examManager.showMonitor(targetExam.id, targetExam.name);
    }
};

// Make globally available
window.TeacherDashboard = TeacherDashboard;