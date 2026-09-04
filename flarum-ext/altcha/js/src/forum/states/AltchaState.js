import app from 'flarum/forum/app';
import 'altcha';

export default class AltchaState {
  constructor() {
    this.payload = '';
    this.status = 'idle';
    this.widget = null;
    this.mounting = false;
  }

  mount(container) {
    if (this.widget || this.mounting || !container) {
      return;
    }

    this.mounting = true;
    this.status = 'loading';
    m.redraw();

    try {
      const widget = document.createElement('altcha-widget');
      const apiUrl = app.forum.attribute('apiUrl');

      widget.setAttribute('challengeurl', `${apiUrl}/altcha/challenge`);
      widget.setAttribute('auto', 'onload');
      widget.setAttribute('name', 'altcha');
      widget.setAttribute('hidelogo', '1');
      widget.setAttribute('hidefooter', '1');

      widget.addEventListener('verified', (event) => {
        this.payload = event.detail?.payload || widget.value || '';
        this.status = 'solved';
        m.redraw();
      });

      widget.addEventListener('statechange', (event) => {
        const state = event.detail?.state;
        if (state === 'error' || state === 'expired') {
          this.status = 'error';
          m.redraw();
        } else if (state === 'verifying' || state === 'unverified' || state === 'code') {
          this.status = 'loading';
          m.redraw();
        } else if (state === 'verified') {
          this.payload = widget.value || this.payload;
          this.status = 'solved';
          m.redraw();
        }
      });

      widget.addEventListener('error', () => {
        this.status = 'error';
        m.redraw();
      });

      container.appendChild(widget);
      this.widget = widget;
      this.mounting = false;
    } catch (e) {
      this.mounting = false;
      this.status = 'error';
      m.redraw();
    }
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

  waitUntilSettled(timeout = 15000) {
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
