const path = require('path');

const paths = {
    themePath: path.resolve(__dirname, './'), // Theme root
    source: path.resolve(__dirname, './resources'), // Source files
    output: path.resolve(__dirname, './dist'), // Compiled output
};

module.exports = {
    paths,
    proxyUrl: 'http://domain.local', // Your local development URL

    // Entry points for Webpack
    entries: {
        main: [
            path.resolve(paths.source, 'scripts/main.js'),
            path.resolve(paths.source, 'styles/main.scss'),
        ],
        editor: [
            path.resolve(paths.source, 'scripts/editor.js'),
            path.resolve(paths.source, 'styles/editor.scss'),
        ],
    },

    // Copy patterns for assets like images and fonts
    copyPatterns: [
        {from: path.resolve(paths.source, 'fonts'), to: 'fonts'},
        {from: path.resolve(paths.source, 'images'), to: 'images'},
    ],
};
