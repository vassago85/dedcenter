<?php

if (! function_exists('domain_url')) {
    /**
     * Build a full URL for a given subdomain key (shooter, md, app).
     */
    function domain_url(string $domain, string $path = '/'): string
    {
        $host = config("domains.{$domain}");
        $scheme = request()->secure() ? 'https' : 'http';

        return rtrim("{$scheme}://{$host}", '/') . '/' . ltrim($path, '/');
    }
}

if (! function_exists('app_url')) {
    function app_url(string $path = '/'): string
    {
        return domain_url('app', $path);
    }
}

if (! function_exists('shooter_url')) {
    function shooter_url(string $path = '/'): string
    {
        return domain_url('shooter', $path);
    }
}

if (! function_exists('md_url')) {
    function md_url(string $path = '/'): string
    {
        return domain_url('md', $path);
    }
}

if (! function_exists('domain_context')) {
    function domain_context(): string
    {
        return request()->attributes->get('domain_context', 'shooter');
    }
}
