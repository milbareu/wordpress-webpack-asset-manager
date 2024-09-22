const path = require('path');

module.exports = {
  paths: {
    source: path.resolve(__dirname, './resources'), // Source files
    output: path.resolve(__dirname, './public'), // Output files
  },
  proxyUrl: 'http://yourlocaldomain', // Your local development URL

  // Entry points for Webpack
  entries: {
    main: [
      path.resolve(__dirname, './resources/scripts/main.js'),
      path.resolve(__dirname, './resources/styles/main.scss'),
    ],
    editor: [
      path.resolve(__dirname, './resources/scripts/editor.js'),
      path.resolve(__dirname, './resources/styles/editor.scss'),
    ],
  },

  // Copy patterns for assets like images and fonts
  copyPatterns: [
    { from: path.resolve(__dirname, './resources/images'), to: 'images' },
    { from: path.resolve(__dirname, './resources/fonts'), to: 'fonts' },
  ],
};
