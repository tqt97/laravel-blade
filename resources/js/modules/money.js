const zeroDecimalCurrencies = new Set(['JPY', 'VND']);

const getLocale = () => typeof document !== 'undefined' && document.documentElement.lang === 'vi'
    ? 'vi-VN'
    : 'en-US';

export const formatMoney = (minorUnits, currency) => {
    const normalizedCurrency = String(currency).toUpperCase();
    const fractionDigits = zeroDecimalCurrencies.has(normalizedCurrency) ? 0 : 2;
    const amount = Number(minorUnits) / (10 ** fractionDigits);

    return `${new Intl.NumberFormat(getLocale(), {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(amount)} ${normalizedCurrency}`.trim();
};
