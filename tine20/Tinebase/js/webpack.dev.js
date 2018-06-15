const merge = require('webpack-merge');
const common = require('./webpack.common.js');

module.exports = merge(common, {
    devtool: 'eval',
    devServer: {
        hot: false,
        inline: false,
        host: '0.0.0.0',
        port: 10443,
        disableHostCheck: true,
        proxy: [
            {
                context: ['**', '!/webpack-dev-server*/**'],
                target: 'http://localhost/',
                secure: false
            }
        ],
        before: function(app, server) {
            app.use(function(req, res, next) {
                // check for langfile chunk requests
                // build on demand
                // extract-text
                next();
            });
        }
    },
});