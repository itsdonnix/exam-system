/**
 * ExamManager - Shared module for exam management
 * Handles exam CRUD, monitoring, and filtering for both teacher and admin dashboards
 * ENHANCED: Removed Toleransi, added search, simplified UI, kept Aktif stat
 */

class ExamManager {
  constructor(options) {
    this.containerId = options.containerId || "exam-list";
    this.searchInputId = options.searchInputId || "examSearch";
    this.role = options.role || "teacher"; // 'teacher' or 'admin'
    this.onExamAction = options.onExamAction || (() => {});
    this.allExams = [];
    this.currentMonitorExamId = null;
    this.monitorRefreshInterval = null;
    this.currentParticipants = []; // Store for filtering
    this.monitorSearchTerm = "";
    this.apiBaseUrl = "../php/exam_api.php";

    this.init();
  }

  init() {
    this.renderSkeleton();
    this.attachSearchListener();
    this.attachModalCloseListener();
    this.injectViolationModal();
  }

  renderSkeleton() {
    const container = document.getElementById(this.containerId);
    if (!container) return;

    container.innerHTML = `
      <div class="exam-card">
        <div class="exam-card-info">
          <div class="skeleton-loader">
            <div class="skeleton-line" style="width: 40%"></div>
            <div class="skeleton-line" style="width: 60%"></div>
            <div class="skeleton-line" style="width: 30%"></div>
          </div>
        </div>
      </div>
      <div class="exam-card">
        <div class="exam-card-info">
          <div class="skeleton-loader">
            <div class="skeleton-line" style="width: 50%"></div>
            <div class="skeleton-line" style="width: 70%"></div>
            <div class="skeleton-line" style="width: 40%"></div>
          </div>
        </div>
      </div>
    `;
  }

  attachSearchListener() {
    const searchInput = document.getElementById(this.searchInputId);
    if (searchInput) {
      searchInput.addEventListener("keyup", () => this.filterExams());
    }
  }

  attachModalCloseListener() {
    const modal = document.getElementById("monitor-modal");
    const closeBtn = modal?.querySelector(".modal-close");
    const overlay = modal?.querySelector(".modal-overlay");

    if (closeBtn) {
      closeBtn.onclick = () => this.closeMonitor();
    }

    if (overlay) {
      overlay.onclick = () => this.closeMonitor();
    }
  }

