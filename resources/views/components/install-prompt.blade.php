<div
    x-data="{
        show: false,
        deferredPrompt: null,
        isIos: false,
        dismissed: localStorage.getItem('dc_install_dismissed'),
        init() {
            if (this.dismissed && (Date.now() - parseInt(this.dismissed)) < 30 * 24 * 60 * 60 * 1000) return;
            if (window.matchMedia('(display-mode: standalone)').matches) return;

            this.isIos = /iPad|iPhone|iPod/.test(navigator.userAgent);
            if (this.isIos) { this.show = true; return; }

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                this.deferredPrompt = e;
                this.show = true;
            });
        },
        async install() {
            if (this.deferredPrompt) {
                this.deferredPrompt.prompt();
                const { outcome } = await this.deferredPrompt.userChoice;
                this.deferredPrompt = null;
                this.show = false;
            }
        },
        dismiss() {
            this.show = false;
            localStorage.setItem('dc_install_dismissed', Date.now().toString());
        }
    }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4"
    x-cloak
    class="fixed bottom-4 left-4 right-4 z-50 mx-auto max-w-md rounded-2xl border border-white/10 bg-slate-800/95 p-4 shadow-2xl backdrop-blur-lg sm:left-auto sm:right-6 sm:bottom-6"
>
    <div class="flex items-start gap-3">
        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-red-600/20">
            <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3" />
            </svg>
        </div>
        <div class="flex-1">
            <p class="text-sm font-semibold text-white">Add DeadCenter to Home Screen</p>
            <p class="mt-0.5 text-xs text-slate-400" x-show="!isIos">Get quick access to matches, live scores, and notifications.</p>
            <p class="mt-0.5 text-xs text-slate-400" x-show="isIos">Tap <strong class="text-slate-300">Share</strong> then <strong class="text-slate-300">Add to Home Screen</strong> for the best experience.</p>
            <div class="mt-3 flex gap-2" x-show="!isIos">
                <button @click="install" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-red-700">Install</button>
                <button @click="dismiss" class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-medium text-slate-300 transition-colors hover:bg-slate-600">Not now</button>
            </div>
            <div class="mt-2" x-show="isIos">
                <button @click="dismiss" class="text-xs font-medium text-slate-400 hover:text-white transition-colors">Dismiss</button>
            </div>
        </div>
        <button @click="dismiss" class="text-slate-500 hover:text-white transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>
