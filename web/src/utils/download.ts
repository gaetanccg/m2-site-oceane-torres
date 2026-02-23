export function isInAppBrowser(): boolean {
    const ua = navigator.userAgent || ''
    return /FBAN|FBAV|Instagram/i.test(ua)
}
