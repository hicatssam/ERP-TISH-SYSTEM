{{-- Pages without the full product catalog must NOT initialize cart-engine:
     that engine reconciles line items against the loaded catalog.
     This read-only helper shares exact branch-scoped cart/favorites counts. --}}
<script>
(() => {
    'use strict';
    const locationCode = @json($location->code);
    const cartKey = 'dahab_cart_' + locationCode;
    const favoriteKey = 'dahab_favorites_' + locationCode;

    function refreshNavBadges() {
        let cartCount = 0;
        let favoriteCount = 0;
        try {
            const cart = JSON.parse(localStorage.getItem(cartKey) || '{}');
            if (cart && typeof cart === 'object' && !Array.isArray(cart)) {
                cartCount = Object.values(cart).reduce((sum, item) => {
                    const quantity = Number(item && typeof item === 'object' ? item.quantity ?? item.qty : item);
                    return sum + (Number.isFinite(quantity) && quantity > 0 ? quantity : 0);
                }, 0);
            }
        } catch (error) { /* Safari privacy mode / invalid old cart: display zero. */ }

        try {
            const favorites = JSON.parse(localStorage.getItem(favoriteKey) || '[]');
            favoriteCount = Array.isArray(favorites) ? favorites.length : 0;
        } catch (error) { /* Keep the navigation usable when storage is blocked. */ }

        const cartBadge = document.getElementById('cartBadgeNav');
        const favoriteBadge = document.getElementById('favBadgeNav');
        if (cartBadge) cartBadge.textContent = String(Math.min(9999, cartCount));
        if (favoriteBadge) favoriteBadge.textContent = String(Math.min(9999, favoriteCount));
    }

    refreshNavBadges();
    window.addEventListener('pageshow', refreshNavBadges);
    window.addEventListener('storage', event => {
        if (event.key === null || event.key === cartKey || event.key === favoriteKey) refreshNavBadges();
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshNavBadges();
    });
})();
</script>
