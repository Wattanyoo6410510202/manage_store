</div>
</main>

<script>
    function toggleSidebar() {
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
        const submenu = document.getElementById(id);
        const arrow = document.getElementById('arrow-' + id);

        // สลับการแสดงผล (Hidden)
        submenu.classList.toggle('hidden');

        // หมุนลูกศร
        if (arrow) {
            arrow.classList.toggle('rotate-180');
        }
    }
</script>
</body>

</html>