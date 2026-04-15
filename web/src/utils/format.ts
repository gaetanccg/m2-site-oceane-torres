const priceFormatter = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
})

export function formatPrice(price: number): string {
    return priceFormatter.format(price)
}
