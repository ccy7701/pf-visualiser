(() => {
    const docks = document.querySelectorAll('.module-nav-dock');
    const hasRightDock = document.querySelector('.module-nav-dock.module-nav-dock-right') !== null;

    if (!docks.length) {
        return;
    }

    if (hasRightDock) {
        document.body.classList.add('has-right-edge-nav');
    }

    const leftThreshold = 72;
    const rightThreshold = 72;

    function clearEdgeClasses() {
        document.body.classList.remove('edge-nav-left-active', 'edge-nav-right-active');
    }

    function updateByPointer(clientX) {
        const width = window.innerWidth || document.documentElement.clientWidth || 0;

        if (clientX <= leftThreshold) {
            document.body.classList.add('edge-nav-left-active');
        } else {
            document.body.classList.remove('edge-nav-left-active');
        }

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

        const dock = event.target.closest('.module-nav-dock');
        if (!dock) {
            return;
        }

        if (dock.classList.contains('module-nav-dock-right')) {
            document.body.classList.add('edge-nav-right-active');
            return;
        }

        document.body.classList.add('edge-nav-left-active');
    });
})();
