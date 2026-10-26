// Reveal kit components marked data-reveal as they scroll into view. Declared in facts.scripts.
(function () {
    if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var nodes = document.querySelectorAll('[data-reveal]');
    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-revealed');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    nodes.forEach(function (node) {
        node.classList.add('is-reveal-ready');
        observer.observe(node);
    });
})();
