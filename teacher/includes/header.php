<?php
// Shared navbar header for teacher pages
// Requires $teacherData array to be available
?>
<nav class="navbar navbar-teacher">
  <div style="display: flex; align-items: center; gap: 12px">
    <button
      class="hamburger-btn"
      id="hamburgerBtn"
      aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
    <div class="navbar-brand">
      <div>
        🎓 Exam<span>Safe</span>
      </div>
      <span>GURU</span>
    </div>
  </div>
  <div class="navbar-nav">
    <a
      href="../php/logout.php"
      class="btn btn-sm btn-outline">Keluar</a>
  </div>
</nav>
