<?php
/**
 * student/_sidebar_js.php
 * Sidebar toggle JS — included at the bottom of every student page.
 */
?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.getElementById('sidebarCollapse');
        const overlay = document.getElementById('sidebarOverlay');

        if (toggle && sidebar) {
            toggle.addEventListener('click', e => {
                e.stopPropagation();
                sidebar.classList.toggle('active');
                if (overlay) overlay.classList.toggle('active');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            });
        }

        // Close on outside click (mobile)
        document.addEventListener('click', e => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                if (!sidebar.contains(e.target) && e.target !== toggle) {
                    sidebar.classList.remove('active');
                    if (overlay) overlay.classList.remove('active');
                }
            }
        });

        // Toggle mobile profile dropdown
        const profileBtn = document.getElementById('mobileProfileBtn');
        const profileDropdown = document.getElementById('mobileProfileDropdown');

        if (profileBtn && profileDropdown) {
            profileBtn.addEventListener('click', e => {
                e.stopPropagation();
                profileDropdown.classList.toggle('active');
            });
            
            // Close when clicking outside
            document.addEventListener('click', e => {
                if (profileDropdown.classList.contains('active')) {
                    if (!profileDropdown.contains(e.target) && e.target !== profileBtn) {
                        profileDropdown.classList.remove('active');
                    }
                }
            });
        }
    });
</script>