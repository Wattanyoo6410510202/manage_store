const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function classAttribute(tag) {
    return tag.match(/class="([^"]*)"/)?.[1] ?? '';
}

function isVisibleAt(className, width, { removeBaseHidden = false } = {}) {
    const classes = new Set(className.split(/\s+/).filter(Boolean));
    let display = classes.has('hidden') && !removeBaseHidden ? 'none' : 'initial';

    if (width >= 768) {
        if (classes.has('md:hidden')) display = 'none';
        if (classes.has('md:flex')) display = 'flex';
    }
    if (width >= 769) {
        if (classes.has('min-[769px]:hidden')) display = 'none';
        if (classes.has('min-[769px]:flex')) display = 'flex';
    }

    return display !== 'none';
}

function extractTag(source, pattern, description) {
    const tag = source.match(pattern)?.[0];
    assert.ok(tag, `missing ${description} markup`);
    return tag;
}

function createClassList(initial = []) {
    const values = new Set(initial);
    return {
        add: (...names) => names.forEach((name) => values.add(name)),
        remove: (...names) => names.forEach((name) => values.delete(name)),
        contains: (name) => values.has(name),
        toggle(name, force) {
            const enabled = force === undefined ? !values.has(name) : Boolean(force);
            if (enabled) values.add(name);
            else values.delete(name);
            return enabled;
        },
    };
}

function createElement(initialClasses = []) {
    const attributes = new Map();
    const listeners = new Map();
    return {
        classList: createClassList(initialClasses),
        dataset: {},
        style: {},
        textContent: '',
        listeners,
        setAttribute: (name, value) => attributes.set(name, String(value)),
        getAttribute: (name) => attributes.get(name) ?? null,
        hasAttribute: (name) => attributes.has(name),
        addEventListener: (name, listener) => listeners.set(name, listener),
        getBoundingClientRect: () => ({ right: 72, top: 0, height: 48 }),
    };
}

