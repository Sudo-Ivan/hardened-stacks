import app from 'flarum/admin/app';

app.initializers.add('hardened-stacks-spam-protection', () => {
  const ext = app.extensionData.for('hardened-stacks-spam-protection');
  const t = (key) => app.translator.trans(`hardened-stacks-spam-protection.admin.settings.${key}`);

  ext
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.new_user_post_delay_enabled',
        type: 'boolean',
        label: t('new_user_post_delay_enabled_label'),
        help: t('new_user_post_delay_enabled_help'),
      },
      102
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.new_user_post_delay',
        type: 'number',
        label: t('new_user_post_delay_label'),
        help: t('new_user_post_delay_help'),
      },
      101
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.min_post_interval',
        type: 'number',
        label: t('min_post_interval_label'),
        help: t('min_post_interval_help'),
      },
      100
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.new_user_min_post_interval',
        type: 'number',
        label: t('new_user_min_post_interval_label'),
        help: t('new_user_min_post_interval_help'),
      },
      95
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.burst_posts_hour',
        type: 'number',
        label: t('burst_posts_hour_label'),
        help: t('burst_posts_hour_help'),
      },
      90
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.duplicate_window',
        type: 'number',
        label: t('duplicate_window_label'),
        help: t('duplicate_window_help'),
      },
      85
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.new_user_days',
        type: 'number',
        label: t('new_user_days_label'),
        help: t('new_user_days_help'),
      },
      80
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.new_user_post_count',
        type: 'number',
        label: t('new_user_post_count_label'),
        help: t('new_user_post_count_help'),
      },
      75
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.min_links_for_context_check',
        type: 'number',
        label: t('min_links_for_context_check_label'),
        help: t('min_links_for_context_check_help'),
      },
      70
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.min_non_link_chars',
        type: 'number',
        label: t('min_non_link_chars_label'),
        help: t('min_non_link_chars_help'),
      },
      65
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.max_links',
        type: 'number',
        label: t('max_links_label'),
        help: t('max_links_help'),
      },
      40
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.new_user_max_links',
        type: 'number',
        label: t('new_user_max_links_label'),
        help: t('new_user_max_links_help'),
      },
      35
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.max_url_ratio',
        type: 'number',
        label: t('max_url_ratio_label'),
        help: t('max_url_ratio_help'),
      },
      30
    )
    .registerSetting(
      {
        setting: 'hardened-stacks-spam-protection.url_ratio_min_length',
        type: 'number',
        label: t('url_ratio_min_length_label'),
        help: t('url_ratio_min_length_help'),
      },
      25
    );
});
