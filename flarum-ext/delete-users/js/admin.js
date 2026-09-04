import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import EditUserModal from 'flarum/common/components/EditUserModal';
import UserListPage from 'flarum/admin/components/UserListPage';
import Button from 'flarum/common/components/Button';
import extractText from 'flarum/common/utils/extractText';

function canDelete(user) {
  return !!(user && user.attribute('canPmgDelete'));
}

function selectedIds(page) {
  return Array.from(page.pmgSelectedUserIds || []);
}

async function deleteUserRequest(userId) {
  return app.request({
    method: 'POST',
    url: app.forum.attribute('apiUrl') + '/pmg/users/' + userId + '/delete',
    body: {
      data: {
        attributes: {
          purgeFirst: true,
          hard: true,
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
          hard: true,
        },
      },
    },
  });
}

function confirmText(translation) {
  return extractText(translation);
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

    const confirmed = window.confirm(
      confirmText(
        app.translator.trans('hardened-stacks-delete-users.admin.delete_confirm', {
          username: user.displayName(),
        })
      )
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
    this.pmgBulkStatus = '';
  });

  extend(UserListPage.prototype, 'columns', function (columns) {
    const page = this;
    const deletableOnPage = (this.pageData || []).filter(canDelete);
    const selectedOnPage = deletableOnPage.filter((user) =>
      page.pmgSelectedUserIds.has(String(user.id()))
    );
    const allSelected =
      deletableOnPage.length > 0 && selectedOnPage.length === deletableOnPage.length;
    const someSelected = selectedOnPage.length > 0 && !allSelected;

    columns.add(
      'pmgSelect',
      {
        name: (
          <label className="PmgUserSelect PmgUserSelect--header">
            <input
              type="checkbox"
              className="PmgUserSelect-input"
              checked={allSelected}
              disabled={deletableOnPage.length === 0 || page.pmgBulkDeleting}
              indeterminate={someSelected}
              aria-label={app.translator.trans('hardened-stacks-delete-users.admin.select_all')}
              onclick={(e) => e.stopPropagation()}
              oncreate={(vnode) => {
                vnode.dom.indeterminate = someSelected;
              }}
              onupdate={(vnode) => {
                vnode.dom.indeterminate = someSelected;
              }}
              onchange={(e) => {
                if (e.target.checked) {
                  deletableOnPage.forEach((user) => {
                    page.pmgSelectedUserIds.add(String(user.id()));
                  });
                } else {
                  deletableOnPage.forEach((user) => {
                    page.pmgSelectedUserIds.delete(String(user.id()));
                  });
                }
                page.pmgBulkStatus = '';
                m.redraw();
              }}
            />
          </label>
        ),
        content: (user) => {
          if (!canDelete(user)) {
            return <span className="PmgUserSelect PmgUserSelect--empty" aria-hidden="true" />;
          }

          const id = String(user.id());
          const selected = page.pmgSelectedUserIds.has(id);

          return (
            <label className="PmgUserSelect">
              <input
                type="checkbox"
                className="PmgUserSelect-input"
                checked={selected}
                disabled={page.pmgBulkDeleting}
                aria-label={app.translator.trans('hardened-stacks-delete-users.admin.select_user', {
                  username: user.displayName(),
                })}
                onclick={(e) => e.stopPropagation()}
                onchange={(e) => {
                  if (e.target.checked) {
                    page.pmgSelectedUserIds.add(id);
                  } else {
                    page.pmgSelectedUserIds.delete(id);
                  }
                  page.pmgBulkStatus = '';
                  m.redraw();
                }}
              />
            </label>
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
        className="Button Button--danger"
        icon="fas fa-user-times"
        disabled={this.pmgBulkDeleting}
        type="button"
        onclick={(e) => {
          e.preventDefault();
          this.pmgDeleteUser(user);
        }}
      >
        {app.translator.trans('hardened-stacks-delete-users.admin.delete_button')}
      </Button>,
      50
    );
  });

  extend(UserListPage.prototype, 'headerItems', function (items) {
    const count = selectedIds(this).length;
    const status = this.pmgBulkStatus || '';

    items.add(
      'pmgBulkBar',
      <div className="PmgUserBulkBar">
        <span className="PmgUserBulkBar-status">
          {status
            ? status
            : app.translator.trans('hardened-stacks-delete-users.admin.selected_count', { count })}
        </span>
        <Button
          className="Button Button--danger"
          icon="fas fa-user-times"
          loading={this.pmgBulkDeleting}
          disabled={count === 0 || this.pmgBulkDeleting}
          type="button"
          onclick={(e) => {
            e.preventDefault();
            this.pmgBulkDeleteSelected();
          }}
        >
          {app.translator.trans('hardened-stacks-delete-users.admin.bulk_delete_button', { count })}
        </Button>
      </div>,
      85
    );
  });

  UserListPage.prototype.pmgDeleteUser = async function (user) {
    if (!canDelete(user) || this.pmgBulkDeleting) {
      return;
    }

    const confirmed = window.confirm(
      confirmText(
        app.translator.trans('hardened-stacks-delete-users.admin.delete_confirm', {
          username: user.displayName(),
        })
      )
    );

    if (!confirmed) {
      return;
    }

    this.pmgBulkStatus = app.translator.trans('hardened-stacks-delete-users.admin.deleting_one', {
      username: user.displayName(),
    });
    this.pmgBulkDeleting = true;
    m.redraw();

    try {
      await deleteUserRequest(user.id());
      this.pmgSelectedUserIds.delete(String(user.id()));
      app.store.remove(user);
      this.pmgBulkStatus = app.translator.trans('hardened-stacks-delete-users.admin.delete_done_one', {
        username: user.displayName(),
      });
      this.isLoadingPage = true;
      await this.loadPage(this.pageNumber);
    } catch (error) {
      this.pmgBulkStatus = app.translator.trans('hardened-stacks-delete-users.admin.delete_failed');
      throw error;
    } finally {
      this.pmgBulkDeleting = false;
      m.redraw();
    }
  };

  UserListPage.prototype.pmgBulkDeleteSelected = async function () {
    // Array.from: Babel may rewrite [...Set] to [].concat(Set), which does not expand Sets.
    const ids = Array.from(this.pmgSelectedUserIds)
      .map((id) => parseInt(id, 10))
      .filter((id) => id > 0);

    if (ids.length === 0 || this.pmgBulkDeleting) {
      return;
    }

    const confirmed = window.confirm(
      confirmText(
        app.translator.trans('hardened-stacks-delete-users.admin.bulk_delete_confirm', {
          count: ids.length,
        })
      )
    );

    if (!confirmed) {
      return;
    }

    this.pmgBulkDeleting = true;
    this.pmgBulkStatus = app.translator.trans('hardened-stacks-delete-users.admin.deleting_many', {
      count: ids.length,
    });
    m.redraw();

    try {
      const response = await bulkDeleteRequest(ids);
      const deleted = response?.data?.deletedUsers ?? 0;
      const skipped = response?.data?.skipped?.length ?? 0;

      this.pmgSelectedUserIds.clear();

      if (skipped > 0) {
        this.pmgBulkStatus = app.translator.trans(
          'hardened-stacks-delete-users.admin.bulk_delete_partial',
          { deleted, skipped }
        );
        app.alerts.show(
          { type: 'warning' },
          app.translator.trans('hardened-stacks-delete-users.admin.bulk_delete_partial', {
            deleted,
            skipped,
          })
        );
      } else {
        this.pmgBulkStatus = app.translator.trans(
          'hardened-stacks-delete-users.admin.bulk_delete_success',
          { count: deleted }
        );
        app.alerts.show(
          { type: 'success' },
          app.translator.trans('hardened-stacks-delete-users.admin.bulk_delete_success', {
            count: deleted,
          })
        );
      }

      this.isLoadingPage = true;
      await this.loadPage(this.pageNumber);
    } catch (error) {
      this.pmgBulkStatus = app.translator.trans('hardened-stacks-delete-users.admin.delete_failed');
      throw error;
    } finally {
      this.pmgBulkDeleting = false;
      m.redraw();
    }
  };
});
