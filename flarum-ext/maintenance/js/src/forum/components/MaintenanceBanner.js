import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';

export default class MaintenanceBanner extends Component {
  view() {
    const title = app.forum.attribute('hardened-stacksMaintenanceTitle');
    const message = app.forum.attribute('hardened-stacksMaintenanceMessage');
    const mode = app.forum.attribute('hardened-stacksMaintenanceMode');
    const readOnlyHint =
      mode === 'read_only'
        ? ' ' + app.translator.trans('hardened-stacks-maintenance.forum.read_only_hint')
        : '';

    return (
      <div className="PmgMaintenanceBanner" role="status">
        <span className="PmgMaintenanceBanner-title">{title}</span>
        <span className="PmgMaintenanceBanner-message">
          {message}
          {readOnlyHint}
        </span>
      </div>
    );
  }
}
