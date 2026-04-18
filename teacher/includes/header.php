<?php
// Shared navbar header for teacher pages
// Requires $teacherData array to be available
?>
<nav class="navbar">
  <div style="display: flex; align-items: center; gap: 12px">
    <button
      class="hamburger-btn"
      id="hamburgerBtn"
      aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
    <div class="navbar-brand">
      🎓 Exam<span>Safe</span>
      <span
        style="
          font-size: 0.75rem;
          background: rgba(255, 255, 255, 0.2);
          padding: 2px 8px;
          border-radius: 50px;
          margin-left: 8px;
        ">GURU</span>
    </div>
  </div>
  <div class="navbar-nav">
    <div class="nav-user">
      <div class="nav-avatar"><?= htmlspecialchars($teacherData['avatar_initial']) ?></div>
      <span><?= htmlspecialchars($teacherData['full_name_with_gelar']) ?></span>
    </div>
    <a
      href="../php/logout.php"
      class="btn btn-sm btn-outline"
      style="color: #fff; border-color: rgba(255, 255, 255, 0.4)">Keluar</a>
  </div>
</nav>
