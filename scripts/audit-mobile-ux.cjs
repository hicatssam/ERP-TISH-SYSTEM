#!/usr/bin/env node
'use strict';

/**
 * Read-only mobile UX inventory. It scans every Blade view, not just the
 * dashboard. Reported risks are candidates for visual review, not proof of
 * a defect: inline components and scoped CSS can resolve a flagged pattern.
 *
 * Usage:
 *   node scripts/audit-mobile-ux.cjs
 *   node scripts/audit-mobile-ux.cjs --json > mobile-ux-audit.json
 */
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const viewsRoot = path.join(root, 'resources/views');

function listBlades(dir) {
    return fs.readdirSync(dir, {withFileTypes: true}).flatMap(item => {
        const filename = path.join(dir, item.name);
        if (item.isDirectory()) return listBlades(filename);
        return item.isFile() && filename.endsWith('.blade.php') ? [filename] : [];
    });
}

function inspectBlade(filename) {
    const source = fs.readFileSync(filename, 'utf8');
    const relative = path.relative(root, filename).replace(/\\/g, '/');
    const moduleName = relative.split('/')[2];
    const nativePrompts = [...source.matchAll(/(?:\bwindow\.)?\b(?:confirm|alert|prompt)\s*\(/g)];
    const tables = [...source.matchAll(/<table\b/gi)];
    const scrollWrappers = [...source.matchAll(/table-wrap|table-responsive|overflow-x\s*:\s*auto|overflow-x-auto/gi)];
    const dialogs = [...source.matchAll(/<dialog\b|role\s*=\s*["']dialog["']|class\s*=\s*["'][^"']*(?:modal|popup)/gi)];
    const pixelWidths = [...source.matchAll(/(?:min-)?width\s*:\s*(?:[4-9]\d\d|[1-9]\d{3,})px/gi)];
    const fixed = [...source.matchAll(/position\s*:\s*fixed/gi)];
    const media = [...source.matchAll(/@media\s*\(/gi)];

    const findings = [];
    if (nativePrompts.length) findings.push('browser-native-prompt');
    if (tables.length && !scrollWrappers.length) findings.push('table-without-local-scroll-wrapper');
    if (pixelWidths.length && !media.length) findings.push('fixed-wide-element-without-inline-breakpoint');
    if (dialogs.length && !media.length) findings.push('popup-without-inline-breakpoint');
    if (fixed.length && !media.length) findings.push('fixed-overlay-without-inline-breakpoint');

    return {
        page: relative,
        module: moduleName,
        lines: source.split('\n').length,
        tables: tables.length,
        scrollWrappers: scrollWrappers.length,
        popups: dialogs.length,
        browserPrompts: nativePrompts.length,
        pixelWidths: pixelWidths.length,
        mediaQueries: media.length,
        findings,
    };
}

function makeReport() {
    const pages = listBlades(viewsRoot).map(inspectBlade);
    const counts = {};
    const findings = {};
    for (const page of pages) {
        counts[page.module] = (counts[page.module] || 0) + 1;
        for (const key of page.findings) findings[key] = (findings[key] || 0) + 1;
    }

    return {
        checkedBlades: pages.length,
        modules: counts,
        findings,
        reviewQueue: pages.filter(page => page.findings.length)
            .sort((a, b) => b.findings.length - a.findings.length
                || b.popups - a.popups || a.page.localeCompare(b.page)),
        pages,
        limitation: 'Static risk scan only. Authenticated screens require viewport/browser/role testing to verify UX.',
    };
}

if (require.main === module) {
    const report = makeReport();
    if (process.argv.includes('--json')) {
        process.stdout.write(JSON.stringify(report, null, 2) + '\n');
    } else {
        console.log('DAHAB Mobile UI/UX static audit');
        console.log('Blade templates:', report.checkedBlades);
        console.log('Sections:', JSON.stringify(report.modules));
        console.log('Risk categories:', JSON.stringify(report.findings));
        console.log('Highest-priority review candidates:');
        for (const page of report.reviewQueue.slice(0, 45)) {
            console.log('- ' + page.page + ' => ' + page.findings.join(', '));
        }
        console.log('NOTE:', report.limitation);
    }
}

module.exports = {inspectBlade, makeReport};
