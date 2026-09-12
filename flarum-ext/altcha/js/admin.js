import app from 'flarum/admin/app';

app.initializers.add('hardened-stacks-altcha', () => {
  const ext = app.extensionData.for('hardened-stacks-altcha');
  const t = (key) => app.translator.trans(`hardened-stacks-altcha.admin.settings.${key}`);

  ext
    .registerSetting(
      {
        setting: 'hardened-stacks-altcha.enabled',
        type: 'boolean',
        label: t('enabled_label'),
        help: t('enabled_help'),
      },
      100
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-altcha.cost',
        type: 'number',
        label: t('cost_label'),
        help: t('cost_help'),
      },
      90
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-altcha.protect_registration',
        type: 'boolean',
        label: t('protect_registration_label'),
      },
      85
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-altcha.protect_login',
        type: 'boolean',
        label: t('protect_login_label'),
      },
      80
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-altcha.protect_password_reset',
        type: 'boolean',
        label: t('protect_password_reset_label'),
      },
      75
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-altcha.protect_discussion',
        type: 'boolean',
        label: t('protect_discussion_label'),
      },
      70
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-altcha.protect_reply',
        type: 'boolean',
        label: t('protect_reply_label'),
      },
      65
    );
});
