document.addEventListener('DOMContentLoaded', function () {
    var menuBtn = document.getElementById('mobileMenuBtn');
    var sidebar = document.getElementById('sidebarNav');
    var overlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-visible');
        menuBtn.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-visible');
        menuBtn.setAttribute('aria-expanded', 'false');
    }

    if (menuBtn && sidebar && overlay) {
        menuBtn.addEventListener('click', function () {
            if (sidebar.classList.contains('is-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
        overlay.addEventListener('click', closeSidebar);
    }

    // Tutup notifikasi flash otomatis setelah beberapa detik.
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alertEl) {
        setTimeout(function () {
            alertEl.style.transition = 'opacity 300ms ease';
            alertEl.style.opacity = '0';
            setTimeout(function () { alertEl.remove(); }, 300);
        }, 5000);
    });
});
