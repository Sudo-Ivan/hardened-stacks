import app from 'flarum/forum/app';

export default class AltchaState {
  constructor() {
    this.payload = '';
    this.status = 'idle';
    this.widget = null;
  }

  mount(container) {
    import('altcha').then(() => {
      const widget = document.createElement('altcha-widget');
      const apiUrl = app.forum.attribute('apiUrl');

      widget.setAttribute('challengeurl', `${apiUrl}/altcha/challenge`);
      widget.setAttribute('display', 'floating');
      widget.setAttribute('name', 'altcha');

      widget.addEventListener('verified', (event) => {
        this.payload = event.detail?.payload || widget.value || '';
        this.status = 'solved';
        m.redraw();
      });

      widget.addEventListener('statechange', (event) => {
        const state = event.detail?.state;
        if (state === 'error') {
          this.status = 'error';
          m.redraw();
        } else if (state === 'verifying' || state === 'unverified') {
          this.status = 'loading';
          m.redraw();
        }
      });

      container.appendChild(widget);
      this.widget = widget;
      this.status = 'loading';
      m.redraw();
    });
  }

  getResponse() {
    if (this.payload) {
      return this.payload;
    }

    return this.widget?.value || '';
  }

  getStatus() {
    return this.status;
  }

  waitUntilSettled(timeout = 8000) {
    const start = Date.now();

    return new Promise((resolve) => {
      const tick = () => {
        const status = this.getStatus();
        if (status === 'solved' || status === 'error') {
          resolve(status);
          return;
        }

        if (Date.now() - start >= timeout) {
          resolve(status);
          return;
        }

        setTimeout(tick, 200);
      };

      tick();
    });
  }

  retry() {
    this.payload = '';
    if (this.widget && typeof this.widget.reset === 'function') {
      this.widget.reset();
    }
    this.status = 'loading';
    m.redraw();
  }
}
