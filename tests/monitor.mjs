// Run the emitted JS through real event listeners with a controlled DOM/clock/storage.
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
const php = process.env.TEST_PHP || 'php';
const emitter = fileURLToPath(new URL('./emit_script.php', import.meta.url));

function monitor({threshold = 5, warnings = 1, action = 'review', max = 3, questions = [
    {id: 'q5', number: '7', top: 0, bottom: 300},
], page = '', storage = new Map(), beacon = null} = {}) {
    const script = execFileSync(php, ['-n', emitter], {encoding: 'utf8', env: {
        ...process.env, FM_THRESHOLD: String(threshold), FM_WARNINGS: String(warnings),
        FM_ACTION: action, FM_MAXWARNINGS: String(max),
    }});
    let clock = Date.now();
    const callbacks = {window: {}, document: {}};
    const posts = [], beacons = [], timers = [], created = new Map();
    const elements = questions.map(q => ({...q, nodeType: 1, className: 'que multichoice deferredfeedback',
        querySelector: () => ({textContent: q.number}),
        getBoundingClientRect: () => ({top: q.top, bottom: q.bottom}),
    }));
    const container = {firstChild: null, appendChild(el) { created.set(el.id, el); },
        insertBefore(el) { created.set(el.id, el); }};
    const doc = {
        readyState: 'complete', hidden: false, visibilityState: 'visible', body: container,
        documentElement: {clientHeight: 700, contains: el => elements.includes(el)},
        querySelectorAll: () => elements,
        querySelector: () => container,
        getElementById: id => created.get(id) || null,
        createElement: () => ({style: {}}),
        addEventListener: (name, fn) => (callbacks.document[name] ||= []).push(fn),
    };
    const store = {getItem: k => storage.get(k) || null, setItem: (k,v) => storage.set(k,v), removeItem: k => storage.delete(k)};
    const win = {
        location: {pathname: '/mod/quiz/attempt.php', search: `?attempt=9${page === '' ? '' : '&page='+page}`},
        innerHeight: 700, sessionStorage: store, localStorage: store,
        addEventListener: (name, fn) => (callbacks.window[name] ||= []).push(fn),
        setTimeout: (fn, ms) => timers.push({fn, at: clock + ms}),
    };
    class FakeDate extends Date { constructor() { super(clock); } }
    class XHR {
        open() {} setRequestHeader() {}
        send(payload) { const p = new URLSearchParams(payload); posts.push({type: p.get('eventtype'), data: JSON.parse(p.get('eventdata'))}); }
    }
    const nav = beacon === null ? {} : {sendBeacon(url, blob) { beacons.push(blob); return beacon; }};
    vm.runInNewContext(script, {window: win, document: doc, navigator: nav, Date: FakeDate,
        XMLHttpRequest: XHR, Blob, console});
    function advance(ms) {
        clock += ms;
        for (const timer of timers.splice(0)) { if (timer.at <= clock) timer.fn(); else timers.push(timer); }
    }
    function fire(surface, type, event = {}) {
        for (const fn of callbacks[surface][type] || []) fn(event);
    }
    function leave() {
        fire('window', 'blur');
        doc.hidden = true; doc.visibilityState = 'hidden'; fire('document', 'visibilitychange');
    }
    function returned() {
        doc.hidden = false; doc.visibilityState = 'visible'; fire('document', 'visibilitychange'); fire('window', 'focus');
    }
    return {posts, beacons, win, doc, elements, fire, advance, leave, returned, storage, timers};
}
const warnings = m => m.posts.filter(p => p.type === 'focus_warning');
let m = monitor();
m.leave(); m.advance(4000); m.returned();
assert.equal(warnings(m).length, 0);
m.advance(1000); m.leave(); m.advance(6000); m.returned();
assert.equal(warnings(m).length, 1);
assert.equal(warnings(m)[0].data.duration, 6);
assert.equal(warnings(m)[0].data.context.question.number, '7');
assert.equal(warnings(m)[0].data.context.question.slot, 5);
assert.equal(warnings(m)[0].data.context.pageindex, 0);
assert.ok(warnings(m)[0].data.context.elapsed >= 125);
assert.equal(m.win.location.href, undefined, 'Review-only must not redirect');

