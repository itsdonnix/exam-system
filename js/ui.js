// UI helpers: responsive sidebar toggle and navbar collapse
(function(){
  function el(sel){return document.querySelector(sel);} 
  function els(sel){return Array.from(document.querySelectorAll(sel));}

  document.addEventListener('DOMContentLoaded', ()=>{
    const navbar = el('.navbar');
    const sidebar = el('.sidebar');
    if (!navbar) return;

    // Insert sidebar toggle button when sidebar exists
    if (sidebar && !el('.sidebar-toggle')) {
      const btn = document.createElement('button');
      btn.className = 'sidebar-toggle';
      btn.setAttribute('aria-label','Toggle menu');
      btn.innerHTML = '☰';
      navbar.insertBefore(btn, navbar.firstChild);

      const backdrop = document.createElement('div');
      backdrop.className = 'sidebar-backdrop';
      document.body.appendChild(backdrop);

      function openSidebar(){ sidebar.classList.add('open'); backdrop.classList.add('show'); document.body.classList.add('no-scroll');
        // focus first focusable element
        const focusable = sidebar.querySelector('a,button,input,select,textarea'); if (focusable) focusable.focus();
      }
      function closeSidebar(){ sidebar.classList.remove('open'); backdrop.classList.remove('show'); document.body.classList.remove('no-scroll'); }

      btn.addEventListener('click', ()=>{ if (sidebar.classList.contains('open')) closeSidebar(); else openSidebar(); });
      backdrop.addEventListener('click', closeSidebar);

      // Close sidebar on resize to large screens
      window.addEventListener('resize', ()=>{ if (window.innerWidth > 992) { closeSidebar(); } });

      // Close sidebar with Escape and trap focus while open
      document.addEventListener('keydown', (e)=>{
        if (e.key === 'Escape' && sidebar.classList.contains('open')) { closeSidebar(); }
        if (!sidebar.classList.contains('open')) return;
        if (e.key === 'Tab') {
          const focusable = Array.from(sidebar.querySelectorAll('a,button,input,select,textarea')).filter(n=>!n.disabled && n.offsetParent !== null);
          if (focusable.length === 0) return;
          const first = focusable[0]; const last = focusable[focusable.length-1];
          if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
          else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
      });
    }

    // Navbar links collapse for small screens: create a toggle that shows cloned links
    const navNav = el('.navbar-nav');
    if (navNav) {
      // ensure menuToggle exists (use existing if present)
      let menuToggle = el('.nav-menu-toggle');
      if (!menuToggle) {
        menuToggle = document.createElement('button');
        menuToggle.className = 'nav-menu-toggle';
        menuToggle.setAttribute('aria-label','Open menu');
        menuToggle.style.marginLeft = '8px';
        menuToggle.style.background = 'transparent';
        menuToggle.style.border = 'none';
        menuToggle.style.color = 'white';
        menuToggle.style.fontSize = '1.15rem';
        menuToggle.innerHTML = '⋯';
        navbar.appendChild(menuToggle);
      }

      // ensure collapse exists (use existing if present)
      let collapse = el('.nav-collapse');
      if (!collapse) {
        collapse = document.createElement('div');
        collapse.className = 'nav-collapse';
        // clone nav links
        els('.nav-link').forEach(a=>{
          const clone = a.cloneNode(true);
          clone.classList.remove('active');
          clone.addEventListener('click', ()=>{ collapse.classList.remove('show'); });
          collapse.appendChild(clone);
        });
        document.body.appendChild(collapse);
      }

      // Move/hide logout button into collapse on mobile to reduce clutter
      // Heuristic + fallback: find element with text 'Keluar' or 'Logout', href pointing to index.html, contains 'logout', or data-logout attribute
      function ensureLogoutInCollapse() {
        if (!navbar) return;
        const candidates = Array.from(document.querySelectorAll('a,button'));
        let possibleLogout = candidates.find(elm => {
          const t = (elm.textContent || '').trim().toLowerCase();
          const href = elm.getAttribute ? (elm.getAttribute('href') || '') : '';
          const dataLogout = elm.getAttribute && (elm.getAttribute('data-logout') || elm.dataset && elm.dataset.logout);
          return dataLogout || t.includes('keluar') || t.includes('logout') || href.toLowerCase().includes('index.html') || href.toLowerCase().includes('logout');
        });

        if (!possibleLogout) {
          // Try looking inside navbar specifically as fallback
          possibleLogout = Array.from(navbar.querySelectorAll('a,button')).find(elm => {
            const t = (elm.textContent || '').trim().toLowerCase();
            const href = elm.getAttribute ? (elm.getAttribute('href') || '') : '';
            return t.includes('keluar') || t.includes('logout') || href.toLowerCase().includes('index.html') || href.toLowerCase().includes('logout');
          });
        }

        if (possibleLogout) {
          // Avoid duplicating if already moved
          const alreadyMoved = collapse.querySelectorAll('a,button');
          const exists = Array.from(alreadyMoved).some(n => (n.textContent || '').trim() === (possibleLogout.textContent || '').trim());
          if (!exists) {
            const logoutClone = possibleLogout.cloneNode(true);
            // mark original to hide on mobile
            possibleLogout.classList.add('hide-on-mobile');
            logoutClone.addEventListener('click', ()=>{ collapse.classList.remove('show'); });
            collapse.appendChild(logoutClone);
          }
        }
        else {
          // Fallback: create a generic logout link if none found
          const fallback = document.createElement('a');
          fallback.href = '../index.html';
          fallback.className = 'nav-link';
          fallback.textContent = 'Keluar';
          fallback.addEventListener('click', ()=>{ collapse.classList.remove('show'); });
          // Avoid duplicate fallback
          const hasFallback = Array.from(collapse.querySelectorAll('a')).some(a=> (a.textContent||'').trim() === 'Keluar');
          if (!hasFallback) collapse.appendChild(fallback);
        }
        // Also ensure a visible logout button inside sidebar on mobile
        try {
          if (typeof sidebar !== 'undefined' && sidebar) {
            const existing = sidebar.querySelector('.mobile-logout');
            if (!existing) {
              // Try to get href/text from possibleLogout or fallback
              const source = possibleLogout || null;
              const href = source && source.getAttribute ? (source.getAttribute('href') || '../index.html') : '../index.html';
              const text = source ? (source.textContent || 'Keluar') : 'Keluar';
              const wrap = document.createElement('div');
              wrap.className = 'mobile-logout';
              const link = document.createElement('a');
              link.href = href;
              link.textContent = text.trim();
              link.addEventListener('click', ()=>{ closeSidebar(); });
              wrap.appendChild(link);
              sidebar.appendChild(wrap);
            }
          }
        } catch (e) { /* ignore */ }
      }

      // Run immediately and also after a short delay for pages that mutate navbar later
      ensureLogoutInCollapse();
      setTimeout(ensureLogoutInCollapse, 300);

      menuToggle.addEventListener('click', ()=>{ collapse.classList.toggle('show'); });

      window.addEventListener('click', (e)=>{
        try {
          const target = e.target;
          const collapseHas = collapse && typeof collapse.contains === 'function' && collapse.contains(target);
          const toggleHas = menuToggle && typeof menuToggle.contains === 'function' && menuToggle.contains(target);
          if (!collapseHas && !toggleHas) {
            collapse.classList.remove('show');
          }
        } catch (err) {
          // Defensive: if DOM nodes change unexpectedly, don't throw
          console.warn('ui.js: click handler error', err);
        }
      });
    }

    // Improve focus outlines for keyboard users
    document.body.classList.add('ui-js-ready');
  });
})();
