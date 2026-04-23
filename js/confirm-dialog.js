/**
 * ExamSafe Confirm Dialog
 * Shared composable + Vue 3 component for confirm dialogs.
 *
 * Usage in any Vue 3 app:
 *   const { confirm, ConfirmDialog } = useConfirm();
 *   app.component('ConfirmDialog', ConfirmDialog);
 *
 *   // In template: <confirm-dialog />
 *   // In logic:
 *   if (await confirm('Submit exam?')) { ... }
 */
function useConfirm() {
  // ── Reactive State ────────────────────────────────────
  const state = Vue.reactive({
    visible: false,
    message: "",
    resolve: null,
  });

  // ── Public API ────────────────────────────────────────

  /**
   * Show confirm dialog. Returns Promise<boolean>.
   * true = "Ya" clicked, false = "Batal" clicked or dismissed.
   */
  function confirm(message) {
    return new Promise((resolve) => {
      state.visible = true;
      state.message = message;
      state.resolve = resolve;
    });
  }

  // ── Internal Handlers ─────────────────────────────────

  function handleYes() {
    state.visible = false;
    if (state.resolve) state.resolve(true);
    state.resolve = null;
  }

  function handleNo() {
    state.visible = false;
    if (state.resolve) state.resolve(false);
    state.resolve = null;
  }

  // ── CSS Injection ─────────────────────────────────────

  function injectStyles() {
    if (document.getElementById("confirm-dialog-styles")) return;
    const style = document.createElement("style");
    style.id = "confirm-dialog-styles";
    style.textContent = `
      .confirm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 9000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        backdrop-filter: blur(2px);
        animation: confirmFadeIn 0.2s ease;
      }
      @keyframes confirmFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      .confirm-dialog {
        background: #fff;
        border-radius: 20px;
        max-width: 26rem;
        width: 100%;
        padding: 2rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        animation: confirmSlideUp 0.2s ease;
      }
      @keyframes confirmSlideUp {
        from { opacity: 0; transform: translateY(16px); }
        to { opacity: 1; transform: translateY(0); }
      }
      .confirm-message {
        font-size: 1.05rem;
        font-weight: 500;
        color: #1e293b;
        line-height: 1.6;
        margin: 0 0 1.75rem 0;
        white-space: pre-line;
        word-break: break-word;
      }
      .confirm-actions {
        display: flex;
        gap: 0.75rem;
        justify-content: flex-end;
      }
      .confirm-btn {
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
      }
      .confirm-btn-cancel {
        background: #f1f5f9;
        color: #475569;
      }
      .confirm-btn-cancel:hover {
        background: #e2e8f0;
      }
      .confirm-btn-yes {
        background: #2563eb;
        color: #fff;
      }
      .confirm-btn-yes:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
      }
      .confirm-btn-danger {
        background: #dc2626;
        color: #fff;
      }
      .confirm-btn-danger:hover {
        background: #b91c1c;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
      }
      @media (max-width: 480px) {
        .confirm-dialog {
          padding: 1.5rem;
        }
        .confirm-actions {
          flex-direction: column-reverse;
        }
        .confirm-btn {
          width: 100%;
          justify-content: center;
        }
      }
    `;
    document.head.appendChild(style);
  }

  // ── Component Definition ──────────────────────────────

  const ConfirmDialog = {
    name: "ConfirmDialog",
    template: `
      <div v-if="state.visible" class="confirm-overlay">
        <div class="confirm-dialog">
          <p class="confirm-message">{{ state.message }}</p>
          <div class="confirm-actions">
            <button class="confirm-btn confirm-btn-cancel" @click="handleNo">Batal</button>
            <button class="confirm-btn confirm-btn-yes" @click="handleYes">Ya</button>
          </div>
        </div>
      </div>
    `,
    setup() {
      // Inject CSS on first mount
      injectStyles();

      return { state, handleYes, handleNo };
    },
  };

  return { confirm, ConfirmDialog };
}

window.useConfirm = useConfirm
