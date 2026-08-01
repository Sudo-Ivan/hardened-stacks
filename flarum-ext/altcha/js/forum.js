import app from 'flarum/forum/app';
import extendAuthModals from './src/forum/extendAuthModals';

app.initializers.add('hardened-stacks-altcha', () => {
  extendAuthModals();
});
