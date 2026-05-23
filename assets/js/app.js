(function () {
    var body = document.body;
    var themeToggle = document.getElementById('themeToggle');
    var sidebarToggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('mainSidebar');
    var notifDropdown = document.getElementById('notifDropdown');
    var notifToggle = document.getElementById('notifToggle');

    function setTheme(theme) {
        body.setAttribute('data-theme', theme);
        localStorage.setItem('fl360_theme', theme);
    }

    var savedTheme = localStorage.getItem('fl360_theme');
    if (!savedTheme) {
        savedTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }
    setTheme(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            var current = body.getAttribute('data-theme') || 'light';
            setTheme(current === 'dark' ? 'light' : 'dark');
        });
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            body.classList.toggle('sidebar-open');
        });
    }

    if (notifToggle && notifDropdown) {
        notifToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            notifDropdown.classList.toggle('open');
            notifToggle.setAttribute('aria-expanded', notifDropdown.classList.contains('open') ? 'true' : 'false');
        });
    }

    document.addEventListener('click', function (event) {
        if (body.classList.contains('sidebar-open') && sidebar) {
            var clickedInsideSidebar = sidebar.contains(event.target);
            var clickedSidebarToggle = sidebarToggle && sidebarToggle.contains(event.target);
            if (!clickedInsideSidebar && !clickedSidebarToggle) {
                body.classList.remove('sidebar-open');
            }
        }

        if (notifDropdown && notifDropdown.classList.contains('open')) {
            var clickedInsideNotif = notifDropdown.contains(event.target);
            if (!clickedInsideNotif) {
                notifDropdown.classList.remove('open');
                if (notifToggle) {
                    notifToggle.setAttribute('aria-expanded', 'false');
                }
            }
        }
    });

    document.querySelectorAll('.sidebar-link').forEach(function (link) {
        link.addEventListener('click', function () {
            body.classList.remove('sidebar-open');
        });
    });

    setTimeout(function () {
        document.querySelectorAll('.flash').forEach(function (item) {
            item.style.transition = 'opacity .3s ease';
            item.style.opacity = '0';
            setTimeout(function () {
                item.remove();
            }, 300);
        });
    }, 3500);
})();