  injectViolationModal() {
    if (document.getElementById("violation-detail-modal")) return;

    const modalHTML = `
      <div class="modal-overlay" id="violation-detail-modal">
        <div class="modal" style="max-width: 500px">
          <div class="modal-header">
            <div class="modal-title">📋 Detail Pelanggaran</div>
            <button class="modal-close" onclick="document.getElementById('violation-detail-modal').classList.remove('active')">✕</button>
          </div>
          <div id="violation-detail-content" style="padding: 20px">
            <div style="text-align: center; color: #64748b">Memuat...</div>
          </div>
          <div style="padding: 20px; border-top: 1px solid #eee; text-align: right">
            <button class="btn btn-danger" id="delete-violation-btn" style="display: none;">
              🗑️ Hapus Pelanggaran
            </button>
            <button class="btn btn-outline" onclick="document.getElementById('violation-detail-modal').classList.remove('active')">Tutup</button>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML("beforeend", modalHTML);
  }

  async fetchExams() {
    try {
      const response = await fetch(`${this.apiBaseUrl}?action=get_exams`, {
        credentials: "include",
      });
      const data = await response.json();

      if (data.success) {
        this.allExams = data.exams;
        this.renderExams(this.allExams);
        return this.allExams;
      } else {
        console.error("Failed to fetch exams:", data.message);
        return [];
      }
    } catch (error) {
      console.error("Error fetching exams:", error);
      return [];
    }
  }

  filterExams() {
    const searchInput = document.getElementById(this.searchInputId);
    const query = searchInput?.value.toLowerCase() || "";

    const filtered = this.allExams.filter(
      (exam) =>
        exam.name.toLowerCase().includes(query) ||
        exam.class.toLowerCase().includes(query) ||
        exam.subject.toLowerCase().includes(query) ||
        (this.role === "admin" &&
          exam.teacher_name?.toLowerCase().includes(query))
    );

    this.renderExams(filtered);
  }

  renderExams(exams) {
    const container = document.getElementById(this.containerId);
    if (!container) return;

    if (!exams || exams.length === 0) {
      container.innerHTML =
        '<div style="text-align:center;padding:40px;color:#64748b">Belum ada ujian atau tidak ditemukan.</div>';
      return;
    }

    container.innerHTML = exams
      .map((exam) => this.renderExamCard(exam))
      .join("");
  }

  renderExamCard(exam) {
    const statusClass =
      exam.status === "active"
        ? "badge-success"
        : exam.status === "ended"
        ? "badge-secondary"
        : "badge-warning";
    const statusText =
      exam.status.charAt(0).toUpperCase() + exam.status.slice(1);
    const examDate = new Date(exam.start_time).toLocaleDateString("id-ID", {
      day: "numeric",
      month: "short",
      year: "numeric",
    });
    const examTime = new Date(exam.start_time).toLocaleTimeString("id-ID", {
      hour: "2-digit",
      minute: "2-digit",
    });

    let actionButtons = `
      ${
        exam.status === "draft" || exam.status === "ended"
          ? `<button class="btn btn-sm btn-success" onclick="window.examManager.activateExam(${exam.id})">▶️ Aktifkan</button>`
          : `<button class="btn btn-sm btn-danger" onclick="window.examManager.deactivateExam(${exam.id})">⏹ Berhentikan</button>`
      }
      ${
        exam.status === "active"
          ? `<button class="btn btn-sm btn-secondary" onclick="window.examManager.showMonitor(${
              exam.id
            }, '${exam.name.replace(/'/g, "\\'")}')">👁️ Monitor</button>`
          : `<button class="btn btn-sm btn-outline" onclick="window.examManager.deleteExam(${exam.id})">🗑️ Hapus</button>`
      }
      <button class="btn btn-sm btn-outline" onclick="window.examManager.duplicateExam(${
        exam.id
      })">👯 Duplikat</button>
    `;

    if (this.role === "teacher") {
      actionButtons += `<a href="results.html?exam_id=${exam.id}" class="btn btn-sm btn-outline">📊 Hasil</a>`;
    }

    return `
      <div class="exam-card" style="border-left-color: ${
        exam.status === "active" ? "var(--success)" : "#94a3b8"
      }">
        <div class="exam-card-info">
          <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px; flex-wrap:wrap;">
            <span class="badge badge-info" style="font-size:0.7rem">📅 ${examDate}</span>
            <h4>${exam.name}</h4>
          </div>
          <div class="exam-card-meta">
            <span>📚 ${exam.subject}</span>
            <span>👥 ${exam.class}</span>
            <span>⏱️ ${exam.duration_minutes} menit</span>
            <span>📝 ${exam.question_count} soal</span>
          </div>
          <div class="exam-card-meta">
            <span class="badge ${statusClass}">${statusText}</span>
            <span>🕒 Mulai: ${examTime}</span>
            <span>🔑 Kode: <b style="letter-spacing:1px">${
              exam.exam_code
            }</b></span>
            ${
              this.role === "admin" && exam.teacher_name
                ? `<span>👨‍🏫 Guru: ${exam.teacher_name}</span>`
                : ""
            }
          </div>
        </div>
        <div class="exam-card-actions">
          ${actionButtons}
        </div>
      </div>
    `;
  }

  async activateExam(id) {
    if (
      !confirm(
        "Aktifkan ujian ini? Setelah aktif, siswa dapat mengerjakan sesuai jadwal."
      )
    )
      return;

    try {
      const response = await fetch(this.apiBaseUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "activate_exam", exam_id: id }),
      });
      const result = await response.json();

      if (result.success) {
        alert("🚀 " + result.message);
        await this.fetchExams();
        this.onExamAction();
      } else {
        alert("❌ " + result.message);
      }
    } catch (error) {
      console.error("Error activating exam:", error);
      alert("Terjadi kesalahan saat mengaktifkan ujian.");
    }
  }

  async deactivateExam(id) {
    if (
      !confirm(
        "⚠️ Hentikan ujian ini?\nSiswa yang sedang mengerjakan akan terputus dan tidak bisa masuk lagi."
      )
    )
      return;

    try {
      const response = await fetch(this.apiBaseUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "deactivate_exam", exam_id: id }),
      });
      const result = await response.json();

      if (result.success) {
        alert("⏹ " + result.message);
        await this.fetchExams();
        this.onExamAction();
      } else {
        alert("❌ " + result.message);
      }
    } catch (error) {
      console.error("Error deactivating exam:", error);
      alert("Terjadi kesalahan saat menghentikan ujian.");
    }
  }

  async deleteExam(id) {
    if (
      !confirm(
        "⚠️ PERHATIAN!\nMenghapus ujian akan menghapus seluruh soal dan data nilai siswa terkait ujian ini.\n\nApakah Anda yakin ingin menghapus ujian ini secara permanen?"
      )
    )
      return;

    try {
      const response = await fetch(this.apiBaseUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "delete_exam", exam_id: id }),
      });
      const result = await response.json();

      if (result.success) {
        alert("🗑️ " + result.message);
        await this.fetchExams();
        this.onExamAction();
      } else {
        alert("❌ " + result.message);
      }
    } catch (error) {
      console.error("Error deleting exam:", error);
      alert("Terjadi kesalahan saat menghapus ujian.");
    }
  }

  async duplicateExam(id) {
    if (
      !confirm(
        "Duplikat ujian ini?\nSeluruh soal akan disalin secara otomatis ke draf baru."
      )
    )
      return;

    try {
      const response = await fetch(this.apiBaseUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "duplicate_exam", exam_id: id }),
      });
      const result = await response.json();

      if (result.success) {
        alert("✅ " + result.message);
        await this.fetchExams();
        this.onExamAction();
      } else {
        alert("❌ " + result.message);
      }
    } catch (error) {
      console.error("Error duplicating exam:", error);
      alert("Terjadi kesalahan saat menduplikasi ujian.");
    }
  }

  async showMonitor(examId, examName) {
    this.currentMonitorExamId = examId;
    this.monitorSearchTerm = "";
    const modal = document.getElementById("monitor-modal");
    if (!modal) {
      console.error("Monitor modal not found");
      return;
    }

    modal.classList.add("active");

    // Update modal structure with search row and footer
    this.enhanceMonitorModalStructure(modal, examName);

    await this.loadMonitorData(examId);
    this.startMonitorRefresh(examId);
  }

  enhanceMonitorModalStructure(modal, examName) {
    // Update title
    const titleElement = modal.querySelector(".modal-title");
    if (titleElement) {
      titleElement.innerHTML = `👁️ Monitor Ujian — ${examName}`;
    }

    // Check if search row already exists
    if (!modal.querySelector(".monitor-search-row")) {
      const statsGrid = modal.querySelector(".stats-grid");
      if (statsGrid) {
        // Insert search row after stats grid
        const searchRow = document.createElement("div");
        searchRow.className = "monitor-search-row";
        searchRow.style.cssText =
          "padding: 12px 0; border-bottom: 1px solid #e2e8f0;";
        searchRow.innerHTML = `
          <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 0.85rem; color: #64748b;">🔍</span>
            <input type="text" id="monitor-search-input" class="form-control" placeholder="Cari siswa..." style="flex: 1; padding: 8px 12px; font-size: 0.85rem;">
          </div>
        `;
        statsGrid.insertAdjacentElement("afterend", searchRow);

        // Add search event listener
        const searchInput = document.getElementById("monitor-search-input");
        if (searchInput) {
          searchInput.addEventListener("keyup", (e) => {
            this.monitorSearchTerm = e.target.value.toLowerCase();
            this.renderMonitorTable(this.currentParticipants);
          });
        }
      }
    }

    // Check if footer exists
    if (!modal.querySelector(".monitor-footer")) {
      const tableWrapper = modal.querySelector(".table-wrapper");
      if (tableWrapper) {
        const footer = document.createElement("div");
        footer.className = "monitor-footer";
        footer.style.cssText =
          "padding: 12px 16px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; align-items: center; gap: 8px; font-size: 0.7rem; color: #64748b;";
        footer.innerHTML = `
          <span class="auto-refresh-footer">
            <span class="pulse-dot"></span>
            Auto-refresh 30s
          </span>
        `;
        tableWrapper.insertAdjacentElement("afterend", footer);
      }
    }
  }

  async loadMonitorData(examId) {
    const tbody = document.getElementById("monitor-tbody");
    if (tbody) {
      tbody.innerHTML =
        '<tr><td colspan="6" style="text-align:center">Memuat...</td></tr>';
    }

    try {
      const response = await fetch(
        `${this.apiBaseUrl}?action=get_exam_monitor&exam_id=${examId}`
      );
      const data = await response.json();

      if (data.success) {
        // Update stats - USE data.stats for all counts
        const totalEl = document.getElementById("monitor-total");
        const activeEl = document.getElementById("monitor-active");
        const finishedEl = document.getElementById("monitor-finished");
        const violationsEl = document.getElementById("monitor-violations");

        if (totalEl) totalEl.textContent = data.stats.total || 0;
        // FIXED: Use data.stats.active instead of participants length
        if (activeEl) activeEl.textContent = data.stats.active || 0;
        if (finishedEl) finishedEl.textContent = data.stats.finished || 0;
        if (violationsEl) violationsEl.textContent = data.stats.violation || 0;

        // Make sure active stat is visible
        if (activeEl && activeEl.parentElement) {
          activeEl.parentElement.style.display = null;
        }

        this.currentParticipants = data.participants || [];
        this.renderMonitorTable(this.currentParticipants);
      } else {
        if (tbody)
          tbody.innerHTML =
            '<tr><td colspan="6" style="text-align:center;color:#64748b">Gagal memuat data monitor. </div>';
      }
    } catch (error) {
      console.error("Error loading monitor:", error);
      const tbody = document.getElementById("monitor-tbody");
      if (tbody)
        tbody.innerHTML =
          '<tr><td colspan="6" style="text-align:center;color:#64748b">Terjadi kesalahan. </div>';
    }
  }

  renderMonitorTable(participants) {
    const tbody = document.getElementById("monitor-tbody");
    if (!tbody) return;

    // Filter participants by search term
    let filteredParticipants = participants;
    if (this.monitorSearchTerm) {
      filteredParticipants = participants.filter((p) =>
        p.full_name.toLowerCase().includes(this.monitorSearchTerm)
      );
    }

    if (!filteredParticipants || filteredParticipants.length === 0) {
      tbody.innerHTML =
        '<tr><td colspan="6" style="text-align:center;padding:20px">' +
        (this.monitorSearchTerm
          ? "Tidak ada siswa yang cocok."
          : "Belum ada siswa yang bergabung.") +
        "</td></tr>";
      return;
    }

    tbody.innerHTML = filteredParticipants
      .map((p) => {
        // Score display
        const scoreDisplay =
          p.total_score !== null && p.total_score !== undefined
            ? `${p.total_score}`
            : p.score || "—";

        // Status icon - use the status field from database
        let statusIcon = "";
        let statusTooltip = "";
        if (p.is_forced == 1) {
          statusIcon = "❌";
          statusTooltip = "Dipaksa keluar";
        } else if (p.status === "submitted") {
          statusIcon = "✅";
          statusTooltip = "Selesai";
        } else if (p.status === "in_progress") {
          statusIcon = "🟡";
          statusTooltip = "Sedang Mengerjakan";
        } else {
          // Fallback for old data
          if (p.status === "graded") {
            statusIcon = "✅";
            statusTooltip = "Selesai";
          } else if (p.status === "pending") {
            statusIcon = "⏳";
            statusTooltip = "Pending (Esai)";
          } else {
            statusIcon = "🟡";
            statusTooltip = "Dalam Proses";
          }
        }

        // Violation badge
        let violationBadge = "";
        if (p.v_count === 0) {
          violationBadge = '<span class="violation-badge zero">🟢 0</span>';
        } else if (p.v_count <= 2) {
          violationBadge = `<span class="violation-badge low" onclick="window.examManager.showViolationDetails(${
            p.student_id
          }, '${p.full_name.replace(/'/g, "\\'")}', ${
            this.currentMonitorExamId
          })">⚠️ ${p.v_count}</span>`;
        } else {
          violationBadge = `<span class="violation-badge high" onclick="window.examManager.showViolationDetails(${
            p.student_id
          }, '${p.full_name.replace(/'/g, "\\'")}', ${
            this.currentMonitorExamId
          })">🔴 ${p.v_count}</span>`;
        }

        // Reset button (only if submitted)
        const resetButton =
          (p.status === "graded" ||
            p.status === "pending" ||
            p.status === "submitted") &&
          p.is_forced != 1
            ? `<button class="action-icon" onclick="window.examManager.resetStudentResult(${
                this.currentMonitorExamId
              }, ${p.student_id}, '${p.full_name.replace(/'/g, "\\'")}', ${
                p.total_score || p.score || 0
              })" title="Reset Hasil">🔄</button>`
            : "";

        return `
        <tr>
          <td><strong>${p.full_name}</strong></td>
          <td style="text-align:center; font-weight:500;">${scoreDisplay}</td>
          <td style="text-align:center;">${
            p.submitted_at
              ? new Date(p.submitted_at).toLocaleTimeString("id-ID")
              : "—"
          }</td>
          <td style="text-align:center;">
            <span title="${statusTooltip}">${statusIcon}</span>
          </td>
          <td style="text-align:center;">${violationBadge}</td>
          <td style="text-align:center;">
            <div class="row-actions">
              ${resetButton}
            </div>
          </td>
        </tr>
      `;
      })
      .join("");
  }

  startMonitorRefresh(examId) {
    if (this.monitorRefreshInterval) clearInterval(this.monitorRefreshInterval);
    this.monitorRefreshInterval = setInterval(() => {
      if (this.currentMonitorExamId === examId) {
        this.loadMonitorData(examId);
      }
    }, 30000);
  }

  stopMonitorRefresh() {
    if (this.monitorRefreshInterval) {
      clearInterval(this.monitorRefreshInterval);
      this.monitorRefreshInterval = null;
    }
  }

  async showViolationDetails(studentId, studentName, examId) {
    const modal = document.getElementById("violation-detail-modal");
    const content = document.getElementById("violation-detail-content");
    const deleteBtn = document.getElementById("delete-violation-btn");

    if (!modal || !content) return;

    content.innerHTML =
      '<div style="text-align: center; color: #64748b">Memuat data pelanggaran...</div>';
    deleteBtn.style.display = "none";
    modal.classList.add("active");

    try {
      const response = await fetch(
        `${this.apiBaseUrl}?action=get_student_violations&student_id=${studentId}&exam_id=${examId}`
      );
      const data = await response.json();

      if (data.success && data.violations) {
        if (data.violations.length === 0) {
          content.innerHTML =
            '<div style="text-align: center; color: #64748b">Tidak ada catatan pelanggaran untuk siswa ini.</div>';
          return;
        }

        let html = `
          <div style="margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0">
            <strong>Siswa:</strong> ${studentName}<br>
            <strong>Total Pelanggaran:</strong> <span class="badge badge-danger">${data.total_count}</span>
          </div>
          <div style="max-height: 300px; overflow-y: auto;">
        `;

        data.violations.forEach((v, idx) => {
          html += `
            <div style="padding: 12px; margin-bottom: 8px; background: #f8fafc; border-radius: 8px; border-left: 3px solid ${
              idx === 0 ? "#ef4444" : "#f59e0b"
            }">
              <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 4px">
                ${new Date(v.created_at).toLocaleString("id-ID")}
              </div>
              <div style="font-size: 0.9rem">${v.reason}</div>
              <div style="margin-top: 8px">
                <button class="btn btn-sm btn-danger" onclick="window.examManager.deleteViolation(${
                  v.id
                }, ${studentId}, ${examId}, '${studentName.replace(
            /'/g,
            "\\'"
          )}')">
                  🗑️ Hapus
                </button>
              </div>
            </div>
          `;
        });

        html += `</div>`;
        content.innerHTML = html;
      } else {
        content.innerHTML =
          '<div style="text-align: center; color: #ef4444">Gagal memuat data pelanggaran.</div>';
      }
    } catch (error) {
      console.error("Error loading violation details:", error);
      content.innerHTML =
        '<div style="text-align: center; color: #ef4444">Terjadi kesalahan saat memuat data.</div>';
    }
  }

  async deleteViolation(violationId, studentId, examId, studentName) {
    if (!confirm(`Hapus catatan pelanggaran untuk ${studentName}?`)) return;

    try {
      const response = await fetch(this.apiBaseUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "delete_violation",
          violation_id: violationId,
        }),
      });
      const data = await response.json();

      if (data.success) {
        alert("✅ Pelanggaran berhasil dihapus");
        this.showViolationDetails(studentId, studentName, examId);
        this.loadMonitorData(examId);
      } else {
        alert("❌ Gagal menghapus: " + (data.message || "Unknown error"));
      }
    } catch (error) {
      console.error("Error deleting violation:", error);
      alert("Terjadi kesalahan saat menghapus pelanggaran");
    }
  }

  async resetStudentResult(examId, studentId, studentName, currentScore) {
    const confirmMsg = `⚠️ RESET HASIL UJIAN\n\nSiswa: ${studentName}\nNilai saat ini: ${currentScore}\n\nTindakan ini akan MENGHAPUS semua jawaban siswa serta seluruh catatan pelanggaran.\n\nApakah Anda yakin?`;

    if (!confirm(confirmMsg)) return;

    try {
      const response = await fetch(this.apiBaseUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "reset_student_result",
          exam_id: examId,
          student_id: studentId,
        }),
      });
      const result = await response.json();

      if (result.success) {
        alert("✅ " + result.message);
        await this.loadMonitorData(examId);
        this.onExamAction();
      } else {
        alert("❌ " + result.message);
      }
    } catch (error) {
      console.error("Error resetting student result:", error);
      alert("Terjadi kesalahan saat mereset hasil.");
    }
  }

  closeMonitor() {
    this.stopMonitorRefresh();
    const modal = document.getElementById("monitor-modal");
    if (modal) {
      modal.classList.remove("active");
    }
    this.currentMonitorExamId = null;
    this.currentParticipants = [];
    this.monitorSearchTerm = "";
  }
}

