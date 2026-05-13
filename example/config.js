const path = require('path');

module.exports = {
    paths: {
        themePath: path.resolve(__dirname, './'), // Output files
        source: path.resolve(__dirname, './resources/assets'), // Source files
        output: path.resolve(__dirname, './dist'), // Output files
    },
    proxyUrl: 'http://domain.local', // Your local development URL

    // Entry points for Webpack
    entries: {
        main: [
            path.resolve(path.source, 'scripts/main.js'),
            path.resolve(path.source, 'styles/main.scss'),
        ],
        editor: [
            path.resolve(path.source, 'scripts/editor.js'),
            path.resolve(path.source, 'styles/editor.scss'),
        ],
    },

    // Copy patterns for assets like images and fonts
    copyPatterns: [
        {from: path.resolve(path.source, 'fonts'), to: 'fonts'},
        {from: path.resolve(paths.source, 'images'), to: 'images'},
    ],
};
