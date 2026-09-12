import app from 'flarum/forum/app';
import User from 'flarum/common/models/User';
import Model from 'flarum/common/Model';
import extendAuthModals from './src/forum/extendAuthModals';
import extendUserCard from './src/forum/extendUserCard';
import extendSettingsPage from './src/forum/extendSettingsPage';

app.initializers.add('hardened-stacks-lxmf', () => {
  User.prototype.lxmfAddress = Model.attribute('lxmfAddress');

  extendAuthModals();
  extendUserCard();
  extendSettingsPage();
});
