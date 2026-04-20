/**
 * City Courier - Admin Panel JavaScript
 * Handles sidebar toggle, responsive behavior, and UI interactions
 */

document.addEventListener('DOMContentLoaded', function () {

    // ─── Sidebar Toggle ────────────────────────────────────
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const hamburger = document.getElementById('hamburgerBtn');

    function openSidebar() {
        if (sidebar) sidebar.classList.add('active');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('active');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            if (sidebar && sidebar.classList.contains('active')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar on window resize to desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    // ─── Auto-dismiss alerts ───────────────────────────────
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(function () {
                alert.remove();
            }, 500);
        }, 4000);
    });

    // ─── Confirm Dialogs ───────────────────────────────────
    const confirmForms = document.querySelectorAll('[data-confirm]');
    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const message = form.getAttribute('data-confirm') || 'Apakah Anda yakin?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // ─── Smooth number animation on stat cards ─────────────
    const statValues = document.querySelectorAll('.stat-value[data-value]');
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = parseInt(el.getAttribute('data-value'), 10);
                const prefix = el.getAttribute('data-prefix') || '';
                const suffix = el.getAttribute('data-suffix') || '';

                if (isNaN(target)) return;

                let current = 0;
                const duration = 1200;
                const step = Math.max(1, Math.floor(target / (duration / 16)));
                const startTime = performance.now();

                function animate(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
                    current = Math.floor(eased * target);
                    el.textContent = prefix + current.toLocaleString('id-ID') + suffix;

                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        el.textContent = prefix + target.toLocaleString('id-ID') + suffix;
                    }
                }

                requestAnimationFrame(animate);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.3 });

    statValues.forEach(function (el) {
        observer.observe(el);
    });

    // ─── Table row click navigation ────────────────────────
    const clickableRows = document.querySelectorAll('tr[data-href]');
    clickableRows.forEach(function (row) {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function (e) {
            if (e.target.closest('a, button, form')) return;
            window.location.href = row.getAttribute('data-href');
        });
    });

    // ─── Search debounce ───────────────────────────────────
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function () {
            clearTimeout(timeout);
            timeout = setTimeout(function () {
                searchInput.closest('form').submit();
            }, 500);
        });
    }
});
