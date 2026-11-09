// Builds the Chahriyti Demo plugin (served at /demo) from the landing page.
// The landing's index.html stays the single source: styles, the live demo,
// the order form and all scripts are copied from it, so rerun this after
// every change to it.
//
//   node build-demo.mjs && zip -r chahriyti-demo-plugin.zip chahriyti-demo

import { readFileSync, writeFileSync, copyFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const src = join(root, 'chahriyti');
const out = join(root, 'chahriyti-demo');
const html = readFileSync(join(src, 'index.html'), 'utf8');

function between(start, end) {
  const i = html.indexOf(start);
  const j = html.indexOf(end, i + start.length);
  if (i < 0 || j < 0) throw new Error(`Marker not found: ${i < 0 ? start : end}`);
  return html.slice(i, j);
}

const head = html.slice(0, html.indexOf('</head>'))
  .replace(/<title>[\s\S]*?<\/title>/, '<title>جرّب تطبيق شهريتي — نسخة تجريبية</title>');

const styles = `
  <style>
    .demo-page .demo-section .section { padding-top: 110px; }
    .demo-enter {
      display: inline-flex; align-items: center; gap: 10px;
      font-family: 'Cairo', sans-serif; font-size: 17px; font-weight: 800; color: #fff; cursor: pointer;
      background: linear-gradient(135deg, var(--teal), var(--teal-light)); border: 0; border-radius: 50px;
      padding: 14px 30px; box-shadow: 0 10px 30px var(--teal-glow);
    }
    .demo-enter:hover { transform: translateY(-2px); }
    .demo-enter-note { font-size: 14px; color: var(--text-muted); margin-top: -10px; }
  </style>
</head>`;

const nav = `
<body class="demo-page">

  <!-- ─── NAV ─── -->
  <nav id="nav">
    <a href="#demo" class="nav-logo">
      <img src="../CHAHRIYTI png.png" alt="شهريتي">
      <span>شهريتي</span>
    </a>
    <ul class="nav-links">
      <li><a href="#demo">جرّب التطبيق</a></li>
      <li><a href="#order">اطلب الآن</a></li>
    </ul>
    <a href="#order" class="nav-cta">اطلب نسختك</a>
    <button class="nav-menu-btn" onclick="document.getElementById('order').scrollIntoView({behavior:'smooth'})">☰</button>
  </nav>

`;

// The demo section, plus the "enter the app" button and its exit pill
let demo = between('<!-- ─── LIVE DEMO ─── -->', '<!-- ─── QUOTE ─── -->');
const replaceOnce = (s, from, to) => {
  if (!s.includes(from)) throw new Error(`Not found in demo section: ${from}`);
  return s.replace(from, to);
};
demo = replaceOnce(demo, '<div class="dm-stage" id="dmStage">',
  '<div class="dm-stage" id="dmStage">\n          <button type="button" class="dm-exit" id="dmExit" aria-label="الخروج من التطبيق">✕ خروج</button>');
demo = replaceOnce(demo, '<div class="demo-wrap">',
  `<div class="demo-wrap">
        <button type="button" class="demo-enter" data-demo-enter>📱 ادخل للتطبيق</button>
        <p class="demo-enter-note">يفتح التطبيق على كامل الشاشة كأنك راك فيه</p>`);

const rest = html.slice(html.indexOf('<!-- ─── ORDER FORM ─── -->'));

mkdirSync(out, { recursive: true });
writeFileSync(join(out, 'demo.html'), head + styles + nav + '  ' + demo + rest);

for (const f of ['algeria_cities.csv', 'Tarifs_Wilayas_Domicile_Bureau.json', 'CHAHRIYTI png.png', 'favicon.png', 'apple-touch-icon.png']) {
  copyFileSync(join(src, f), join(out, f));
}
console.log('Built chahriyti-demo/demo.html');
