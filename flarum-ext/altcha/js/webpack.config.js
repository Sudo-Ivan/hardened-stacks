const webpack = require('webpack');
const config = require('flarum-webpack-config')();

function forceSingleBundle(cfg) {
  cfg.optimization = Object.assign({}, cfg.optimization, {
    splitChunks: false,
    runtimeChunk: false,
  });
  cfg.plugins = (cfg.plugins || []).concat([
    new webpack.optimize.LimitChunkCountPlugin({ maxChunks: 1 }),
  ]);
  return cfg;
}

module.exports = Array.isArray(config)
  ? config.map(forceSingleBundle)
  : forceSingleBundle(config);
