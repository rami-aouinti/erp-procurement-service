import { useEffect, useMemo, useRef, useState } from 'react';
import { ArrowDown, ArrowUp, Command, Search, CornerDownLeft } from 'lucide-react';

export default function CommandPalette({ open, onClose, onNavigate, roles = [] }) {
  const [query, setQuery] = useState('');
  const [activeIndex, setActiveIndex] = useState(0);
  const inputRef = useRef(null);
  const dialogRef = useRef(null);
  const previousFocusRef = useRef(null);

  const commands = useMemo(() => {
    const items = [
      { id: 'dashboard', label: 'Go to dashboard', detail: 'Workspace overview', group: 'Navigation', keywords: 'home overview', path: '/' },
      { id: 'suppliers', label: 'Open suppliers', detail: 'Search and manage suppliers', group: 'Navigation', keywords: 'vendors directory', path: '/suppliers' },
      { id: 'configuration', label: 'Open configuration', detail: 'Site settings and personal preferences', group: 'Preferences', keywords: 'settings theme profile', path: '/configuration' },
      { id: 'help', label: 'Open help & support', detail: 'Guides and assistance', group: 'Support', keywords: 'documentation faq', path: '/help' },
    ];
    if (roles.includes('supplier-reviewer') || roles.includes('supplier-admin')) {
      items.splice(2, 0, { id: 'onboarding', label: 'Open approvals', detail: 'Review supplier onboarding requests', group: 'Navigation', keywords: 'review queue pending', path: '/onboarding' });
    }
    if (roles.includes('supplier-admin')) {
      items.splice(items.length - 2, 0, { id: 'classifications', label: 'Supplier classifications', detail: 'Manage supplier categories', group: 'Administration', keywords: 'classification categories', path: '/supplier-classifications' });
    }
    return items;
  }, [roles]);

  const filtered = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return commands;
    return commands.filter((item) => `${item.label} ${item.detail} ${item.keywords}`.toLowerCase().includes(normalized));
  }, [commands, query]);

  useEffect(() => {
    if (!open) return undefined;
    previousFocusRef.current = document.activeElement;
    setQuery('');
    setActiveIndex(0);
    const frame = window.requestAnimationFrame(() => inputRef.current?.focus());
    return () => {
      window.cancelAnimationFrame(frame);
      const previous = previousFocusRef.current;
      if (previous && typeof previous.focus === 'function' && document.contains(previous)) previous.focus();
    };
  }, [open]);

  useEffect(() => {
    setActiveIndex((index) => Math.min(index, Math.max(filtered.length - 1, 0)));
  }, [filtered.length]);

  useEffect(() => {
    if (!open) return undefined;
    const onKeyDown = (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
        return;
      }
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActiveIndex((index) => filtered.length ? (index + 1) % filtered.length : 0);
      }
      if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActiveIndex((index) => filtered.length ? (index - 1 + filtered.length) % filtered.length : 0);
      }
      if (event.key === 'Enter' && filtered[activeIndex]) {
        event.preventDefault();
        onNavigate(filtered[activeIndex].path);
        onClose();
        return;
      }
      if (event.key === 'Tab') {
        const focusable = dialogRef.current?.querySelectorAll('input:not([disabled]), button:not([disabled])');
        if (!focusable?.length) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [open, filtered, activeIndex, onClose, onNavigate]);

  if (!open) return null;

  return (
    <div className="command-overlay" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
      <section ref={dialogRef} className="command-dialog" role="dialog" aria-modal="true" aria-labelledby="command-title" aria-describedby="command-description">
        <h2 id="command-title" className="sr-only">Command palette</h2>
        <p id="command-description" className="sr-only">Search available workspace actions. Use arrow keys to navigate and Enter to open.</p>
        <div className="command-search"><Search size={19} aria-hidden="true" /><input ref={inputRef} value={query} onChange={(event) => { setQuery(event.target.value); setActiveIndex(0); }} placeholder="What would you like to do?" aria-label="Search commands" role="combobox" aria-expanded="true" aria-autocomplete="list" aria-controls="command-results" aria-activedescendant={filtered[activeIndex] ? `command-option-${filtered[activeIndex].id}` : undefined} /><kbd>ESC</kbd></div>
        <div id="command-results" className="command-results" role="listbox" aria-label="Available commands">
          {filtered.length ? filtered.map((item, index) => (
            <button type="button" role="option" aria-selected={index === activeIndex} className={index === activeIndex ? 'command-option active' : 'command-option'} key={item.id} id={`command-option-${item.id}`} onMouseEnter={() => setActiveIndex(index)} onClick={() => { onNavigate(item.path); onClose(); }}>
              <span className="command-option-copy"><strong>{item.label}</strong><small>{item.detail}</small></span><span className="command-option-group">{item.group}</span>
            </button>
          )) : <div className="command-empty" role="status">No commands match “{query}”. Try another search.</div>}
        </div>
        <footer className="command-footer"><span><kbd><ArrowUp size={11}/></kbd><kbd><ArrowDown size={11}/></kbd> to navigate</span><span><kbd><CornerDownLeft size={11}/></kbd> to open</span><span><kbd>ESC</kbd> to close</span><span className="command-brand"><Command size={13}/> Workspace</span></footer>
      </section>
    </div>
  );
}
