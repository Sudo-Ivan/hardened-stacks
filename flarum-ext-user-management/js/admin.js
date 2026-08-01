import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import EditUserModal from 'flarum/admin/components/EditUserModal';
import Button from 'flarum/common/components/Button';
import UserModerationModal from './src/common/components/UserModerationModal';
import './src/common/extendUserModel';

app.initializers.add('hardened-stacks-user-management', () => {
  extend(EditUserModal.prototype, 'content', function (original) {
    const content = original();
    const user = this.user;

    if (!user || !user.canPmgModerate()) {
      return content;
    }

    return (
      <>
        {content}
        <div className="Form-group">
          <Button
            className="Button Button--primary"
            icon="fas fa-user-shield"
            onclick={() => app.modal.show(UserModerationModal, { user, app })}
          >
            {app.translator.trans('hardened-stacks-user-management.forum.moderate_button')}
          </Button>
        </div>
      </>
    );
  });
});
