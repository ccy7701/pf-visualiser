(() => {
    const dock = document.querySelector('.module-nav-stack');

    if (!dock) {
        return;
    }

    document.body.classList.add('has-right-edge-nav');

    const rightThreshold = 72;

    function clearEdgeClasses() {
        document.body.classList.remove('edge-nav-right-active');
    }

    function updateByPointer(clientX) {
        const width = window.innerWidth || document.documentElement.clientWidth || 0;

        if (width - clientX <= rightThreshold) {
            document.body.classList.add('edge-nav-right-active');
        } else {
            document.body.classList.remove('edge-nav-right-active');
        }
    }

    document.addEventListener('mousemove', (event) => {
        updateByPointer(event.clientX);
    });

    document.addEventListener('mouseleave', clearEdgeClasses);

    document.addEventListener('focusin', (event) => {
        if (!(event.target instanceof HTMLElement)) {
            return;
        }

        if (!event.target.closest('.module-nav-stack')) {
            return;
        }

        document.body.classList.add('edge-nav-right-active');
    });
})();
