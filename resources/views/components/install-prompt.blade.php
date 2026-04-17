<div id="dc-install-prompt" style="display:none; position:fixed; bottom:1rem; left:1rem; right:1rem; z-index:9999; max-width:28rem; margin:0 auto; border-radius:1rem; border:1px solid rgba(255,255,255,0.1); background:rgba(30,41,59,0.97); padding:1rem; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); backdrop-filter:blur(16px);">
    <div style="display:flex; align-items:flex-start; gap:0.75rem;">
        <div style="flex-shrink:0; width:2.5rem; height:2.5rem; display:flex; align-items:center; justify-content:center; border-radius:0.75rem; background:rgba(220,38,38,0.2);">
            <x-icon name="smartphone" style="width:1.25rem; height:1.25rem; color:#ef4444;" />
        </div>
        <div style="flex:1;">
            <p style="font-size:0.875rem; font-weight:600; color:#fff; margin:0;">Add DeadCenter to Home Screen</p>
            <p id="dc-install-desc" style="margin-top:0.25rem; font-size:0.75rem; color:#94a3b8;"></p>
            <div id="dc-install-buttons" style="display:none; margin-top:0.75rem; gap:0.5rem;">
                <button onclick="window._dcInstall()" style="border-radius:0.5rem; background:#dc2626; padding:0.375rem 0.75rem; font-size:0.75rem; font-weight:500; color:#fff; border:none; cursor:pointer;">Install</button>
                <button onclick="window._dcDismiss()" style="border-radius:0.5rem; background:#334155; padding:0.375rem 0.75rem; font-size:0.75rem; font-weight:500; color:#cbd5e1; border:none; cursor:pointer;">Not now</button>
            </div>
            <div id="dc-ios-dismiss" style="display:none; margin-top:0.5rem;">
                <button onclick="window._dcDismiss()" style="font-size:0.75rem; font-weight:500; color:#94a3b8; background:none; border:none; cursor:pointer; text-decoration:underline;">Dismiss</button>
            </div>
        </div>
        <button onclick="window._dcDismiss()" style="color:#64748b; background:none; border:none; cursor:pointer; padding:0.25rem;">
            <x-icon name="x" style="width:1rem; height:1rem;" />
        </button>
    </div>
</div>
<script>
(function() {
    var KEY = 'dc_install_dismissed';
    var dismissed = localStorage.getItem(KEY);
    if (dismissed && (Date.now() - parseInt(dismissed, 10)) < 30 * 24 * 60 * 60 * 1000) return;
    if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) return;
    if (window.navigator && window.navigator.standalone === true) return;

    var el = document.getElementById('dc-install-prompt');
    var desc = document.getElementById('dc-install-desc');
    var btns = document.getElementById('dc-install-buttons');
    var iosDis = document.getElementById('dc-ios-dismiss');
    if (!el || !desc) return;

    var deferredPrompt = null;

    window._dcDismiss = function() {
        localStorage.setItem(KEY, Date.now().toString());
        el.style.opacity = '0';
        el.style.transform = 'translateY(1rem)';
        setTimeout(function() { el.style.display = 'none'; }, 250);
    };

    window._dcInstall = function() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function() { deferredPrompt = null; window._dcDismiss(); });
        }
    };

    function show() {
        el.style.display = '';
        el.style.opacity = '0';
        el.style.transform = 'translateY(1rem)';
        el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        setTimeout(function() { el.style.opacity = '1'; el.style.transform = 'translateY(0)'; }, 30);
    }

    var isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    if (isIos) {
        desc.textContent = 'Tap Share then Add to Home Screen for the best experience.';
        if (iosDis) iosDis.style.display = '';
        show();
    } else {
        desc.textContent = 'Get quick access to matches, live scores, and notifications.';
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            if (btns) { btns.style.display = 'flex'; }
            show();
        });
    }
})();
</script>
