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

    let previousFocus;
    let previousOverflow = '';
    const openDrawer = () => {
        previousFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        drawer.inert = false;
        drawer.setAttribute('aria-hidden', 'false');
        hamburger?.setAttribute('aria-expanded', 'true');
        drawer.classList.add('open');
        backdrop.classList.add('open');
        document.body.style.overflow = 'hidden';
        (closeBtn || drawer).focus();
    };

    const closeDrawer = () => {
        if (!drawer.classList.contains('open')) return;
        drawer.classList.remove('open');
        backdrop.classList.remove('open');
        document.body.style.overflow = previousOverflow;
        hamburger?.setAttribute('aria-expanded', 'false');
        previousFocus?.focus();
        drawer.inert = true;
        drawer.setAttribute('aria-hidden', 'true');
    };

    if (hamburger) hamburger.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (backdrop) backdrop.addEventListener('click', closeDrawer);

    document.addEventListener('keydown', (e) => {
        if (!drawer.classList.contains('open')) return;
        if (e.key === 'Escape' && drawer.classList.contains('open')) {
            closeDrawer();
        }
        if (e.key === 'Tab') {
            const focusable = [...drawer.querySelectorAll('a[href], button, input, [tabindex="0"]')]
                .filter(el => !el.disabled && !el.closest('[inert]') && el.getClientRects().length);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (!first) { e.preventDefault(); drawer.focus(); return; }
            if (e.shiftKey && (document.activeElement === first || !drawer.contains(document.activeElement))) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && (document.activeElement === last || !drawer.contains(document.activeElement))) {
                e.preventDefault(); first.focus();
            }
        }
    });
    drawer.addEventListener('click', e => {
        if (e.target.closest('a[href]')) closeDrawer();
    });
    window.matchMedia('(min-width: 1025px)').addEventListener('change', e => {
        if (e.matches) closeDrawer();
    });

    // Accordion inside drawer
    const accordionBtn = document.querySelector('.theme-drawer-accordion-btn');
    const accordionContent = document.querySelector('.theme-drawer-accordion-content');
    if (accordionBtn && accordionContent) {
        accordionBtn.addEventListener('click', () => {
            const expanded = accordionBtn.classList.toggle('expanded');
            accordionContent.classList.toggle('expanded', expanded);
            accordionContent.inert = !expanded;
            accordionBtn.setAttribute('aria-expanded', String(expanded));
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
        let requestVersion = 0;
        input.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            const version = ++requestVersion;
            clearTimeout(debounceTimer);

            if (query.length < 2) {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
                return;
            }

            debounceTimer = setTimeout(() => {
                fetch(`cbd-products.php?search=${encodeURIComponent(query)}&format=json`)
                    .then(res => { if (!res.ok) throw new Error('Search unavailable'); return res.json(); })
                    .then(data => {
                        if (version !== requestVersion) return;
                        dropdown.replaceChildren();
                        if (Array.isArray(data) && data.length > 0) {
                            data.slice(0, 5).forEach(p => {
                                const link = document.createElement('a');
                                link.href = `product_details.php?id=${encodeURIComponent(p.id)}`;
                                link.className = 'theme-dropdown-link';
                                const img = document.createElement('img');
                                // Only permit image URLs with ordinary HTTP(S) protocols.
                                const imageUrl = new URL(p.image || 'uploads/products/default.webp', document.baseURI);
                                if (['http:', 'https:'].includes(imageUrl.protocol)) img.src = imageUrl.href;
                                img.alt = String(p.title || '');
                                img.width = 36;
                                img.height = 36;
                                img.style.cssText = 'width:36px;height:36px;object-fit:cover;border-radius:6px;';
                                const details = document.createElement('div');
                                const title = document.createElement('div');
                                title.textContent = String(p.title || '');
                                const price = document.createElement('div');
                                price.textContent = String(p.price || '');
                                details.append(title, price);
                                link.append(img, details);
                                dropdown.append(link);
                            });
                            const allResults = document.createElement('a');
                            allResults.href = `cbd-products.php?search=${encodeURIComponent(query)}`;
                            allResults.className = 'theme-dropdown-link';
                            allResults.textContent = 'View all results →';
                            dropdown.append(allResults);
                            dropdown.style.display = 'block';
                        } else {
                            dropdown.innerHTML = `<div style="padding:14px; font-size:13px; color:#94a3b8; text-align:center;">No matching formulations found</div>`;
                            dropdown.style.display = 'block';
                        }
                    })
                    .catch(() => {
                        if (version === requestVersion) dropdown.style.display = 'none';
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
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    const iconElement = document.createElement('span');
    iconElement.textContent = icon;
    iconElement.setAttribute('aria-hidden', 'true');
    const messageElement = document.createElement('span');
    messageElement.textContent = String(message);
    toast.append(iconElement, messageElement);

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
