import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';

export default class AltchaWidget extends Component {
  view() {
    const status = this.attrs.state.getStatus();

    return (
      <div className="PmgAltchaWidget Form-group" oncreate={(vnode) => this.attrs.state.mount(vnode.dom)}>
        {status === 'loading' && (
          <p className="helpBlock">{app.translator.trans('hardened-stacks-altcha.forum.verifying')}</p>
        )}
        {status === 'error' && (
          <p className="helpBlock PmgAltchaWidget-error">{app.translator.trans('hardened-stacks-altcha.forum.challenge_error')}</p>
        )}
      </div>
    );
  }
}
