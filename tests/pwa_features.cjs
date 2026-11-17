/* Deterministic worker/cache tests. No server or database mutations. */
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
let checks = 0;
function check(condition, label) { assert.ok(condition, label); checks++; console.log(`PASS: ${label}`); }
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'public/manifest.webmanifest'), 'utf8'));
check(manifest.display === 'standalone' && manifest.start_url === './' && manifest.scope === './', 'manifest installs within the deployment scope');
check(!('orientation' in manifest), 'portrait and landscape are supported');
for (const icon of manifest.icons) {
  const bytes=fs.readFileSync(path.join(root,'public',icon.src));
  const size=Number(icon.sizes.split('x')[0]);
  check(bytes.readUInt32BE(16)===size && bytes.readUInt32BE(20)===size, `manifest icon dimensions match ${icon.src}`);
}
const worker=fs.readFileSync(path.join(root,'public/service-worker.js'),'utf8');
async function workerTests(scope) {
  const listeners={}, stored=new Map(), calls=[];
  let offline=false, claimed=false;
  const cache={addAll:async urls=>{for(const url of urls)stored.set(url,new Response(url.endsWith('offline.html')?'<html><head></head><body>Offline</body></html>':'static'));},put:async(request,response)=>stored.set(typeof request==='string'?request:request.url,response),match:async request=>stored.get(typeof request==='string'?request:request.url)?.clone()};
  const context={URL,Response,console,self:{registration:{scope},clients:{claim:async()=>{claimed=true;}},addEventListener:(name,handler)=>{listeners[name]=handler;},skipWaiting:()=>{}},caches:{open:async()=>cache,match:cache.match,keys:async()=>['rideutem-public-v1','unrelated-app'],delete:async key=>calls.push(['delete',key])},fetch:async request=>{calls.push(['fetch',request.url]);if(offline)throw Error('offline');const response=new Response('online');Object.defineProperty(response,'type',{value:'basic'});return response;}};
  vm.runInNewContext(worker,context);
  let pending;listeners.install({waitUntil:p=>pending=p});await pending;
  check([...stored.keys()].every(url=>url.startsWith(scope)), 'precache respects '+scope);
  check([...stored.keys()].every(url=>!/(login|dashboard|findride|research|api\/)/.test(url)), 'precache contains no private application pages');
  listeners.activate({waitUntil:p=>pending=p});await pending;
  check(claimed && !calls.some(call=>call[0]==='delete'&&call[1]==='unrelated-app'),'worker claims scope without removing unrelated app caches');
  async function dispatch(method,relative,mode='cors') {
    let result;
    listeners.fetch({request:{url:new URL(relative,scope).href,method,mode},respondWith:p=>result=p});
    return result===undefined ? null : await result;
  }
  check(await dispatch('POST','findride.php')===null,'POST requests are never cached or replayed');
  check(await dispatch('GET','api/locations/search?q=Campus')===null,'location and API responses bypass the cache');
  check(await dispatch('GET','research/export/survey')===null,'research exports bypass the cache');
  check(await dispatch('GET','https://maps.googleapis.com/example')===null,'third-party map requests bypass the cache');
  await dispatch('GET','dashboard.php','navigate');
  check(!stored.has(new URL('dashboard.php',scope).href),'successful private navigation is not cached');
  const css=await dispatch('GET','css/device.css');
  check(await css.text()==='online'&&stored.has(new URL('css/device.css',scope).href),'public CSS can be cached');
  offline=true;
  check(await (await dispatch('GET','css/device.css')).text()==='online','public CSS is available if its network fetch fails');
  const fallback=await dispatch('GET','driver/route/42','navigate');
  check(fallback.status===503 && (await fallback.text()).includes(`<base href="${scope}">`),'offline fallback uses the application base even on nested routes');
}
(async()=>{
  await workerTests('https://example.test/workshop/');
  await workerTests('https://example.test/');
  check(fs.readFileSync(path.join(root,'public/js/device.js'),'utf8').includes("form.getAttribute('aria-busy')==='true'"),'duplicate form submission is guarded');
  console.log(`${checks} PWA checks passed; no database writes.`);
})().catch(error=>{console.error(error);process.exit(1);});
