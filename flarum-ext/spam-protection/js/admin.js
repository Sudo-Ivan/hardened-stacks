import app from 'flarum/admin/app';

app.initializers.add('hardened-stacks-spam-protection', () => {
  const ext = app.extensionData.for('hardened-stacks-spam-protection');
  const t = (key) => app.translator.trans(`hardened-stacks-spam-protection.admin.settings.${key}`);

  ext
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.enabled',
        type: 'boolean',
        label: t('enabled_label'),
        help: t('enabled_help'),
      },
      120
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.api_key',
        type: 'password',
        label: t('api_key_label'),
        help: t('api_key_help'),
      },
      115
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.base_url',
        type: 'text',
        label: t('base_url_label'),
        help: t('base_url_help'),
      },
      110
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.model',
        type: 'text',
        label: t('model_label'),
        help: t('model_help'),
      },
      105
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.monitor_posts',
        type: 'boolean',
        label: t('monitor_posts_label'),
        help: t('monitor_posts_help'),
      },
      100
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.monitor_new_users',
        type: 'boolean',
        label: t('monitor_new_users_label'),
        help: t('monitor_new_users_help'),
      },
      95
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.new_user_days',
        type: 'number',
        label: t('new_user_days_label'),
        help: t('new_user_days_help'),
      },
      90
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.new_user_post_count',
        type: 'number',
        label: t('new_user_post_count_label'),
        help: t('new_user_post_count_help'),
      },
      85
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.min_confidence',
        type: 'number',
        label: t('min_confidence_label'),
        help: t('min_confidence_help'),
      },
      80
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.action_hide_post',
        type: 'boolean',
        label: t('action_hide_post_label'),
      },
      75
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.action_hide_discussion',
        type: 'boolean',
        label: t('action_hide_discussion_label'),
      },
      70
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.action_lock_discussion',
        type: 'boolean',
        label: t('action_lock_discussion_label'),
        help: t('action_lock_discussion_help'),
      },
      65
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.action_suspend_user',
        type: 'boolean',
        label: t('action_suspend_user_label'),
        help: t('action_suspend_user_help'),
      },
      60
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.suspend_days',
        type: 'number',
        label: t('suspend_days_label'),
        help: t('suspend_days_help'),
      },
      55
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.fail_open',
        type: 'boolean',
        label: t('fail_open_label'),
        help: t('fail_open_help'),
      },
      50
    );
});
