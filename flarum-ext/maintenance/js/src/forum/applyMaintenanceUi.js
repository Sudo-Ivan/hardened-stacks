import app from 'flarum/forum/app';
import { override } from 'flarum/common/extend';
import ForumApplication from 'flarum/forum/ForumApplication';
import Composer from 'flarum/forum/components/Composer';
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
      app.alerts.show(
        { type: 'error' },
        app.forum.attribute('hardened-stacksMaintenanceMessage')
      );
      return;
    }

    return original.apply(this, args);
  });
}
