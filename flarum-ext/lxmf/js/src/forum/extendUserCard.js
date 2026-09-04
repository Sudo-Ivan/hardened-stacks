import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserCard from 'flarum/forum/components/UserCard';
import Button from 'flarum/common/components/Button';

export default function extendUserCard() {
  extend(UserCard.prototype, 'infoItems', function (items) {
    if (!app.session.user) return;

    const user = this.attrs.user;
    const address = user?.lxmfAddress?.();
    if (!address) return;

    items.add(
      'lxmf',
      <div className="UserCard-lxmf">
        <span className="UserCard-lxmf-label">
          {app.translator.trans('hardened-stacks-lxmf.forum.usercard.lxmf_label')}
        </span>
        <code className="UserCard-lxmf-address">{address}</code>
        <Button
          className="Button Button--link"
          onclick={() => {
            if (navigator.clipboard?.writeText) {
              navigator.clipboard.writeText(address).then(() => {
                app.alerts.show(
                  { type: 'success' },
                  app.translator.trans('hardened-stacks-lxmf.forum.usercard.copied')
                );
              });
            }
          }}
        >
          {app.translator.trans('hardened-stacks-lxmf.forum.usercard.copy')}
        </Button>
      </div>,
      80
    );
  });
}
