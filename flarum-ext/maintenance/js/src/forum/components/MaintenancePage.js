import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LogInModal from 'flarum/forum/components/LogInModal';

export default class MaintenancePage extends Component {
  view() {
    const title = app.forum.attribute('hardened-stacksMaintenanceTitle');
    const message = app.forum.attribute('hardened-stacksMaintenanceMessage');
    const allowLogin = !!app.forum.attribute('hardened-stacksMaintenanceAllowLogin');
    const loggedIn = !!app.session.user;

    return (
      <div className="PmgMaintenancePage">
        <h1 className="PmgMaintenancePage-title">{title}</h1>
        <p className="PmgMaintenancePage-message">{message}</p>
        {allowLogin && !loggedIn && (
          <div className="PmgMaintenancePage-actions">
            <Button className="Button Button--primary" onclick={() => app.modal.show(LogInModal)}>
              {app.translator.trans('hardened-stacks-maintenance.forum.login')}
            </Button>
          </div>
        )}
      </div>
    );
  }
}
