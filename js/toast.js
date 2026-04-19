/**
 * Shared Toast Notification System
 * Usage:
 *   Legacy: showToast('Message', 'success')
 *   Enhanced: Toast.success('Message')
 *            Toast.error('Message', 5000) // custom duration
 *            Toast.info('Message')
 *            Toast.warning('Message')
 */

(function() {
    // CSS injected lazily
    const toastStyles = `
        .toast-container {
            position: fixed;
            top: 5rem;
            right: 1.25rem;
            z-index: 2000;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            pointer-events: none;
            max-width: 90vw;
        }
        @media (min-width: 768px) {
            .toast-container {
                max-width: 24rem;
            }
        }
        .toast {
            padding: 0.875rem 1.25rem;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 500;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
            pointer-events: auto;
            animation: toastSlideIn 0.3s ease;
            backdrop-filter: blur(8px);
            line-height: 1.4;
            word-wrap: break-word;
            transition: opacity 0.2s ease;
        }
        .toast:hover {
            opacity: 0.95;
        }
        .toast-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        .toast-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        .toast-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }
        .toast-warning {
            background: #fed7aa;
            color: #9a3412;
            border-left: 4px solid #f97316;
        }
        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(2rem);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        @keyframes toastSlideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(2rem);
            }
        }
        .toast-removing {
            animation: toastSlideOut 0.2s ease forwards;
        }
    `;

    let container = null;
    let activeTimeouts = new Map();

    function getContainer() {
        if (container) return container;
        container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    function injectStyles() {
        if (document.getElementById('toast-styles')) return;
        const style = document.createElement('style');
        style.id = 'toast-styles';
        style.textContent = toastStyles;
        document.head.appendChild(style);
    }

    function createToastElement(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        return toast;
    }

    function removeToast(toast, timeoutId) {
        if (timeoutId) {
            const existing = activeTimeouts.get(toast);
            if (existing) clearTimeout(existing);
            activeTimeouts.delete(toast);
        }
        toast.classList.add('toast-removing');
        setTimeout(() => {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 200);
    }

    function show(message, type = 'success', duration = 3000) {
        if (!message) return;
        
        injectStyles();
        const containerEl = getContainer();
        const toast = createToastElement(message, type);
        
        containerEl.appendChild(toast);
        
        let timeoutId = setTimeout(() => {
            removeToast(toast, timeoutId);
        }, duration);
        
        activeTimeouts.set(toast, timeoutId);
        
        // Pause timer on hover
        toast.addEventListener('mouseenter', () => {
            const existing = activeTimeouts.get(toast);
            if (existing) {
                clearTimeout(existing);
                activeTimeouts.delete(toast);
            }
        });
        
        // Resume timer on leave
        toast.addEventListener('mouseleave', () => {
            const newTimeout = setTimeout(() => {
                removeToast(toast, newTimeout);
            }, duration);
            activeTimeouts.set(toast, newTimeout);
        });
        
        return toast;
    }

    // Legacy function (backward compatible)
    window.showToast = function(message, type = 'success') {
        return show(message, type, 3000);
    };

    // Enhanced API
    window.Toast = {
        show: show,
        success: function(message, duration = 3000) {
            return show(message, 'success', duration);
        },
        error: function(message, duration = 3000) {
            return show(message, 'error', duration);
        },
        info: function(message, duration = 3000) {
            return show(message, 'info', duration);
        },
        warning: function(message, duration = 3000) {
            return show(message, 'warning', duration);
        }
    };
})();