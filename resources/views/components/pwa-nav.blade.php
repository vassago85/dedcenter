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
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
        </svg>
    </button>
    <button @click="home()" class="pwa-nav-btn" aria-label="Home">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="m2.25 12 8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
        </svg>
    </button>
    <button @click="forward()" class="pwa-nav-btn" aria-label="Go forward">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
    </button>
</div>
