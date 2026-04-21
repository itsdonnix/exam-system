/**
 * DraftManager - Orchestrator for exam draft workflow
 * Depends on: DraftAutoSave, TeacherAPI, showToast
 *
 * Usage:
 *   DraftManager.init({
 *     formCollector: () => collectAllFormData(),
 *     formPopulator: (data) => populateFormFromDraft(data),
 *     csrfInput: '#csrf-token',
 *     examIdInput: '#exam-id',
 *     recoveryBanner: '#draft-recovery-banner',
 *     lastSavedIndicator: '#last-saved-indicator',
 *     pageTitle: '#page-title-text',
 *     onCsrfUpdate: (token) => { csrfToken = token; },
 *   });
 */
const DraftManager = {
  _config: null,
  _dirty: false,
  _loading: false,
  _lastServerSave: null,
  _currentExamId: null,

  init(config = {}) {
    this._config = config;
    this._dirty = false;
    this._loading = false;
    this._lastServerSave = null;
    this._currentExamId = null;

    DraftAutoSave.init({
      key: config.autoSaveKey || "exam_draft_autosave",
      interval: config.autoSaveInterval || 30000,
      collectData: () => {
        if (this._config.formCollector) {
          return this._config.formCollector();
        }
        return {};
      },
      onSave: () => {
        this._updateLastSavedIndicator("auto");
        if (this._config.onAutoSave) this._config.onAutoSave();
      },
    });
  },

  destroy() {
    DraftAutoSave.destroy();
  },

  markDirty() {
    this._dirty = true;
    DraftAutoSave.markDirty();
  },

  isDirty() {
    return this._dirty;
  },

  getCurrentExamId() {
    return this._currentExamId;
  },

  /**
   * Save draft to server DB (explicit user action)
   */
  async saveDraftToServer() {
    if (this._loading) return;

    let formData;
    try {
      formData = this._config.formCollector();
    } catch (e) {
      showToast("Gagal mengumpulkan data form", "error");
      return;
    }

    // Set button loading state
    const saveBtn = document.getElementById("saveDraftBtn");
    if (saveBtn) saveBtn.classList.add("btn-loading");

    try {
      const result = await TeacherAPI.saveDraft(formData);

      // Update exam_id hidden input
      const examIdInput = document.querySelector(this._config.examIdInput);
      if (examIdInput && result.exam_id) {
        examIdInput.value = result.exam_id;
        this._currentExamId = result.exam_id;
      }

      // Update CSRF token
      if (result.csrf_token) {
        const csrfInput = document.querySelector(this._config.csrfInput);
        if (csrfInput) csrfInput.value = result.csrf_token;
        if (this._config.onCsrfUpdate) this._config.onCsrfUpdate(result.csrf_token);
      }

      // Update exam code if returned
      if (result.exam_code) {
        const codeInput = document.getElementById("exam-code");
        if (codeInput) codeInput.value = result.exam_code;
      }

      // Update URL to ?edit=ID (no reload)
      if (result.exam_id && !formData.exam_id) {
        const url = new URL(window.location);
        url.searchParams.set("edit", result.exam_id);
        window.history.replaceState({}, "", url);
      }

      // Update save button text
      if (saveBtn) saveBtn.textContent = "💾 Simpan Perubahan";

      this._dirty = false;
      this._lastServerSave = new Date();
      this._updateLastSavedIndicator("server");
      this._updatePageTitle(formData.name);

      showToast("Draft berhasil disimpan ke server");
    } catch (error) {
      showToast(error || "Gagal menyimpan draft", "error");
    } finally {
      if (saveBtn) saveBtn.classList.remove("btn-loading");
    }
  },

  /**
   * Publish exam (save to server as active)
   */
  async publishExam() {
    if (this._loading) return;

    let formData;
    try {
      formData = this._config.formCollector();
    } catch (e) {
      showToast("Gagal mengumpulkan data form", "error");
      return;
    }

    // Client-side validation
    if (!formData.name || !formData.name.trim()) {
      showToast("Nama ujian wajib diisi", "error");
      return;
    }
    if (!formData.questions || formData.questions.length === 0) {
      showToast("Tambahkan minimal 1 soal sebelum mempublikasikan", "error");
      return;
    }

    // Set loading state on publish buttons
    const btn1 = document.getElementById("publishBtn");
    const btn2 = document.getElementById("publishBtn2");
    if (btn1) btn1.classList.add("btn-loading");
    if (btn2) btn2.classList.add("btn-loading");

    try {
      const result = await TeacherAPI.publishDraft(formData);

      // Clear auto-save on successful publish
      DraftAutoSave.clear();

      showToast("Ujian berhasil dipublikasikan!");

      setTimeout(() => {
        window.location.href = "dashboard.php";
      }, 1000);
    } catch (error) {
      showToast(error || "Gagal mempublikasikan ujian", "error");
      if (btn1) btn1.classList.remove("btn-loading");
      if (btn2) btn2.classList.remove("btn-loading");
    }
  },

  /**
   * Load draft from server for ?edit=ID mode
   */
  async loadDraftForEditing(examId) {
    this._loading = true;
    this._currentExamId = examId;

    try {
      const result = await TeacherAPI.getDraft(examId);

      if (this._config.formPopulator) {
        this._config.formPopulator(result);
      }

      this._dirty = false;
      this._lastServerSave = new Date();

      // Now check for auto-save recovery
      this._checkAutoSaveRecovery(examId);
    } catch (error) {
      showToast(error || "Gagal memuat draft", "error");
      // Redirect to clean create-exam on failure
      setTimeout(() => {
        window.location.href = "create-exam.php";
      }, 2000);
    } finally {
      this._loading = false;
    }
  },

  /**
   * Check for auto-save data and show recovery banner
   */
  checkAutoSaveRecovery(currentExamId) {
    this._checkAutoSaveRecovery(currentExamId);
  },

  _checkAutoSaveRecovery(currentExamId) {
    const autoSave = DraftAutoSave.load();
    if (!autoSave || !autoSave.data) return;

    // Check if auto-save is for the same context
    const autoSaveExamId = autoSave.examId;
    if (currentExamId && autoSaveExamId && String(currentExamId) !== String(autoSaveExamId)) {
      DraftAutoSave.clear();
      return;
    }
    if (!currentExamId && autoSaveExamId) {
      DraftAutoSave.clear();
      return;
    }

    // Show recovery banner
    const banner = document.querySelector(this._config.recoveryBanner);
    if (banner) {
      const timeLabel = document.getElementById("recovery-time-label");
      if (timeLabel && autoSave.savedAt) {
        const savedTime = new Date(autoSave.savedAt);
        const now = new Date();
        const diffMs = now - savedTime;
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 1) {
          timeLabel.textContent = "(barusan)";
        } else if (diffMins < 60) {
          timeLabel.textContent = "(" + diffMins + " menit lalu)";
        } else {
          const diffHours = Math.floor(diffMins / 60);
          timeLabel.textContent = "(" + diffHours + " jam lalu)";
        }
      }
      banner.style.display = "flex";
    }
  },

  /**
   * Recover form data from localStorage auto-save
   */
  recoverAutoSave() {
    const autoSave = DraftAutoSave.load();
    if (!autoSave || !autoSave.data) {
      showToast("Tidak ada data auto-save ditemukan", "error");
      return;
    }

    // Convert auto-save format to normalized format for formPopulator
    const formData = autoSave.data;
    const normalizedData = {
      exam: {
        id: formData.exam_id || null,
        name: formData.name || "",
        subject: formData.subject || "",
        class: formData.class || "",
        exam_code: formData.exam_code || "",
        start_time: formData.start_time || "",
        end_time: formData.end_time || "",
        duration_minutes: formData.duration || 90,
        question_count: formData.question_count || 0,
        description: formData.description || "",
        shuffle_questions: formData.shuffle_questions ? 1 : 0,
        shuffle_options: formData.shuffle_options ? 1 : 0,
        passing_score: formData.passing_score || 75,
        max_violations: formData.max_violations || 3,
        show_results_setting: formData.show_results_setting || "direct_submit",
        security_settings: formData.security_settings || {},
      },
      questions: formData.questions || [],
    };

    if (this._config.formPopulator) {
      this._config.formPopulator(normalizedData);
    }

    // Hide banner
    const banner = document.querySelector(this._config.recoveryBanner);
    if (banner) banner.style.display = "none";

    this._dirty = true;
    DraftAutoSave.markDirty();
    showToast("Data auto-save berhasil dipulihkan");
  },

  /**
   * Dismiss auto-save recovery and clear localStorage
   */
  dismissAutoSave() {
    DraftAutoSave.clear();

    const banner = document.querySelector(this._config.recoveryBanner);
    if (banner) banner.style.display = "none";
  },

  _updateLastSavedIndicator(source) {
    const indicator = document.querySelector(this._config.lastSavedIndicator);
    if (!indicator) return;

    const icon = document.getElementById("last-saved-icon");
    const text = document.getElementById("last-saved-text");

    const now = new Date();
    const timeStr = now.toLocaleTimeString("id-ID", {
      hour: "2-digit",
      minute: "2-digit",
    });

    if (icon) icon.style.display = "inline";
    if (text) {
      text.textContent =
        source === "auto"
          ? "Auto-save: " + timeStr
          : "Tersimpan: " + timeStr;
    }

    indicator.style.display = "flex";
  },

  _updatePageTitle(name) {
    const titleEl = document.querySelector(this._config.pageTitle);
    if (!titleEl) return;

    if (name && name.trim()) {
      titleEl.textContent = "✏️ Edit Draft: " + name;
    } else {
      titleEl.textContent = "➕ Buat Ujian Baru";
    }
  },
};

window.DraftManager = DraftManager;