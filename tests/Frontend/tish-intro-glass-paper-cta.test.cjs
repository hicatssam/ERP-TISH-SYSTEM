const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const view = fs.readFileSync(path.resolve(__dirname, '../../resources/views/customer-menu/show.blade.php'),'utf8');

test('intro removes the sound button and its handlers but still plays muted video', () => {
 assert.doesNotMatch(view, /introSoundToggle|syncSoundToggle|soundEnabledByUser/);
 assert.match(view, /reel\.muted = true/);
 assert.match(view, /reel\.play\(\)\.catch/);
 assert.match(view, /<video id="introReel"/);
});

test('order button is on a paper cutout anchored in booth glass above the slogan', () => {
 assert.match(view, /class="intro-enter intro-enter--paper" id="introEnter"/);
 assert.match(view, /#intro\.tish-reel-intro \.intro-box\{/);
 assert.match(view, /top:var\(--intro-order-y\)/);
 assert.match(view, /transform:translate\(-50%,-50%\)/);
 assert.match(view, /clip-path:polygon\(/);
 assert.match(view, /fittedWidth \* \.66/);
 assert.match(view, /fittedHeight \* \.59/);
});

test('entry action, timed reveal, responsive motion and reduced motion are preserved', () => {
 assert.match(view, /enter\.addEventListener\('click'/);
 assert.match(view, /hideIntro\(\)/);
 assert.match(view, /const orderButtonCue = 6\.2/);
 assert.match(view, /@keyframes tish-paper-cta-appear/);
 assert.match(view, /@keyframes tish-paper-cta-breathe/);
 assert.match(view, /@media\(max-width:420px\)/);
 assert.match(view, /@media\(prefers-reduced-motion:reduce\)/);
 assert.match(view, /\.intro-enter\[hidden\]\{display:none!important\}/);
});
