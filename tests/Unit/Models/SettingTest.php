<?php

use App\Models\Setting;

it('can get and set values', function () {
    Setting::set('test_key', 'test_value');

    expect(Setting::get('test_key'))->toBe('test_value');
});

it('returns default when key does not exist', function () {
    expect(Setting::get('nonexistent', 'fallback'))->toBe('fallback');
});

it('returns null when key does not exist and no default', function () {
    expect(Setting::get('nonexistent'))->toBeNull();
});

it('overwrites existing value', function () {
    Setting::set('update_key', 'first');
    Setting::set('update_key', 'second');

    expect(Setting::get('update_key'))->toBe('second');
    expect(Setting::where('key', 'update_key')->count())->toBe(1);
});
