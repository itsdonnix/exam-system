/**
 * Shared utility functions for ExamSafe
 */

// Escape HTML to prevent XSS attacks
function escapeHtml(str) {
  if (!str) return '';
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
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
