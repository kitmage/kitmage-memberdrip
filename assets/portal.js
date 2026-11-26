(() => {
    'use strict';
    const load = async (node) => {
        if (node.dataset.kmdLoaded) return;
        node.dataset.kmdLoaded = '1';
        node.setAttribute('aria-busy', 'true');
        try {
            const body = new URLSearchParams({
                action: 'kmd_portal',
                experience: node.dataset.kmdExperience || '0'
            });
            const response = await fetch(node.dataset.kmdEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body
            });
            if (!response.ok) throw new Error('Portal request failed');
            const result = await response.json();
            if (!result.success || typeof result.data.html !== 'string') throw new Error('Invalid portal response');
            node.innerHTML = result.data.html;
        } catch (error) {
            node.textContent = 'Your experience could not be loaded. Please refresh the page and try again.';
        } finally {
            node.removeAttribute('aria-busy');
        }
    };
    const scan = () => document.querySelectorAll('.kmd-portal[data-kmd-endpoint]').forEach(load);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
    else scan();
    // Elementor can insert shortcode widgets after the initial page render.
    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(scan).observe(document.documentElement, {childList:true, subtree:true});
    }
})();
