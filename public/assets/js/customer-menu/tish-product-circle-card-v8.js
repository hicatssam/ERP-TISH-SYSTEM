/* Shared public TISH circular-paper card.
 * UI only: product, pricing, availability, favorites and cart remain owned by
 * the existing Laravel CustomerMenu engine. No other page is affected.
 */
(function (window) {
    'use strict';

    function render(product, options) {
        const cm = window.CustomerMenu;
        if (!cm || !product) return '';
        const opts = options || {};
        const url = typeof opts.productUrl === 'function' ? opts.productUrl(product.id) : '#';
        const id = cm.esc(product.id);
        const name = cm.esc(product.name);
        const category = cm.esc(product.category_name || '');
        const liked = cm.isFavorite(product.id);
        const popular = !!opts.isPopular && product.available;
        const available = !!product.available;
        const label = !available ? 'غير متوفر' : product.requiresChoices ? 'اختر الخيارات' : 'إضافة للسلة';
        const icon = product.requiresChoices ? 'fa-sliders' : 'fa-cart-plus';
        const favoriteLabel = liked ? 'إزالة من المفضلة' : 'إضافة للمفضلة';
        const badge = !available
            ? '<span class="tish-circle-badge tish-circle-badge--soldout">غير متوفر</span>'
            : popular
                ? '<span class="tish-circle-badge tish-circle-badge--popular"><i class="fa-solid fa-fire" aria-hidden="true"></i><span>الأكثر طلبًا</span></span>'
                : '<span class="tish-circle-badge tish-circle-badge--available"><span class="tish-circle-availability-dot" aria-hidden="true"></span>متوفر</span>';

        return '<article class="tish-circle-card" data-id="' + id + '" dir="rtl">' +
            '<span class="tish-circle-paper" aria-hidden="true"></span>' +
            '<button class="tish-circle-favorite' + (liked ? ' active' : '') + '" type="button" data-fav="' + id +
                '" aria-label="' + favoriteLabel + ' ' + name + '" aria-pressed="' + String(liked) + '">' +
                '<i class="' + (liked ? 'fa-solid' : 'fa-regular') + ' fa-heart" aria-hidden="true"></i></button>' +
            badge +
            '<a class="tish-circle-image" href="' + cm.esc(url) + '" aria-label="عرض ' + name + '">' +
                cm.image(product, 'tish-circle-photo') + '</a>' +
            '<a class="tish-circle-info" href="' + cm.esc(url) + '">' +
                '<h3 class="tish-circle-name" title="' + name + '">' + name + '</h3>' +
                (category ? '<p class="tish-circle-category" title="' + category + '">' + category + '</p>' : '') +
            '</a>' +
            '<span class="tish-circle-price" dir="ltr">' + cm.esc(cm.money(product.price)) + '</span>' +
            '<button class="tish-circle-add" type="button" data-add="' + id + '"' +
                (available ? '' : ' disabled') + ' aria-label="' + cm.esc(label + ': ' + product.name) + '">' +
                '<i class="fa-solid ' + icon + '" aria-hidden="true"></i>' +
                '<span class="tish-circle-add-label">' + cm.esc(label) + '</span></button>' +
        '</article>';
    }

    /* Prevent rapid repeat taps while the existing cart handler runs;
     * retain the engine toast and show a short success state on this button.
     */
    function addFromButton(button, product, productUrl) {
        const cm = window.CustomerMenu;
        if (!button || button.disabled || button.dataset.busy === '1' || !product || !product.available) return false;
        if (product.requiresChoices) {
            window.location.href = productUrl;
            return false;
        }
        button.dataset.busy = '1';
        button.disabled = true;
        let success = false;
        try {
            success = cm.addToCart(product.id, 1) === true;
        } finally {
            delete button.dataset.busy;
        }
        if (!success) {
            button.disabled = false;
            return false;
        }
        const label = button.querySelector('.tish-circle-add-label');
        const icon = button.querySelector('i');
        button.classList.add('is-added');
        if (label) label.textContent = 'تمت الإضافة';
        if (icon) icon.className = 'fa-solid fa-check';
        window.setTimeout(function () {
            if (!button.isConnected) return;
            button.classList.remove('is-added');
            button.disabled = false;
            if (label) label.textContent = 'إضافة للسلة';
            if (icon) icon.className = 'fa-solid fa-cart-plus';
        }, 850);
        return true;
    }

    window.TishProductCircleCard = { render: render, addFromButton: addFromButton };
})(window);