/**
 * ExamSafe Security Module
 * Anti-cheat system untuk ujian online SMA
 */

const ExamSecurity = {
  violationCount: 0,
  maxViolations: 3,
  isExamActive: false,
  monitorInterval: null,
  violations: [],

  init() {
    this.isExamActive = true;
    this.blockKeyboardShortcuts();
    this.blockContextMenu();
    this.blockCopyPaste();
    this.monitorTabSwitch();
    this.monitorFullscreen();
    this.blockDevTools();
    // Fullscreen must be initiated by a direct user gesture — callers should
    // call `requestFullscreen()` from the user-initiated handler (e.g. Start button).
    this.preventNavigation();
    console.log('[ExamSafe] Security module initialized');
  },

  // ===== FULLSCREEN =====
  requestFullscreen() {
    const el = document.documentElement;
    if (el.requestFullscreen) el.requestFullscreen();
    else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
    else if (el.mozRequestFullScreen) el.mozRequestFullScreen();
  },

  monitorFullscreen() {
    const checkFullscreen = () => {
      if (!this.isExamActive) return;
      const isFS = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement;
      if (!isFS) {
        this.triggerViolation('Keluar dari mode fullscreen terdeteksi!');
        setTimeout(() => this.requestFullscreen(), 500);
      }
    };
    document.addEventListener('fullscreenchange', checkFullscreen);
    document.addEventListener('webkitfullscreenchange', checkFullscreen);
    document.addEventListener('mozfullscreenchange', checkFullscreen);
  },

  // ===== KEYBOARD BLOCKING =====
  blockKeyboardShortcuts() {
    const blockedKeys = [
      { ctrl: true, key: 't' },   // New tab
      { ctrl: true, key: 'n' },   // New window
      { ctrl: true, key: 'w' },   // Close tab
      { ctrl: true, key: 'r' },   // Refresh
      { ctrl: true, key: 'l' },   // Address bar
      { ctrl: true, key: 'u' },   // View source
      { ctrl: true, key: 'a' },   // Select all
      { ctrl: true, key: 'c' },   // Copy
      { ctrl: true, key: 'v' },   // Paste
      { ctrl: true, key: 'x' },   // Cut
      { ctrl: true, key: 'p' },   // Print
      { ctrl: true, key: 's' },   // Save
      { ctrl: true, key: 'f' },   // Find
      { ctrl: true, key: 'h' },   // History
      { ctrl: true, key: 'j' },   // Downloads
      { ctrl: true, shift: true, key: 'i' }, // DevTools
      { ctrl: true, shift: true, key: 'j' }, // Console
      { ctrl: true, shift: true, key: 'c' }, // Inspector
      { alt: true, key: 'F4' },   // Close window
      { alt: true, key: 'Tab' },  // Switch window
      { key: 'F12' },             // DevTools
      { key: 'F5' },              // Refresh
      { key: 'F11' },             // Fullscreen toggle
      { key: 'Escape' },          // Escape
      { meta: true, key: 'Tab' }, // Mac switch
    ];

    document.addEventListener('keydown', (e) => {
      if (!this.isExamActive) return;

      for (const blocked of blockedKeys) {
        const ctrlMatch = blocked.ctrl ? (e.ctrlKey || e.metaKey) : true;
        const altMatch = blocked.alt ? e.altKey : true;
        const shiftMatch = blocked.shift ? e.shiftKey : true;
        const keyMatch = blocked.key ? e.key.toLowerCase() === blocked.key.toLowerCase() : true;
        const noExtra = !blocked.ctrl && !blocked.alt && !blocked.shift;

        if (blocked.ctrl && e.ctrlKey && blocked.key && e.key.toLowerCase() === blocked.key.toLowerCase()) {
          if (blocked.shift && !e.shiftKey) continue;
          e.preventDefault(); e.stopPropagation();
          this.showBlockedAction(`Shortcut Ctrl+${e.key.toUpperCase()} diblokir`);
          return;
        }
        if (blocked.alt && e.altKey && blocked.key && e.key === blocked.key) {
          e.preventDefault(); e.stopPropagation();
          this.showBlockedAction(`Shortcut Alt+${e.key} diblokir`);
          return;
        }
        if (!blocked.ctrl && !blocked.alt && blocked.key && e.key === blocked.key) {
          e.preventDefault(); e.stopPropagation();
          this.showBlockedAction(`Tombol ${e.key} diblokir selama ujian`);
          return;
        }
      }
    }, true);
  },

  // ===== COPY PASTE BLOCKING =====
  blockCopyPaste() {
    ['copy', 'cut', 'paste', 'selectstart'].forEach(evt => {
      document.addEventListener(evt, (e) => {
        if (!this.isExamActive) return;
        e.preventDefault();
        this.showBlockedAction('Copy/Paste diblokir selama ujian!');
      });
    });
  },

  // ===== RIGHT CLICK BLOCKING =====
  blockContextMenu() {
    document.addEventListener('contextmenu', (e) => {
      if (!this.isExamActive) return;
      e.preventDefault();
      this.showBlockedAction('Klik kanan diblokir selama ujian!');
    });
  },

  // ===== TAB/WINDOW SWITCH MONITORING =====
  monitorTabSwitch() {
    document.addEventListener('visibilitychange', () => {
      if (!this.isExamActive) return;
      if (document.hidden) {
        this.triggerViolation('Perpindahan tab/jendela terdeteksi!');
      }
    });

    window.addEventListener('blur', () => {
      if (!this.isExamActive) return;
      // ignore blur events that happen immediately after a user gesture
      if (this._suppressBlurUntil && Date.now() < this._suppressBlurUntil) return;
      this.triggerViolation('Jendela browser kehilangan fokus!');
    });

    window.addEventListener('focus', () => {
      if (this.isExamActive) {
        // do not automatically request fullscreen here; only re-request when
        // explicitly allowed by user gesture. Keep focus handling light.
      }
    });
  },

  // ===== DEVTOOLS BLOCKING =====
  blockDevTools() {
    // Detect DevTools via size change
    const threshold = 160;
    setInterval(() => {
      if (!this.isExamActive) return;
      if (window.outerWidth - window.innerWidth > threshold ||
          window.outerHeight - window.innerHeight > threshold) {
        this.triggerViolation('Developer Tools terdeteksi dibuka!');
      }
    }, 1000);

    // Disable right-click inspect
    document.addEventListener('keydown', (e) => {
      if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key.toUpperCase()))) {
        e.preventDefault();
      }
    });
  },

  // ===== PREVENT NAVIGATION =====
  preventNavigation() {
    window.addEventListener('beforeunload', (e) => {
      if (!this.isExamActive) return;
      e.preventDefault();
      e.returnValue = 'Ujian sedang berlangsung! Apakah Anda yakin ingin keluar?';
      return e.returnValue;
    });

    // Block back button
    history.pushState(null, null, location.href);
    window.addEventListener('popstate', () => {
      if (this.isExamActive) {
        history.pushState(null, null, location.href);
        this.showBlockedAction('Tombol back diblokir selama ujian!');
      }
    });
  },

  // ===== VIOLATION HANDLER =====
  triggerViolation(reason) {
    this.violationCount++;
    const timestamp = new Date().toLocaleTimeString('id-ID');
    this.violations.push({ reason, timestamp, count: this.violationCount });

    console.warn(`[ExamSafe] VIOLATION #${this.violationCount}: ${reason}`);

    // Show warning overlay
    this.showViolationWarning(reason);

    // Notify supervisor (in real app: send to server)
    this.notifySupervisor(reason);

    // Auto-submit if max violations reached
    if (this.violationCount >= this.maxViolations) {
      this.forceSubmit('Batas pelanggaran tercapai');
    }
  },

  showViolationWarning(reason) {
    const overlay = document.getElementById('violation-overlay');
    const msg = document.getElementById('violation-msg');
    const count = document.getElementById('violation-count');
    if (overlay && msg) {
      msg.textContent = reason;
      count.textContent = `Pelanggaran ${this.violationCount}/${this.maxViolations}`;
      overlay.classList.add('show');
      setTimeout(() => overlay.classList.remove('show'), 4000);
    }
  },

  showBlockedAction(msg) {
    const toast = document.getElementById('blocked-toast');
    if (toast) {
      toast.textContent = '🚫 ' + msg;
      toast.style.opacity = '1';
      toast.style.transform = 'translateY(0)';
      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(20px)';
      }, 2000);
    }
  },

  notifySupervisor(reason) {
    // In production: send AJAX to server
    const data = {
      student: sessionStorage.getItem('user'),
      reason,
      timestamp: new Date().toISOString(),
      violationCount: this.violationCount
    };
    console.log('[ExamSafe] Notifying supervisor:', data);
    // fetch('/php/notify_supervisor.php', { method: 'POST', body: JSON.stringify(data) });
  },

  forceSubmit(reason) {
    this.isExamActive = false;
    alert(`⚠️ Ujian dihentikan paksa!\nAlasan: ${reason}\nJawaban Anda telah disimpan otomatis.`);
    if (typeof ExamEngine !== 'undefined') {
      ExamEngine.submitExam(true);
    }
  },

  stop() {
    this.isExamActive = false;
    if (this.monitorInterval) clearInterval(this.monitorInterval);
    if (document.exitFullscreen) document.exitFullscreen();
  }
};