// Make available globally
window.ExamManager = ExamManager;

// Add CSS for new styles if not present
if (!document.querySelector("#monitor-styles")) {
  const style = document.createElement("style");
  style.id = "monitor-styles";
  style.textContent = `
    .violation-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
      padding: 4px 10px;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      min-width: 40px;
    }
    .violation-badge.low {
      background: #fef3c7;
      color: #92400e;
    }
    .violation-badge.high {
      background: #fee2e2;
      color: #991b1b;
    }
    .violation-badge.zero {
      background: #f1f5f9;
      color: #64748b;
      cursor: default;
    }
    .violation-badge:hover:not(.zero) {
      transform: scale(1.05);
    }
    .row-actions {
      display: flex;
      gap: 6px;
      justify-content: center;
    }
    .action-icon {
      background: none;
      border: none;
      cursor: pointer;
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 1rem;
      transition: all 0.2s;
    }
    .action-icon:hover {
      background: #f1f5f9;
      transform: scale(1.05);
    }
    .monitor-search-row input {
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 8px 12px;
      font-size: 0.85rem;
    }
    .monitor-search-row input:focus {
      outline: none;
      border-color: var(--primary-light);
      box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
    }
    .auto-refresh-footer {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .pulse-dot {
      width: 8px;
      height: 8px;
      background: #10b981;
      border-radius: 50%;
      animation: pulse-green 2s infinite;
    }
    @keyframes pulse-green {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.4; }
    }
    @media (max-width: 768px) {
      .monitor-search-row input {
        width: 100%;
      }
    }
  `;
  document.head.appendChild(style);
}
