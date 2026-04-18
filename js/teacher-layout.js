// Shared sidebar toggle functionality for teacher pages

document.addEventListener("DOMContentLoaded", function () {
  const hamburgerBtn = document.getElementById("hamburgerBtn");
  const sidebar = document.getElementById("sidebar");
  const sidebarOverlay = document.getElementById("sidebarOverlay");

  // Toggle sidebar when hamburger button is clicked
  if (hamburgerBtn) {
    hamburgerBtn.addEventListener("click", function (e) {
      e.stopPropagation();
      if (sidebar) {
        sidebar.classList.toggle("sidebar-open");
      }
      if (sidebarOverlay) {
        sidebarOverlay.classList.toggle("sidebar-overlay-visible");
      }
    });
  }

  // Close sidebar when overlay is clicked
  if (sidebarOverlay) {
    sidebarOverlay.addEventListener("click", function () {
      if (sidebar) {
        sidebar.classList.remove("sidebar-open");
      }
      sidebarOverlay.classList.remove("sidebar-overlay-visible");
    });
  }

  // Close sidebar when a menu link is clicked (mobile UX)
  if (sidebar) {
    const menuLinks = sidebar.querySelectorAll(".sidebar-menu a");
    menuLinks.forEach(function (link) {
      link.addEventListener("click", function () {
        if (window.innerWidth <= 768) {
          sidebar.classList.remove("sidebar-open");
          if (sidebarOverlay) {
            sidebarOverlay.classList.remove("sidebar-overlay-visible");
          }
        }
      });
    });
  }
});
