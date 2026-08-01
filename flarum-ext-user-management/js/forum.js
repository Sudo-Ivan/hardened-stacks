import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import UserPage from 'flarum/forum/components/UserPage';
import Button from 'flarum/common/components/Button';
import UserModerationModal from './src/common/components/UserModerationModal';
import './src/common/extendUserModel';

app.initializers.add('hardened-stacks-user-management', () => {
  extend(UserPage.prototype, 'actionItems', function (items) {
    const user = this.user;

    if (!user || !user.canPmgModerate()) {
      return;
    }

    items.add(
      'pmg-moderate',
      Button.component(
        {
          className: 'Button',
          icon: 'fas fa-user-shield',
          onclick: () => app.modal.show(UserModerationModal, { user, app }),
        },
        app.translator.trans('hardened-stacks-user-management.forum.moderate_button')
      ),
      10
    );
  });
});
