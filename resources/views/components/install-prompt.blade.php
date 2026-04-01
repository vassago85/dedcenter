<div id="dc-install-prompt" style="display:none;" class="fixed bottom-4 left-4 right-4 z-50 mx-auto max-w-md rounded-2xl border border-white/10 bg-slate-800/95 p-4 shadow-2xl backdrop-blur-lg sm:left-auto sm:right-6 sm:bottom-6">
    <div class="flex items-start gap-3">
        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-red-600/20">
            <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3" />
            </svg>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-white">Add DeadCenter to Home Screen</p>
            <p id="dc-install-desc" class="mt-0.5 text-xs text-slate-400"></p>
            <div id="dc-install-buttons" class="mt-3 flex gap-2" style="display:none;">
                <button id="dc-install-btn" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700">Install</button>
                <button id="dc-dismiss-btn" class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 transition-colors hover:bg-slate-600">Not now</button>
            </div>
            <div id="dc-ios-dismiss" class="mt-2" style="display:none;">
                <button id="dc-ios-dismiss-btn" class="text-xs font-medium text-slate-400 hover:text-white transition-colors">Dismiss</button>
            </div>
        </div>
        <button id="dc-close-btn" class="text-slate-500 hover:text-white transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>
<script>
(function() {
    var dismissed = localStorage.getItem('dc_install_dismissed');
    if (dismissed && (Date.now() - parseInt(dismissed)) < 30 * 24 * 60 * 60 * 1000) return;
    if (window.matchMedia('(display-mode: standalone)').matches) return;

    var prompt = document.getElementById('dc-install-prompt');
    var desc = document.getElementById('dc-install-desc');
    var buttons = document.getElementById('dc-install-buttons');
    var iosDismiss = document.getElementById('dc-ios-dismiss');
    var deferredPrompt = null;

    function show() {
        prompt.style.display = '';
        prompt.style.opacity = '0';
        prompt.style.transform = 'translateY(1rem)';
        requestAnimationFrame(function() {
            prompt.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            prompt.style.opacity = '1';
            prompt.style.transform = 'translateY(0)';
        });
    }

    function dismiss() {
        localStorage.setItem('dc_install_dismissed', Date.now().toString());
        prompt.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
        prompt.style.opacity = '0';
        prompt.style.transform = 'translateY(1rem)';
        setTimeout(function() { prompt.style.display = 'none'; }, 200);
    }

    var isIos = /iPad|iPhone|iPod/.test(navigator.userAgent);
    if (isIos) {
        desc.textContent = 'Tap Share then Add to Home Screen for the best experience.';
        iosDismiss.style.display = '';
        show();
    } else {
        desc.textContent = 'Get quick access to matches, live scores, and notifications.';
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            buttons.style.display = '';
            show();
        });
    }

    document.getElementById('dc-install-btn').addEventListener('click', function() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function() {
                deferredPrompt = null;
                dismiss();
            });
        }
    });

    document.getElementById('dc-dismiss-btn').addEventListener('click', dismiss);
    document.getElementById('dc-ios-dismiss-btn').addEventListener('click', dismiss);
    document.getElementById('dc-close-btn').addEventListener('click', dismiss);
})();
</script>
