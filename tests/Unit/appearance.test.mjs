import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import ts from 'typescript';

const source = fs.readFileSync(new URL('../../resources/js/hooks/use-appearance.tsx', import.meta.url), 'utf8');
const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;

function themeBrowser({ saved = null, dark = false, blocked = false, server = false } = {}) {
    const storage = new Map(saved === null ? [] : [['appearance', saved]]);
    const mediaListeners = [];
    const events = {};
    let appliedDark = false;
    const media = { matches: dark, addEventListener: (_name, listener) => mediaListeners.push(listener) };
    const document = { documentElement: { classList: { toggle: (_name, value) => { appliedDark = value; } }, style: {} } };
    const module = { exports: {} };
    const context = { module, exports: module.exports, require: () => ({ useEffect: (effect) => effect(), useSyncExternalStore: (_subscribe, snapshot) => snapshot() }) };
    if (!server) {
        Object.assign(context, { document, window: {
            localStorage: { getItem: (key) => { if (blocked) throw new Error('Blocked'); return storage.get(key) ?? null; }, setItem: (key, value) => { if (blocked) throw new Error('Blocked'); storage.set(key, value); } },
            matchMedia: () => media,
            addEventListener: (name, listener) => { events[name] = listener; },
        } });
    }
    vm.runInNewContext(compiled, context);
    module.exports.initializeTheme();
    return { api: module.exports, storage, media, events, mediaListeners, document, isDark: () => appliedDark };
}

test('explicit light and dark choices persist and override the operating system', () => {
    const browser = themeBrowser({ dark: true });
    browser.api.useAppearance().updateAppearance('light');
    assert.equal(browser.isDark(), false);
    assert.equal(browser.storage.get('appearance'), 'light');
    browser.mediaListeners[0]();
    assert.equal(browser.isDark(), false);
    browser.api.useAppearance().updateAppearance('dark');
    assert.equal(browser.isDark(), true);
    assert.equal(browser.document.documentElement.style.colorScheme, 'dark');
    assert.equal(browser.storage.get('appearance'), 'dark');
});

test('system theme follows device changes after settings subscribers have left', () => {
    const browser = themeBrowser();
    browser.api.useAppearance().updateAppearance('system');
    browser.media.matches = true;
    browser.mediaListeners[0]();
    assert.equal(browser.isDark(), true);
    browser.media.matches = false;
    browser.mediaListeners[0]();
    assert.equal(browser.isDark(), false);
    assert.equal(browser.storage.get('appearance'), 'system');
});

test('saved settings load immediately and invalid values fall back to system', () => {
    assert.equal(themeBrowser({ saved: 'dark' }).isDark(), true);
    assert.equal(themeBrowser({ saved: 'light', dark: true }).isDark(), false);
    const browser = themeBrowser({ saved: 'invalid', dark: true });
    assert.equal(browser.api.useAppearance().appearance, 'system');
    assert.equal(browser.isDark(), true);
});

test('theme initializes once and synchronizes changes from another browser tab', () => {
    const browser = themeBrowser();
    browser.api.initializeTheme();
    assert.equal(browser.mediaListeners.length, 1);
    browser.events.storage({ key: 'appearance', newValue: 'dark' });
    assert.equal(browser.api.useAppearance().appearance, 'dark');
    assert.equal(browser.isDark(), true);
    browser.events.storage({ key: 'unrelated', newValue: 'light' });
    assert.equal(browser.isDark(), true);
    browser.events.storage({ key: null, newValue: null });
    assert.equal(browser.api.useAppearance().appearance, 'system');
    assert.equal(browser.isDark(), false);
});

test('blocked browser storage does not break theme switching', () => {
    const browser = themeBrowser({ blocked: true });
    browser.api.useAppearance().updateAppearance('dark');
    assert.equal(browser.isDark(), true);
    assert.equal(browser.api.useAppearance().appearance, 'dark');
});

test('theme module can initialize without browser globals during server rendering', () => {
    const browser = themeBrowser({ server: true });
    assert.equal(browser.api.useAppearance().appearance, 'system');
});
