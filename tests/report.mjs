import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
const php = process.env.TEST_PHP || 'php';
const harness = fileURLToPath(new URL('./report_harness.php', import.meta.url));
const run = (mode, offset = 0) => execFileSync(php, ['-n', harness, mode], {encoding: 'utf8',
    env: {...process.env, FM_CLIENT_CLOCK_OFFSET: String(offset)}, maxBuffer: 20 * 1024 * 1024});
const summary = run('summarycsv').trim().split('\n');
assert.equal(summary.length, 2);
assert.match(summary[1], /,2105,2101,1,7,7100,/);
const events = run('eventscsv').trim().split('\n');
assert.equal(events.length, 2106, 'CSV must retain more than 2,000 events');
assert.match(events[0], /"Quiz page","Displayed question","Internal position \(slot\)","Question attribution","Seconds since attempt started"$/);
assert.match(events[1], /,3,7,5,"Only question on this page",4108$/);
const episodes = run('episodecsv').trim().split('\n');
assert.equal(episodes.length, 2);
assert.match(episodes[1], /,7,"Return recorded",3,7,5,"Only question on this page",4100$/);
const html = run('');
assert.match(html, /Show focus departures by question and time/);
assert.match(html, /<td>7 s<\/td>/);
assert.match(html, /<td>7<\/td><td>5<\/td>/);
assert.doesNotMatch(html, /Warning:|Fatal error|Deprecated:/);
assert.equal(run('episodecsv', 3454), run('episodecsv'), 'Client clock shifted episode times');
// Event raw JSON retains the client time, while the report time must stay server-aligned.
const shiftedEvents = run('eventscsv', 3454).trim().split('\n');
assert.equal(shiftedEvents[1].split(',')[0], events[1].split(',')[0]);
console.log('Complete report and CSV regressions passed (2,105 events)');
