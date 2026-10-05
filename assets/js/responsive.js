/**
 * assets/js/responsive.js
 * Universal Responsive Controller for Customer Storefront & Admin Panel
 */

document.addEventListener('DOMContentLoaded', function () {
    initCustomerMobileNav();
    initAdminResponsiveNav();
    wrapResponsiveTables();
});

/**
 * 1. Customer Storefront Mobile Drawer Navigation
 */
function initCustomerMobileNav() {
    const menuBtn = document.getElementById('mobileMenuBtn');
    const drawer = document.getElementById('mobileNavDrawer');
    const backdrop = document.getElementById('mobileNavBackdrop');
    const closeBtn = document.getElementById('mobileDrawerClose');

    if (!drawer) return;

    function openDrawer() {
        drawer.classList.add('active');
        if (backdrop) backdrop.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
        if (menuBtn) menuBtn.setAttribute('aria-expanded', 'true');
    }

    function closeDrawer() {
        drawer.classList.remove('active');
        if (backdrop) backdrop.classList.remove('active');
        document.body.style.overflow = '';
        if (menuBtn) menuBtn.setAttribute('aria-expanded', 'false');
    }

    if (menuBtn) {
        menuBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (drawer.classList.contains('active')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeDrawer();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeDrawer);
    }

    // Close when clicking any nav link inside drawer
    const drawerLinks = drawer.querySelectorAll('.mobile-drawer-link, .mobile-drawer-accordion-content a');
    drawerLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            closeDrawer();
        });
    });

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('active')) {
            closeDrawer();
        }
    });

    // Mobile Category Accordion Toggle
    window.toggleMobileAccordion = function (btn) {
        const accordion = btn.closest('.mobile-drawer-accordion');
        if (accordion) {
            accordion.classList.toggle('open');
        }
    };
}

/**
 * 2. Admin Panel Responsive Off-Canvas Drawer
 */
function initAdminResponsiveNav() {
    const adminLayout = document.querySelector('.admin-layout');
    const sidebar = document.querySelector('.admin-sidebar');
    const topbar = document.querySelector('.topbar');

    if (!adminLayout || !sidebar) return;

    // Check if backdrop already exists, always place it directly on document.body
    let backdrop = document.querySelector('.admin-drawer-backdrop');
    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'admin-drawer-backdrop';
        document.body.appendChild(backdrop);
    } else if (backdrop.parentElement !== document.body) {
        document.body.appendChild(backdrop);
    }

    // Check if toggle button exists in topbar, otherwise inject it
    let toggleBtn = document.getElementById('adminSidebarToggle');
    if (!toggleBtn && topbar) {
        toggleBtn = document.createElement('button');
        toggleBtn.id = 'adminSidebarToggle';
        toggleBtn.className = 'admin-mobile-toggle';
        toggleBtn.type = 'button';
        toggleBtn.setAttribute('aria-label', 'Toggle Admin Navigation');
        toggleBtn.innerHTML = `
            <svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none">
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        `;
        topbar.insertBefore(toggleBtn, topbar.firstChild);
    }

    function openAdminSidebar() {
        sidebar.classList.add('show');
        backdrop.classList.add('active');
        document.body.classList.add('admin-drawer-open');
        document.body.style.overflow = 'hidden';
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
    }

    function closeAdminSidebar() {
        sidebar.classList.remove('show');
        backdrop.classList.remove('active');
        document.body.classList.remove('admin-drawer-open');
        document.body.style.overflow = '';
        if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (sidebar.classList.contains('show')) {
                closeAdminSidebar();
            } else {
                openAdminSidebar();
            }
        });
    }

    backdrop.addEventListener('click', function(e) {
        e.preventDefault();
        closeAdminSidebar();
    });

    sidebar.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    // Close on close button inside sidebar if present
    const closeBtn = sidebar.querySelector('.admin-sidebar-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeAdminSidebar();
        });
    }

    // Close on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeAdminSidebar();
        }
    });

    // Close when navigating in sidebar on mobile
    const navLinks = sidebar.querySelectorAll('.nav-link');
    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 1024) {
                closeAdminSidebar();
            }
        });
    });
}

/**
 * 3. Automatic Table Responsive Wrapper
 * Ensures every table is safely wrapped so it cannot overflow the screen
 */
function wrapResponsiveTables() {
    const tables = document.querySelectorAll('table');
    tables.forEach(function (tbl) {
        const parent = tbl.parentElement;
        if (!parent.classList.contains('table-responsive')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'table-responsive';
            parent.insertBefore(wrapper, tbl);
            wrapper.appendChild(tbl);
        }
    });
}
