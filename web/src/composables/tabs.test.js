import { describe, expect, it, vi } from 'vitest'
import { activateTab, initTabs, revealTabPanel } from '../../../cms/app/Site/assets/tabs.js'

function documentFixture() {
  const elements = new Map(), listeners = new Map(), groups = []
  const doc = {
    defaultView: { getComputedStyle: () => ({ direction: 'ltr' }) },
    getElementById: (id) => elements.get(id),
    querySelectorAll: () => groups,
    addEventListener: (name, handler) => listeners.set(name, [...(listeners.get(name) ?? []), handler]),
  }
  function group(id, parentPanel = null) {
    const root = { ownerDocument: doc, dataset: { tabs: id, tabsInitial: `${id}-0` }, setAttribute: vi.fn(), querySelectorAll: () => buttons,
      closest: (selector) => selector === '[data-tabs]' ? root : parentPanel }
    const buttons = [0, 1, 2].map((index) => {
      const key = `${id}-${index}`, attrs = { 'aria-controls': `${key}-panel`, 'aria-selected': String(index === 0) }
      const button = { dataset: { tabKey: key }, parentElement: { children: null }, focus: vi.fn(),
        closest: (selector) => selector === '[data-tabs]' ? root : button,
        getAttribute: (name) => attrs[name], setAttribute: (name, value) => { attrs[name] = value } }
      const panel = { ownerDocument: doc, parentElement: root, hidden: false,
        closest: () => panel, getAttribute: () => `${key}-button` }
      elements.set(`${key}-panel`, panel)
      elements.set(`${key}-button`, button)
      return button
    })
    for (const button of buttons) button.parentElement.children = buttons
    groups.push(root)
    return { root, buttons, panel: (index) => elements.get(`${id}-${index}-panel`) }
  }
  function key(button, name, extra = {}) {
    const event = { target: button, key: name, preventDefault: vi.fn(), stopImmediatePropagation: vi.fn(), ...extra }
    for (const handler of listeners.get('keydown') ?? []) handler(event)
    return event
  }
  return { doc, group, key, listeners }
}

describe('tab interactions', () => {
  it('supports keyboard wrapping, Home, End and a single tab stop without changing neighbors', () => {
    const { doc, group, key } = documentFixture()
    const first = group('first'), second = group('second')
    initTabs(doc)
    expect(first.panel(0).hidden).toBe(false)
    expect(first.panel(1).hidden).toBe(true)
    const event = key(first.buttons[0], 'ArrowLeft')
    expect(first.buttons[2].tabIndex).toBe(0)
    expect(first.buttons[2].focus).toHaveBeenCalled()
    expect(first.panel(2).hidden).toBe(false)
    expect(first.panel(0).hidden).toBe(true)
    expect(second.buttons[0].getAttribute('aria-selected')).toBe('true')
    expect(event.preventDefault).toHaveBeenCalled()
    key(first.buttons[2], 'Home')
    expect(first.buttons[0].tabIndex).toBe(0)
    key(first.buttons[0], 'End')
    expect(first.buttons[2].tabIndex).toBe(0)
    key(first.buttons[2], 'ArrowRight')
    expect(first.buttons[0].tabIndex).toBe(0)
    expect(key(first.buttons[0], 'ArrowRight', { altKey: true }).preventDefault).not.toHaveBeenCalled()
  })

  it('retains preview state, honors changed defaults, and avoids duplicate listeners', () => {
    const { doc, group, listeners } = documentFixture()
    const tabs = group('tabs')
    initTabs(doc)
    activateTab(tabs.buttons[1])
    // A preview patch restores the server's default attributes.
    tabs.buttons.forEach((button, index) => button.setAttribute('aria-selected', String(index === 0)))
    initTabs(doc)
    expect(tabs.buttons[1].getAttribute('aria-selected')).toBe('true')
    tabs.root.dataset.tabsInitial = 'tabs-2'
    tabs.buttons.forEach((button, index) => button.setAttribute('aria-selected', String(index === 2)))
    initTabs(doc)
    expect(tabs.buttons[2].getAttribute('aria-selected')).toBe('true')
    expect(listeners.get('click')).toHaveLength(1)
    expect(listeners.get('keydown')).toHaveLength(1)
  })

  it('reveals outline selections inside nested panels and supports right-to-left navigation', () => {
    const { doc, group, key } = documentFixture()
    const outer = group('outer'), inner = group('inner', outer.panel(1))
    initTabs(doc)
    revealTabPanel(inner.panel(2))
    expect(inner.panel(2).hidden).toBe(false)
    expect(outer.panel(1).hidden).toBe(false)
    expect(outer.panel(0).hidden).toBe(true)
    doc.defaultView.getComputedStyle = () => ({ direction: 'rtl' })
    key(inner.buttons[2], 'ArrowRight')
    expect(inner.buttons[1].tabIndex).toBe(0)
    expect(outer.panel(1).hidden).toBe(false)
  })
})
