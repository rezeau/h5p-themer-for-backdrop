/** Loopback-only, read-only disposable asset server. GPL-3.0-or-later. */
'use strict';
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const root = fs.realpathSync(path.resolve(__dirname, '../../artifacts/native-density-checkpoint1'));
const mime = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.svg': 'image/svg+xml', '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.png': 'image/png', '.gif': 'image/gif' };
http.createServer((req, res) => {
  if (!['GET', 'HEAD'].includes(req.method)) { res.writeHead(405).end(); return; }
  try {
    const url = new URL(req.url, 'http://127.0.0.1');
    const file = fs.realpathSync(path.join(root, decodeURIComponent(url.pathname === '/' ? '/index.html' : url.pathname)));
    if (!file.startsWith(root + path.sep) || !fs.statSync(file).isFile()) { res.writeHead(403).end(); return; }
    res.writeHead(200, { 'Content-Type': mime[path.extname(file)] || 'application/octet-stream', 'Cache-Control': 'no-store' });
    if (req.method === 'HEAD') res.end(); else fs.createReadStream(file).pipe(res);
  } catch { res.writeHead(404).end(); }
}).listen(8767, '127.0.0.1', () => console.log('Native Density fixture: http://127.0.0.1:8767/'));
