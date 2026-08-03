import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import EditUserModal from 'flarum/admin/components/EditUserModal';
import Button from 'flarum/common/components/Button';

app.initializers.add('hardened-stacks-delete-users', () => {
  extend(EditUserModal.prototype, 'content', function (original) {
    const content = original();
    const user = this.user;

    if (!user || !user.attribute('canPmgDelete')) {
      return content;
    }

    return (
      <>
        {content}
        <div className="Form-group">
          <Button
            className="Button Button--danger"
            icon="fas fa-user-times"
            loading={this.deletingUser}
            onclick={() => this.deleteUserAccount()}
          >
            {app.translator.trans('hardened-stacks-delete-users.admin.delete_button')}
          </Button>
        </div>
      </>
    );
  });

  extend(EditUserModal.prototype, 'oninit', function () {
    this.deletingUser = false;
  });

  EditUserModal.prototype.deleteUserAccount = async function () {
    const user = this.user;
    if (!user || !user.attribute('canPmgDelete')) {
      return;
    }

    const confirmed = confirm(
      app.translator.trans('hardened-stacks-delete-users.admin.delete_confirm', {
        username: user.displayName(),
      })
    );

    if (!confirmed) {
      return;
    }

    this.deletingUser = true;
    m.redraw();

    try {
      await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/pmg/users/' + user.id() + '/delete',
        body: {
          data: {
            attributes: {
              purgeFirst: true,
              hard: false,
            },
          },
        },
      });

      app.modal.close();
      m.route.set('/users');
    } catch (error) {
      this.deletingUser = false;
      m.redraw();
      throw error;
    }
  };
});
