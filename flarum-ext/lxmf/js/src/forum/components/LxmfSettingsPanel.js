import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';

export default class LxmfSettingsPanel extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.user = vnode.attrs.user;
    this.address = Stream(this.user.lxmfAddress() || '');
    this.loading = false;
  }

  view() {
    const linked = !!this.user.lxmfAddress();

    return (
      <div className="Settings-lxmf Form-group">
        <label>{app.translator.trans('hardened-stacks-lxmf.forum.settings.lxmf_heading')}</label>
        <p className="helpText">
          {app.translator.trans('hardened-stacks-lxmf.forum.settings.lxmf_help')}
        </p>
        <input
          className="FormControl"
          type="text"
          bidi={this.address}
          disabled={this.loading}
          placeholder={app.translator.trans('hardened-stacks-lxmf.forum.settings.lxmf_placeholder')}
          autocomplete="off"
          spellcheck="false"
        />
        <div className="Settings-lxmf-actions">
          <Button
            className="Button Button--primary"
            loading={this.loading}
            disabled={this.loading}
            onclick={() => this.save()}
          >
            {app.translator.trans('hardened-stacks-lxmf.forum.settings.lxmf_save')}
          </Button>
          {linked ? (
            <Button
              className="Button"
              loading={this.loading}
              disabled={this.loading}
              onclick={() => this.unlink()}
            >
              {app.translator.trans('hardened-stacks-lxmf.forum.settings.lxmf_unlink')}
            </Button>
          ) : null}
        </div>
      </div>
    );
  }

  save() {
    this.loading = true;

    this.user
      .save({ lxmfAddress: this.address() })
      .then(() => {
        this.address(this.user.lxmfAddress() || '');
        app.alerts.show(
          { type: 'success' },
          app.translator.trans('hardened-stacks-lxmf.forum.settings.lxmf_saved')
        );
      })
      .catch(() => {})
      .then(() => {
        this.loading = false;
        m.redraw();
      });
  }

  unlink() {
    this.loading = true;

    this.user
      .save({ lxmfAddress: '' })
      .then(() => {
        this.address('');
        app.alerts.show(
          { type: 'success' },
          app.translator.trans('hardened-stacks-lxmf.forum.settings.lxmf_unlinked')
        );
      })
      .catch(() => {})
      .then(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
