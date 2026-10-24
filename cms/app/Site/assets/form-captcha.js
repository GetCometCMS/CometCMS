/** Generate a fresh v3 token for each submission, then resume native validation and submission. */
function prepareScoreCaptcha(state, view) {
  const { widget, form, status } = state;
  const token = widget.querySelector('[data-captcha-token]');
  let pending = false, resuming = false, generation = 0;
  const showError = state.error;
  state.error = () => {
    generation++;
    pending = false;
    token.value = '';
    form.removeAttribute('aria-busy');
    return showError();
  };
  state.options = {
    sitekey: widget.dataset.sitekey, size: 'invisible', badge: 'inline',
    theme: state.options.theme,
    'error-callback': state.error,
    'expired-callback': state.error,
  };
  form.addEventListener('submit', async (event) => {
    if (resuming) return;
    event.preventDefault();
    if (pending) return;
    if (state.id === null || typeof view.grecaptcha?.execute !== 'function') {
      state.error();
      return;
    }
    const attempt = ++generation;
    pending = true;
    token.value = '';
    form.setAttribute('aria-busy', 'true');
    status.textContent = widget.dataset.checking;
    let timeout;
    try {
      view.grecaptcha.reset(state.id);
      const result = await Promise.race([
        view.grecaptcha.execute(state.id, { action: widget.dataset.action }),
        new Promise((_, reject) => { timeout = view.setTimeout(() => reject(new Error('CAPTCHA timeout')), 15000); }),
      ]);
      // A reset or provider error cancels this attempt, including a late token.
      if (attempt !== generation) return;
      if (typeof result !== 'string' || !result.trim() || result.length > 8192) throw new Error('Invalid CAPTCHA token');
      token.value = result;
      status.textContent = '';
      pending = false;
      form.removeAttribute('aria-busy');
      resuming = true;
      if (event.submitter) form.requestSubmit(event.submitter);
      else form.requestSubmit();
    } catch {
      if (attempt === generation) state.error();
    } finally {
      view.clearTimeout(timeout);
      resuming = false;
    }
  });
  form.addEventListener('reset', () => {
    generation++;
    pending = false;
    token.value = '';
    status.textContent = '';
    form.removeAttribute('aria-busy');
    const attempt = generation;
    view.setTimeout(() => {
      if (attempt === generation && state.id !== null) view.grecaptcha.reset(state.id);
    }, 0);
  });
}

/** Render each form independently and load each provider once, after its callback exists. */
export function initFormCaptchas(doc) {
  const view = doc.defaultView;
  const providers = {
    turnstile: { api: 'turnstile', callback: 'cometTurnstileReady', src: 'https://challenges.cloudflare.com/turnstile/v0/api.js' },
    recaptcha: { api: 'grecaptcha', callback: 'cometRecaptchaReady', src: 'https://www.google.com/recaptcha/api.js', selector: '[data-form-captcha="recaptcha"], [data-form-captcha="recaptcha_v3"]' },
  };

  for (const [provider, config] of Object.entries(providers)) {
    const widgets = [...doc.querySelectorAll(config.selector || `[data-form-captcha="${provider}"]`)];
    if (!widgets.length) continue;
    const pending = [];
    for (const widget of widgets) {
      if (widget.dataset.captchaInitialized) continue;
      widget.dataset.captchaInitialized = 'true';
      const form = widget.closest('form');
      const status = widget.querySelector('[data-captcha-status]');
      const state = { widget, form, status, id: null, solved: false };
      pending.push(state);
      const prompt = () => {
        state.solved = false;
        status.textContent = widget.dataset.prompt;
      };
      state.error = () => {
        state.solved = false;
        status.textContent = widget.dataset.error;
        return true;
      };
      state.options = {
        sitekey: widget.dataset.sitekey,
        size: widget.clientWidth < 310 ? 'compact' : 'normal',
        theme: doc.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',
        callback: () => { state.solved = true; status.textContent = ''; },
        'expired-callback': prompt,
        'error-callback': state.error,
      };
      if (provider === 'turnstile') state.options.action = widget.dataset.action;
      if (widget.dataset.formCaptcha === 'recaptcha_v3') {
        // Explicit invisible rendering lets v2 and v3 share one API script and use separate keys.
        prepareScoreCaptcha(state, view);
        continue;
      }
      form.addEventListener('submit', (event) => {
        if (!state.solved) {
          event.preventDefault();
          status.textContent = status.textContent || widget.dataset.prompt;
        }
      });
      form.addEventListener('reset', () => {
        prompt();
        // Reset after the browser clears the provider's response input.
        view.setTimeout(() => {
          if (state.id !== null) view[config.api].reset(state.id);
        }, 0);
      });
    }
    if (!pending.length) continue;
    const render = () => {
      for (const state of pending) {
        if (state.id !== null) continue;
        try {
          state.id = view[config.api].render(state.widget.querySelector('[data-captcha-widget]'), state.options);
        } catch {
          state.error();
        }
      }
    };
    if (typeof view[config.api]?.render === 'function') {
      render();
      continue;
    }
    const previous = view[config.callback];
    view[config.callback] = () => { previous?.(); render(); };
    if (doc.querySelector(`script[data-captcha-provider="${provider}"]`)) continue;
    const script = doc.createElement('script');
    script.dataset.captchaProvider = provider;
    script.src = `${config.src}?onload=${config.callback}&render=explicit`;
    script.async = true;
    script.defer = true;
    script.addEventListener('error', () => pending.forEach((state) => state.error()));
    doc.head.append(script);
  }
}
