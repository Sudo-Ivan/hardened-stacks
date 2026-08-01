import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Stream from 'flarum/common/utils/Stream';

export default class UserModerationModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    this.app = vnode.attrs.app;
    this.user = vnode.attrs.user;
    this.posts = [];
    this.loadingPosts = false;
    this.busy = false;
    this.selected = {};
    this.lockMessage = Stream('');
    this.suspendUntil = Stream('');
    this.hardDelete = Stream(false);

    this.loadPosts();
  }

  className() {
    return 'UserModerationModal Modal--small';
  }

  title() {
    return this.app.translator.trans('hardened-stacks-user-management.forum.modal.title', {
      username: this.user.displayName(),
    });
  }

  content() {
    const t = (key) => this.app.translator.trans(`hardened-stacks-user-management.forum.modal.${key}`);

    return (
      <div className="Modal-body">
        <div className="Form-group">{this.statusLine()}</div>

        <div className="Form-group">
          <label>{t('lock_message_label')}</label>
          <input className="FormControl" type="text" bidi={this.lockMessage} />
        </div>

        <div className="Form-group">
          <label>{t('suspend_until_label')}</label>
          <input className="FormControl" type="datetime-local" value={this.suspendUntil()} oninput={(e) => this.suspendUntil(e.target.value)} />
        </div>

        <div className="Form-group">
          <label>
            <input type="checkbox" checked={this.hardDelete()} onchange={(e) => this.hardDelete(e.target.checked)} />
            {' '}
            {t('purge_hard')}
          </label>
        </div>

        <div className="ButtonGroup">{this.actionButtons()}</div>

        <hr />

        <div className="UserModerationModal-posts">
          <div className="UserModerationModal-postsHeader">
            <h4>Posts</h4>
            <Button className="Button" icon="fas fa-sync" onclick={() => this.loadPosts()} loading={this.loadingPosts}>
              {t('load_posts')}
            </Button>
          </div>

          {this.loadingPosts ? <LoadingIndicator /> : this.postList()}
        </div>
      </div>
    );
  }

  statusLine() {
    const t = (key) => this.app.translator.trans(`hardened-stacks-user-management.forum.modal.${key}`);

    if (this.user.pmgSuspended()) {
      return <p><strong>{t('status_suspended')}</strong></p>;
    }

    if (this.user.pmgPostingLocked()) {
      return <p><strong>{t('status_locked')}</strong></p>;
    }

    return <p>{t('status_active')}</p>;
  }

  actionButtons() {
    const t = (key) => this.app.translator.trans(`hardened-stacks-user-management.forum.modal.${key}`);

    const buttons = [
      Button.component(
        { className: 'Button', icon: 'fas fa-lock', onclick: () => this.run('lock_posting', { message: this.lockMessage() }), loading: this.busy },
        t('lock_posting')
      ),
      Button.component(
        { className: 'Button', icon: 'fas fa-lock-open', onclick: () => this.run('unlock_posting'), loading: this.busy },
        t('unlock_posting')
      ),
      Button.component(
        { className: 'Button', icon: 'fas fa-user-slash', onclick: () => this.run('suspend', { message: this.lockMessage(), until: this.suspendUntil() || 'forever' }), loading: this.busy },
        t('suspend')
      ),
      Button.component(
        { className: 'Button', icon: 'fas fa-user-check', onclick: () => this.run('unsuspend'), loading: this.busy },
        t('unsuspend')
      ),
      Button.component(
        { className: 'Button', icon: 'fas fa-image', onclick: () => this.run('reset_avatar'), loading: this.busy },
        t('reset_avatar')
      ),
      Button.component(
        { className: 'Button', icon: 'fas fa-eraser', onclick: () => this.run('reset_profile'), loading: this.busy },
        t('reset_profile')
      ),
    ];

    if (this.user.canPmgPurgeContent()) {
      buttons.push(
        Button.component(
          {
            className: 'Button Button--danger',
            icon: 'fas fa-trash',
            onclick: () => {
              if (!confirm(t(this.hardDelete() ? 'confirm_purge_hard' : 'confirm_purge_soft'))) return;
              this.run('purge_content', { hard: this.hardDelete() });
            },
            loading: this.busy,
          },
          this.hardDelete() ? t('purge_hard') : t('purge_soft')
        ),
        Button.component(
          {
            className: 'Button Button--danger',
            icon: 'fas fa-check-double',
            onclick: () => this.deleteSelected(),
            loading: this.busy,
          },
          this.hardDelete() ? t('delete_selected_hard') : t('delete_selected_soft')
        )
      );
    }

    if (this.user.canPmgDeleteUser()) {
      buttons.push(
        Button.component(
          {
            className: 'Button Button--danger',
            icon: 'fas fa-user-times',
            onclick: () => {
              if (!confirm(t('confirm_delete_user'))) return;
              this.run('delete_user', { purgeFirst: true, hard: this.hardDelete() });
            },
            loading: this.busy,
          },
          t('delete_user')
        )
      );
    }

    return buttons;
  }

  postList() {
    const t = (key) => this.app.translator.trans(`hardened-stacks-user-management.forum.modal.${key}`);

    if (!this.posts.length) {
      return <p>{t('no_posts')}</p>;
    }

    return (
      <ul className="UserModerationModal-postList">
        {this.posts.map((post) => (
          <li key={post.id}>
            <label>
              <input
                type="checkbox"
                checked={!!this.selected[post.id]}
                onchange={(e) => {
                  this.selected[post.id] = e.target.checked;
                  m.redraw();
                }}
              />
              <strong>{post.discussionTitle}</strong>
              <span className="UserModerationModal-postMeta"> #{post.number}</span>
              <div className="UserModerationModal-postPreview">{post.preview}</div>
            </label>
          </li>
        ))}
      </ul>
    );
  }

  apiUrl(path) {
    const base = this.app.forum?.attribute('apiUrl') || this.app.data?.apiUrl || '/api';
    return `${base}${path}`;
  }

  loadPosts() {
    this.loadingPosts = true;

    this.app
      .request({
        method: 'GET',
        url: this.apiUrl(`/pmg/users/${this.user.id()}/posts`),
      })
      .then((response) => {
        this.posts = response.data || [];
        this.selected = {};
      })
      .catch(() => {
        this.posts = [];
      })
      .finally(() => {
        this.loadingPosts = false;
        m.redraw();
      });
  }

  deleteSelected() {
    const t = (key) => this.app.translator.trans(`hardened-stacks-user-management.forum.modal.${key}`);
    const postIds = Object.keys(this.selected).filter((id) => this.selected[id]).map((id) => parseInt(id, 10));

    if (!postIds.length) {
      return;
    }

    if (!confirm(t('confirm_delete_selected'))) {
      return;
    }

    this.run('delete_posts', { postIds, hard: this.hardDelete() });
  }

  run(action, extra = {}) {
    const t = (key) => this.app.translator.trans(`hardened-stacks-user-management.forum.modal.${key}`);
    this.busy = true;

    return this.app
      .request({
        method: 'POST',
        url: this.apiUrl(`/pmg/users/${this.user.id()}/moderate`),
        body: {
          data: {
            type: 'pmgModeration',
            attributes: {
              action,
              ...extra,
            },
          },
        },
      })
      .then((response) => {
        const data = response.data || {};

        if (typeof data.postingLocked === 'boolean') {
          this.user.pushAttributes({
            pmgPostingLocked: data.postingLocked,
            pmgPostingLockMessage: data.postingLockMessage,
            pmgSuspended: data.suspended,
            pmgSuspendedUntil: data.suspendedUntil,
          });
        }

        if (data.userDeleted) {
          this.app.modal.close();
          m.route.set('/');
          return;
        }

        if (action === 'purge_content' || action === 'delete_posts') {
          this.loadPosts();
        }

        this.app.alerts.show({ type: 'success' }, t('success'));
      })
      .catch(() => {
        this.app.alerts.show({ type: 'error' }, t('error'));
      })
      .finally(() => {
        this.busy = false;
        m.redraw();
      });
  }
}
