import app from 'flarum/admin/app';

app.initializers.add('hardened-stacks-lxmf', () => {
  const ext = app.extensionData.for('hardened-stacks-lxmf');
  const t = (key) => app.translator.trans(`hardened-stacks-lxmf.admin.settings.${key}`);

  ext
    .registerSetting(
      {
        setting: 'hardened-stacks-lxmf.no_email_registration',
        type: 'boolean',
        label: t('no_email_registration_label'),
        help: t('no_email_registration_help'),
      },
      100
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-lxmf.email_domain',
        type: 'text',
        label: t('email_domain_label'),
        help: t('email_domain_help'),
      },
      90
    );
});
