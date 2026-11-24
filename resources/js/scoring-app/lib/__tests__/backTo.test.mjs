import { test } from 'node:test';
import assert from 'node:assert/strict';

import { historyBackAction } from '../backTo.js';

test('pops when the parent page is already behind this one', () => {
    assert.equal(historyBackAction('/score/12', '/score/12'), 'back');
});

test('pops when the parent only differs by a query string', () => {
    assert.equal(historyBackAction('/score/matches?from=home', '/score/matches'), 'back');
});

test('replaces when this page was opened directly', () => {
    assert.equal(historyBackAction(undefined, '/score/matches'), 'replace');
    assert.equal(historyBackAction('', '/score/12'), 'replace');
});

test('replaces when the page behind is a different screen', () => {
    assert.equal(historyBackAction('/app-login', '/score/matches'), 'replace');
    assert.equal(historyBackAction('/score/12', '/score/12/matrix'), 'replace');
});
