import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import {
    ADVISOR_SERIES_COLORS,
    ringGradient,
    seriesColor,
} from '../../resources/js/lib/advisor/series.ts';
import {
    documentNumber,
    sealDate,
    sealDateTime,
} from '../../resources/js/lib/advisor/format.ts';

function read(path: string): string {
    return readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8');
}

const appCss = read('resources/css/app.css');
const blade = read('resources/views/app.blade.php');
const sidebar = read('resources/js/components/AppSidebar.vue');
const header = read('resources/js/components/AppSidebarHeader.vue');
const paywall = read('resources/js/pages/Advisor/Paywall.vue');
const home = read('resources/js/pages/Advisor/Index.vue');
const assessment = read('resources/js/pages/Advisor/Assessment.vue');

/* ── Tokens ─────────────────────────────────────────────────────────────── */

test('the gold, the serif and the mono are declared once as tokens', () => {
    // The handoff is explicit: a value with no existing token becomes a token
    // rather than a literal repeated across five pages.
    assert.match(appCss, /--advisor-gold: #d9c48f;/);
    assert.match(
        appCss,
        /--advisor-font-serif: 'Instrument Serif', Georgia, serif;/,
    );
    assert.match(
        appCss,
        /--advisor-font-mono: 'JetBrains Mono', ui-monospace, monospace;/,
    );
});

test('the editorial serif falls back to Vazirmatn in RTL', () => {
    // Instrument Serif has no Persian coverage. Shipping Latin serif against
    // Persian text would be worse than not having a serif at all, so RTL gets a
    // lighter Vazirmatn instead.
    const rtl = appCss.slice(
        appCss.indexOf(
            "html[lang='fa'],\nhtml[dir='rtl'] {\n    --advisor-font-serif",
        ),
    );

    assert.ok(rtl.length > 0, 'an RTL override for the serif exists');
    assert.match(
        rtl.slice(0, 400),
        /--advisor-font-serif:\s*\n?\s*'Vazirmatn'/,
    );
    assert.match(rtl.slice(0, 400), /--advisor-serif-weight: 300;/);
});

test('every mono figure is tabular', () => {
    const mono = appCss.slice(appCss.indexOf('.advisor-mono {'));

    assert.match(mono.slice(0, 200), /font-variant-numeric: tabular-nums;/);
});

test('figures stay left-to-right inside an RTL page', () => {
    const figure = appCss.slice(appCss.indexOf('.advisor-figure {'));

    assert.match(figure.slice(0, 200), /direction: ltr;/);
    assert.match(figure.slice(0, 200), /unicode-bidi: isolate;/);
});

test('the two new faces are actually loaded', () => {
    assert.match(blade, /family=instrument-serif:400,400i/);
    assert.match(blade, /family=jetbrains-mono:400,500/);
    // The RTL serif substitute needs a 300 weight to read as editorial.
    assert.match(blade, /family=vazirmatn:300,400,500,600,700/);
});

/* ── Series ─────────────────────────────────────────────────────────────── */

test('the asset series is the handoff order', () => {
    assert.deepEqual(
        [...ADVISOR_SERIES_COLORS],
        ['#02cd86', '#d9c48f', '#60a5fa', '#947bff', '#e9944e', '#55635c'],
    );
});

test('the series wraps past the sixth holding', () => {
    assert.equal(seriesColor(0), '#02cd86');
    assert.equal(seriesColor(6), seriesColor(0));
});

test('a fully drawn ring paints every segment and no track', () => {
    const gradient = ringGradient([
        { percent: 60, color: '#02cd86' },
        { percent: 40, color: '#d9c48f' },
    ]);

    assert.equal(gradient, 'conic-gradient(#02cd86 0% 60%, #d9c48f 60% 100%)');
});

test('a partly filled ring clips the segment the sweep is inside', () => {
    // The design's point: the ring assembles holding by holding rather than
    // growing as one undifferentiated arc.
    const gradient = ringGradient(
        [
            { percent: 60, color: '#02cd86' },
            { percent: 40, color: '#d9c48f' },
        ],
        30,
    );

    assert.equal(
        gradient,
        'conic-gradient(#02cd86 0% 30%, rgba(255,255,255,.05) 30% 100%)',
    );
});

test('an empty plan still renders a valid gradient', () => {
    // A conic-gradient needs two stops; a plan with no allocations is a bare
    // track, not a CSS parse error that leaves the ring transparent.
    assert.equal(
        ringGradient([], 0),
        'conic-gradient(rgba(255,255,255,.05) 0% 100%)',
    );
});

/* ── Document formatting ───────────────────────────────────────────────── */

test('the dossier reference is always four digits', () => {
    assert.equal(documentNumber(2), '0002');
    assert.equal(documentNumber('7f3a'), '7f3a');
});

test('a seal date prints in the mono band form', () => {
    assert.equal(sealDate('2026-08-21T14:38:00Z', 'gregorian'), '21 AUG 2026');
    assert.match(
        sealDateTime('2026-08-21T14:38:00Z', 'gregorian'),
        /^21 AUG 2026, /,
    );
});

test('a jalali user reads the seal in their own calendar', () => {
    // Handing a Jalali user "21 AUG 2026" on a sealed document makes them
    // convert it in their head to know when they sealed it.
    assert.equal(sealDate('2026-08-21T09:00:00Z', 'jalali'), '30 مرداد 1405');
});

test('a missing date is an em dash, not "Invalid Date"', () => {
    assert.equal(sealDate(null, 'gregorian'), '—');
    assert.equal(sealDate('not-a-date', 'gregorian'), '—');
});

/* ── Shell ──────────────────────────────────────────────────────────────── */

test('Advisor is the one gold item in the shared chrome', () => {
    for (const source of [sidebar, header]) {
        assert.match(
            source,
            /function isAdvisor\(item: ModuleNavItem\): boolean/,
        );
        assert.match(
            source,
            /border-s-2 border-s-\[#d9c48f\] bg-\[#d9c48f\]\/10/,
        );
        // The gold badge this used to check was the PRO badge. Advisor is not
        // a paid tier any more, so the accent is the row itself and nothing
        // claims a tier beside the name.
        assert.doesNotMatch(source, /advisor-mono rounded-\[4px\]/);
        assert.doesNotMatch(source, /modules\.tiers\./);
    }
});

test('the gold treatment does not swallow the locked and promo markers', () => {
    // Advisor still has to say which state it is in. It used to need carving
    // out of the badge because it had a gold one of its own; now there is a
    // single badge and no isAdvisor exclusion left to get that wrong.
    assert.match(sidebar, /v-if="item\.state !== 'enabled'"/);
    assert.match(sidebar, /item\.state === 'locked'/);
    assert.doesNotMatch(sidebar, /!isAdvisor\(item\)/);
});

/* ── Paywall ────────────────────────────────────────────────────────────── */

test('the paywall shows a real recommendation, blurred, behind a seal', () => {
    // An illustration would say nothing about what is behind the lock.
    assert.match(paywall, /opacity-50 blur-\[5px\]/);
    assert.match(paywall, /previewRing/);
    assert.match(paywall, /advisor-seal/);
    assert.match(paywall, /t\('advisor\.paywall\.members_only'\)/);
});

test('the paywall CTA is the only gold-filled button in the product', () => {
    const goldFill = /bg-\[linear-gradient\(180deg,#e6d6a8,#d9c48f\)\]/;

    assert.match(paywall, goldFill);
    for (const source of [home, assessment, sidebar, header]) {
        assert.doesNotMatch(source, goldFill);
    }
});

test('the paywall does not promise a plans page that is switched off', () => {
    // config('billing.enabled') off means billing.edit 404s. The modules page
    // already guards this; the paywall has to as well or its one CTA is a dead
    // link.
    assert.match(paywall, /const billingEnabled = computed\(/);
    assert.match(paywall, /v-if="billingEnabled"/);
    assert.match(paywall, /t\('modules\.upgrade\.unavailable'\)/);
});

test('a lapsed subscriber is told their profile survived', () => {
    assert.match(paywall, /hasProfile: boolean/);
    assert.match(paywall, /t\('advisor\.paywall\.profile_saved'\)/);
});

test('the paywall sends the reader somewhere they can actually upgrade', () => {
    assert.match(
        paywall,
        /import \{ edit as editBilling \} from '@\/routes\/billing'/,
    );
    assert.match(paywall, /:href="editBilling\(\)"/);
});

/* ── Home and assessment ───────────────────────────────────────────────── */

test('the home progress strip marks the current section apart from the done ones', () => {
    // Three states, not two: completed, current, pending. A two-state bar
    // cannot say whether section three is finished or open.
    assert.match(home, /step < resumeSection\s*\?\s*'bg-\[#d9c48f\]'/);
    assert.match(home, /step === resumeSection\s*\?\s*'bg-\[#d9c48f\]\/42'/);
});

test('the assessment rail names the eight sections and where you are', () => {
    assert.match(assessment, /const railSections = computed/);
    assert.match(
        assessment,
        /:aria-current="\s*item\.state === 'current' \? 'step' : undefined\s*"/,
    );
});

test('the assessment keeps a progress indicator where the rail hides', () => {
    // The rail is dropped below the shell breakpoint, so progress would
    // otherwise become invisible on a phone.
    assert.match(assessment, /grid grid-cols-8 gap-\[5px\] lg:hidden/);
});

test('a selected assessment option is gold, never the action green', () => {
    // Green means action and nothing else. Selection is ceremony.
    assert.match(
        assessment,
        /selected\(key, option\)\s*\?\s*'border-\[#d9c48f\]\/50 bg-\[#d9c48f\]\/9 text-white'/,
    );
    assert.ok(
        !assessment.includes('#02CD86'),
        'no green selection states survive in the assessment',
    );
});

test('long option labels stack and short scales wrap', () => {
    // Five full sentences in a wrapping row read as a wall; a stacked column of
    // "5%" / "10%" wastes the page. Label length decides, so a longer
    // translation gets the right shape too.
    assert.match(assessment, /function isStacked\(key: string\): boolean/);
    assert.match(assessment, /\.length > 24/);
});

test('the answered count reflects the composite liquidity question honestly', () => {
    // q10 is one answer made of two fields, and half of it is not an answer.
    assert.match(assessment, /function isAnswered\(key: string\): boolean/);
    assert.match(assessment, /key === 'q10_liquidity'/);
});

/* ── Subtitles ──────────────────────────────────────────────────────────── */

test('every Advisor screen names its step in the header subtitle', () => {
    for (const [name, source] of [
        ['Paywall', paywall],
        ['Index', home],
        ['Assessment', assessment],
        ['Profile', read('resources/js/pages/Advisor/Profile.vue')],
        [
            'Recommendation',
            read('resources/js/pages/Advisor/Recommendation.vue'),
        ],
    ] as const) {
        assert.match(
            source,
            /usePageSubtitle\(/,
            `${name} publishes a header subtitle`,
        );
    }
});

test('the h1 stays "Advisor" on every screen, with the step in the subtitle', () => {
    // The design puts the same wordmark-level title on all seven screens; the
    // subtitle is what changes.
    for (const source of [
        paywall,
        home,
        assessment,
        read('resources/js/pages/Advisor/Profile.vue'),
        read('resources/js/pages/Advisor/Recommendation.vue'),
    ]) {
        assert.ok(
            source.includes("{ title: 'Advisor'"),
            'the last breadcrumb is Advisor',
        );
    }

    assert.match(header, /Advisor: navigationName\('navigation\.advisor'\)/);
});
