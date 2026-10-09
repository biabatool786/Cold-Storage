import './bootstrap';

const STORAGE_KEY = 'csms.sidebar.openModules';

function readOpenModules() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function writeOpenModules(keys) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify([...new Set(keys)]));
    } catch {
        // Ignore private-mode / quota errors.
    }
}

function setModuleOpen(module, open) {
    module.classList.toggle('is-open', open);
    const panel = module.querySelector('.sidebar-panel');
    const toggle = module.querySelector('.sidebar-module-toggle');
    if (panel) {
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        panel.querySelectorAll('a').forEach((link) => {
            if (open) {
                link.removeAttribute('tabindex');
            } else {
                link.setAttribute('tabindex', '-1');
            }
        });
    }
    if (toggle) {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
}

function initSidebar() {
    const sidebar = document.getElementById('app-sidebar');
    if (! sidebar) {
        return;
    }

    const serverOpen = (() => {
        try {
            return JSON.parse(sidebar.dataset.openKeys || '[]');
        } catch {
            return [];
        }
    })();

    const stored = readOpenModules();
    const modules = [...sidebar.querySelectorAll('.sidebar-module')];

    modules.forEach((module) => {
        const key = module.dataset.moduleKey;
        const forceOpen = module.dataset.forceOpen === '1';
        const shouldOpen = forceOpen || stored.includes(key);
        setModuleOpen(module, shouldOpen);

        const toggle = module.querySelector('.sidebar-module-toggle');
        toggle?.addEventListener('click', () => {
            const next = ! module.classList.contains('is-open');
            setModuleOpen(module, next);

            const current = readOpenModules().filter((item) => item !== key);
            if (next) {
                current.push(key);
            }
            serverOpen.forEach((item) => {
                if (! current.includes(item)) {
                    current.push(item);
                }
            });
            writeOpenModules(current);
        });
    });

    sidebar.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            const toggle = document.getElementById('nav-toggle');
            if (toggle && window.matchMedia('(max-width: 1023px)').matches) {
                toggle.checked = false;
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', initSidebar);