function runHeaderPreferenceBootstrap({ storedCollapsed = true, throwOnRead = false } = {}) {
    const headerSource = fs.readFileSync(require.resolve('../header.php'), 'utf8');
    const inlineScripts = [...headerSource.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((match) => match[1]);
    const bootstrapSource = inlineScripts.find((source) => source.includes('prosystem.sidebar.collapsed'));
    assert.ok(bootstrapSource, 'header.php must apply the saved sidebar preference before rendering');

    const documentElement = createElement();
    const context = {
        document: { documentElement },
        localStorage: {
            getItem: () => {
                if (throwOnRead) throw new Error('storage unavailable');
                return String(storedCollapsed);
            },
        },
    };

    vm.createContext(context);
    vm.runInContext(bootstrapSource, context, { filename: 'header.php:inline-sidebar-preference' });
    return documentElement;
}

function loadSidebarController({ desktop = true, storedCollapsed = true, throwOnWrite = false } = {}) {
    const footer = fs.readFileSync(require.resolve('../footer.php'), 'utf8');
    const inlineScripts = [...footer.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((match) => match[1]);
    const controllerSource = inlineScripts.find((source) => source.includes('function toggleSidebar'));
    assert.ok(controllerSource, 'footer.php must contain the shared sidebar controller');

    const elements = {
        sidebar: createElement(),
        'sidebar-overlay': createElement(['hidden']),
        'sidebar-toggle': createElement(),
        'sidebar-toggle-icon': createElement(['fas', 'fa-chevron-left']),
        'sidebar-tooltip': createElement(),
        'sidebar-nav': createElement(),
    };
    const storage = new Map([['prosystem.sidebar.collapsed', String(storedCollapsed)]]);
    const documentElement = runHeaderPreferenceBootstrap({ storedCollapsed });
    const media = { matches: desktop };
    const tooltipTarget = createElement();
    tooltipTarget.textContent = 'Dashboard';

    const context = {
        console,
        document: {
            documentElement,
            getElementById: (id) => elements[id] ?? null,
            querySelectorAll: (selector) => {
                assert.equal(
                    selector,
                    '#sidebar-nav > a, #sidebar-nav > div > button, .sidebar-footer a[href="logout.php"]',
                    'tooltip targets must match top-level navigation and logout controls'
                );
                return [tooltipTarget];
            },
        },
        localStorage: {
            getItem: (key) => storage.get(key) ?? null,
            setItem: (key, value) => {
                if (throwOnWrite) throw new Error('storage unavailable');
                storage.set(key, String(value));
            },
        },
        setTimeout: (callback) => callback(),
        window: {
            matchMedia: () => media,
            addEventListener: () => {},
        },
    };

    vm.createContext(context);
    vm.runInContext(controllerSource, context, { filename: 'footer.php:inline-sidebar-controller' });
    return { context, elements, storage, documentElement, tooltipTarget };
}

const desktop = loadSidebarController();
assert.equal(typeof desktop.context.toggleDesktopSidebar, 'function', 'desktop toggle API must exist');
assert.equal(desktop.elements.sidebar.classList.contains('sidebar-collapsed'), true, 'saved compact state must be applied');
assert.equal(desktop.elements['sidebar-toggle'].getAttribute('aria-expanded'), 'false', 'toggle must expose collapsed state');
assert.equal(desktop.elements['sidebar-toggle-icon'].classList.contains('fa-chevron-right'), true, 'collapsed toggle must point toward expansion');
assert.equal(desktop.elements['sidebar-toggle-icon'].classList.contains('fa-chevron-left'), false, 'collapsed toggle must not point toward collapse');
assert.equal(desktop.tooltipTarget.getAttribute('aria-label'), 'Dashboard', 'icon-only menu must retain an accessible name');
assert.equal(typeof desktop.tooltipTarget.listeners.get('mouseenter'), 'function', 'icon-only menu must expose its tooltip on hover');

desktop.tooltipTarget.listeners.get('mouseenter')();
assert.equal(desktop.elements['sidebar-tooltip'].textContent, 'Dashboard', 'tooltip must show the menu label');
assert.equal(desktop.elements['sidebar-tooltip'].classList.contains('is-visible'), true, 'tooltip must become visible');

desktop.tooltipTarget.listeners.get('mouseleave')();
assert.equal(desktop.elements['sidebar-tooltip'].classList.contains('is-visible'), false, 'tooltip must hide after hover');
assert.equal(desktop.tooltipTarget.getAttribute('aria-describedby'), 'sidebar-tooltip', 'tooltip must be associated with its trigger');

desktop.tooltipTarget.listeners.get('focus')();
assert.equal(desktop.elements['sidebar-tooltip'].classList.contains('is-visible'), true, 'tooltip must become visible on keyboard focus');
desktop.tooltipTarget.listeners.get('blur')();
assert.equal(desktop.elements['sidebar-tooltip'].classList.contains('is-visible'), false, 'tooltip must hide after keyboard focus leaves');

desktop.context.toggleDesktopSidebar();
assert.equal(desktop.elements.sidebar.classList.contains('sidebar-collapsed'), false, 'toggle must restore expanded state');
assert.equal(desktop.storage.get('prosystem.sidebar.collapsed'), 'false', 'expanded preference must persist');
assert.equal(desktop.elements['sidebar-toggle'].getAttribute('aria-expanded'), 'true', 'toggle must expose expanded state');
assert.equal(desktop.elements['sidebar-toggle-icon'].classList.contains('fa-chevron-left'), true, 'expanded toggle must point toward collapse');
assert.equal(desktop.elements['sidebar-toggle-icon'].classList.contains('fa-chevron-right'), false, 'expanded toggle must not point toward expansion');

const initiallyExpanded = loadSidebarController({ storedCollapsed: false });
assert.equal(initiallyExpanded.elements.sidebar.classList.contains('sidebar-collapsed'), false, 'saved expanded state must remain expanded');

const submenu = loadSidebarController();
submenu.context.toggleSubmenu('doc-submenu');
assert.equal(submenu.elements.sidebar.classList.contains('sidebar-collapsed'), false, 'submenu icon must expand a collapsed sidebar');
assert.equal(submenu.storage.get('prosystem.sidebar.collapsed'), 'false', 'submenu expansion must persist expanded state');

const storageReadFallback = runHeaderPreferenceBootstrap({ throwOnRead: true });
assert.equal(storageReadFallback.classList.contains('sidebar-collapsed-preference'), false, 'storage read errors must fall back to expanded state');

const storageWriteFallback = loadSidebarController({ throwOnWrite: true });
assert.doesNotThrow(() => storageWriteFallback.context.toggleDesktopSidebar(), 'storage write errors must not block visual toggling');
assert.equal(storageWriteFallback.elements.sidebar.classList.contains('sidebar-collapsed'), false, 'visual state must still toggle when storage write fails');

const mobile = loadSidebarController({ desktop: false, storedCollapsed: true });
mobile.context.toggleSidebar();
assert.equal(mobile.elements.sidebar.classList.contains('open'), true, 'mobile toggle must open the existing drawer');
assert.equal(mobile.elements['sidebar-overlay'].classList.contains('hidden'), false, 'mobile toggle must reveal the overlay');
assert.equal(mobile.elements['sidebar-overlay'].classList.contains('opacity-100'), true, 'mobile overlay must retain its fade state');
assert.equal(mobile.storage.get('prosystem.sidebar.collapsed'), 'true', 'mobile drawer must not overwrite desktop preference');

mobile.context.toggleSidebar();
assert.equal(mobile.elements.sidebar.classList.contains('open'), false, 'second mobile toggle must close the drawer');
assert.equal(mobile.elements['sidebar-overlay'].classList.contains('hidden'), true, 'closing mobile drawer must hide the overlay');

const header = fs.readFileSync(require.resolve('../header.php'), 'utf8');
const desktopToggleTag = extractTag(header, /<button\s+id="sidebar-toggle"[\s\S]*?>/, 'desktop sidebar toggle');
const desktopToggleMarkup = extractTag(header, /<button\s+id="sidebar-toggle"[\s\S]*?<\/button>/, 'desktop sidebar toggle content');
const overlayTag = extractTag(header, /<div\s+id="sidebar-overlay"[\s\S]*?>/, 'mobile sidebar overlay');
const mobileToggleTags = [...header.matchAll(/<button[^>]*onclick="toggleSidebar\(\)"[^>]*>/g)].map((match) => match[0]);
const mobileCategoryTag = extractTag(header, /<!-- Mobile Category Dropdown -->\s*(<div[^>]*>)/, 'mobile category navigation');
const desktopCategoryTag = extractTag(header, /<!-- Desktop Category Tabs -->\s*(<div[^>]*>)/, 'desktop category navigation');
assert.equal(mobileToggleTags.length, 2, 'header must retain mobile close and open controls');

const desktopToggleClasses = new Set(classAttribute(desktopToggleTag).split(/\s+/).filter(Boolean));
assert.equal(desktopToggleClasses.has('absolute'), true, 'first design must float the desktop toggle at the sidebar edge');
assert.equal(desktopToggleClasses.has('-right-3'), true, 'first design must overlap the sidebar edge by 12px');
assert.equal(desktopToggleClasses.has('rounded-full'), true, 'first design must render the toggle as a circle');
assert.equal(desktopToggleClasses.has('focus:ring-2'), true, 'first design must retain its original focus ring');
assert.equal(desktopToggleMarkup.includes('fa-chevron-left'), true, 'desktop toggle must render the original collapse chevron');
assert.equal(desktopToggleMarkup.includes('fa-bars'), false, 'desktop toggle must not render a hamburger icon');

const sidebarHeaderIndex = header.indexOf('<div class="sidebar-header');
const desktopToggleIndex = header.indexOf('id="sidebar-toggle"');
const sidebarNavIndex = header.indexOf('<nav id="sidebar-nav"');
assert.ok(
    sidebarHeaderIndex < desktopToggleIndex && desktopToggleIndex < sidebarNavIndex,
    'first design must keep the desktop toggle inside the sidebar header'
);

assert.equal(isVisibleAt(classAttribute(desktopToggleTag), 767), false, 'desktop toggle must stay hidden below the breakpoint');
assert.equal(isVisibleAt(classAttribute(desktopToggleTag), 768), false, 'desktop toggle must stay hidden at the 768px mobile boundary');
for (const mobileToggleTag of mobileToggleTags) {
    assert.equal(isVisibleAt(classAttribute(mobileToggleTag), 767), true, 'mobile sidebar controls must show below the breakpoint');
    assert.equal(isVisibleAt(classAttribute(mobileToggleTag), 768), true, 'mobile sidebar controls must stay visible at 768px');
    assert.equal(isVisibleAt(classAttribute(mobileToggleTag), 769), false, 'mobile sidebar controls must hide at 769px');
}
assert.equal(
    isVisibleAt(classAttribute(overlayTag), 768, { removeBaseHidden: true }),
    true,
    'opening the mobile overlay must be able to reveal it at 768px'
);
assert.equal(isVisibleAt(classAttribute(overlayTag), 769, { removeBaseHidden: true }), false, 'mobile overlay must stay hidden on desktop');
assert.equal(isVisibleAt(classAttribute(desktopToggleTag), 769), true, 'desktop toggle must become visible at 769px');
assert.equal(isVisibleAt(classAttribute(mobileCategoryTag), 768), true, 'mobile category navigation must remain visible at 768px');
assert.equal(isVisibleAt(classAttribute(mobileCategoryTag), 769), false, 'mobile category navigation must hide at 769px');
assert.equal(isVisibleAt(classAttribute(desktopCategoryTag), 768), false, 'desktop category navigation must stay hidden at 768px');
assert.equal(isVisibleAt(classAttribute(desktopCategoryTag), 769), true, 'desktop category navigation must show at 769px');

console.log('collapsible sidebar behavior: PASS');
