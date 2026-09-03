import app from 'flarum/admin/app';

app.initializers.add('hardened-stacks-maintenance', () => {
  const ext = app.extensionData.for('hardened-stacks-maintenance');
  const t = (key) => app.translator.trans(`hardened-stacks-maintenance.admin.settings.${key}`);

  ext
    .registerSetting(
      {
        setting: 'hardened-stacks-maintenance.mode',
        type: 'select',
        label: t('mode_label'),
        help: t('mode_help'),
        options: {
          off: t('mode_options.off'),
          banner: t('mode_options.banner'),
          read_only: t('mode_options.read_only'),
          closed: t('mode_options.closed'),
        },
      },
      100
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-maintenance.title',
        type: 'text',
        label: t('title_label'),
        help: t('title_help'),
      },
      90
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-maintenance.message',
        type: 'textarea',
        label: t('message_label'),
        help: t('message_help'),
      },
      80
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-maintenance.allow_login',
        type: 'boolean',
        label: t('allow_login_label'),
        help: t('allow_login_help'),
      },
      70
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-maintenance.show_banner',
        type: 'boolean',
        label: t('show_banner_label'),
        help: t('show_banner_help'),
      },
      60
    );
});
