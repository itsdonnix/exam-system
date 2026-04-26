/**
 * Shared utility functions for ExamSafe
 */

// Escape HTML to prevent XSS attacks
function escapeHtml(str) {
  if (!str) return '';
  const htmlEscapes = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  };
  return str.replace(/[&<>"']/g, ch => htmlEscapes[ch]);
}

// Format date to locale string
function formatDate(dateString, options = {}) {
  if (!dateString) return '—';
  const date = new Date(dateString);
  const defaultOptions = { day: 'numeric', month: 'short', year: 'numeric' };
  return date.toLocaleDateString('id-ID', { ...defaultOptions, ...options });
}

// Format time to locale string
function formatTime(dateString) {
  if (!dateString) return '—';
  return new Date(dateString).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}
