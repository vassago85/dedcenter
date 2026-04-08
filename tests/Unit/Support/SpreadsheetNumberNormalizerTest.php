<?php

use App\Support\SpreadsheetNumberNormalizer;

describe('SpreadsheetNumberNormalizer', function () {
    it('expands scientific notation for SA-style ids', function () {
        expect(SpreadsheetNumberNormalizer::normalizeIdDigits('8.43E+08'))->toBe('843000000');
        expect(SpreadsheetNumberNormalizer::normalizeIdDigits('6009115009081'))->toBe('6009115009081');
    });

    it('strips non-digits from id cells', function () {
        expect(SpreadsheetNumberNormalizer::normalizeIdDigits('760 425 5013 080'))->toBe('7604255013080');
    });

    it('normalizes phone cells including scientific', function () {
        expect(SpreadsheetNumberNormalizer::normalizePhoneDigits('823177882'))->toBe('823177882');
        expect(SpreadsheetNumberNormalizer::normalizePhoneDigits('082 653 7928'))->toBe('0826537928');
    });
});
