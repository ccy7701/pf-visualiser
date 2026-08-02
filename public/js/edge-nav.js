(() => {
    const dock = document.querySelector('.module-nav-stack');

    if (!dock) {
        return;
    }

    document.body.classList.add('has-right-edge-nav');

    const rightActivationThreshold = 40;
    const rightDismissalThreshold = 80;
    let lastInteractionWasPointer = false;

    function clearEdgeClasses() {
        document.body.classList.remove('edge-nav-right-active');
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('theme', theme);
    }

    function blurPointerFocusedDockElement() {
        if (!lastInteractionWasPointer) {
            return;
        }

        if (!(document.activeElement instanceof HTMLElement)) {
            return;
        }

        if (!document.activeElement.closest('.module-nav-stack')) {
            return;
        }

        document.activeElement.blur();
    }

    function updateByPointer(clientX) {
        const width = window.innerWidth || document.documentElement.clientWidth || 0;
        const distanceFromRight = width - clientX;
        const threshold = document.body.classList.contains('edge-nav-right-active')
            ? rightDismissalThreshold
            : rightActivationThreshold;

        if (distanceFromRight <= threshold) {
            document.body.classList.add('edge-nav-right-active');
        } else {
            document.body.classList.remove('edge-nav-right-active');
        }
    }

    document.addEventListener('pointerdown', () => {
        lastInteractionWasPointer = true;
    });

    document.addEventListener('keydown', () => {
        lastInteractionWasPointer = false;
    });

    document.addEventListener('mousemove', (event) => {
        updateByPointer(event.clientX);
    });

    document.addEventListener('mouseleave', clearEdgeClasses);

    dock.addEventListener('mouseleave', () => {
        blurPointerFocusedDockElement();
        clearEdgeClasses();
    });

    document.addEventListener('focusin', (event) => {
        if (!(event.target instanceof HTMLElement)) {
            return;
        }

        if (!event.target.closest('.module-nav-stack')) {
            return;
        }

        document.body.classList.add('edge-nav-right-active');
    });

    window.addEventListener('theme-changed', (event) => {
        applyTheme(event.detail.theme);
    });
})();
