import app from 'flarum/forum/app';

export default function isNoEmailRegistration() {
  return !!app.forum.attribute('hardenedStacksLxmfNoEmail');
}
