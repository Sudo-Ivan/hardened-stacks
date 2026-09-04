import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import SettingsPage from 'flarum/forum/components/SettingsPage';
import LxmfSettingsPanel from './components/LxmfSettingsPanel';
import isNoEmailRegistration from './isNoEmailRegistration';

export default function extendSettingsPage() {
  extend(SettingsPage.prototype, 'accountItems', function (items) {
    if (isNoEmailRegistration()) {
      items.remove('changeEmail');
    }

    const user = this.user || app.session.user;
    if (!user || !app.session.user || user.id() !== app.session.user.id()) {
      return;
    }

    items.add('lxmf', <LxmfSettingsPanel user={user} />, 85);
  });
}
