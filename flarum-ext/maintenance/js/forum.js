import app from 'flarum/forum/app';
import applyMaintenanceUi from './src/forum/applyMaintenanceUi';

app.initializers.add('hardened-stacks-maintenance', () => {
  applyMaintenanceUi();
});
