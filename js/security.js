/**
 * ExamSafe Security Module
 * Anti-cheat system for online exams
 *
 * NEW: Per-exam security_settings support
 * NEW: Violation counter + auto-stop + Vue callback bridge
 * NEW: Toast.warning() integration (falls back to own toast)
 */

const ExamSecurity = {
  debugMode: false,
  isActive: false,
  examId: null,
  listenersAttached: false,

  // NEW: Per-exam security settings (defaults = all enabled for backward compat)
  settings: {
    fullscreen: true,
    block_shortcuts: true,
    block_copy: true,
    tab_detection: true,
    notify_proctor: true,
    auto_stop: true,
  },

  // NEW: Violation tracking
  maxViolations: 3,
  violationCount: 0,
  onViolationCallback: null,

  // ===== INITIALIZATION =====

  // Basic initialization - does NOT attach any security listeners
  init(debugFlag = false) {
    this.debugMode =
      debugFlag === true || window.DEBUG_DISABLE_VIOLATIONS === true;

    if (this.debugMode) {
      console.log(
        "%c🔧 ExamSecurity: DEBUG MODE - No security active",
        "background: #f59e0b; color: #000; font-size: 14px; padding: 4px 8px; border-radius: 4px;"
      );
      return;
    }

    console.log(
      "[ExamSafe] Security module initialized (inactive - no listeners attached)"
    );
  },

  /**
   * NEW: Configure per-exam security settings.
   * Call this BEFORE start() with data from exam.security_settings.
   * If never called, defaults match original behavior (all enabled).
   *
   * @param {string|object} securitySettingsJson - JSON string or parsed object from exams.security_settings
   * @param {number} maxViolations - From exams.max_violations column
   */
  configure(securitySettingsJson, maxViolations) {
    // Reset to defaults first (in case of re-configuration)
    this.settings = {
      fullscreen: true,
      block_shortcuts: true,
      block_copy: true,
      tab_detection: true,
      notify_proctor: true,
      auto_stop: true,
    };

    // Parse security_settings
    if (securitySettingsJson) {
      try {
        const parsed =
          typeof securitySettingsJson === "string"
            ? JSON.parse(securitySettingsJson)
            : securitySettingsJson;
        // Merge — only known keys, ignore garbage
        Object.keys(this.settings).forEach((key) => {
          if (key in parsed) {
            this.settings[key] = Boolean(parsed[key]);
          }
        });
      } catch (e) {
        console.warn(
          "[ExamSafe] Failed to parse security_settings, using defaults"
        );
      }
    }

    // Set max violations
    if (typeof maxViolations === "number" && maxViolations > 0) {
      this.maxViolations = maxViolations;
    }

    console.log(
      "[ExamSafe] Configured — settings:",
      this.settings,
      "maxViolations:",
      this.maxViolations
    );
  },

  // Start security - attaches listeners based on configured settings
  start() {
    if (this.debugMode) {
      console.log("[ExamSafe] Security would start here (debug mode)");
      return;
    }

    if (this.isActive) {
      console.log("[ExamSafe] Security already active");
      return;
    }

    console.log(
      "[ExamSafe] 🟢 Security monitoring STARTED - Attaching listeners"
    );

    // Get examId
    if (window.ExamEngine && window.ExamEngine.examId) {
      this.examId = window.ExamEngine.examId;
    } else {
      this.tryGetExamIdFromUrl();
    }

    // NEW: Reset violation count for this session
    this.violationCount = 0;

    this.isActive = true;
    this.attachAllListeners();

    // MODIFIED: Only request fullscreen if setting is enabled
    if (this.settings.fullscreen) {
      this.requestFullscreen();
    }
  },

  // MODIFIED: Attach listeners based on per-exam settings
  attachAllListeners() {
    if (this.listenersAttached) return;

    // Always protect against accidental page close/navigation
    this.attachNavigation();

    if (this.settings.tab_detection) {
      this.attachTabSwitch();
      this.attachDevTools();
    }

    if (this.settings.block_shortcuts) {
      this.attachKeyboardShortcuts();
    }

    if (this.settings.block_copy) {
      this.attachCopyPaste();
      this.attachContextMenu();
    }

    if (this.settings.fullscreen) {
      this.attachFullscreen();
    }

    this.listenersAttached = true;
    console.log(
      "[ExamSafe] Security listeners attached (settings-aware)",
      this.settings
    );
  },

  tryGetExamIdFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const examId = urlParams.get("exam_id");
    if (examId) {
      this.examId = parseInt(examId);
      console.log("[ExamSafe] Found examId in URL:", this.examId);
    }
  },

  setExamId(examId) {
    if (!this.examId && examId) {
      this.examId = examId;
      console.log("[ExamSafe] ExamId set to:", this.examId);
    }
  },

  // ===== LISTENER ATTACHMENT METHODS =====

  attachKeyboardShortcuts() {
    const blockedKeys = [
      { ctrl: true, key: "t" },
      { ctrl: true, key: "n" },
      { ctrl: true, key: "w" },
      { ctrl: true, key: "r" },
      { ctrl: true, key: "l" },
      { ctrl: true, key: "u" },
      { ctrl: true, key: "a" },
      { ctrl: true, key: "c" },
      { ctrl: true, key: "v" },
      { ctrl: true, key: "x" },
      { ctrl: true, key: "p" },
      { ctrl: true, key: "s" },
      { ctrl: true, key: "f" },
      { ctrl: true, key: "h" },
      { ctrl: true, key: "j" },
      { ctrl: true, shift: true, key: "i" },
      { ctrl: true, shift: true, key: "j" },
      { ctrl: true, shift: true, key: "c" },
      { alt: true, key: "F4" },
      { alt: true, key: "Tab" },
      { key: "F12" },
      { key: "F5" },
      { key: "F11" },
      { key: "Escape" },
      { meta: true, key: "Tab" },
    ];

    document.addEventListener(
      "keydown",
      (e) => {
        if (!this.isActive) return;

        for (const blocked of blockedKeys) {
          if (
            blocked.ctrl &&
            e.ctrlKey &&
            blocked.key &&
            e.key.toLowerCase() === blocked.key.toLowerCase()
          ) {
            if (blocked.shift && !e.shiftKey) continue;
            e.preventDefault();
            e.stopPropagation();
            this.showBlockedToast(
              `Shortcut Ctrl+${e.key.toUpperCase()} diblokir`
            );
            this.logViolation(
              `Mencoba menggunakan shortcut Ctrl+${e.key.toUpperCase()}`
            );
            return;
          }
          if (blocked.alt && e.altKey && blocked.key && e.key === blocked.key) {
            e.preventDefault();
            e.stopPropagation();
            this.showBlockedToast(`Shortcut Alt+${e.key} diblokir`);
            this.logViolation(`Mencoba menggunakan shortcut Alt+${e.key}`);
            return;
          }
          if (
            !blocked.ctrl &&
            !blocked.alt &&
            blocked.key &&
            e.key === blocked.key
          ) {
            e.preventDefault();
            e.stopPropagation();
            this.showBlockedToast(`Tombol ${e.key} diblokir selama ujian`);
            this.logViolation(`Mencoba menggunakan tombol ${e.key}`);
            return;
          }
        }
      },
      true
    );
  },

  attachCopyPaste() {
    ["copy", "cut", "paste", "selectstart"].forEach((evt) => {
      document.addEventListener(evt, (e) => {
        if (!this.isActive) return;
        e.preventDefault();
        this.showBlockedToast("Copy/Paste diblokir selama ujian!");
        this.logViolation(`Mencoba melakukan ${evt}`);
      });
    });
  },

  attachContextMenu() {
    document.addEventListener("contextmenu", (e) => {
      if (!this.isActive) return;
      e.preventDefault();
      this.showBlockedToast("Klik kanan diblokir selama ujian!");
      this.logViolation("Mencoba menggunakan klik kanan");
    });
  },

  attachTabSwitch() {
    let lastVisibilityWarning = 0;
    const VISIBILITY_WARNING_COOLDOWN = 10000;

    document.addEventListener("visibilitychange", () => {
      if (!this.isActive) return;
      if (document.hidden) {
        const now = Date.now();
        if (now - lastVisibilityWarning > VISIBILITY_WARNING_COOLDOWN) {
          lastVisibilityWarning = now;
          this.logViolation("Perpindahan tab/jendela terdeteksi!");
          this.showBlockedToast("⚠️ Jangan pindah tab! Pelanggaran dicatat.");
        }
      }
    });

    let lastBlurWarning = 0;
    window.addEventListener("blur", () => {
      if (!this.isActive) return;
      const now = Date.now();
      if (now - lastBlurWarning > VISIBILITY_WARNING_COOLDOWN) {
        lastBlurWarning = now;
        this.logViolation("Jendela browser kehilangan fokus!");
        this.showBlockedToast(
          "⚠️ Jangan keluar dari jendela ujian! Pelanggaran dicatat."
        );
      }
    });
  },

  attachFullscreen() {
    let fullscreenExitWarningShown = false;

    const checkFullscreen = () => {
      if (!this.isActive) return;

      setTimeout(() => {
        const isFS =
          document.fullscreenElement ||
          document.webkitFullscreenElement ||
          document.mozFullScreenElement;
        if (!isFS && !fullscreenExitWarningShown) {
          fullscreenExitWarningShown = true;
          this.logViolation("Keluar dari mode fullscreen terdeteksi!");
          this.showBlockedToast(
            "⚠️ Jangan keluar dari mode layar penuh! Pelanggaran dicatat."
          );
          setTimeout(() => {
            fullscreenExitWarningShown = false;
          }, 5000);
        }
      }, 100);
    };

    document.addEventListener("fullscreenchange", checkFullscreen);
    document.addEventListener("webkitfullscreenchange", checkFullscreen);
    document.addEventListener("mozfullscreenchange", checkFullscreen);
  },

  attachDevTools() {
    const threshold = 160;
    let devToolsWarningShown = false;

    const checkDevTools = setInterval(() => {
      if (!this.isActive) return;
      if (
        window.outerWidth - window.innerWidth > threshold ||
        window.outerHeight - window.innerHeight > threshold
      ) {
        if (!devToolsWarningShown) {
          devToolsWarningShown = true;
          this.logViolation("Developer Tools terdeteksi dibuka!");
          this.showBlockedToast(
            "⚠️ Developer Tools terdeteksi! Pelanggaran dicatat."
          );
          setTimeout(() => {
            devToolsWarningShown = false;
          }, 10000);
        }
      }
    }, 1000);

    // Store interval ID for cleanup if needed
    this.devToolsInterval = checkDevTools;

    document.addEventListener("keydown", (e) => {
      if (!this.isActive) return;
      if (
        e.key === "F12" ||
        (e.ctrlKey &&
          e.shiftKey &&
          ["I", "J", "C"].includes(e.key.toUpperCase()))
      ) {
        e.preventDefault();
        this.logViolation("Mencoba membuka Developer Tools");
        this.showBlockedToast("Developer Tools diblokir!");
      }
    });
  },

  attachNavigation() {
    window.addEventListener("beforeunload", (e) => {
      if (!this.isActive) return;
      e.preventDefault();
      e.returnValue =
        "Ujian sedang berlangsung! Apakah Anda yakin ingin keluar?";
      this.logViolation("Mencoba menutup/merefresh halaman ujian");
      return "Ujian sedang berlangsung! Apakah Anda yakin ingin keluar?";
    });

    history.pushState(null, null, location.href);
    window.addEventListener("popstate", () => {
      if (!this.isActive) return;
      history.pushState(null, null, location.href);
      this.showBlockedToast("Tombol back diblokir selama ujian!");
      this.logViolation("Mencoba menggunakan tombol back");
    });
  },

  requestFullscreen() {
    if (!this.isActive) return;
    const el = document.documentElement;
    if (el.requestFullscreen) {
      el.requestFullscreen().catch((err) =>
        console.warn("[ExamSafe] Fullscreen request failed:", err)
      );
    } else if (el.webkitRequestFullscreen) {
      el.webkitRequestFullscreen();
    } else if (el.mozRequestFullScreen) {
      el.mozRequestFullScreen();
    }
  },

  // ===== VIOLATION LOGGING =====

  // MODIFIED: Increment counter, call Vue callback, respect notify_proctor
  async logViolation(reason) {
    if (!this.isActive) {
      console.log(`[ExamSafe] Violation ignored (exam not started): ${reason}`);
      return;
    }

    if (!this.examId) {
      this.tryGetExamIdFromUrl();
      if (window.ExamEngine && window.ExamEngine.examId) {
        this.examId = window.ExamEngine.examId;
      }
    }

    // NEW: Increment client-side counter
    this.violationCount++;
    console.log(
      `[ExamSafe] Violation #${this.violationCount}/${this.maxViolations}: ${reason}`
    );

    // NEW: Notify Vue app via callback (for overlay + auto-stop)
    if (typeof this.onViolationCallback === "function") {
      this.onViolationCallback({
        reason,
        count: this.violationCount,
        maxViolations: this.maxViolations,
        autoStop: this.settings.auto_stop,
      });
    }

    // MODIFIED: Only send to server if notify_proctor is enabled
    if (this.settings.notify_proctor) {
      await this.sendViolationToServer(reason);
    } else {
      console.log(
        "[ExamSafe] Violation not reported to server (notify_proctor disabled)"
      );
    }
  },

  async sendViolationToServer(reason) {
    try {
      const response = await fetch("../php/exam_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "report_violation",
          exam_id: this.examId,
          reason: reason,
          violation_count: 1,
        }),
        credentials: "include",
      });
      const data = await response.json();
      if (data.success) {
        console.log("[ExamSafe] Violation logged successfully:", reason);
      } else {
        console.error("[ExamSafe] Failed to log violation:", data.message);
      }
    } catch (error) {
      console.error("[ExamSafe] Network error logging violation:", error);
    }
  },

  // MODIFIED: Use Toast.warning() if available, fall back to own implementation
  showBlockedToast(msg) {
    if (!this.isActive) return;

    // NEW: Use shared Toast system when available
    if (typeof window.Toast !== "undefined" && typeof window.Toast.warning === "function") {
      window.Toast.warning("🚫 " + msg, 3000);
      return;
    }

    // Legacy fallback (for pages without toast.js loaded)
    let toast = document.getElementById("blocked-toast");
    if (!toast) {
      toast = document.createElement("div");
      toast.id = "blocked-toast";
      toast.style.cssText = `
        position: fixed;
        bottom: 80px;
        left: 50%;
        transform: translateX(-50%) translateY(20px);
        background: #334155;
        color: #fff;
        padding: 10px 20px;
        border-radius: 50px;
        font-size: 0.85rem;
        z-index: 10001;
        opacity: 0;
        transition: all 0.2s;
        pointer-events: none;
        white-space: nowrap;
        max-width: 90%;
        white-space: normal;
        text-align: center;
        font-family: 'Poppins', sans-serif;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      `;
      document.body.appendChild(toast);
    }

    toast.textContent = "🚫 " + msg;
    toast.style.opacity = "1";
    toast.style.transform = "translateX(-50%) translateY(0)";

    setTimeout(() => {
      toast.style.opacity = "0";
      toast.style.transform = "translateX(-50%) translateY(20px)";
    }, 3000);
  },

  stop() {
    console.log("[ExamSafe] 🔴 Security monitoring STOPPED");
    this.isActive = false;
    // Listeners remain attached but are inactive (checked via isActive flag)
  },
};

// Initialize (only sets up debug mode, attaches NO listeners)
setTimeout(() => {
  ExamSecurity.init();
}, 100);
