import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import LogInModal from 'flarum/forum/components/LogInModal';
import SignUpModal from 'flarum/forum/components/SignUpModal';
import ForgotPasswordModal from 'flarum/forum/components/ForgotPasswordModal';
import AltchaWidget from './components/AltchaWidget';
import AltchaState from './states/AltchaState';

function isConfigured() {
  return !!app.forum.attribute('hardened-stacks-altcha.configured');
}

function isMasterEnabled() {
  return !!app.forum.attribute('hardened-stacks-altcha.enabled');
}

function isEnabled(key) {
  return isConfigured() && isMasterEnabled() && !!app.forum.attribute(`hardened-stacks-altcha.${key}`);
}

function applyToModal(modal, enabledKey, dataMethod) {
  const prototype = modal.prototype;
  const skipCaptcha = modal === SignUpModal
    ? function () {
        return !!this.attrs?.token;
      }
    : () => false;

  extend(prototype, 'oninit', function () {
    if (!isEnabled(enabledKey)) return;
    if (skipCaptcha.call(this)) return;
    this.altchaState = new AltchaState();
  });

  extend(prototype, dataMethod, function (data) {
    if (!isEnabled(enabledKey)) return;
    if (skipCaptcha.call(this)) return;
    data.captchaToken = this.altchaState?.getResponse() ?? '';
  });

  extend(prototype, 'fields', function (items) {
    if (!isEnabled(enabledKey)) return;
    if (skipCaptcha.call(this)) return;
    if (!this.altchaState) return;

    items.add('pmg-altcha', <AltchaWidget state={this.altchaState} />, -5);
  });

  extend(prototype, 'onerror', function () {
    if (!isEnabled(enabledKey)) return;
    const status = this.altchaState?.getStatus();
    if (status === 'solved' || status === 'error') {
      this.altchaState?.retry();
    }
  });

  const checkAndBlock = async function (e) {
    if (!isEnabled(enabledKey)) return true;
    if (skipCaptcha.call(this)) return true;

    let status = this.altchaState?.getStatus();
    if (status !== 'solved') {
      status = await this.altchaState?.waitUntilSettled(8000);
    }

    if (status !== 'solved' || !this.altchaState?.getResponse()) {
      if (e && typeof e.preventDefault === 'function') {
        e.preventDefault();
        e.stopPropagation();
      }
      this.loading = false;
      m.redraw();

      if (status === 'error' || status === 'idle') {
        this.altchaState?.retry();
      }

      app.alerts.show(
        { type: 'error' },
        app.translator.trans('hardened-stacks-altcha.forum.challenge_not_ready')
      );
      return false;
    }

    return true;
  };

  const wrapSubmit = function (original, e) {
    if (e && typeof e.preventDefault === 'function') {
      e.preventDefault();
      e.stopPropagation();
    }

    checkAndBlock.call(this, e).then((canSubmit) => {
      if (canSubmit) {
        original.call(this, e);
      }
    });
  };

  override(prototype, 'onsubmit', function (original, e) {
    return wrapSubmit.call(this, original, e);
  });
}

export default function extendAuthModals() {
  applyToModal(LogInModal, 'protectLogin', 'loginParams');
  applyToModal(SignUpModal, 'protectRegistration', 'submitData');
  applyToModal(ForgotPasswordModal, 'protectForgot', 'requestParams');
}
