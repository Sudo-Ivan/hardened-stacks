import app from 'flarum/forum/app';
import { extend, override } from 'flarum/common/extend';
import SignUpModal from 'flarum/forum/components/SignUpModal';
import LogInModal from 'flarum/forum/components/LogInModal';
import isNoEmailRegistration from './isNoEmailRegistration';

export default function extendAuthModals() {
  extend(SignUpModal.prototype, 'fields', function (items) {
    if (!isNoEmailRegistration()) return;

    items.remove('email');

    items.add(
      'pmg-lxmf-signup-help',
      <div className="Form-group">
        <p className="helpText">
          {app.translator.trans('hardened-stacks-lxmf.forum.signup_no_email_help')}
        </p>
      </div>,
      10
    );
  });

  extend(SignUpModal.prototype, 'submitData', function (data) {
    if (!isNoEmailRegistration()) return;
    delete data.email;
  });

  override(LogInModal.prototype, 'footer', function (original) {
    const content = original.call(this);
    if (!isNoEmailRegistration()) {
      return content;
    }

    const nodes = Array.isArray(content) ? content : [content];

    return nodes.filter((node) => {
      if (!node || !node.attrs) return true;
      const className = node.attrs.className || '';
      return !String(className).includes('LogInModal-forgotPassword');
    });
  });
}
