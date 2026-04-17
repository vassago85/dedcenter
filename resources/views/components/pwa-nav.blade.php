{{-- Floating navigation bar for PWA standalone mode (no browser chrome) --}}
<div id="pwa-nav"
     x-data="{
         canBack: false,
         init() {
             this.canBack = window.history.length > 1;
             window.addEventListener('popstate', () => {
                 this.canBack = window.history.length > 1;
             });
         },
         back()    { window.history.back(); },
         forward() { window.history.forward(); },
         home()    { window.location.href = '/'; },
     }"
     class="pwa-nav">
    <button @click="back()" :disabled="!canBack" :class="!canBack && 'opacity-30 pointer-events-none'"
            class="pwa-nav-btn" aria-label="Go back">
        <x-icon name="chevron-left" class="h-5 w-5" />
    </button>
    <button @click="home()" class="pwa-nav-btn" aria-label="Home">
        <x-icon name="house" class="h-5 w-5" />
    </button>
    <button @click="forward()" class="pwa-nav-btn" aria-label="Go forward">
        <x-icon name="chevron-right" class="h-5 w-5" />
    </button>
</div>
