/* TightShip App (vanilla) — no build step, no dependencies.
 * Security model: WP cookie auth + REST nonce.
 */

(() => {
  'use strict';

  const root = document.getElementById('tightship-app');
  if (!root) return;

  const cfg = (window.TightShipApp && (window.TightShipApp.config || window.TightShipApp)) || {};
  const API_BASE = String(cfg.restUrl || '').replace(/\/+$/, '');
  const APP_URL = String(cfg.appUrl || '');
  const LOGIN_URL = String(cfg.loginUrl || '');

  const STATUS_FLOW = [
    'inbox',
    'draft',
    'review',
    'approved',
    'submitted',
    'awarded',
    'reporting',
    'closed',
  ];

  const STATUS_LABEL = {
    inbox: 'Inbox',
    draft: 'Draft',
    review: 'Review',
    approved: 'Approved',
    submitted: 'Submitted',
    awarded: 'Awarded',
    reporting: 'Reporting',
    closed: 'Closed',
  };

  const EVENT_TYPES = {
    deadline: { label: 'Deadline', icon: '⏰' },
    meeting: { label: 'Meeting', icon: '👥' },
    reminder: { label: 'Reminder', icon: '🔔' },
    task: { label: 'Milestone', icon: '🎯' },
    call: { label: 'Call', icon: '📞' },
  };

  const PRIORITY_LABEL = { low: 'Low', medium: 'Medium', high: 'High' };

  const state = {
    booted: false,
    mode: String(root.dataset.mode || cfg.mode || 'dashboard'),
    nonce: String(cfg.nonce || ''),
    user: null,
    caps: { access: false, manage: false },
    route: parseRoute(),

    overlay: null, // {title, contentEl, actions[]}
    toastId: 0,

    // data caches
    overview: null,
    activity: null,

    spaces: null,
    rules: null,
    projects: null,
    proposals: null,
    contacts: null,
    templates: null,

    // documents view state
    documents: null,
    docSelected: null,
    docFilter: { status: '', space_id: '', search: '' },

    // calendar state
    cal: { month: new Date(), view: 'month', events: null, includeDone: false },

    // ui
    busy: false,
    error: null,
  };

  if (!API_BASE) {
    renderFatal('TightShip misconfigured: REST base URL missing.');
    return;
  }

  // ----------------------------
  // Utilities
  // ----------------------------

  function parseRoute() {
    const h = String(window.location.hash || '');
    const m = h.match(/^#\/(.+)$/);
    const path = m ? m[1] : 'command';
    const [section, id] = path.split('/');
    return { section: section || 'command', id: id || '' };
  }

  function setRoute(section, id = '') {
    const clean = String(section || 'command');
    const frag = id ? `#/${clean}/${encodeURIComponent(String(id))}` : `#/${clean}`;
    if (window.location.hash !== frag) window.location.hash = frag;
    state.route = parseRoute();
    render();
    void loadForRoute();
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function fmtDateTime(mysqlOrIso) {
    if (!mysqlOrIso) return '';
    const s = String(mysqlOrIso).replace(' ', 'T');
    const d = new Date(s);
    if (Number.isNaN(d.getTime())) return String(mysqlOrIso);
    return d.toLocaleString(undefined, {
      year: 'numeric', month: 'short', day: '2-digit',
      hour: '2-digit', minute: '2-digit'
    });
  }

  function fmtDate(mysqlOrIso) {
    if (!mysqlOrIso) return '';
    const s = String(mysqlOrIso).replace(' ', 'T');
    const d = new Date(s);
    if (Number.isNaN(d.getTime())) return String(mysqlOrIso);
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: '2-digit' });
  }

  function pad2(n){ return String(n).padStart(2,'0'); }

  function monthKey(d){
    return `${d.getFullYear()}-${pad2(d.getMonth()+1)}`;
  }

  function monthRange(d) {
    const year = d.getFullYear();
    const month = d.getMonth();
    const start = new Date(year, month, 1);
    const end = new Date(year, month + 1, 0);
    // mysql-ish
    const startStr = `${start.getFullYear()}-${pad2(start.getMonth()+1)}-${pad2(start.getDate())} 00:00:00`;
    const endStr = `${end.getFullYear()}-${pad2(end.getMonth()+1)}-${pad2(end.getDate())} 23:59:59`;
    return { start: startStr, end: endStr };
  }

  function classNames(...parts) {
    return parts.filter(Boolean).join(' ');
  }

  function el(tag, attrs, ...children) {
    const node = document.createElement(tag);
    if (attrs && typeof attrs === 'object') {
      for (const [k, v] of Object.entries(attrs)) {
        if (v === null || typeof v === 'undefined') continue;
        if (k === 'class') node.className = String(v);
        else if (k === 'html') node.innerHTML = String(v);
        else if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.substring(2), v);
        else if (k === 'dataset' && v && typeof v === 'object') {
          for (const [dk, dv] of Object.entries(v)) node.dataset[dk] = String(dv);
        } else node.setAttribute(k, String(v));
      }
    }
    for (const c of children.flat()) {
      if (c === null || typeof c === 'undefined') continue;
      if (typeof c === 'string') node.appendChild(document.createTextNode(c));
      else node.appendChild(c);
    }
    return node;
  }

  function toast(kind, message) {
    const area = document.querySelector('.ts-toasts');
    if (!area) return;
    const id = ++state.toastId;
    const t = el('div', { class: `ts-toast ts-toast--${kind}`, dataset: { id } },
      el('div', { class: 'ts-toast__msg' }, message),
      el('button', { class: 'ts-toast__x', type: 'button', onclick: () => removeToast(id) }, '×')
    );
    area.appendChild(t);
    setTimeout(() => removeToast(id), 5000);
  }

  function removeToast(id) {
    const area = document.querySelector('.ts-toasts');
    if (!area) return;
    const node = area.querySelector(`[data-id="${id}"]`);
    if (node) node.remove();
  }

  function apiUrl(path, params = null) {
    let url = `${API_BASE}${path.startsWith('/') ? '' : '/'}${path}`;
    if (params && typeof params === 'object') {
      const qs = new URLSearchParams();
      for (const [k, v] of Object.entries(params)) {
        if (v === null || typeof v === 'undefined' || String(v) === '') continue;
        qs.set(k, String(v));
      }
      const q = qs.toString();
      if (q) url += `?${q}`;
    }
    return url;
  }

  async function apiFetch(path, { method = 'GET', json = null, form = null, params = null } = {}) {
    const headers = {};
    if (state.nonce) headers['X-WP-Nonce'] = state.nonce;

    let body;
    if (form instanceof FormData) {
      body = form;
    } else if (json !== null) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(json);
    }

    const res = await fetch(apiUrl(path, params), {
      method,
      headers,
      body,
      credentials: 'same-origin',
    });

    const contentType = res.headers.get('content-type') || '';
    let data;
    try {
      if (contentType.includes('application/json')) data = await res.json();
      else data = await res.text();
    } catch (e) {
      data = null;
    }

    if (!res.ok) {
      const msg = (data && data.message) ? data.message : (typeof data === 'string' ? data : `HTTP ${res.status}`);
      const err = new Error(msg);
      err.status = res.status;
      err.data = data;
      throw err;
    }

    return data;
  }

  function openModal(title, contentEl, actions = []) {
    state.overlay = { title, contentEl, actions };
    render();
  }

  function closeModal() {
    state.overlay = null;
    render();
  }

  // ----------------------------
  // Boot
  // ----------------------------

  window.addEventListener('hashchange', () => {
    state.route = parseRoute();
    render();
    void loadForRoute();
  });

  void boot();

  async function boot() {
    state.booted = true;
    render();

    if (state.mode === 'login') {
      // If already logged in, bounce to app.
      try {
        const me = await apiFetch('/auth/me');
        if (me && me.ok && APP_URL) {
          window.location.href = APP_URL;
          return;
        }
      } catch (_) {
        // ignore
      }
      render();
      return;
    }

    // dashboard mode
    try {
      const me = await apiFetch('/auth/me');
      if (me && me.ok) {
        state.user = me.user || null;
        state.caps = me.caps || { access: false, manage: false };
        if (me.nonce) state.nonce = String(me.nonce);
      }
    } catch (e) {
      state.user = null;
      state.caps = { access: false, manage: false };
    }

    if (!state.user) {
      render();
      return;
    }

    await loadForRoute();
    render();
  }

  async function loadForRoute() {
    if (!state.user) return;

    const section = state.route.section || 'command';

    if (section === 'command') return loadCommandCenter();
    if (section === 'inbox' || section === 'documents' || section === 'files') return loadDocuments();
    if (section === 'spaces') return loadSpaces();
    if (section === 'rules') return loadRules();
    if (section === 'calendar') return loadCalendar();
    if (section === 'projects') return loadProjects();
    if (section === 'proposals') return loadProposals();
    if (section === 'crm' || section === 'contacts') return loadContacts();
    if (section === 'templates') return loadTemplates();
    if (section === 'reports') return loadCommandCenter();
  }

  // ----------------------------
  // Data loaders
  // ----------------------------

  async function loadCommandCenter() {
    if (state.busy) return;
    state.busy = true;
    render();
    try {
      const [overview, activity] = await Promise.all([
        apiFetch('/reports/overview'),
        apiFetch('/audit/recent', { params: { limit: 20 } }).catch(() => null),
      ]);
      state.overview = overview && overview.ok ? overview : null;
      state.activity = activity && activity.ok ? activity.activity : null;
    } catch (e) {
      state.error = e.message || String(e);
      toast('error', state.error);
    } finally {
      state.busy = false;
      render();
    }
  }

  async function loadSpaces() {
    if (state.spaces) return;
    try {
      const res = await apiFetch('/spaces');
      state.spaces = res && res.ok ? res.spaces || [] : [];
    } catch (e) {
      toast('error', e.message || 'Failed loading spaces');
      state.spaces = [];
    }
  }

  async function loadRules() {
    if (state.rules) return;
    await loadSpaces();
    try {
      const res = await apiFetch('/rules');
      state.rules = res && res.ok ? res.rules || [] : [];
    } catch (e) {
      toast('error', e.message || 'Failed loading rules');
      state.rules = [];
    }
  }

  async function loadDocuments(force = false) {
    if (state.busy) return;
    if (state.documents && !force) return;

    state.busy = true;
    render();
    await loadSpaces();

    try {
      const params = {
        status: state.docFilter.status,
        space_id: state.docFilter.space_id,
        search: state.docFilter.search,
        limit: 80,
      };
      const res = await apiFetch('/documents', { params });
      state.documents = res && res.ok ? res.documents || [] : [];

      // If route points to a document, load its details.
      const id = state.route.id ? parseInt(state.route.id, 10) : 0;
      if (id > 0) {
        await selectDocument(id);
      } else {
        state.docSelected = null;
      }
    } catch (e) {
      toast('error', e.message || 'Failed loading documents');
      state.documents = [];
    } finally {
      state.busy = false;
      render();
    }
  }

  async function selectDocument(id) {
    if (!id || Number.isNaN(id)) return;
    try {
      const res = await apiFetch(`/documents/${id}`);
      state.docSelected = res && res.ok ? res.document || null : null;
    } catch (e) {
      toast('error', e.message || 'Failed loading document');
      state.docSelected = null;
    }
  }

  async function loadCalendar(force = false) {
    if (state.busy) return;
    const key = monthKey(state.cal.month);
    if (state.cal.events && state.cal.events._key === key && !force) return;

    state.busy = true;
    render();

    try {
      const range = monthRange(state.cal.month);
      const res = await apiFetch('/events', {
        params: {
          start: range.start,
          end: range.end,
          include_done: state.cal.includeDone ? 1 : 0,
        }
      });
      const events = res && res.ok ? (res.events || []) : [];
      events._key = key;
      state.cal.events = events;
    } catch (e) {
      toast('error', e.message || 'Failed loading events');
      state.cal.events = [];
      state.cal.events._key = key;
    } finally {
      state.busy = false;
      render();
    }
  }

  async function loadProjects() {
    if (state.projects) return;
    try {
      const res = await apiFetch('/projects');
      state.projects = res && res.ok ? res.projects || [] : [];
    } catch (e) {
      toast('error', e.message || 'Failed loading projects');
      state.projects = [];
    }
  }

  async function loadProposals() {
    if (state.proposals) return;
    try {
      const res = await apiFetch('/proposals');
      state.proposals = res && res.ok ? res.proposals || [] : [];
    } catch (e) {
      toast('error', e.message || 'Failed loading proposals');
      state.proposals = [];
    }
  }

  async function loadContacts() {
    if (state.contacts) return;
    try {
      const res = await apiFetch('/contacts');
      state.contacts = res && res.ok ? res.contacts || [] : [];
    } catch (e) {
      toast('error', e.message || 'Failed loading contacts');
      state.contacts = [];
    }
  }

  async function loadTemplates() {
    if (state.templates) return;
    try {
      const res = await apiFetch('/templates');
      state.templates = res && res.ok ? res.templates || [] : [];
    } catch (e) {
      toast('error', e.message || 'Failed loading templates');
      state.templates = [];
    }
  }

  // ----------------------------
  // Actions
  // ----------------------------

  async function doLogin(username, password, remember) {
    state.busy = true;
    render();
    try {
      const res = await apiFetch('/auth/login', {
        method: 'POST',
        json: { username, password, remember: !!remember },
      });

      if (res && res.ok) {
        state.user = res.user || null;
        state.caps = res.caps || { access: false, manage: false };
        if (res.nonce) state.nonce = String(res.nonce);
        toast('ok', `Welcome, ${state.user ? state.user.display_name : 'captain'}.`);
        if (APP_URL) window.location.href = APP_URL;
        else window.location.reload();
      }
    } catch (e) {
      toast('error', e.message || 'Login failed');
    } finally {
      state.busy = false;
      render();
    }
  }

  async function doLogout() {
    try {
      await apiFetch('/auth/logout', { method: 'POST' });
    } catch (_) {
      // ignore
    }
    window.location.href = LOGIN_URL || (cfg.homeUrl || '/');
  }

  async function doUploadDocument(file, title, notes) {
    if (!file) return;
    const form = new FormData();
    form.append('file', file, file.name);
    if (title) form.append('meta[title]', title);
    if (notes) form.append('meta[notes]', notes);

    state.busy = true;
    render();

    try {
      const res = await apiFetch('/documents/upload', { method: 'POST', form });
      if (res && res.ok && res.document) {
        toast('ok', 'Uploaded to Inbox.');
        state.documents = null;
        state.docSelected = res.document;
        setRoute('documents', String(res.document.id));
      }
    } catch (e) {
      toast('error', e.message || 'Upload failed');
    } finally {
      state.busy = false;
      render();
    }
  }

  async function doAddRevision(documentId, file, notes) {
    if (!documentId || !file) return;
    const form = new FormData();
    form.append('file', file, file.name);
    if (notes) form.append('meta[notes]', notes);

    state.busy = true;
    render();

    try {
      const res = await apiFetch(`/documents/${documentId}/revision`, { method: 'POST', form });
      if (res && res.ok && res.document) {
        toast('ok', 'Revision added.');
        state.docSelected = res.document;
        state.documents = null;
        await loadDocuments(true);
      }
    } catch (e) {
      toast('error', e.message || 'Revision failed');
    } finally {
      state.busy = false;
      render();
    }
  }

  async function doUpdateStatus(documentId, status, moveSpaceId = null) {
    if (!documentId || !status) return;

    state.busy = true;
    render();

    try {
      const payload = { status };
      if (moveSpaceId) payload.space_id = moveSpaceId;
      const res = await apiFetch(`/documents/${documentId}/status`, { method: 'POST', json: payload });
      if (res && res.ok && res.document) {
        toast('ok', `Status → ${STATUS_LABEL[status] || status}`);
        state.docSelected = res.document;
        state.documents = null;
        await loadDocuments(true);
      }
    } catch (e) {
      toast('error', e.message || 'Status update failed');
    } finally {
      state.busy = false;
      render();
    }
  }

  async function doAutoroute() {
    state.busy = true;
    render();

    try {
      const res = await apiFetch('/documents/autoroute', { method: 'POST', json: {} });
      if (res && res.ok) {
        toast('ok', `Auto-route complete: ${res.moved || 0} moved.`);
        state.documents = null;
        await loadDocuments(true);
      }
    } catch (e) {
      toast('error', e.message || 'Auto-route failed');
    } finally {
      state.busy = false;
      render();
    }
  }

  async function doCreateSpace(name, description) {
    state.busy = true;
    render();

    try {
      const res = await apiFetch('/spaces', {
        method: 'POST',
        json: { name, description },
      });
      if (res && res.ok) {
        toast('ok', 'Space created.');
        state.spaces = null;
        await loadSpaces();
        closeModal();
        render();
      }
    } catch (e) {
      toast('error', e.message || 'Create space failed');
    } finally {
      state.busy = false;
      render();
    }
  }

  async function doCreateRule(payload) {
    state.busy = true;
    render();

    try {
      const res = await apiFetch('/rules', { method: 'POST', json: payload });
      if (res && res.ok) {
        toast('ok', 'Rule created.');
        state.rules = null;
        await loadRules();
        closeModal();
        render();
      }
    } catch (e) {
      toast('error', e.message || 'Create rule failed');
    } finally {
      state.busy = false;
      render();
    }
  }

  async function doCreateEvent(payload) {
    state.busy = true;
    render();

    try {
      const res = await apiFetch('/events', { method: 'POST', json: payload });
      if (res && res.ok) {
        toast('ok', 'Event created.');
        state.cal.events = null;
        await loadCalendar(true);
        closeModal();
        render();
      }
    } catch (e) {
      toast('error', e.message || 'Create event failed');
    } finally {
      state.busy = false;
      render();
    }
  }

  async function doToggleEventDone(id, done) {
    try {
      await apiFetch(`/events/${id}/done`, { method: 'POST', json: { is_done: done ? 1 : 0 } });
      toast('ok', done ? 'Marked done.' : 'Marked active.');
      state.cal.events = null;
      await loadCalendar(true);
      render();
    } catch (e) {
      toast('error', e.message || 'Update failed');
    }
  }

  // ----------------------------
  // Render
  // ----------------------------

  function renderFatal(message) {
    root.innerHTML = '';
    root.appendChild(
      el('div', { class: 'ts-card' },
        el('h2', {}, 'TightShip'),
        el('p', {}, message)
      )
    );
  }

  function render() {
    // Clear placeholder from PHP render.
    root.innerHTML = '';

    const toasts = el('div', { class: 'ts-toasts' });

    if (state.mode === 'login') {
      root.appendChild(renderLoginPage());
      root.appendChild(toasts);
      return;
    }

    if (!state.user) {
      root.appendChild(renderNeedLogin());
      root.appendChild(toasts);
      return;
    }

    root.appendChild(renderAppShell());
    root.appendChild(toasts);

    if (state.overlay) {
      root.appendChild(renderModal());
    }
  }

  function renderNeedLogin() {
    const login = LOGIN_URL ? LOGIN_URL : (cfg.homeUrl || '/wp-login.php');
    const href = login ? login : '/';
    return el('div', { class: 'ts-loginShell' },
      el('div', { class: 'ts-loginCard' },
        el('div', { class: 'ts-loginTop' },
          el('div', { class: 'ts-brand' },
            el('div', { class: 'ts-logo' }, 'TS'),
            el('div', {},
              el('div', { class: 'ts-brandTitle' }, 'TightShip Workspace'),
              el('div', { class: 'ts-brandSub' }, 'Inbox-zero document control, EU workflow, reminders, routing rules')
            )
          )
        ),
        el('div', { class: 'ts-loginForm' },
          el('div', { class: 'ts-alert' },
            el('div', { class: 'ts-alert__title' }, 'Login required'),
            el('div', { class: 'ts-alert__text' }, 'Your session is not authenticated. Use the login page to start the workspace.')
          ),
          el('a', { class: 'ts-btn ts-btn-primary', href }, 'Open Login')
        )
      )
    );
  }

  function renderLoginPage() {
    const wrap = el('div', { class: 'ts-loginShell' });

    const left = el('div', { class: 'ts-loginLeft' },
      el('div', { class: 'ts-brand' },
        el('div', { class: 'ts-logo' }, 'TS'),
        el('div', {},
          el('div', { class: 'ts-brandTitle' }, 'TightShip Workspace'),
          el('div', { class: 'ts-brandSub' }, 'Stop making “final-final-last.docx”. Ship revisions, not chaos.')
        )
      ),
      el('div', { class: 'ts-featureGrid' },
        feature('📥', 'File Inbox', 'Upload landing zone + auto-route rules'),
        feature('🧭', 'Command Center', 'Inbox Zero meter, ship list, next deadlines'),
        feature('🧾', 'Revisions', 'Immutable history: r1, r2, r3…'),
        feature('📅', 'Calendar', 'Deadlines, meetings, reminders & priorities')
      ),
      el('div', { class: 'ts-mutedSmall' }, 'Cookie auth + REST nonce. Nothing is stored in the browser beyond your session.')
    );

    const form = el('form', {
      class: 'ts-form',
      onsubmit: (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const username = String(fd.get('username') || '').trim();
        const password = String(fd.get('password') || '');
        const remember = !!fd.get('remember');
        if (!username || !password) {
          toast('warn', 'Enter username/email and password.');
          return;
        }
        void doLogin(username, password, remember);
      }
    },
      el('h2', { class: 'ts-h2' }, 'Log in'),
      el('div', { class: 'ts-mutedSmall' }, 'Use your WordPress account. (Email also works.)'),
      el('label', { class: 'ts-label' }, 'Username or email'),
      el('input', { class: 'ts-input', name: 'username', type: 'text', autocomplete: 'username' }),
      el('label', { class: 'ts-label' }, 'Password'),
      el('input', { class: 'ts-input', name: 'password', type: 'password', autocomplete: 'current-password' }),
      el('label', { class: 'ts-check' },
        el('input', { type: 'checkbox', name: 'remember', value: '1' }),
        el('span', {}, 'Remember me')
      ),
      el('button', { class: 'ts-btn ts-btn-primary', type: 'submit', disabled: state.busy ? 'disabled' : null }, state.busy ? 'Logging in…' : 'Log in'),
      el('div', { class: 'ts-mutedSmall' }, 'If this fails, your WP install might block REST login. Use wp-login.php and refresh.')
    );

    const right = el('div', { class: 'ts-loginRight' },
      el('div', { class: 'ts-loginCard' },
        el('div', { class: 'ts-loginTop' },
          el('div', { class: 'ts-badge' }, 'Secure Workspace')
        ),
        el('div', { class: 'ts-loginForm' },
          form
        )
      )
    );

    wrap.appendChild(el('div', { class: 'ts-loginInner' }, left, right));
    return wrap;
  }

  function feature(icon, title, text) {
    return el('div', { class: 'ts-feature' },
      el('div', { class: 'ts-feature__icon' }, icon),
      el('div', {},
        el('div', { class: 'ts-feature__title' }, title),
        el('div', { class: 'ts-feature__text' }, text)
      )
    );
  }

  function renderAppShell() {
    const shell = el('div', { class: 'ts-shell' });

    shell.appendChild(
      el('div', { class: 'ts-sidebar' },
        el('div', { class: 'ts-sideTop' },
          el('div', { class: 'ts-brand' },
            el('div', { class: 'ts-logo' }, 'TS'),
            el('div', {},
              el('div', { class: 'ts-brandTitle' }, 'TightShip'),
              el('div', { class: 'ts-brandSub' }, state.user ? state.user.display_name : '')
            )
          )
        ),
        navItem('command', '🧭', 'Command Center'),
        navItem('documents', '📥', 'File Inbox'),
        navItem('calendar', '📅', 'Calendar'),
        navItem('spaces', '🗂️', 'Spaces'),
        navItem('rules', '🧩', 'Auto-Route Rules'),
        navItem('projects', '🧱', 'Projects'),
        navItem('proposals', '🧾', 'Proposals'),
        navItem('contacts', '👥', 'CRM'),
        navItem('templates', '📄', 'Templates'),
        navItem('reports', '📊', 'Reports'),
        el('div', { class: 'ts-sideBottom' },
          el('button', { class: 'ts-btn ts-btn-ghost ts-btn-block', type: 'button', onclick: () => void doLogout() }, 'Log out')
        )
      )
    );

    shell.appendChild(
      el('div', { class: 'ts-main' },
        renderTopbar(),
        el('div', { class: 'ts-content' }, renderView())
      )
    );

    return shell;
  }

  function navItem(section, icon, label) {
    const active = state.route.section === section || (section === 'documents' && (state.route.section === 'inbox' || state.route.section === 'files'));
    return el('button', {
      class: classNames('ts-nav', active ? 'is-active' : ''),
      type: 'button',
      onclick: () => setRoute(section),
    },
      el('span', { class: 'ts-nav__icon' }, icon),
      el('span', { class: 'ts-nav__label' }, label)
    );
  }

  function renderTopbar() {
    const search = el('input', {
      class: 'ts-search',
      type: 'search',
      placeholder: 'Search documents…',
      value: state.docFilter.search,
      oninput: (ev) => {
        state.docFilter.search = String(ev.target.value || '');
      },
      onkeydown: (ev) => {
        if (ev.key === 'Enter') {
          ev.preventDefault();
          state.documents = null;
          setRoute('documents');
          void loadDocuments(true);
        }
      }
    });

    const badge = el('div', { class: 'ts-topBadge' },
      state.caps.manage ? 'Captain' : 'Crew'
    );

    return el('div', { class: 'ts-topbar' },
      el('div', { class: 'ts-topLeft' },
        el('div', { class: 'ts-pageTitle' }, pageTitle()),
        state.busy ? el('div', { class: 'ts-dotPulse', title: 'Working…' }) : el('div', { class: 'ts-dotIdle', title: 'Ready' })
      ),
      el('div', { class: 'ts-topRight' },
        search,
        badge
      )
    );
  }

  function pageTitle() {
    const s = state.route.section;
    const map = {
      command: 'Command Center',
      documents: 'File Inbox',
      inbox: 'File Inbox',
      files: 'Files',
      calendar: 'Calendar',
      spaces: 'Spaces',
      rules: 'Auto-Route Rules',
      projects: 'Projects',
      proposals: 'Proposals',
      contacts: 'CRM',
      crm: 'CRM',
      templates: 'Templates',
      reports: 'Reports',
    };
    return map[s] || 'TightShip';
  }

  function renderView() {
    const section = state.route.section || 'command';

    if (section === 'command' || section === 'reports') return renderCommandCenter();
    if (section === 'documents' || section === 'inbox' || section === 'files') return renderDocuments();
    if (section === 'spaces') return renderSpaces();
    if (section === 'rules') return renderRules();
    if (section === 'calendar') return renderCalendar();
    if (section === 'projects') return renderProjects();
    if (section === 'proposals') return renderProposals();
    if (section === 'contacts' || section === 'crm') return renderContacts();
    if (section === 'templates') return renderTemplates();

    return el('div', { class: 'ts-card' }, el('h3', {}, 'Not found'));
  }

  function renderCommandCenter() {
    const ov = state.overview && state.overview.ok ? state.overview : null;
    const docsByStatus = (ov && ov.documents_by_status) ? ov.documents_by_status : {};

    const inboxCount = Number(docsByStatus.inbox || 0);
    const reviewCount = Number(docsByStatus.review || 0);
    const approvedCount = Number(docsByStatus.approved || 0);

    const dueThisWeek = Number(ov && ov.due_next_7d ? ov.due_next_7d : 0);

    const meterPct = Math.max(0, Math.min(100, 100 - Math.min(100, inboxCount * 7)));

    const cards = el('div', { class: 'ts-grid' },
      statCard('Inbox Zero', `${meterPct}%`, `${inboxCount} waiting`, meterPct),
      statCard('Drafts in Review', `${reviewCount}`, 'Stuck? push forward.'),
      statCard('Ship List', `${approvedCount}`, 'Approved & ready to submit'),
      statCard('Due This Week', `${dueThisWeek}`, 'Deadlines in next 7 days')
    );

    const nextDeadlines = (ov && Array.isArray(ov.next_deadlines)) ? ov.next_deadlines : [];
    const shipList = (ov && Array.isArray(ov.ship_list)) ? ov.ship_list : [];

    const activity = Array.isArray(state.activity) ? state.activity : [];

    return el('div', {},
      cards,
      el('div', { class: 'ts-grid2' },
        el('div', { class: 'ts-card' },
          el('div', { class: 'ts-card__head' },
            el('h3', { class: 'ts-h3' }, 'Next Deadlines'),
            el('button', { class: 'ts-btn ts-btn-ghost', type: 'button', onclick: () => setRoute('calendar') }, 'Open Calendar')
          ),
          nextDeadlines.length
            ? listTable(nextDeadlines.map(e => ({
                left: `${EVENT_TYPES[e.type]?.icon || '•'} ${e.title}`,
                right: fmtDateTime(e.start_at),
                meta: `${EVENT_TYPES[e.type]?.label || e.type} · ${PRIORITY_LABEL[e.priority] || e.priority}`,
              })))
            : el('div', { class: 'ts-empty' }, 'No upcoming deadlines in the next month.')
        ),
        el('div', { class: 'ts-card' },
          el('div', { class: 'ts-card__head' },
            el('h3', { class: 'ts-h3' }, 'Recent Activity'),
            el('button', { class: 'ts-btn ts-btn-ghost', type: 'button', onclick: () => setRoute('documents') }, 'Open Inbox')
          ),
          activity.length
            ? listTable(activity.map(a => ({
                left: `${a.action.replace(/_/g,' ')}`,
                right: fmtDateTime(a.created_at),
                meta: `${a.actor_name || 'user'} · ${a.entity_type}#${a.entity_id}`,
              })))
            : el('div', { class: 'ts-empty' }, 'No activity yet. Start by uploading files.')
        )
      ),
      el('div', { class: 'ts-card ts-card--soft' },
        el('div', { class: 'ts-card__head' },
          el('h3', { class: 'ts-h3' }, 'Quick Actions')
        ),
        el('div', { class: 'ts-actionsRow' },
          el('button', { class: 'ts-btn ts-btn-primary', type: 'button', onclick: () => setRoute('documents') }, 'Upload & Route'),
          el('button', { class: 'ts-btn ts-btn-ghost', type: 'button', onclick: () => setRoute('rules') }, 'Manage Rules'),
          el('button', { class: 'ts-btn ts-btn-ghost', type: 'button', onclick: () => setRoute('spaces') }, 'Create Space')
        ),
        shipList.length ? el('div', { class: 'ts-sub' },
          el('h4', { class: 'ts-h4' }, 'Ship List'),
          listTable(shipList.map(d => ({
            left: d.title,
            right: fmtDateTime(d.updated_at),
            meta: `Status: ${STATUS_LABEL[d.status] || d.status}`,
            onclick: () => setRoute('documents', String(d.id)),
          })))
        ) : el('div', { class: 'ts-empty' }, 'No approved items yet. Push documents through review.')
      )
    );
  }

  function statCard(title, value, subtitle, meterPct = null) {
    const meter = (meterPct === null)
      ? null
      : el('div', { class: 'ts-meter' },
          el('div', { class: 'ts-meter__bar', style: `width:${Math.max(0, Math.min(100, meterPct))}%` })
        );

    return el('div', { class: 'ts-card' },
      el('div', { class: 'ts-stat' },
        el('div', { class: 'ts-stat__k' }, title),
        el('div', { class: 'ts-stat__v' }, value),
        el('div', { class: 'ts-stat__s' }, subtitle)
      ),
      meter
    );
  }

  function listTable(items) {
    const wrap = el('div', { class: 'ts-list' });
    items.forEach((it) => {
      const row = el('div', { class: 'ts-row', onclick: typeof it.onclick === 'function' ? it.onclick : null },
        el('div', { class: 'ts-row__left' },
          el('div', { class: 'ts-row__title' }, it.left),
          it.meta ? el('div', { class: 'ts-row__meta' }, it.meta) : null
        ),
        el('div', { class: 'ts-row__right' }, it.right)
      );
      wrap.appendChild(row);
    });
    return wrap;
  }

  function renderDocuments() {
    const docs = Array.isArray(state.documents) ? state.documents : [];

    const header = el('div', { class: 'ts-card ts-card--soft' },
      el('div', { class: 'ts-card__head' },
        el('h3', { class: 'ts-h3' }, 'File Inbox'),
        el('div', { class: 'ts-actionsRow' },
          el('button', { class: 'ts-btn ts-btn-primary', type: 'button', onclick: () => openUploadModal() }, 'Upload'),
          el('button', { class: 'ts-btn ts-btn-ghost', type: 'button', onclick: () => void doAutoroute() }, 'Auto-Route')
        )
      ),
      el('div', { class: 'ts-filters' },
        filterSelect('Status', 'status', STATUS_FLOW.map(s => ({ value: s, label: STATUS_LABEL[s] }))),
        filterSpaces(),
        el('button', { class: 'ts-btn ts-btn-ghost', type: 'button', onclick: () => { state.docFilter = { status: '', space_id: '', search: '' }; state.documents=null; void loadDocuments(true); render(); } }, 'Reset')
      )
    );

    const list = el('div', { class: 'ts-card' },
      el('div', { class: 'ts-card__head' },
        el('h3', { class: 'ts-h3' }, `Documents (${docs.length})`),
        el('button', { class: 'ts-btn ts-btn-ghost', type: 'button', onclick: () => { state.documents=null; void loadDocuments(true); } }, 'Refresh')
      ),
      docs.length
        ? renderDocumentsTable(docs)
        : el('div', { class: 'ts-empty' }, 'Inbox is empty. Upload a file to start.')
    );

    const detail = el('div', { class: 'ts-card' },
      el('div', { class: 'ts-card__head' },
        el('h3', { class: 'ts-h3' }, 'Document')
      ),
      state.docSelected ? renderDocumentDetail(state.docSelected) : el('div', { class: 'ts-empty' }, 'Select a document to view details.')
    );

    const grid = el('div', { class: 'ts-split' },
      el('div', {}, header, list),
      el('div', {}, detail)
    );

    // Trigger initial load if needed
    if (state.documents === null && !state.busy) {
      void loadDocuments(true);
    }

    return grid;
  }

  function filterSelect(label, key, options) {
    const sel = el('select', {
      class: 'ts-select',
      onchange: (ev) => {
        state.docFilter[key] = String(ev.target.value || '');
        state.documents = null;
        void loadDocuments(true);
      }
    },
      el('option', { value: '' }, 'All')
    );

    (options || []).forEach(o => sel.appendChild(el('option', { value: o.value }, o.label)));

    sel.value = String(state.docFilter[key] || '');

    return el('div', { class: 'ts-filter' },
      el('div', { class: 'ts-filter__k' }, label),
      sel
    );
  }

  function filterSpaces() {
    const spaces = Array.isArray(state.spaces) ? state.spaces : [];

    const sel = el('select', {
      class: 'ts-select',
      onchange: (ev) => {
        state.docFilter.space_id = String(ev.target.value || '');
        state.documents = null;
        void loadDocuments(true);
      }
    },
      el('option', { value: '' }, 'All')
    );

    spaces.forEach(s => sel.appendChild(el('option', { value: String(s.id) }, s.name)));
    sel.value = String(state.docFilter.space_id || '');

    return el('div', { class: 'ts-filter' },
      el('div', { class: 'ts-filter__k' }, 'Space'),
      sel
    );
  }

  function renderDocumentsTable(docs) {
    const wrap = el('div', { class: 'ts-docTable' });

    docs.forEach((d) => {
      const row = el('button', {
        class: classNames('ts-docRow', state.docSelected && state.docSelected.id === d.id ? 'is-active' : ''),
        type: 'button',
        onclick: () => {
          setRoute('documents', String(d.id));
          void selectDocument(d.id).then(render);
        }
      },
        el('div', { class: 'ts-docRow__main' },
          el('div', { class: 'ts-docRow__title' }, d.title),
          el('div', { class: 'ts-docRow__meta' },
            `${STATUS_LABEL[d.status] || d.status} · `,
            d.project_code ? `Project ${d.project_code} · ` : '',
            fmtDateTime(d.updated_at)
          )
        ),
        el('div', { class: 'ts-pillWrap' },
          el('span', { class: `ts-pill ts-pill--${d.status}` }, STATUS_LABEL[d.status] || d.status)
        )
      );
      wrap.appendChild(row);
    });

    return wrap;
  }

  function renderDocumentDetail(doc) {
    const spaces = Array.isArray(state.spaces) ? state.spaces : [];
    const spaceName = spaces.find(s => s.id === doc.space_id)?.name || `Space #${doc.space_id}`;

    const downloadUrl = doc.attachment && doc.attachment.url ? String(doc.attachment.url) : '';

    const step = STATUS_FLOW.indexOf(doc.status);
    const progress = step >= 0 ? ((step + 1) / STATUS_FLOW.length) * 100 : 12;

    const statusSelect = el('select', {
      class: 'ts-select',
      onchange: (ev) => {
        const next = String(ev.target.value || '');
        if (!next) return;
        void doUpdateStatus(doc.id, next);
      }
    });
    STATUS_FLOW.forEach(s => statusSelect.appendChild(el('option', { value: s }, STATUS_LABEL[s] || s)));
    statusSelect.value = doc.status;

    const moveSpaceSelect = el('select', { class: 'ts-select' },
      el('option', { value: '' }, 'Move to…')
    );
    spaces.forEach(s => moveSpaceSelect.appendChild(el('option', { value: String(s.id) }, s.name)));

    const moveBtn = el('button', {
      class: 'ts-btn ts-btn-ghost',
      type: 'button',
      onclick: () => {
        const sid = parseInt(String(moveSpaceSelect.value || '0'), 10);
        if (sid > 0) void doUpdateStatus(doc.id, doc.status, sid);
      }
    }, 'Move');

    const revisionBtn = el('button', {
      class: 'ts-btn ts-btn-ghost',
      type: 'button',
      onclick: () => openRevisionModal(doc.id)
    }, 'Add revision');

    const advanceBtn = el('button', {
      class: 'ts-btn ts-btn-primary',
      type: 'button',
      onclick: () => {
        const idx = STATUS_FLOW.indexOf(doc.status);
        const next = idx >= 0 && idx < STATUS_FLOW.length - 1 ? STATUS_FLOW[idx + 1] : doc.status;
        if (next !== doc.status) void doUpdateStatus(doc.id, next);
      }
    }, 'Advance');

    const flow = el('div', { class: 'ts-flow' },
      el('div', { class: 'ts-flow__bar' },
        el('div', { class: 'ts-flow__fill', style: `width:${progress}%` })
      ),
      el('div', { class: 'ts-flow__labels' },
        ...STATUS_FLOW.map((s, i) => el('div', { class: classNames('ts-flow__step', i <= step ? 'is-done' : '') }, STATUS_LABEL[s]))
      )
    );

    const revs = Array.isArray(doc.revisions) ? doc.revisions : [];

    return el('div', { class: 'ts-docDetail' },
      el('div', { class: 'ts-docHead' },
        el('div', {},
          el('div', { class: 'ts-docTitle' }, doc.title),
          el('div', { class: 'ts-docMeta' },
            `${spaceName} · `,
            doc.project_code ? `Project ${doc.project_code} · ` : '',
            `${doc.mime} · ${Math.round((doc.size || 0) / 1024)} KB`
          )
        ),
        el('div', { class: 'ts-actionsRow' },
          downloadUrl ? el('a', { class: 'ts-btn ts-btn-ghost', href: downloadUrl, target: '_blank', rel: 'noopener' }, 'Download') : null,
          revisionBtn,
          advanceBtn
        )
      ),

      flow,

      el('div', { class: 'ts-formRow' },
        el('div', {},
          el('div', { class: 'ts-filter__k' }, 'Status'),
          statusSelect
        ),
        el('div', {},
          el('div', { class: 'ts-filter__k' }, 'Space'),
          el('div', { class: 'ts-inline' }, moveSpaceSelect, moveBtn)
        )
      ),

      el('div', { class: 'ts-sub' },
        el('h4', { class: 'ts-h4' }, `Revisions (${revs.length})`),
        revs.length
          ? el('div', { class: 'ts-list' },
              ...revs.map(r => el('div', { class: 'ts-row' },
                el('div', { class: 'ts-row__left' },
                  el('div', { class: 'ts-row__title' }, `r${r.rev_no}`),
                  el('div', { class: 'ts-row__meta' }, r.notes ? r.notes : '—')
                ),
                el('div', { class: 'ts-row__right' }, fmtDateTime(r.created_at))
              ))
            )
          : el('div', { class: 'ts-empty' }, 'No revisions recorded.')
      )
    );
  }

  function openUploadModal() {
    const form = el('form', {
      class: 'ts-form',
      onsubmit: (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const file = fd.get('file');
        const title = String(fd.get('title') || '').trim();
        const notes = String(fd.get('notes') || '').trim();
        if (!(file instanceof File) || !file.name) {
          toast('warn', 'Choose a file.');
          return;
        }
        void doUploadDocument(file, title, notes);
        closeModal();
      }
    },
      el('label', { class: 'ts-label' }, 'File'),
      el('input', { class: 'ts-input', type: 'file', name: 'file', required: 'required' }),
      el('label', { class: 'ts-label' }, 'Title (optional)'),
      el('input', { class: 'ts-input', type: 'text', name: 'title', placeholder: 'Proposal – Project X' }),
      el('label', { class: 'ts-label' }, 'Notes (optional)'),
      el('textarea', { class: 'ts-input', name: 'notes', rows: '3', placeholder: 'What changed in this revision?' })
    );

    openModal('Upload to Inbox', form, [
      { label: 'Cancel', kind: 'ghost', onClick: closeModal },
      { label: 'Upload', kind: 'primary', onClick: () => form.requestSubmit() },
    ]);
  }

  function openRevisionModal(docId) {
    const form = el('form', {
      class: 'ts-form',
      onsubmit: (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const file = fd.get('file');
        const notes = String(fd.get('notes') || '').trim();
        if (!(file instanceof File) || !file.name) {
          toast('warn', 'Choose a file.');
          return;
        }
        void doAddRevision(docId, file, notes);
        closeModal();
      }
    },
      el('label', { class: 'ts-label' }, 'New revision file'),
      el('input', { class: 'ts-input', type: 'file', name: 'file', required: 'required' }),
      el('label', { class: 'ts-label' }, 'Notes'),
      el('textarea', { class: 'ts-input', name: 'notes', rows: '3', placeholder: 'Describe the change (review feedback, budget update, etc).' })
    );

    openModal('Add Revision', form, [
      { label: 'Cancel', kind: 'ghost', onClick: closeModal },
      { label: 'Add', kind: 'primary', onClick: () => form.requestSubmit() },
    ]);
  }

  function renderSpaces() {
    const spaces = Array.isArray(state.spaces) ? state.spaces : [];

    if (!state.spaces && !state.busy) {
      void loadSpaces().then(render);
    }

    return el('div', {},
      el('div', { class: 'ts-card ts-card--soft' },
        el('div', { class: 'ts-card__head' },
          el('h3', { class: 'ts-h3' }, 'Spaces'),
          state.caps.manage
            ? el('button', { class: 'ts-btn ts-btn-primary', type: 'button', onclick: () => openCreateSpaceModal() }, 'New Space')
            : null
        ),
        el('div', { class: 'ts-mutedSmall' }, 'Spaces are destinations for routing rules and document organization.')
      ),
      el('div', { class: 'ts-card' },
        el('div', { class: 'ts-card__head' },
          el('h3', { class: 'ts-h3' }, `All Spaces (${spaces.length})`)
        ),
        spaces.length
          ? listTable(spaces.map(s => ({ left: s.name, right: `#${s.id}`, meta: s.description || '—' })))
          : el('div', { class: 'ts-empty' }, 'No spaces found.')
      )
    );
  }

  function openCreateSpaceModal() {
    const form = el('form', {
      class: 'ts-form',
      onsubmit: (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const name = String(fd.get('name') || '').trim();
        const description = String(fd.get('description') || '').trim();
        if (!name) {
          toast('warn', 'Space name required.');
          return;
        }
        void doCreateSpace(name, description);
      }
    },
      el('label', { class: 'ts-label' }, 'Name'),
      el('input', { class: 'ts-input', name: 'name', type: 'text', required: 'required' }),
      el('label', { class: 'ts-label' }, 'Description'),
      el('textarea', { class: 'ts-input', name: 'description', rows: '3' })
    );

    openModal('Create Space', form, [
      { label: 'Cancel', kind: 'ghost', onClick: closeModal },
      { label: 'Create', kind: 'primary', onClick: () => form.requestSubmit() },
    ]);
  }

  function renderRules() {
    if (!state.rules && !state.busy) {
      void loadRules().then(render);
    }

    const rules = Array.isArray(state.rules) ? state.rules : [];
    const spaces = Array.isArray(state.spaces) ? state.spaces : [];

    return el('div', {},
      el('div', { class: 'ts-card ts-card--soft' },
        el('div', { class: 'ts-card__head' },
          el('h3', { class: 'ts-h3' }, 'Auto-Route Rules'),
          state.caps.manage
            ? el('button', { class: 'ts-btn ts-btn-primary', type: 'button', onclick: () => openCreateRuleModal() }, 'New Rule')
            : null
        ),
        el('div', { class: 'ts-mutedSmall' }, 'Rules match filenames (contains/regex) and move documents into a destination space.')
      ),
      el('div', { class: 'ts-card' },
        el('div', { class: 'ts-card__head' },
          el('h3', { class: 'ts-h3' }, `Rules (${rules.length})`)
        ),
        rules.length
          ? el('div', { class: 'ts-ruleTable' },
              ...rules.map(r => {
                const dest = spaces.find(s => s.id === r.destination_space_id)?.name || `#${r.destination_space_id}`;
                return el('div', { class: 'ts-rule' },
                  el('div', { class: 'ts-rule__main' },
                    el('div', { class: 'ts-rule__name' }, r.name),
                    el('div', { class: 'ts-rule__meta' }, `${r.match_type.toUpperCase()} · ${r.pattern} → ${dest}`)
                  ),
                  el('div', { class: 'ts-pillWrap' },
                    el('span', { class: classNames('ts-pill', r.active ? 'ts-pill--ok' : 'ts-pill--muted') }, r.active ? 'Active' : 'Off')
                  )
                );
              })
            )
          : el('div', { class: 'ts-empty' }, 'No rules yet. Create one to stop the Inbox from piling up.')
      )
    );
  }

  function openCreateRuleModal() {
    const spaces = Array.isArray(state.spaces) ? state.spaces : [];

    const form = el('form', {
      class: 'ts-form',
      onsubmit: (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const name = String(fd.get('name') || '').trim();
        const match_type = String(fd.get('match_type') || 'contains');
        const pattern = String(fd.get('pattern') || '').trim();
        const destination_space_id = parseInt(String(fd.get('destination_space_id') || '0'), 10);
        const active = !!fd.get('active');
        const tags = String(fd.get('tags') || '').trim();

        if (!name || !pattern || !(destination_space_id > 0)) {
          toast('warn', 'Name, pattern and destination required.');
          return;
        }

        const payload = {
          name,
          match_type,
          pattern,
          destination_space_id,
          active,
          tags: tags ? tags.split(',').map(t => t.trim()).filter(Boolean) : [],
        };

        void doCreateRule(payload);
      }
    },
      el('label', { class: 'ts-label' }, 'Name'),
      el('input', { class: 'ts-input', name: 'name', type: 'text', required: 'required', placeholder: 'Route PRAG annexes' }),

      el('div', { class: 'ts-formRow' },
        el('div', {},
          el('label', { class: 'ts-label' }, 'Match type'),
          el('select', { class: 'ts-select', name: 'match_type' },
            el('option', { value: 'contains' }, 'Contains'),
            el('option', { value: 'regex' }, 'Regex')
          )
        ),
        el('div', {},
          el('label', { class: 'ts-label' }, 'Destination'),
          el('select', { class: 'ts-select', name: 'destination_space_id', required: 'required' },
            el('option', { value: '' }, 'Select space…'),
            ...spaces.map(s => el('option', { value: String(s.id) }, s.name))
          )
        )
      ),

      el('label', { class: 'ts-label' }, 'Pattern'),
      el('input', { class: 'ts-input', name: 'pattern', type: 'text', required: 'required', placeholder: 'Annex VI or /annex\s+vi/i' }),

      el('label', { class: 'ts-label' }, 'Tags (comma-separated)'),
      el('input', { class: 'ts-input', name: 'tags', type: 'text', placeholder: 'eu,prag,annex' }),

      el('label', { class: 'ts-check' },
        el('input', { type: 'checkbox', name: 'active', value: '1', checked: 'checked' }),
        el('span', {}, 'Active')
      )
    );

    openModal('Create Auto-Route Rule', form, [
      { label: 'Cancel', kind: 'ghost', onClick: closeModal },
      { label: 'Create', kind: 'primary', onClick: () => form.requestSubmit() },
    ]);
  }

  function renderCalendar() {
    if (!state.cal.events && !state.busy) {
      void loadCalendar(true).then(render);
    }

    const month = state.cal.month;
    const key = monthKey(month);

    const header = el('div', { class: 'ts-card ts-card--soft' },
      el('div', { class: 'ts-card__head' },
        el('h3', { class: 'ts-h3' }, `Calendar · ${month.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}`),
        el('div', { class: 'ts-actionsRow' },
          el('button', { class: 'ts-btn ts-btn-ghost', type: 'button', onclick: () => { const d=new Date(month); d.setMonth(d.getMonth()-1); state.cal.month=d; state.cal.events=null; void loadCalendar(true); render(); } }, '←'),
          el('button', { class: 'ts-btn ts-btn-ghost', type: 'button', onclick: () => { state.cal.month=new Date(); state.cal.events=null; void loadCalendar(true); render(); } }, 'Today'),
          el('button', { class: 'ts-btn ts-btn-ghost', type: 'button', onclick: () => { const d=new Date(month); d.setMonth(d.getMonth()+1); state.cal.month=d; state.cal.events=null; void loadCalendar(true); render(); } }, '→'),
          el('button', { class: 'ts-btn ts-btn-primary', type: 'button', onclick: () => openCreateEventModal() }, 'New Event')
        )
      ),
      el('div', { class: 'ts-actionsRow' },
        el('button', { class: classNames('ts-btn','ts-btn-ghost', state.cal.view==='month'?'is-active':''), type: 'button', onclick: () => { state.cal.view='month'; render(); } }, 'Month'),
        el('button', { class: classNames('ts-btn','ts-btn-ghost', state.cal.view==='list'?'is-active':''), type: 'button', onclick: () => { state.cal.view='list'; render(); } }, 'List'),
        el('label', { class: 'ts-check' },
          el('input', { type: 'checkbox', checked: state.cal.includeDone ? 'checked' : null, onchange: (ev) => { state.cal.includeDone = !!ev.target.checked; state.cal.events=null; void loadCalendar(true); render(); } }),
          el('span', {}, 'Include done')
        )
      )
    );

    const events = Array.isArray(state.cal.events) ? state.cal.events : [];

    const view = state.cal.view === 'list' ? renderCalendarList(events) : renderCalendarMonth(events, month);

    return el('div', {}, header, view);
  }

  function renderCalendarList(events) {
    if (!events.length) return el('div', { class: 'ts-card' }, el('div', { class: 'ts-empty' }, 'No events this month.'));

    return el('div', { class: 'ts-card' },
      el('div', { class: 'ts-card__head' },
        el('h3', { class: 'ts-h3' }, `Events (${events.length})`)
      ),
      el('div', { class: 'ts-list' },
        ...events.map(e => {
          const type = EVENT_TYPES[e.type] || { icon: '•', label: e.type };
          const left = `${type.icon} ${e.title}`;
          const meta = `${type.label} · ${PRIORITY_LABEL[e.priority] || e.priority}${e.project_code ? ' · ' + e.project_code : ''}`;
          const right = fmtDateTime(e.start_at);
          return el('div', { class: 'ts-row' },
            el('div', { class: 'ts-row__left' },
              el('div', { class: 'ts-row__title' }, left),
              el('div', { class: 'ts-row__meta' }, meta)
            ),
            el('div', { class: 'ts-row__right' },
              el('div', {}, right),
              el('button', { class: 'ts-btn ts-btn-ghost ts-btn-xs', type: 'button', onclick: () => void doToggleEventDone(e.id, !e.is_done) }, e.is_done ? 'Undo' : 'Done')
            )
          );
        })
      )
    );
  }

  function renderCalendarMonth(events, month) {
    const year = month.getFullYear();
    const m = month.getMonth();

    const first = new Date(year, m, 1);
    const startDay = (first.getDay() + 6) % 7; // monday=0
    const gridStart = new Date(year, m, 1 - startDay);

    const cells = [];
    for (let i=0;i<42;i++) {
      const d = new Date(gridStart);
      d.setDate(gridStart.getDate()+i);
      cells.push(d);
    }

    const byDate = new Map();
    events.forEach(e => {
      const day = String(e.start_at || '').slice(0,10);
      if (!day) return;
      if (!byDate.has(day)) byDate.set(day, []);
      byDate.get(day).push(e);
    });

    const todayKey = monthKey(new Date()) === monthKey(month) ? new Date().toISOString().slice(0,10) : '';

    const grid = el('div', { class: 'ts-cal' },
      ...['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].map(w => el('div', { class: 'ts-cal__dow' }, w)),
      ...cells.map(d => {
        const key = d.toISOString().slice(0,10);
        const inMonth = d.getMonth() === m;
        const evs = byDate.get(key) || [];
        evs.sort((a,b) => String(a.start_at).localeCompare(String(b.start_at)));

        const cell = el('div', { class: classNames('ts-cal__cell', inMonth ? '' : 'is-out', key === todayKey ? 'is-today' : '') },
          el('div', { class: 'ts-cal__day' }, String(d.getDate())),
          el('div', { class: 'ts-cal__events' },
            ...evs.slice(0,2).map(e => {
              const t = EVENT_TYPES[e.type] || { icon:'•', label:e.type };
              return el('div', { class: classNames('ts-cal__evt', `ts-cal__evt--${e.type}`), title: `${t.label}: ${e.title}` },
                `${t.icon} ${e.title}`
              );
            }),
            evs.length > 2 ? el('div', { class: 'ts-cal__more' }, `+${evs.length-2} more`) : null
          )
        );
        cell.addEventListener('click', () => {
          state.cal.view = 'list';
          render();
        });
        return cell;
      })
    );

    return el('div', { class: 'ts-card' },
      el('div', { class: 'ts-card__head' },
        el('h3', { class: 'ts-h3' }, 'Month View'),
        el('div', { class: 'ts-mutedSmall' }, 'Click any day to switch to List view.')
      ),
      grid
    );
  }

  function openCreateEventModal() {
    const now = new Date();
    const start = `${now.getFullYear()}-${pad2(now.getMonth()+1)}-${pad2(now.getDate())} 09:00:00`;
    const end = `${now.getFullYear()}-${pad2(now.getMonth()+1)}-${pad2(now.getDate())} 10:00:00`;

    const form = el('form', {
      class: 'ts-form',
      onsubmit: (ev) => {
        ev.preventDefault();
        const fd = new FormData(ev.currentTarget);
        const title = String(fd.get('title') || '').trim();
        const type = String(fd.get('type') || 'deadline');
        const priority = String(fd.get('priority') || 'medium');
        const start_at = String(fd.get('start_at') || '').trim();
        const end_at = String(fd.get('end_at') || '').trim();
        const all_day = !!fd.get('all_day');
        const project_code = String(fd.get('project_code') || '').trim();
        const description = String(fd.get('description') || '').trim();
        const reminders = String(fd.get('reminders') || '').trim();

        if (!title || !start_at) {
          toast('warn', 'Title and start date/time required.');
          return;
        }

        const payload = {
          title,
          type,
          priority,
          start_at,
          end_at: end_at || start_at,
          all_day: all_day ? 1 : 0,
          project_code,
          description,
          reminders: reminders ? reminders.split(',').map(r => r.trim()).filter(Boolean) : [],
        };

        void doCreateEvent(payload);
      }
    },
      el('label', { class: 'ts-label' }, 'Title'),
      el('input', { class: 'ts-input', name: 'title', type: 'text', required: 'required', placeholder: 'Proposal submission – Project X' }),

      el('div', { class: 'ts-formRow' },
        el('div', {},
          el('label', { class: 'ts-label' }, 'Type'),
          el('select', { class: 'ts-select', name: 'type' },
            el('option', { value: 'deadline' }, 'Deadline'),
            el('option', { value: 'meeting' }, 'Meeting'),
            el('option', { value: 'reminder' }, 'Reminder'),
            el('option', { value: 'task' }, 'Milestone'),
            el('option', { value: 'call' }, 'Call')
          )
        ),
        el('div', {},
          el('label', { class: 'ts-label' }, 'Priority'),
          el('select', { class: 'ts-select', name: 'priority' },
            el('option', { value: 'high' }, 'High'),
            el('option', { value: 'medium', selected: 'selected' }, 'Medium'),
            el('option', { value: 'low' }, 'Low')
          )
        )
      ),

      el('div', { class: 'ts-formRow' },
        el('div', {},
          el('label', { class: 'ts-label' }, 'Start (YYYY-MM-DD HH:MM:SS)'),
          el('input', { class: 'ts-input', name: 'start_at', type: 'text', value: start, required: 'required' })
        ),
        el('div', {},
          el('label', { class: 'ts-label' }, 'End (YYYY-MM-DD HH:MM:SS)'),
          el('input', { class: 'ts-input', name: 'end_at', type: 'text', value: end })
        )
      ),

      el('label', { class: 'ts-check' },
        el('input', { type: 'checkbox', name: 'all_day', value: '1' }),
        el('span', {}, 'All day')
      ),

      el('label', { class: 'ts-label' }, 'Project code (optional)'),
      el('input', { class: 'ts-input', name: 'project_code', type: 'text', placeholder: 'EU-IPA-2025-12' }),

      el('label', { class: 'ts-label' }, 'Reminders (comma-separated: 1w,3d,1d,2h,15m)'),
      el('input', { class: 'ts-input', name: 'reminders', type: 'text', placeholder: '1w,3d,1d' }),

      el('label', { class: 'ts-label' }, 'Description'),
      el('textarea', { class: 'ts-input', name: 'description', rows: '3' })
    );

    openModal('Create Event', form, [
      { label: 'Cancel', kind: 'ghost', onClick: closeModal },
      { label: 'Create', kind: 'primary', onClick: () => form.requestSubmit() },
    ]);
  }

  function renderProjects() {
    if (!state.projects && !state.busy) {
      void loadProjects().then(render);
    }
    const projects = Array.isArray(state.projects) ? state.projects : [];

    return el('div', {},
      el('div', { class: 'ts-card ts-card--soft' },
        el('div', { class: 'ts-card__head' },
          el('h3', { class: 'ts-h3' }, 'Projects')
        ),
        el('div', { class: 'ts-mutedSmall' }, 'Pipeline tracking (basic). Tie documents and events via project codes.')
      ),
      el('div', { class: 'ts-card' },
        el('div', { class: 'ts-card__head' }, el('h3', { class: 'ts-h3' }, `Projects (${projects.length})`)),
        projects.length
          ? listTable(projects.map(p => ({ left: p.name, right: p.code || '', meta: p.status || '—' })))
          : el('div', { class: 'ts-empty' }, 'No projects found.')
      )
    );
  }

  function renderProposals() {
    if (!state.proposals && !state.busy) {
      void loadProposals().then(render);
    }
    const proposals = Array.isArray(state.proposals) ? state.proposals : [];

    return el('div', {},
      el('div', { class: 'ts-card ts-card--soft' },
        el('div', { class: 'ts-card__head' }, el('h3', { class: 'ts-h3' }, 'Proposals')),
        el('div', { class: 'ts-mutedSmall' }, 'Stages: Concept Note → Full Application → Submitted → Awarded.')
      ),
      el('div', { class: 'ts-card' },
        el('div', { class: 'ts-card__head' }, el('h3', { class: 'ts-h3' }, `Proposals (${proposals.length})`)),
        proposals.length
          ? listTable(proposals.map(p => ({ left: p.title, right: p.stage || '', meta: p.project_code || '—' })))
          : el('div', { class: 'ts-empty' }, 'No proposals yet.')
      )
    );
  }

  function renderContacts() {
    if (!state.contacts && !state.busy) {
      void loadContacts().then(render);
    }
    const contacts = Array.isArray(state.contacts) ? state.contacts : [];

    return el('div', {},
      el('div', { class: 'ts-card ts-card--soft' },
        el('div', { class: 'ts-card__head' }, el('h3', { class: 'ts-h3' }, 'CRM')),
        el('div', { class: 'ts-mutedSmall' }, 'Contacts & organizations (lite).')
      ),
      el('div', { class: 'ts-card' },
        el('div', { class: 'ts-card__head' }, el('h3', { class: 'ts-h3' }, `Contacts (${contacts.length})`)),
        contacts.length
          ? listTable(contacts.map(c => ({ left: c.name, right: c.role || '', meta: c.email || '—' })))
          : el('div', { class: 'ts-empty' }, 'No contacts found.')
      )
    );
  }

  function renderTemplates() {
    if (!state.templates && !state.busy) {
      void loadTemplates().then(render);
    }
    const templates = Array.isArray(state.templates) ? state.templates : [];

    return el('div', {},
      el('div', { class: 'ts-card ts-card--soft' },
        el('div', { class: 'ts-card__head' }, el('h3', { class: 'ts-h3' }, 'Templates')),
        el('div', { class: 'ts-mutedSmall' }, 'Store and reuse donor / annex templates. (Lite rendering)')
      ),
      el('div', { class: 'ts-card' },
        el('div', { class: 'ts-card__head' }, el('h3', { class: 'ts-h3' }, `Templates (${templates.length})`)),
        templates.length
          ? listTable(templates.map(t => ({ left: t.name, right: t.type || '', meta: t.updated_at ? fmtDateTime(t.updated_at) : '' })))
          : el('div', { class: 'ts-empty' }, 'No templates found.')
      )
    );
  }

  function renderModal() {
    const o = state.overlay;
    if (!o) return el('div');

    const actions = el('div', { class: 'ts-modalActions' },
      ...(o.actions || []).map(a => el('button', {
        class: classNames('ts-btn', a.kind === 'primary' ? 'ts-btn-primary' : 'ts-btn-ghost'),
        type: 'button',
        onclick: typeof a.onClick === 'function' ? a.onClick : null,
      }, a.label))
    );

    return el('div', { class: 'ts-modalBackdrop', onclick: (ev) => { if (ev.target === ev.currentTarget) closeModal(); } },
      el('div', { class: 'ts-modal' },
        el('div', { class: 'ts-modalHead' },
          el('div', { class: 'ts-modalTitle' }, o.title || 'Dialog'),
          el('button', { class: 'ts-modalX', type: 'button', onclick: closeModal }, '×')
        ),
        el('div', { class: 'ts-modalBody' }, o.contentEl || el('div')),
        actions
      )
    );
  }

  // Initial render (placeholder removal)
  render();
})();
