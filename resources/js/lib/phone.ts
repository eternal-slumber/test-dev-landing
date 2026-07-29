export function formatPhoneInput(value: string): string {
    const digits = value.replace(/\D/g, '');

    if (digits.length === 0) {
        return value;
    }

    if (value.startsWith('+') && !value.startsWith('+7')) {
        return value;
    }

    const hasRussianPrefix = digits.startsWith('7') || digits.startsWith('8');
    const nationalNumber = (hasRussianPrefix ? digits.slice(1) : digits).slice(
        0,
        10,
    );

    if (nationalNumber.length === 0) {
        return '+7';
    }

    let formatted = `+7 (${nationalNumber.slice(0, 3)}`;

    if (nationalNumber.length >= 3) {
        formatted += ')';
    }

    if (nationalNumber.length > 3) {
        formatted += ` ${nationalNumber.slice(3, 6)}`;
    }

    if (nationalNumber.length > 6) {
        formatted += `-${nationalNumber.slice(6, 8)}`;
    }

    if (nationalNumber.length > 8) {
        formatted += `-${nationalNumber.slice(8, 10)}`;
    }

    return formatted;
}
