(function () {
  'use strict';

  var TRIGGER = 'select.fi, select.u-input';
  var OPEN_CLASS = 'cs-open';
  var activeDropdown = null;
  var activePanel = null;

  function getText(opt) {
    return (opt.textContent || opt.innerText || '').trim();
  }

  function positionPanel(wrapper, panel) {
    var rect = wrapper.getBoundingClientRect();
    var spaceBelow = window.innerHeight - rect.bottom;
    var spaceAbove = rect.top;
    var panelH = Math.min(260, spaceBelow > 220 ? spaceBelow - 8 : spaceAbove - 8);

    panel.style.width = rect.width + 'px';
    panel.style.left = (rect.left + window.scrollX) + 'px';

    if (spaceBelow < 220 && spaceAbove > spaceBelow) {
      panel.style.top = '';
      panel.style.bottom = (window.innerHeight - rect.top - window.scrollY) + 'px';
      panel.classList.add('cs-up');
    } else {
      panel.style.bottom = '';
      panel.style.top = (rect.bottom + window.scrollY) + 'px';
      panel.classList.remove('cs-up');
    }
  }

  function buildDropdown(select) {
    if (select._csBuilt) return;
    select._csBuilt = true;

    var wrapper = document.createElement('div');
    wrapper.className = 'cs-wrap';
    if (select.classList.contains('u-input')) wrapper.classList.add('cs-ui');

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'cs-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    var triggerText = document.createElement('span');
    triggerText.className = 'cs-val';

    var triggerArrow = document.createElement('span');
    triggerArrow.className = 'cs-arrow';
    triggerArrow.innerHTML = '<svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    trigger.appendChild(triggerText);
    trigger.appendChild(triggerArrow);

    // Panel di-teleport ke body agar tidak terpotong overflow
    var panel = document.createElement('div');
    panel.className = 'cs-panel';
    panel.setAttribute('role', 'listbox');
    document.body.appendChild(panel);

    var searchWrap = document.createElement('div');
    searchWrap.className = 'cs-search-wrap';

    var searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'cs-search';
    searchInput.placeholder = 'Cari...';
    searchInput.setAttribute('autocomplete', 'off');
    searchInput.setAttribute('spellcheck', 'false');

    searchWrap.appendChild(searchInput);

    var list = document.createElement('ul');
    list.className = 'cs-list';

    panel.appendChild(searchWrap);
    panel.appendChild(list);

    wrapper.appendChild(trigger);

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    function syncOptions() {
      list.innerHTML = '';
      var opts = select.querySelectorAll('option');
      opts.forEach(function (opt) {
        var li = document.createElement('li');
        li.className = 'cs-opt';
        li.setAttribute('role', 'option');
        li.dataset.val = opt.value;
        li.textContent = getText(opt);
        if (opt.selected) li.classList.add('cs-selected');
        if (opt.disabled) li.classList.add('cs-disabled');
        list.appendChild(li);
      });
      syncTriggerText();
    }

    function syncTriggerText() {
      var sel = select.options[select.selectedIndex];
      triggerText.textContent = sel ? getText(sel) : '';
    }

    function filterList(q) {
      q = q.toLowerCase().trim();
      list.querySelectorAll('.cs-opt').forEach(function (li) {
        var match = q === '' || li.textContent.toLowerCase().indexOf(q) !== -1;
        li.style.display = match ? '' : 'none';
      });
    }

    function openPanel() {
      if (activeDropdown && activeDropdown !== wrapper) closePanel(activeDropdown, activePanel);
      activeDropdown = wrapper;
      activePanel = panel;

      wrapper.classList.add(OPEN_CLASS);
      trigger.setAttribute('aria-expanded', 'true');
      panel.classList.add(OPEN_CLASS);

      searchInput.value = '';
      filterList('');
      syncOptions();
      positionPanel(wrapper, panel);

      var sel = list.querySelector('.cs-selected');
      if (sel) setTimeout(function () { sel.scrollIntoView({ block: 'nearest' }); }, 0);
      setTimeout(function () { searchInput.focus(); }, 0);
    }

    function closePanel(w, p) {
      w = w || wrapper;
      p = p || panel;
      w.classList.remove(OPEN_CLASS);
      p.classList.remove(OPEN_CLASS);
      var t = w.querySelector('.cs-trigger');
      if (t) t.setAttribute('aria-expanded', 'false');
      if (activeDropdown === w) { activeDropdown = null; activePanel = null; }
    }

    trigger.addEventListener('click', function () {
      if (wrapper.classList.contains(OPEN_CLASS)) {
        closePanel();
      } else {
        openPanel();
      }
    });

    searchInput.addEventListener('input', function () {
      filterList(this.value);
    });

    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { closePanel(); trigger.focus(); }
    });

    list.addEventListener('click', function (e) {
      var li = e.target.closest('.cs-opt');
      if (!li || li.classList.contains('cs-disabled')) return;
      select.value = li.dataset.val;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      list.querySelectorAll('.cs-opt').forEach(function (o) { o.classList.remove('cs-selected'); });
      li.classList.add('cs-selected');
      syncTriggerText();
      closePanel();
      trigger.focus();
    });

    select.addEventListener('change', function () {
      syncTriggerText();
      list.querySelectorAll('.cs-opt').forEach(function (li) {
        li.classList.toggle('cs-selected', li.dataset.val === select.value);
      });
    });

    if (select.disabled) {
      trigger.disabled = true;
      wrapper.classList.add('cs-disabled');
    }

    syncOptions();

    var mo = new MutationObserver(syncOptions);
    mo.observe(select, { childList: true, subtree: true });
  }

  function enhance(root) {
    root = root || document;
    root.querySelectorAll(TRIGGER).forEach(function (sel) {
      if (!sel._csBuilt) buildDropdown(sel);
    });
  }

  // Reposition on scroll/resize
  function onScrollResize() {
    if (!activeDropdown || !activePanel) return;
    positionPanel(activeDropdown, activePanel);
  }
  window.addEventListener('scroll', onScrollResize, true);
  window.addEventListener('resize', onScrollResize);

  // Close on outside click
  document.addEventListener('click', function (e) {
    if (!activeDropdown) return;
    if (activeDropdown.contains(e.target) || (activePanel && activePanel.contains(e.target))) return;
    activeDropdown.classList.remove(OPEN_CLASS);
    if (activePanel) activePanel.classList.remove(OPEN_CLASS);
    var t = activeDropdown.querySelector('.cs-trigger');
    if (t) t.setAttribute('aria-expanded', 'false');
    activeDropdown = null;
    activePanel = null;
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && activeDropdown) {
      var t = activeDropdown.querySelector('.cs-trigger');
      activeDropdown.classList.remove(OPEN_CLASS);
      if (activePanel) activePanel.classList.remove(OPEN_CLASS);
      if (t) { t.setAttribute('aria-expanded', 'false'); t.focus(); }
      activeDropdown = null;
      activePanel = null;
    }
  });

  window.csEnhance = enhance;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { enhance(); });
  } else {
    enhance();
  }
})();
