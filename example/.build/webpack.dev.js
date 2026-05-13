const {merge} = require('webpack-merge');
const common = require('./webpack.common.js');
const webpack = require('webpack');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');
const config = require('../config');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");

module.exports = merge(common, {
  mode: 'development', output: {
    path: config.paths.output,
    filename: 'scripts/[name].js',
  },
  devtool: 'source-map',
  watchOptions: {
    poll: 1000,  // Check for changes every second
    aggregateTimeout: 300,  // Delay the rebuild after the first change
    ignored: /node_modules/,  // Ignore changes in node_modules
  },
  plugins: [
    new webpack.DefinePlugin({
      'process.env.NODE_ENV': JSON.stringify('development'),
    }),
    new MiniCssExtractPlugin({
      filename: 'styles/[name].css',
    }),
    new BrowserSyncPlugin({
      port: 3000,
      proxy: config.proxyUrl,
      reloadOnRestart: true,
      injectChanges: true,
      open: false,
      notify: false,
      files: [
        `${config.paths.themePath}/**/*.js`,
        `${config.paths.themePath}/**/*.css`,
        `${config.paths.themePath}/**/*.scss`,
        `${config.paths.themePath}/**/*.php`,
      ],
    }, {
      reload: true,
    }),
  ],
});
