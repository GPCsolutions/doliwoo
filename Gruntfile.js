module.exports = function (grunt) {
    // Load tasks
    require('load-grunt-tasks')(grunt);

    // Display task timing
    require('time-grunt')(grunt);

    // Project configuration
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        paths: {
            // PHP assets
            php: {
                files_std: [
                    '*.php',
                    '**/*.php',
                    '!node_modules/**/*.php',
                    '!wordpress/**/*.php',
                    '!vendor/**/*.php'
                ], // Standard file match
                files: '<%= paths.php.files_std %>', // Dynamic file match
                exclude: [
                    'assets/.*',
                    'wordpress/.*',
                    'composer.json',
                    'composer.lock',
                    'CONTRIBUTING.md',
                    '.git/.*',
                    '.gitignore',
                    '.gitmodules',
                    'Gruntfile.js',
                    'node_modules/.*',
                    'package.json',
                    'README.md',
                    'release/.*',
                    '.sensiolabs.yml',
                    '.travis.yml',
                    '.tx',
                    'vendor/.*'
                ] // PHP regex match
            }
        },
        phpcs: {
            application: {
                dir: '<%= paths.php.files %>'
            },
            options: {
                bin: 'vendor/bin/phpcs',
                standard: 'Wordpress'
            }
        },
        phplint: {
            options: {
                phpArgs: {
                    '-lf': null
                }
            },
            all: {
                src: '<%= paths.php.files %>'
            }
        },
        makepot: {
            target: {
                options: {
                    mainFile: 'doliwoo.php',
                    exclude: '<%= paths.php.exclude %>',
                    type: 'wp-plugin',
                    potHeaders: {
                        poedit: true,
                        'report-msgid-bugs-to': 'https://github.com/GPCsolutions/doliwoo/issues'
                    },
                    updatePoFiles: true
                }
            }
        },
        po2mo: {
            files: {
                src: 'languages/*.po',
                expand: true
            }
        },
        clean: {
            main: ['release/<%= pkg.version %>']
        },
        copy: {
            // Copy the plugin to a versioned release directory
            main: {
                src: [
                    '**',
                    '!assets/**',
                    '!wordpress/**',
                    '!composer.json',
                    '!composer.lock',
                    '!CONTRIBUTING.md',
                    '!.git/**',
                    '!.gitignore',
                    '!.gitmodules',
                    '!Gruntfile.js',
                    '!node_modules/**',
                    '!package.json',
                    '!README.md',
                    '!release/**',
                    '!.sensiolabs.yml',
                    '!.travis.yml',
                    '!.tx',
                    '!vendor/**'
                ],
                dest: 'release/<%= pkg.version %>/'
            },
            dist: {
                src: 'readme.txt',
                dest: 'README.md'
            }
        },
        compress: {
            main: {
                options: {
                    mode: 'zip',
                    archive: './release/doliwoo-v<%= pkg.version %>.zip'
                },
                expand: true,
                cwd: 'release/<%= pkg.version %>/',
                src: ['**/*'],
                dest: 'doliwoo/'
            }
        },
        exec: {
            txpush: {
                cmd: 'tx push -s'
            },
            txpull: {
                cmd: 'tx pull -a'
            }
        },
        wp_readme_to_markdown: {
            main: {
                files: {
                    'README.md': 'readme.txt'
                }
            }
        },
        checkwpversion: {
            check: { //Check plug-in version and stable tag match
                version1: 'plugin',
                version2: 'readme',
                compare: '>='
            },
            check2: { //Check plug-in version and package.json match
                version1: 'plugin',
                version2: '<%= pkg.version %>',
                compare: '=='
            }
        },
        "sync-json": {
            options: {
                indent: 2,
                include: [
                    'version',
                    'description',
                    'keywords',
                    'homepage',
                    'license'
                ]
            },
            composer: {
                files: {
                    'composer.json': 'package.json'
                }
            }
        }
    });

    grunt.registerTask('default', [
        'test',
        'potupdate',
        'sync-json',
        'wp_readme_to_markdown'
    ]);

    grunt.registerTask('test', [
        'composer:update',
        'phpcs',
        'phplint',
        'checkwpversion'
    ]);

    grunt.registerTask('potupdate', [
        'makepot',
        'exec:txpush'
    ]);

    grunt.registerTask('poupdate', [
        'exec:txpull'
    ]);

    grunt.registerTask('i18n', [
        'potupdate',
        'poupdate',
        'po2mo'
    ]);

    grunt.registerTask('release', [
        'default',
        'i18n',
        'clean',
        'copy',
        'compress'
    ]);

};
