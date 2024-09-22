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
  plugins: [
    new webpack.DefinePlugin({
      'process.env.NODE_ENV': JSON.stringify('development'),
    }),
    new MiniCssExtractPlugin({
      filename: 'styles/[name].css',
    }),
    new BrowserSyncPlugin({
      host: 'localhost',
      port: 3000,
      proxy: config.proxyUrl,
      files: [
        `${config.paths.output}/scripts/*.js`,
        `${config.paths.output}/styles/*.css`,
        './**/*.php',
      ],
    }, {
      reload: false,
    }),
  ],
});
