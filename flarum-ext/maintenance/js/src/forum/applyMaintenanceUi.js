import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import ForumApplication from 'flarum/forum/ForumApplication';
import Composer from 'flarum/forum/components/Composer';
import HeaderSecondary from 'flarum/forum/components/HeaderSecondary';
import SignUpModal from 'flarum/forum/components/SignUpModal';
import LogInModal from 'flarum/forum/components/LogInModal';
import MaintenanceBanner from './components/MaintenanceBanner';
import MaintenancePage from './components/MaintenancePage';

function mode() {
  return app.forum.attribute('hardened-stacksMaintenanceMode') || 'off';
}

function isAdmin() {
  return !!app.session.user && app.session.user.isAdmin();
}

function isClosedForViewer() {
  return mode() === 'closed' && !isAdmin();
}

function shouldShowBanner() {
  if (isClosedForViewer() || isAdmin()) {
    return false;
  }

  return !!app.forum.attribute('hardened-stacksMaintenanceShowBanner');
}

function blocksWritesForViewer() {
  if (isAdmin()) {
    return false;
  }

  return mode() === 'read_only' || mode() === 'closed';
}

function allowLoginUi() {
  if (isAdmin() || app.session.user) {
    return false;
  }

  if (mode() === 'closed') {
    return !!app.forum.attribute('hardened-stacksMaintenanceAllowLogin');
  }

  return true;
}

function allowSignUpUi() {
  if (isAdmin()) {
    return true;
  }

  return mode() !== 'read_only' && mode() !== 'closed';
}

function showMaintenanceAlert() {
  app.alerts.show(
    { type: 'error' },
    app.forum.attribute('hardened-stacksMaintenanceMessage')
  );
}

function mountBanner() {
  if (document.getElementById('pmg-maintenance-banner')) {
    return;
  }

  const el = document.createElement('div');
  el.id = 'pmg-maintenance-banner';
  const appRoot = document.getElementById('app');
  if (appRoot && appRoot.parentNode) {
    appRoot.parentNode.insertBefore(el, appRoot);
  } else {
    document.body.insertBefore(el, document.body.firstChild);
  }

  m.mount(el, MaintenanceBanner);
}

export default function applyMaintenanceUi() {
  override(ForumApplication.prototype, 'mount', function (original, ...args) {
    if (isClosedForViewer()) {
      this.routes = {
        index: { path: '/', component: MaintenancePage },
        'pmg.maintenance': { path: '/:path...', component: MaintenancePage },
      };
    }

    const result = original.apply(this, args);

    if (shouldShowBanner()) {
      mountBanner();
    }

    return result;
  });

  override(Composer.prototype, 'load', function (original, ...args) {
    if (blocksWritesForViewer()) {
      showMaintenanceAlert();
      return;
    }

    return original.apply(this, args);
  });

  extend(HeaderSecondary.prototype, 'items', function (items) {
    if (!allowSignUpUi()) {
      items.remove('signUp');
    }

    if (!allowLoginUi() && !app.session.user) {
      items.remove('logIn');
    }
  });

  override(SignUpModal.prototype, 'oninit', function (original, vnode) {
    if (!allowSignUpUi()) {
      showMaintenanceAlert();
      app.modal.close();
      return;
    }

    return original.call(this, vnode);
  });

  override(LogInModal.prototype, 'oninit', function (original, vnode) {
    if (mode() === 'closed' && !allowLoginUi() && !isAdmin()) {
      showMaintenanceAlert();
      app.modal.close();
      return;
    }

    return original.call(this, vnode);
  });
}
