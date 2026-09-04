const config = require('flarum-webpack-config')();

function withoutSourceMaps(cfg) {
  cfg.devtool = false;
  return cfg;
}

module.exports = Array.isArray(config)
  ? config.map(withoutSourceMaps)
  : withoutSourceMaps(config);
