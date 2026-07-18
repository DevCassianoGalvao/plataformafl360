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

    function fallbackCopy(input) {
        input.focus();
        input.select();
        input.setSelectionRange(0, input.value.length);

        try {
            return document.execCommand('copy');
        } catch (error) {
            return false;
        }
    }

    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-copy-target'));
            if (!input) return;

            var original = button.textContent;
            var showResult = function (copied) {
                button.textContent = copied ? 'Copiado' : 'Selecione e copie';
                if (!copied) {
                    input.focus();
                    input.select();
                }
                setTimeout(function () { button.textContent = original; }, 2200);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value)
                    .then(function () { showResult(true); })
                    .catch(function () { showResult(fallbackCopy(input)); });
                return;
            }

            showResult(fallbackCopy(input));
        });
    });

    document.querySelectorAll('[data-password-strength]').forEach(function (input) {
        var feedback = document.querySelector('[data-password-feedback]');
        if (!feedback) return;
        input.addEventListener('input', function () {
            var length = input.value.length;
            feedback.className = 'password-strength ' + (length >= 16 ? 'is-strong' : (length >= 12 ? 'is-good' : 'is-weak'));
            feedback.textContent = length >= 16 ? 'Senha forte.' : (length >= 12 ? 'Boa senha. Mais comprimento aumenta a proteção.' : 'Use pelo menos 12 caracteres.');
        });
    });

    var materialTarget = document.getElementById('materialTarget');
    if (materialTarget) {
        var moduleTarget = document.querySelector('[data-module-target]');
        var lessonTarget = document.querySelector('[data-lesson-target]');
        var syncMaterialTarget = function () {
            var lessonMode = materialTarget.value === 'lesson';
            moduleTarget.hidden = lessonMode;
            lessonTarget.hidden = !lessonMode;
            moduleTarget.querySelector('select').disabled = lessonMode;
            lessonTarget.querySelector('select').disabled = !lessonMode;
        };
        materialTarget.addEventListener('change', syncMaterialTarget);
        syncMaterialTarget();
    }

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
