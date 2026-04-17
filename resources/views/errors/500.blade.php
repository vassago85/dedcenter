<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('Something went wrong') }} — DeadCenter</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#08142b">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-app text-primary antialiased">
    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-md rounded-2xl border border-border bg-surface p-8 text-center shadow-xl">
            <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-red-500/10 text-red-400">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>
                </svg>
            </div>
            <p class="text-xs font-semibold uppercase tracking-wider text-muted">Error 500</p>
            <h1 class="mt-1 text-2xl font-bold text-primary">{{ __('Something went wrong') }}</h1>
            <p class="mt-3 text-sm text-secondary">
                {{ __('Our server hit a snag. The team has been notified. Please try again in a moment.') }}
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}"
                   class="inline-flex min-h-[44px] items-center rounded-lg border border-border bg-surface-2 px-4 text-sm font-semibold text-primary transition-colors hover:bg-surface-2/70 focus:outline-none focus:ring-2 focus:ring-accent">
                    Go back
                </a>
                <a href="{{ url('/') }}"
                   class="inline-flex min-h-[44px] items-center rounded-lg bg-accent px-4 text-sm font-semibold text-white transition-colors hover:bg-accent-hover focus:outline-none focus:ring-2 focus:ring-accent">
                    Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>
