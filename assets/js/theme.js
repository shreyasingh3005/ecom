/**
 * assets/js/theme.js
 * Universal Interactive Engine for KAMS HEMP
 * Handles Header, Mobile Drawer, Live Search, Toast Notifications, and Micro-interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    initHeaderScroll();
    initMobileDrawer();
    initLiveSearch();
});

// 1. Header scroll effect
function initHeaderScroll() {
    const header = document.querySelector('.theme-header');
    if (!header) return;

    const onScroll = () => {
        if (window.scrollY > 20) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
}

// 2. Mobile Drawer
function initMobileDrawer() {
    const hamburger = document.querySelector('.theme-hamburger-btn');
    const drawer = document.getElementById('themeMobileDrawer');
    const backdrop = document.getElementById('themeMobileBackdrop');
    const closeBtn = document.getElementById('themeDrawerClose');

    if (!drawer || !backdrop) return;

    const openDrawer = () => {
        drawer.classList.add('open');
        backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
    };

    const closeDrawer = () => {
        drawer.classList.remove('open');
        backdrop.classList.remove('open');
        document.body.style.overflow = '';
    };

    if (hamburger) hamburger.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (backdrop) backdrop.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && drawer.classList.contains('open')) {
            closeDrawer();
        }
    });

    // Accordion inside drawer
    const accordionBtn = document.querySelector('.theme-drawer-accordion-btn');
    const accordionContent = document.querySelector('.theme-drawer-accordion-content');
    if (accordionBtn && accordionContent) {
        accordionBtn.addEventListener('click', () => {
            accordionBtn.classList.toggle('expanded');
            accordionContent.classList.toggle('expanded');
        });
    }
}

// 3. Live Search Autocomplete
function initLiveSearch() {
    const searchInputs = document.querySelectorAll('.theme-search-input');
    searchInputs.forEach(input => {
        const parent = input.closest('.theme-header-search');
        if (!parent) return;

        let dropdown = parent.querySelector('.theme-search-dropdown');
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.className = 'theme-search-dropdown';
            parent.appendChild(dropdown);
        }

        let debounceTimer;
        input.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < 2) {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`cbd-products.php?search=${encodeURIComponent(query)}&format=json`)
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.length > 0) {
                            dropdown.innerHTML = data.slice(0, 5).map(p => `
                                <a href="product_details.php?id=${p.id}" class="theme-dropdown-link" style="display:flex; align-items:center; gap:10px; padding:8px; border-bottom:1px solid rgba(255,255,255,0.05);">
                                    <img src="${p.image || 'uploads/products/default.webp'}" alt="${p.title}" style="width:36px; height:36px; object-fit:cover; border-radius:6px;">
                                    <div style="flex:1; overflow:hidden;">
                                        <div style="font-size:13px; font-weight:700; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${p.title}</div>
                                        <div style="font-size:12px; color:#00ffcc; font-weight:600;">${p.price}</div>
                                    </div>
                                </a>
                            `).join('') + `<a href="cbd-products.php?search=${encodeURIComponent(query)}" style="display:block; text-align:center; padding:10px; color:#e5c378; font-size:12px; font-weight:700;">View all results →</a>`;
                            dropdown.style.display = 'block';
                        } else {
                            dropdown.innerHTML = `<div style="padding:14px; font-size:13px; color:#94a3b8; text-align:center;">No matching formulations found</div>`;
                            dropdown.style.display = 'block';
                        }
                    })
                    .catch(() => {
                        dropdown.style.display = 'none';
                    });
            }, 250);
        });

        document.addEventListener('click', (e) => {
            if (!parent.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    });
}

// 4. Toast Notification Engine
window.showToast = function(message, type = 'success') {
    let container = document.getElementById('themeToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'themeToastContainer';
        container.style.cssText = 'position:fixed; bottom:24px; left:24px; z-index:99999; display:flex; flex-direction:column; gap:10px; pointer-events:none;';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const bg = type === 'success' ? '#111722' : (type === 'error' ? '#221115' : '#111722');
    const borderColor = type === 'success' ? '#00ffcc' : (type === 'error' ? '#ef4444' : '#e5c378');
    const icon = type === 'success' ? '✓' : (type === 'error' ? '✕' : 'ℹ');

    toast.style.cssText = `background:${bg}; border:1px solid ${borderColor}; color:#ffffff; padding:12px 18px; border-radius:10px; font-size:13.5px; font-weight:600; display:flex; align-items:center; gap:10px; box-shadow:0 10px 25px rgba(0,0,0,0.6); pointer-events:auto; transform:translateY(20px); opacity:0; transition:all 0.3s cubic-bezier(0.16, 1, 0.3, 1);`;
    toast.innerHTML = `<span style="background:${borderColor}; color:#000; width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:11px; font-weight:800;">${icon}</span> <span>${message}</span>`;

    container.appendChild(toast);
    requestAnimationFrame(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    });

    setTimeout(() => {
        toast.style.transform = 'translateY(-10px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
};
