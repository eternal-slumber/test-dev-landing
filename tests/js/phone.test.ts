import assert from 'node:assert/strict';
import test from 'node:test';

import { formatPhoneInput } from '../../resources/js/lib/phone.ts';

test('formats Russian numbers as they are entered', () => {
    assert.equal(formatPhoneInput('9'), '+7 (9');
    assert.equal(formatPhoneInput('9991'), '+7 (999) 1');
    assert.equal(formatPhoneInput('79991234567'), '+7 (999) 123-45-67');
    assert.equal(formatPhoneInput('8 999 123 45 67'), '+7 (999) 123-45-67');
    assert.equal(formatPhoneInput('9991234567'), '+7 (999) 123-45-67');
    assert.equal(formatPhoneInput('+49 151 23456789'), '+49 151 23456789');
});
