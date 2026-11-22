'use strict';

// Starter suggestions selected from the course and section lists supplied by the group.
// Keep these lists stable between page loads so every group member sees the same choices.
const ScholarDeskPickers = (() => {
  const courses = Object.freeze([
    'ALACC0013', 'CCS0001', 'CE0019', 'COE0003', 'CPE0005',
    'CS0019', 'CS0045', 'CS0053', 'GED0027', 'IT0011'
  ]);
  const sections = Object.freeze([
    'AA11', 'AB21', 'AC31', 'AD21', 'AI21',
    'AM31', 'DS31', 'ME21-A', 'SE31', 'SE32'
  ]);
  const escape = value => String(value ?? '').replace(/[&<>"']/g,
    character => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[character]));
  let listeners;

  function field(name, label, value, maximum) {
    const id = `picker-${name}`;
    return `<div class="field picker-field"><label for="${id}">${escape(label)}</label>
      <div class="picker" data-picker="${name}">
        <div class="picker-control">
          <input id="${id}" name="${name}" type="text" role="combobox" aria-autocomplete="list"
            aria-expanded="false" aria-haspopup="listbox" aria-controls="${id}-options"
            aria-describedby="${id}-hint" autocomplete="off" spellcheck="false"
            maxlength="${maximum}" value="${escape(value)}" placeholder="Search or choose a code…" required>
          <button type="button" class="picker-toggle" aria-label="Show ${escape(label)} options"
            aria-controls="${id}-options" aria-expanded="false" tabindex="-1">⌄</button>
        </div>
        <div class="picker-menu" hidden>
          <div id="${id}-options" class="picker-options" role="listbox" aria-label="${escape(label)} suggestions"></div>
          <p class="picker-empty" role="status" hidden>No matching codes. You can enter another code.</p>
        </div>
      </div>
      <small class="field-hint" id="${id}-hint">Type to search, or enter another code.</small>
    </div>`;
  }

  function mount(container, records = []) {
    // Reopening the editor replaces its controls; discard the previous listeners too.
    listeners?.abort();
    listeners = new AbortController();
    const listen = (target, event, action) => target.addEventListener(event, action, {signal:listeners.signal});
    const pickers = [];

    container.querySelectorAll('[data-picker]').forEach(root => {
      const name = root.dataset.picker;
      const input = root.querySelector('input');
      const toggle = root.querySelector('.picker-toggle');
      const menu = root.querySelector('.picker-menu');
      const list = root.querySelector('[role="listbox"]');
      const empty = root.querySelector('.picker-empty');
      const preset = name === 'section' ? sections : courses;
      const saved = name === 'subject' ? [] : records.map(record => record[name]);
      const choices = [...new Set([...preset, ...saved, input.value].filter(Boolean))];
      let matches = [], active = -1;

      function close() {
        menu.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        active = -1;
      }

      function highlight(index) {
        active = index;
        const buttons = [...list.querySelectorAll('[role="option"]')];
        buttons.forEach((button, position) => button.classList.toggle('is-active', position === index));
        if (buttons[index]) {
          input.setAttribute('aria-activedescendant', buttons[index].id);
          buttons[index].scrollIntoView({block:'nearest'});
        } else input.removeAttribute('aria-activedescendant');
      }

      function choose(value) {
        input.value = value;
        input.dispatchEvent(new Event('change', {bubbles:true}));
        input.focus();
        close();
      }

      function open(showAll = false) {
        pickers.forEach(picker => { if (picker.root !== root) picker.close(); });
        const query = showAll ? '' : input.value.trim().toLowerCase();
        matches = choices.filter(value => value.toLowerCase().includes(query));
        list.replaceChildren();
        matches.forEach((value, index) => {
          const button = document.createElement('button');
          button.type = 'button';
          button.id = `${input.id}-option-${index}`;
          button.className = 'picker-option';
          button.setAttribute('role', 'option');
          button.setAttribute('aria-selected', String(value === input.value));
          button.tabIndex = -1;
          button.textContent = value;
          // Keep focus on the combobox until the click selects the suggestion.
          button.onpointerdown = event => event.preventDefault();
          button.onclick = () => choose(value);
          list.append(button);
        });
        empty.hidden = matches.length !== 0;
        menu.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-expanded', 'true');
        highlight(-1);
      }

      pickers.push({root, close});
      listen(input, 'input', () => open());
      listen(input, 'click', () => open());
      listen(toggle, 'click', () => {
        const wasOpen = !menu.hidden;
        input.focus();
        if (wasOpen) close(); else open(true);
      });
      listen(input, 'keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault();
          if (menu.hidden) open();
          if (matches.length) highlight(event.key === 'ArrowDown'
            ? (active + 1) % matches.length
            : active < 0 ? matches.length - 1 : (active - 1 + matches.length) % matches.length);
        } else if (event.key === 'Enter' && !menu.hidden && active >= 0) {
          event.preventDefault();
          choose(matches[active]);
        } else if (event.key === 'Escape' && !menu.hidden) {
          event.preventDefault();
          event.stopPropagation();
          close();
        } else if (event.key === 'Tab') close();
      });
      listen(root, 'focusout', event => { if (!root.contains(event.relatedTarget)) close(); });
      listen(document, 'pointerdown', event => { if (!root.contains(event.target)) close(); });
    });
  }

  return {field, mount};
})();
