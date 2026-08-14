</div>
</main>

<script>
    const SIDEBAR_COLLAPSED_KEY = 'prosystem.sidebar.collapsed';
    const desktopSidebarMedia = window.matchMedia('(min-width: 769px)');
    const sidebarTooltip = document.getElementById('sidebar-tooltip');

    function hideSidebarTooltip() {
        if (!sidebarTooltip) return;
        sidebarTooltip.classList.remove('is-visible');
        sidebarTooltip.setAttribute('aria-hidden', 'true');
    }

    function setDesktopSidebarCollapsed(collapsed, persist = true) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebar-toggle');
        const icon = document.getElementById('sidebar-toggle-icon');

        if (!sidebar) return;

        sidebar.classList.toggle('sidebar-collapsed', collapsed);
        document.documentElement.classList.toggle('sidebar-collapsed-preference', collapsed);

        if (toggle) {
            toggle.setAttribute('aria-expanded', String(!collapsed));
            toggle.setAttribute('aria-label', collapsed ? 'ขยายแถบเมนู' : 'ย่อแถบเมนู');
        }

        if (icon) {
            icon.classList.toggle('fa-chevron-left', !collapsed);
            icon.classList.toggle('fa-chevron-right', collapsed);
        }

        if (!collapsed) {
            hideSidebarTooltip();
        }

        if (persist) {
            try {
                localStorage.setItem(SIDEBAR_COLLAPSED_KEY, String(collapsed));
            } catch (error) {
                // Keep the current visual state when browser storage is unavailable.
            }
        }
    }

    function toggleDesktopSidebar() {
        if (!desktopSidebarMedia.matches) return;
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        setDesktopSidebarCollapsed(!sidebar.classList.contains('sidebar-collapsed'));
    }

    function toggleSidebar() {
        if (desktopSidebarMedia.matches) {
            toggleDesktopSidebar();
            return;
        }

        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        if (sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.classList.add('hidden');
            overlay.classList.remove('opacity-100');
        } else {
            sidebar.classList.add('open');
            overlay.classList.remove('hidden');
            setTimeout(() => overlay.classList.add('opacity-100'), 10);
        }
    }

    function toggleSubmenu(id) {
        const sidebar = document.getElementById('sidebar');
        if (desktopSidebarMedia.matches && sidebar?.classList.contains('sidebar-collapsed')) {
            setDesktopSidebarCollapsed(false);
            return;
        }

        const submenu = document.getElementById(id);
        const arrow = document.getElementById('arrow-' + id);

        // สลับการแสดงผล (Hidden)
        submenu.classList.toggle('hidden');

        // หมุนลูกศร
        if (arrow) {
            arrow.classList.toggle('rotate-180');
        }
    }

    function showSidebarTooltip(target) {
        const sidebar = document.getElementById('sidebar');
        if (!sidebarTooltip || !sidebar || !desktopSidebarMedia.matches || !sidebar.classList.contains('sidebar-collapsed')) {
            return;
        }

        const label = target.dataset.sidebarTooltip;
        const rect = target.getBoundingClientRect();
        sidebarTooltip.textContent = label;
        sidebarTooltip.style.left = `${rect.right + 12}px`;
        sidebarTooltip.style.top = `${rect.top + (rect.height / 2)}px`;
        sidebarTooltip.classList.add('is-visible');
        sidebarTooltip.setAttribute('aria-hidden', 'false');
    }

    const sidebarTooltipTargets = document.querySelectorAll(
        '#sidebar-nav > a, #sidebar-nav > div > button, .sidebar-footer a[href="logout.php"]'
    );

    sidebarTooltipTargets.forEach((target) => {
        const label = target.textContent.replace(/\s+/g, ' ').trim();
        if (!label) return;

        target.dataset.sidebarTooltip = label;
        if (!target.hasAttribute('aria-label')) {
            target.setAttribute('aria-label', label);
        }
        target.setAttribute('aria-describedby', 'sidebar-tooltip');

        target.addEventListener('mouseenter', () => showSidebarTooltip(target));
        target.addEventListener('mouseleave', hideSidebarTooltip);
        target.addEventListener('focus', () => showSidebarTooltip(target));
        target.addEventListener('blur', hideSidebarTooltip);
        target.addEventListener('click', hideSidebarTooltip);
    });

    document.getElementById('sidebar-nav')?.addEventListener('scroll', hideSidebarTooltip);
    window.addEventListener('resize', hideSidebarTooltip);

    const initialSidebarCollapsed = document.documentElement.classList.contains('sidebar-collapsed-preference');
    setDesktopSidebarCollapsed(initialSidebarCollapsed, false);
</script>
<script src="assets/js/tutorial.js"></script>
</body>

</html>
