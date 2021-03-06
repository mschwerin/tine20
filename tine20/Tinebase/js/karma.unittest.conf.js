let path = require('path')
let basePath = path.resolve(__dirname, "../../../tests/js/unit")

module.exports = function (config) {
    config.set({
        // base path that will be used to resolve all patterns (eg. files, exclude)
        basePath: basePath,

        // available frameworks: https://npmjs.org/browse/keyword/karma-adapter
        frameworks: ['mocha', 'chai', 'chai-as-promised', 'sinon'],

        // list of files / patterns to load in the browser
        files: [
            path.resolve(__dirname, "../../library/ExtJS/adapter/ext/ext-base-debug.js"),
            path.resolve(__dirname, "../../library/ExtJS/ext-all-debug.js"),
            '**/*.spec.js'
        ],

        // list of files / patterns to exclude
        exclude: [],

        // preprocess matching files before serving them to the browser
        // available preprocessors: https://npmjs.org/browse/keyword/karma-preprocessor
        preprocessors: {
            "**/*.js": ["eslint", "webpack", "sourcemap"]
        },

        // webpack configuration
        webpack: require("./webpack.unittest.js"),
        webpackMiddleware: {
            stats: "errors-only"
        },

        eslint: {
            // errorThreshold: 10000,
            // stopAboveErrorThreshold: true,
            // stopOnError: true,
            // stopOnWarning: true,
            // showWarnings: true,
        },

        // test results reporter to use
        // available reporters: https://npmjs.org/browse/keyword/karma-reporter
        reporters: ['spec', 'coverage-istanbul', 'junit' /*, 'dots' */],

        coverageIstanbulReporter: {
            dir: basePath + '/artefacts/coverage',
            reports: ['html', 'text', 'text-summary', 'clover'],
            'report-config': {
                html: {
                    subdir: 'html'
                }
            }
        },

        junitReporter: {
            outputDir: 'artefacts',
            outputFile: 'test-results.xml'
        },

        // web server port
        port: 9876,

        // enable / disable colors in the output (reporters and logs)
        colors: true,

        // level of logging
        // possible values: config.LOG_DISABLE || config.LOG_ERROR || config.LOG_WARN || config.LOG_INFO || config.LOG_DEBUG
        logLevel: config.LOG_INFO,

        // enable / disable watching file and executing tests whenever any file changes
        autoWatch: true,

        // start these browsers
        // available browser launchers: https://npmjs.org/browse/keyword/karma-launcher
        // browsers: ['PhantomJS', 'Chrome', 'ChromeWithoutSecurity', 'Firefox', 'Safari', 'IE'],
        browsers: ['PhantomJS'],
        customLaunchers: {
            ChromeWithoutSecurity: {
                base: 'Chrome',
                flags: ['--disable-web-security']
            }
        },

        // Continuous Integration mode
        // if true, Karma captures browsers, runs the tests and exits
        singleRun: false,

        // Concurrency level
        // how many browser should be started simultaneous
        concurrency: Infinity
    })
}
