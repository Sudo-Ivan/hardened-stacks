import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import EditUserModal from 'flarum/common/components/EditUserModal';
import UserListPage from 'flarum/admin/components/UserListPage';
import Button from 'flarum/common/components/Button';

function canDelete(user) {
  return !!(user && user.attribute('canPmgDelete'));
}

async function deleteUserRequest(userId) {
  return app.request({
    method: 'POST',
    url: app.forum.attribute('apiUrl') + '/pmg/users/' + userId + '/delete',
    body: {
      data: {
        attributes: {
          purgeFirst: true,
          hard: false,
        },
      },
    },
  });
}

async function bulkDeleteRequest(userIds) {
  return app.request({
    method: 'POST',
    url: app.forum.attribute('apiUrl') + '/pmg/users/delete',
    body: {
      data: {
        attributes: {
          userIds,
          purgeFirst: true,
          hard: false,
        },
      },
    },
  });
}

app.initializers.add('hardened-stacks-delete-users', () => {
  app.extensionData.for('hardened-stacks-delete-users').registerPermission(
    {
      icon: 'fas fa-user-times',
      label: app.translator.trans('hardened-stacks-delete-users.admin.permissions.delete_users_label'),
      permission: 'pmg.deleteUsers',
    },
    'moderate'
  );

  extend(EditUserModal.prototype, 'oninit', function () {
    this.deletingUser = false;
  });

  extend(EditUserModal.prototype, 'fields', function (items) {
    const user = this.attrs.user;

    if (!canDelete(user)) {
      return;
    }

    items.add(
      'pmgDelete',
      <div className="Form-group">
        <Button
          className="Button Button--danger"
          icon="fas fa-user-times"
          loading={this.deletingUser}
          type="button"
          onclick={(e) => {
            e.preventDefault();
            this.deleteUserAccount();
          }}
        >
          {app.translator.trans('hardened-stacks-delete-users.admin.delete_button')}
        </Button>
      </div>,
      -20
    );
  });

  EditUserModal.prototype.deleteUserAccount = async function () {
    const user = this.attrs.user;
    if (!canDelete(user)) {
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
      await deleteUserRequest(user.id());
      app.store.remove(user);
      app.modal.close();
      m.route.set('/users');
    } catch (error) {
      this.deletingUser = false;
      m.redraw();
      throw error;
    }
  };

  extend(UserListPage.prototype, 'oninit', function () {
    this.pmgSelectedUserIds = new Set();
    this.pmgBulkDeleting = false;
  });

  extend(UserListPage.prototype, 'columns', function (columns) {
    columns.add(
      'pmgSelect',
      {
        name: '',
        content: (user) => {
          if (!canDelete(user)) {
            return null;
          }

          const id = String(user.id());
          const selected = this.pmgSelectedUserIds.has(id);

          return (
            <input
              type="checkbox"
              className="FormControl"
              checked={selected}
              aria-label={app.translator.trans('hardened-stacks-delete-users.admin.delete_button')}
              onclick={(e) => e.stopPropagation()}
              onchange={(e) => {
                if (e.target.checked) {
                  this.pmgSelectedUserIds.add(id);
                } else {
                  this.pmgSelectedUserIds.delete(id);
                }
                m.redraw();
              }}
            />
          );
        },
      },
      110
    );
  });

  extend(UserListPage.prototype, 'userActionItems', function (items, user) {
    if (!canDelete(user)) {
      return;
    }

    items.add(
      'pmgDelete',
      <Button
        className="Button"
        icon="fas fa-user-times"
        onclick={() => this.pmgDeleteUser(user)}
      >
        {app.translator.trans('hardened-stacks-delete-users.admin.delete_button')}
      </Button>,
      50
    );
  });

  extend(UserListPage.prototype, 'actionItems', function (items) {
    const count = this.pmgSelectedUserIds ? this.pmgSelectedUserIds.size : 0;
    if (count === 0) {
      return;
    }

    items.add(
      'pmgBulkDelete',
      <Button
        className="Button Button--danger"
        icon="fas fa-user-times"
        loading={this.pmgBulkDeleting}
        onclick={() => this.pmgBulkDeleteSelected()}
      >
        {app.translator.trans('hardened-stacks-delete-users.admin.bulk_delete_button', { count })}
      </Button>,
      50
    );
  });

  UserListPage.prototype.pmgDeleteUser = async function (user) {
    if (!canDelete(user)) {
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

    await deleteUserRequest(user.id());
    this.pmgSelectedUserIds.delete(String(user.id()));
    app.store.remove(user);
    this.isLoadingPage = true;
    this.loadPage(this.pageNumber);
  };

  UserListPage.prototype.pmgBulkDeleteSelected = async function () {
    const ids = [...this.pmgSelectedUserIds].map((id) => parseInt(id, 10)).filter((id) => id > 0);
    if (ids.length === 0) {
      return;
    }

    const confirmed = confirm(
      app.translator.trans('hardened-stacks-delete-users.admin.bulk_delete_confirm', {
        count: ids.length,
      })
    );

    if (!confirmed) {
      return;
    }

    this.pmgBulkDeleting = true;
    m.redraw();

    try {
      const response = await bulkDeleteRequest(ids);
      const deleted = response?.data?.deletedUsers ?? 0;
      const skipped = response?.data?.skipped?.length ?? 0;

      this.pmgSelectedUserIds.clear();

      if (skipped > 0) {
        app.alerts.show(
          { type: 'warning' },
          app.translator.trans('hardened-stacks-delete-users.admin.bulk_delete_partial', {
            deleted,
            skipped,
          })
        );
      } else {
        app.alerts.show(
          { type: 'success' },
          app.translator.trans('hardened-stacks-delete-users.admin.bulk_delete_success', {
            count: deleted,
          })
        );
      }

      this.isLoadingPage = true;
      this.loadPage(this.pageNumber);
    } finally {
      this.pmgBulkDeleting = false;
      m.redraw();
    }
  };
});
