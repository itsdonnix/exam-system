/**
 * ExamManager - Shared module for exam management
 * Handles exam CRUD, monitoring, and filtering for both teacher and admin dashboards
 */

class ExamManager {
  constructor(options) {
    this.containerId = options.containerId || "exam-list";
    this.searchInputId = options.searchInputId || "examSearch";
    this.role = options.role || "teacher"; // 'teacher' or 'admin'
    this.onExamAction = options.onExamAction || (() => {});
    this.allExams = [];
    this.currentMonitorExamId = null;
    this.apiBaseUrl = "../php/exam_api.php";

    this.init();
  }

  init() {
    this.renderSkeleton();
    this.attachSearchListener();
    this.attachModalCloseListener();
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
        exam.subject.toLowerCase().includes(query)
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

    // Conditional buttons based on exam status - SAME for both admin and teacher
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

    // Add Hasil button ONLY for teachers
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
    const modal = document.getElementById("monitor-modal");
    if (!modal) {
      console.error("Monitor modal not found");
      return;
    }

    modal.classList.add("active");

    const titleElement = modal.querySelector(".modal-title");
    if (titleElement) {
      titleElement.textContent = `👁️ Monitor Ujian — ${examName}`;
    }

    const tbody = document.getElementById("monitor-tbody");
    if (tbody) {
      tbody.innerHTML =
        '<tr><td colspan="4" style="text-align:center">Memuat...</td></tr>';
    }

    try {
      const response = await fetch(
        `${this.apiBaseUrl}?action=get_exam_monitor&exam_id=${examId}`
      );
      const data = await response.json();

      if (data.success) {
        // Update stats
        const totalEl = document.getElementById("monitor-total");
        const activeEl = document.getElementById("monitor-active");
        const finishedEl = document.getElementById("monitor-finished");
        const violationsEl = document.getElementById("monitor-violations");

        if (totalEl) totalEl.textContent = data.stats.total || 0;
        if (activeEl) activeEl.textContent = data.participants?.length || 0;
        if (finishedEl) finishedEl.textContent = data.stats.finished || 0;
        if (violationsEl) violationsEl.textContent = data.stats.violation || 0;

        if (!data.participants || data.participants.length === 0) {
          if (tbody)
            tbody.innerHTML =
              '<tr><td colspan="4" style="text-align:center;padding:20px">Belum ada siswa yang bergabung.</td></tr>';
          return;
        }

        if (tbody) {
          tbody.innerHTML = data.participants
            .map(
              (p) => `
            <tr>
              <td>${p.full_name}</td>
              <td>${p.score || "—"} (Skor)</td>
              <td>${
                p.submitted_at
                  ? new Date(p.submitted_at).toLocaleTimeString("id-ID")
                  : "—"
              }</td>
              <td style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span class="badge badge-${
                  p.is_forced == 1 ? "danger" : "success"
                }">
                  ${p.is_forced == 1 ? "Diputus" : "Selesai"}
                </span>
                ${
                  p.v_count > 0
                    ? `<span style="color:red; font-size:0.7rem">⚠️ ${p.v_count}x</span>`
                    : ""
                }
                ${
                  p.is_forced == 1
                    ? `<button class="btn btn-sm btn-success" style="padding:2px 8px; font-size:0.7rem" onclick="window.examManager.grantTolerance(${examId}, ${
                        p.student_id
                      }, '${p.full_name.replace(
                        /'/g,
                        "\\'"
                      )}')">🔓 Toleransi</button>`
                    : ""
                }
              </td>
            </tr>
          `
            )
            .join("");
        }
      } else {
        if (tbody)
          tbody.innerHTML =
            '<tr><td colspan="4" style="text-align:center;color:#64748b">Gagal memuat data monitor.</td></tr>';
      }
    } catch (error) {
      console.error("Error loading monitor:", error);
      const tbody = document.getElementById("monitor-tbody");
      if (tbody)
        tbody.innerHTML =
          '<tr><td colspan="4" style="text-align:center;color:#64748b">Terjadi kesalahan.</td></tr>';
    }
  }

  async grantTolerance(examId, studentId, name) {
    if (
      !confirm(
        `Berikan toleransi kepada ${name}?\nSiswa akan dapat masuk kembali ke ujian ini.`
      )
    )
      return;

    try {
      const response = await fetch(this.apiBaseUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "unlock_student",
          exam_id: examId,
          student_id: studentId,
        }),
      });
      const result = await response.json();

      if (result.success) {
        alert("✅ " + result.message);
        this.showMonitor(
          examId,
          this.allExams.find((e) => e.id === examId)?.name || "Ujian"
        );
      } else {
        alert("❌ " + result.message);
      }
    } catch (error) {
      console.error("Error granting tolerance:", error);
      alert("Terjadi kesalahan saat memberikan toleransi.");
    }
  }

  closeMonitor() {
    const modal = document.getElementById("monitor-modal");
    if (modal) {
      modal.classList.remove("active");
    }
    this.currentMonitorExamId = null;
  }
}

// Make available globally
window.ExamManager = ExamManager;
