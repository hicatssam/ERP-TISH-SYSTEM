(() => {
    'use strict';
    document.addEventListener('DOMContentLoaded', () => {
        const links = Array.from(document.querySelectorAll('[data-choose-branch]'));
        links.forEach(link => {
            link.addEventListener('click', () => {
                try { localStorage.setItem('dahab_customer_branch', link.dataset.branchCode); } catch (_) {}
            });
        });
        let selected = null;
        try { selected = localStorage.getItem('dahab_customer_branch'); } catch (_) {}
        const current = links.find(link => link.dataset.branchCode === selected);
        const params = new URLSearchParams(window.location.search);
        if (current && params.get('choose') !== '1') {
            window.location.replace(current.href);
        } else if (selected && !current) {
            try { localStorage.removeItem('dahab_customer_branch'); } catch (_) {}
        }
        const search = document.querySelector('[data-branch-search]');
        const empty = document.querySelector('[data-branch-search-empty]');
        search?.addEventListener('input', () => {
            const query = search.value.trim().toLocaleLowerCase();
            links.forEach(link => {
                link.hidden = !link.textContent.toLocaleLowerCase().includes(query);
            });
            if (empty) empty.hidden = links.some(link => !link.hidden);
        });
    });
})();
