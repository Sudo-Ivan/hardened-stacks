import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

function t(key, params = {}) {
  return app.translator.trans(`hardened-stacks-spam-protection.admin.${key}`, params);
}

app.initializers.add('hardened-stacks-spam-protection', () => {
  const ext = app.extensionData.for('hardened-stacks-spam-protection');
  const ts = (key) => t(`settings.${key}`);

  ext
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.enabled',
        type: 'boolean',
        label: ts('enabled_label'),
        help: ts('enabled_help'),
      },
      120
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.api_key',
        type: 'password',
        label: ts('api_key_label'),
        help: ts('api_key_help'),
      },
      115
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.base_url',
        type: 'text',
        label: ts('base_url_label'),
        help: ts('base_url_help'),
      },
      110
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.model',
        type: 'text',
        label: ts('model_label'),
        help: ts('model_help'),
      },
      105
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.monitor_posts',
        type: 'boolean',
        label: ts('monitor_posts_label'),
        help: ts('monitor_posts_help'),
      },
      100
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.monitor_edits',
        type: 'boolean',
        label: ts('monitor_edits_label'),
        help: ts('monitor_edits_help'),
      },
      98
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.monitor_new_users',
        type: 'boolean',
        label: ts('monitor_new_users_label'),
        help: ts('monitor_new_users_help'),
      },
      95
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.new_user_days',
        type: 'number',
        label: ts('new_user_days_label'),
        help: ts('new_user_days_help'),
      },
      90
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.new_user_post_count',
        type: 'number',
        label: ts('new_user_post_count_label'),
        help: ts('new_user_post_count_help'),
      },
      85
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.min_confidence',
        type: 'number',
        label: ts('min_confidence_label'),
        help: ts('min_confidence_help'),
      },
      80
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.action_hide_post',
        type: 'boolean',
        label: ts('action_hide_post_label'),
      },
      75
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.action_hide_discussion',
        type: 'boolean',
        label: ts('action_hide_discussion_label'),
      },
      70
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.action_lock_discussion',
        type: 'boolean',
        label: ts('action_lock_discussion_label'),
        help: ts('action_lock_discussion_help'),
      },
      65
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.action_suspend_user',
        type: 'boolean',
        label: ts('action_suspend_user_label'),
        help: ts('action_suspend_user_help'),
      },
      60
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.suspend_days',
        type: 'number',
        label: ts('suspend_days_label'),
        help: ts('suspend_days_help'),
      },
      55
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.fail_open',
        type: 'boolean',
        label: ts('fail_open_label'),
        help: ts('fail_open_help'),
      },
      50
    );

  extend(ExtensionPage.prototype, 'oninit', function () {
    if (this.extension.id !== 'hardened-stacks-spam-protection') {
      return;
    }

    this.pmgSpamAudits = [];
    this.pmgSpamEnabled = false;
    this.pmgSpamConfigured = false;
    this.pmgSpamLoading = false;
    this.pmgSpamRescanning = false;
    this.pmgSpamStatus = '';
    this.pmgLoadSpamAudits();
  });

  ExtensionPage.prototype.pmgLoadSpamAudits = async function () {
    if (this.extension.id !== 'hardened-stacks-spam-protection') {
      return;
    }

    this.pmgSpamLoading = true;
    m.redraw();

    try {
      const response = await app.request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/pmg/spam/audits?limit=50',
      });

      this.pmgSpamAudits = response?.data?.audits || [];
      this.pmgSpamEnabled = !!response?.data?.enabled;
      this.pmgSpamConfigured = !!response?.data?.configured;
      this.pmgSpamStatus = '';
    } catch (error) {
      this.pmgSpamStatus = t('audit.load_failed');
      throw error;
    } finally {
      this.pmgSpamLoading = false;
      m.redraw();
    }
  };

  ExtensionPage.prototype.pmgRescanRecent = async function () {
    if (this.extension.id !== 'hardened-stacks-spam-protection' || this.pmgSpamRescanning) {
      return;
    }

    this.pmgSpamRescanning = true;
    this.pmgSpamStatus = t('audit.rescanning');
    m.redraw();

    try {
      const response = await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/pmg/spam/rescan-recent',
        body: {
          data: {
            attributes: {
              limit: 25,
            },
          },
        },
      });

      const scanned = response?.data?.scanned ?? 0;
      const spam = response?.data?.spam ?? 0;
      this.pmgSpamStatus = t('audit.rescan_done', { scanned, spam });
      await this.pmgLoadSpamAudits();
    } catch (error) {
      this.pmgSpamStatus = t('audit.rescan_failed');
      throw error;
    } finally {
      this.pmgSpamRescanning = false;
      m.redraw();
    }
  };

  extend(ExtensionPage.prototype, 'sections', function (items) {
    if (this.extension.id !== 'hardened-stacks-spam-protection') {
      return;
    }

    items.add('pmgSpamAudit', this.pmgSpamAuditSection(), 10);
  });

  ExtensionPage.prototype.pmgSpamAuditSection = function () {
    const audits = this.pmgSpamAudits || [];

    return (
      <div className="PmgSpamAudit">
        <div className="PmgSpamAudit-header">
          <div>
            <h3>{t('audit.title')}</h3>
            <div className="PmgSpamAudit-meta">
              {t('audit.status', {
                enabled: this.pmgSpamEnabled ? t('audit.yes') : t('audit.no'),
                configured: this.pmgSpamConfigured ? t('audit.yes') : t('audit.no'),
              })}
            </div>
            {this.pmgSpamStatus ? <div className="PmgSpamAudit-meta">{this.pmgSpamStatus}</div> : null}
          </div>
          <div className="PmgSpamAudit-actions">
            <Button
              className="Button"
              icon="fas fa-sync"
              loading={this.pmgSpamLoading}
              disabled={this.pmgSpamLoading || this.pmgSpamRescanning}
              onclick={() => this.pmgLoadSpamAudits()}
            >
              {t('audit.refresh')}
            </Button>
            <Button
              className="Button Button--primary"
              icon="fas fa-search"
              loading={this.pmgSpamRescanning}
              disabled={this.pmgSpamLoading || this.pmgSpamRescanning}
              onclick={() => this.pmgRescanRecent()}
            >
              {t('audit.rescan_recent')}
            </Button>
          </div>
        </div>

        {this.pmgSpamLoading && audits.length === 0 ? (
          <LoadingIndicator />
        ) : audits.length === 0 ? (
          <p className="PmgSpamAudit-empty">{t('audit.empty')}</p>
        ) : (
          <div className="PmgSpamAudit-tableWrap">
            <table className="PmgSpamAudit-table">
              <thead>
                <tr>
                  <th>{t('audit.col_time')}</th>
                  <th>{t('audit.col_kind')}</th>
                  <th>{t('audit.col_post')}</th>
                  <th>{t('audit.col_user')}</th>
                  <th>{t('audit.col_status')}</th>
                  <th>{t('audit.col_confidence')}</th>
                  <th>{t('audit.col_actions')}</th>
                  <th>{t('audit.col_reason')}</th>
                </tr>
              </thead>
              <tbody>
                {audits.map((row) => (
                  <tr key={row.id}>
                    <td>{row.created_at || ''}</td>
                    <td>{row.kind || ''}</td>
                    <td>{row.post_id || '-'}</td>
                    <td>{row.user_id || '-'}</td>
                    <td className={'PmgSpamAudit-status-' + (row.status || 'ok')}>
                      {row.status || 'ok'}
                      {row.error ? <div>{row.error}</div> : null}
                    </td>
                    <td>{row.confidence ?? 0}</td>
                    <td>{row.actions || '-'}</td>
                    <td>{row.reason || '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    );
  };
});
