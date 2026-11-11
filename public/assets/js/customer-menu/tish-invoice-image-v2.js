/* PNG export for public TISH invoices: paper-collage art, with live invoice data.
 * No screenshot library, remote image API, or persisted copy is required.
 */
(function () {
    'use strict';

    const WIDTH = 1060;
    const INK = '#241f1b';
    const RED = '#a91c13';
    const SOFT = '#756052';
    const FAMILY = 'Cairo, Tajawal, Arial, sans-serif';

    function fetchArt(url) {
        if (!url || typeof url !== 'string') return Promise.resolve(null);
        return new Promise(function (resolve) {
            const image = new Image();
            image.crossOrigin = 'anonymous';
            image.onload = function () { resolve(image); };
            image.onerror = function () { resolve(null); };
            image.src = url;
        });
    }

    function drawText(ctx, value, x, y, size, weight, color, align, direction) {
        ctx.save();
        ctx.direction = direction || 'rtl';
        ctx.textAlign = align || 'right';
        ctx.textBaseline = 'alphabetic';
        ctx.font = String(weight || 700) + ' ' + size + 'px ' + FAMILY;
        ctx.fillStyle = color || INK;
        ctx.fillText(String(value == null ? '' : value), x, y);
        ctx.restore();
    }

    function words(ctx, text, maxWidth, fontSize, weight) {
        const raw = String(text || '').trim();
        if (!raw) return [''];
        ctx.font = String(weight || 800) + ' ' + fontSize + 'px ' + FAMILY;
        const lines = [];
        let line = '';
        for (const word of raw.split(/\s+/u)) {
            const candidate = line ? line + ' ' + word : word;
            if (line && ctx.measureText(candidate).width > maxWidth) {
                lines.push(line);
                line = word;
            } else {
                line = candidate;
            }
        }
        if (line) lines.push(line);
        return lines;
    }

    function paperPath(ctx, left, top, right, bottom) {
        ctx.beginPath();
        ctx.moveTo(left + 16, top + 6);
        for (let x = left + 16, n = 0; x < right - 14; x += 26, n++) {
            ctx.lineTo(Math.min(right - 14, x), top + (n % 3 === 0 ? 11 : (n % 3 === 1 ? 4 : 8)));
        }
        ctx.lineTo(right - 5, top + 12);
        for (let y = top + 12, n = 0; y < bottom - 15; y += 31, n++) {
            ctx.lineTo(right - (n % 2 ? 3 : 10), y);
        }
        ctx.lineTo(right - 15, bottom - 8);
        for (let x = right - 14, n = 0; x > left + 13; x -= 27, n++) {
            ctx.lineTo(x, bottom - (n % 3 === 0 ? 3 : (n % 3 === 1 ? 12 : 6)));
        }
        ctx.lineTo(left + 7, bottom - 9);
        for (let y = bottom - 12, n = 0; y > top + 13; y -= 29, n++) {
            ctx.lineTo(left + (n % 2 ? 3 : 11), y);
        }
        ctx.closePath();
    }

    function texturePattern(ctx, image) {
        if (!image) return null;
        const tile = document.createElement('canvas');
        tile.width = 560;
        tile.height = 560;
        const inner = tile.getContext('2d');
        if (!inner) return null;
        inner.drawImage(image, 0, 0, tile.width, tile.height);
        return ctx.createPattern(tile, 'repeat');
    }

    function ticket(ctx, image, x, y, w, h) {
        if (image) {
            ctx.save();
            ctx.shadowColor = '#5b301d55';
            ctx.shadowBlur = 13;
            ctx.shadowOffsetY = 6;
            ctx.drawImage(image, x, y, w, h);
            ctx.restore();
        } else {
            ctx.fillStyle = RED;
            ctx.fillRect(x + 8, y + 6, w - 16, h - 12);
        }
    }

    function normalizeQuantity(value) {
        const n = Number(value || 0);
        return Number.isFinite(n) ? n.toFixed(3) : String(value || '0');
    }

    function safeMoney(value) {
        // Values arrive pre-formatted by Laravel. Do not recompute invoice totals.
        return String(value == null ? '0.00' : value);
    }

    async function create(receipt, assets) {
        if (!receipt || !Array.isArray(receipt.items)) throw new Error('Invoice items missing');
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error('Canvas 2D unavailable');

        if (document.fonts && document.fonts.ready) {
            await document.fonts.ready;
        }

        const rows = receipt.items.map(function (item) {
            const lines = words(ctx, item.name, 495, 30, 900);
            return { item: item, lines: lines, height: Math.max(84, lines.length * 44 + 34) };
        });
        const rowsHeight = rows.reduce(function (sum, row) { return sum + row.height; }, 0);
        const summaryTop = 435 + rowsHeight + 29;
        const height = Math.max(1005, summaryTop + 335);
        const scale = 2;

        canvas.width = WIDTH * scale;
        canvas.height = height * scale;
        ctx.scale(scale, scale);

        const art = await Promise.all([
            fetchArt(assets.background),
            fetchArt(assets.paper),
            fetchArt(assets.redTop),
            fetchArt(assets.redPrice),
            fetchArt(assets.lightning),
            fetchArt(assets.halftone),
            fetchArt(assets.logo),
            fetchArt(assets.logoFallback),
            fetchArt(assets.logoDisc),
        ]);
        const paperTexture = texturePattern(ctx, art[0]);

        // Torn red backing with a softly shadowed parchment sheet.
        ctx.fillStyle = '#9d1711';
        ctx.fillRect(0, 0, WIDTH, height);
        ctx.fillStyle = '#f1d7bc';
        ctx.fillRect(0, 59, WIDTH, height - 59);
        ctx.save();
        ctx.shadowColor = '#50301f50';
        ctx.shadowBlur = 22;
        ctx.shadowOffsetY = 10;
        paperPath(ctx, 18, 29, WIDTH - 18, height - 22);
        ctx.fillStyle = '#f6e2ca';
        ctx.fill();
        ctx.restore();

        ctx.save();
        paperPath(ctx, 18, 29, WIDTH - 18, height - 22);
        ctx.clip();
        if (paperTexture) {
            ctx.globalAlpha = .7;
            ctx.fillStyle = paperTexture;
            ctx.fillRect(12, 20, WIDTH - 24, height);
            ctx.globalAlpha = 1;
        }
        const glaze = ctx.createLinearGradient(0, 0, WIDTH, 0);
        glaze.addColorStop(0, '#fff2dd22');
        glaze.addColorStop(.5, '#fff3e516');
        glaze.addColorStop(1, '#b9885d12');
        ctx.fillStyle = glaze;
        ctx.fillRect(18, 29, WIDTH - 36, height - 51);
        ctx.restore();

        if (art[2]) ctx.drawImage(art[2], 0, 0, WIDTH, 61);
        if (art[5]) ctx.drawImage(art[5], 21, 80, 150, 180);

        // Use the original branded torn-paper emblem. It already contains
        // the TISH artwork and irregular paper edge, so no white square or
        // synthetic circle should ever appear in the exported invoice.
        // The previously configured logo remains a fallback if this asset
        // cannot be loaded.
        const logoDisc = art[8];
        const logo = logoDisc || art[7] || art[6];
        if (logo) {
            const badgeX = WIDTH - 221;
            const badgeY = 72;
            const badgeSize = 166;
            ctx.save();
            ctx.shadowColor = '#34241b55';
            ctx.shadowBlur = 10;
            ctx.shadowOffsetY = 6;
            ctx.drawImage(logo, badgeX, badgeY, badgeSize, badgeSize);
            ctx.restore();
        }
        drawText(ctx, receipt.brand || 'Tish', WIDTH - 244, 129, 42, 900, RED, 'right', 'rtl');
        drawText(ctx, receipt.branch || '', WIDTH - 244, 178, 30, 800, INK, 'right', 'rtl');

        // Invoice number on its own irregular strip.
        if (art[1]) ctx.drawImage(art[1], 210, 245, 710, 88);
        drawText(ctx, 'فاتورة رقم ' + String(receipt.number || ''), WIDTH / 2 + 10, 309, 38, 900, INK, 'center', 'rtl');
        if (art[4]) ctx.drawImage(art[4], 151, 242, 72, 75);
        drawText(ctx, String(receipt.number || '') + '  |  ' + String(receipt.date || ''), WIDTH / 2, 369, 25, 600, '#5d5147', 'center', 'ltr');

        ctx.strokeStyle = '#b7a38e';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(91, 397);
        ctx.lineTo(WIDTH - 91, 397);
        ctx.stroke();

        let y = 443;
        for (const row of rows) {
            for (let i = 0; i < row.lines.length; i++) {
                drawText(ctx, row.lines[i], WIDTH - 110, y + i * 44, 30, 900, INK, 'right', 'rtl');
            }
            const value = String(receipt.currency || '₪') + ' ' +
                safeMoney(row.item.unit_price == null ? row.item.total : row.item.unit_price) +
                ' × ' + normalizeQuantity(row.item.quantity);
            drawText(ctx, value, 109, y, 27, 700, INK, 'left', 'ltr');

            y += row.height;
            ctx.strokeStyle = '#b7a38e';
            ctx.lineWidth = 1.6;
            ctx.beginPath();
            ctx.moveTo(91, y - 27);
            ctx.lineTo(WIDTH - 91, y - 27);
            ctx.stroke();
        }

        const money = function (v) {
            return String(receipt.currency || '₪') + ' ' + safeMoney(v);
        };
        const summaryRows = [
            ['المجموع الفرعي:', receipt.subtotal],
            ['الخصم:', receipt.discount],
            ['الضريبة:', receipt.tax],
        ];
        y = summaryTop;
        for (const entry of summaryRows) {
            drawText(ctx, entry[0], WIDTH - 116, y, 32, 900, INK, 'right', 'rtl');
            drawText(ctx, money(entry[1]), WIDTH - 600, y, 29, 700, INK, 'left', 'ltr');
            y += 56;
        }

        // Grand total printed on a torn red paper ticket.
        const grandY = y + 13;
        ticket(ctx, art[3], 426, grandY - 47, 560, 105);
        drawText(ctx, 'الإجمالي:', 937, grandY + 22, 39, 900, '#fff5e5', 'right', 'rtl');
        drawText(ctx, money(receipt.total), 481, grandY + 20, 36, 900, '#fff5e5', 'left', 'ltr');

        const footY = Math.min(height - 66, grandY + 128);
        drawText(ctx, 'شكراً لاختياركم ' + String(receipt.brand || 'Tish'), WIDTH - 113, footY, 22, 600, SOFT, 'right', 'rtl');

        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (blob) resolve(blob);
                else reject(new Error('Could not encode TISH invoice PNG'));
            }, 'image/png');
        });
    }

    window.TishPaperInvoiceImage = { create: create };
})();