m = monitor({threshold: 10}); m.leave(); m.advance(6000); m.returned();
assert.equal(warnings(m).length, 0, 'Configured 10 seconds was ignored');
m.advance(1000); m.leave(); m.advance(10000); m.returned();
assert.equal(warnings(m).length, 1, 'Threshold equality must warn');

m = monitor(); m.leave(); m.advance(6000); m.fire('window', 'focus');
assert.equal(warnings(m).length, 0, 'Hidden window focus prematurely warned');
m.returned(); assert.equal(warnings(m).length, 1);

// Multi-question attribution: viewport fallback, then last interaction; freeze departure context.
m = monitor({page: '2', questions: [
    {id: 'question-123-4', number: '4', top: 0, bottom: 100},
    {id: 'q8', number: '9', top: 100, bottom: 650},
]});
assert.equal(m.posts[0].data.context.contextsource, 'viewport');
assert.equal(m.posts[0].data.context.question.number, '9');
m.fire('document', 'touchstart', {target: m.elements[0]});
m.leave(); m.advance(6000);
m.fire('document', 'focusin', {target: m.elements[1]}); m.returned();
const c = warnings(m)[0].data.context;
assert.equal(c.question.number, '4'); assert.equal(c.question.slot, 4);
assert.equal(c.contextsource, 'last_interaction'); assert.equal(c.pageindex, 2);

// pagehide without beforeunload clears a persisted loss on the new page, even after a slow load.
m = monitor(); m.fire('window', 'blur'); m.advance(6000); m.fire('window', 'pagehide', {persisted: true});
const next = monitor({page: '1', storage: m.storage}); next.advance(600);
assert.equal(warnings(next).length, 0, 'Slow mobile navigation generated warning');

// Chrome can dispatch the navigation-hidden event after pagehide. It must not recreate a loss.
m = monitor(); m.fire('window', 'pagehide', {persisted: true});
m.doc.hidden = true; m.doc.visibilityState = 'hidden'; m.fire('document', 'visibilitychange');
m.advance(9000);
assert.equal(m.posts.at(-1).data.navigating, 1);
assert.equal(m.storage.size, 0, 'Late hidden recreated a navigation marker');
const after = monitor({page: '1', storage: m.storage}); after.advance(600);
assert.equal(warnings(after).length, 0, 'Late hidden caused a warning on the next page');
// A bfcache-restored document must not use its old in-memory loss, then must monitor again.
m.doc.hidden = false; m.doc.visibilityState = 'visible'; m.fire('window', 'pageshow', {persisted: true});
m.advance(600); assert.equal(warnings(m).length, 0);
m.leave(); m.advance(6000); m.returned(); assert.equal(warnings(m).length, 1);
assert.ok(Number.isInteger(warnings(m)[0].data.servertime));

m = monitor({warnings: 0, max: 1}); m.leave(); m.advance(6000); m.returned();
assert.equal(warnings(m).length, 1, 'Silent monitoring stopped recording');
assert.equal(m.win.location.href, undefined);
for (const action of ['summary', 'block']) {
    m = monitor({action, max: 1}); m.leave(); m.advance(6000); m.returned(); m.advance(2500);
    assert.match(m.win.location.href, /summary\.php\?attempt=9&cmid=3/);
    assert.equal(m.posts.filter(p => p.type === 'warning_limit_reached').length, 1);
}
m = monitor({questions: []}); assert.equal(m.posts[0].data.context.question, null);
m = monitor({beacon: true}); m.leave();
assert.equal(m.beacons.length, 2, 'Background loss should use beacon delivery');
const queued = new URLSearchParams(await m.beacons[0].text());
assert.equal(queued.get('eventtype'), 'window_blur');
assert.equal(JSON.parse(queued.get('eventdata')).context.question.number, '7');
m = monitor({beacon: false}); m.leave();
assert.ok(m.posts.some(p => p.type === 'window_blur'), 'Rejected beacon must fall back to XHR');
console.log('Emitted JavaScript regressions passed');
