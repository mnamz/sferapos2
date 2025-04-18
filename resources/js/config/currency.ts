export interface Currency {
    code: string;
    symbol: string;
    name: string;
}

export const currencies: Currency[] = [
    {
        code: 'MYR',
        symbol: 'RM',
        name: 'Malaysian Ringgit'
    },
    {
        code: 'SGD',
        symbol: '$',
        name: 'Singapore Dollar'
    },
    {
        code: 'USD',
        symbol: '$',
        name: 'US Dollar'
    }
];

export const defaultCurrency: Currency = currencies[0];

export function formatCurrency(amount: number, currencyCode: string = defaultCurrency.code): string {
    const currency = currencies.find(c => c.code === currencyCode) || defaultCurrency;
    return `${currency.symbol} ${amount.toFixed(2)}`;
} 