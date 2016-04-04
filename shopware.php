<?php

require __DIR__ . '/../../vendor/deployer/deployer/recipe/common.php';

set('shared_dirs', [
    'engine/Shopware/Plugins/Community',
    'media',
    'files'
]);
set('create_shared_dirs', [
    'engine/Shopware/Plugins/Community/Frontend',
    'engine/Shopware/Plugins/Community/Core',
    'engine/Shopware/Plugins/Community/Backend',
    'media/archive',
    'media/image',
    'media/image/thumbnail',
    'media/music',
    'media/pdf',
    'media/unknown',
    'media/video',
    'media/temp',
    'files/documents',
    'files/downloads'
]);
set('writable_dirs', [
    'var',
    'web',
    'files',
    'media',
    'engine/Shopware/Plugins/Community',
    'recovery',
    'themes'
]);
set('writable_use_sudo', false);

/**
 * Installing vendors tasks.
 */
task('deploy:vendors:recovery', function () {
    $composer = get('composer_command');

    if (! commandExist($composer)) {
        run("cd {{release_path}} && curl -sS https://getcomposer.org/installer | php");
        $composer = 'php {{release_path}}/composer.phar';
    }

    $composerEnvVars = env('env_vars') ? 'export ' . env('env_vars') . ' &&' : '';
    run("cd {{release_path}}/recovery/common && $composerEnvVars $composer {{composer_options}}");

})->desc('Installing recovery vendors for shopware');

after('deploy:vendors', 'deploy:vendors:recovery');

task('deploy:shared:sub', function () {
    $sharedPath = "{{deploy_path}}/shared";

    foreach (get('create_shared_dirs') as $dir) {
        // Create shared dir if it does not exist.
        run("mkdir -p $sharedPath/$dir");
    }
})->desc('Creating shared subdirs');

after('deploy:shared', 'deploy:shared:sub');

task('deploy:prepare:configuration', function() {
    run("cd {{release_path}} && cp {{deploy_path}}/shared/default.ini ./");
    run("cd {{release_path}} && mv ./config.php.dist ./config.php && chmod 777 ./config.php");
    /**
     * Additionally for install needed:
     * ALTER TABLE `s_core_snippets` ADD `dirty` INT( 1 ) NOT NULL DEFAULT '0';
     * ALTER TABLE `s_core_shops` ADD `always_secure` INT( 1 ) NOT NULL DEFAULT '0';
     */
    upload(__DIR__ . '/_sql/install/latest.sql', '{{release_path}}/recovery/install/data/sql/install.sql');
    upload(__DIR__ . '/_sql/snippets.sql', '{{release_path}}/recovery/install/data/sql/snippets.sql');
});

task('deploy:install:shop', function() {
    run("mysql --defaults-extra-file={{deploy_path}}/shared/default.ini < {{release_path}}/recovery/install/data/sql/install.sql");

    run("cd {{release_path}} && php build/ApplyDeltas.php");
    run("cd {{release_path}} && php bin/console sw:generate:attributes");
    run("cd {{release_path}} && php bin/console sw:theme:initialize");
    run("cd {{release_path}} && php bin/console sw:firstrunwizard:disable");

    run("mysql --defaults-extra-file={{deploy_path}}/shared/default.ini < {{release_path}}/recovery/install/data/sql/snippets.sql");

    run("cd {{release_path}}/recovery/install/data && touch install.lock");
});

after('deploy:prepare:configuration', 'deploy:install:shop');

/**
 * Main task
 */
task('shopware:install', [
    'deploy:prepare',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:vendors',
    'deploy:writable',
    'deploy:prepare:configuration',
    'deploy:symlink',
    'cleanup',
])->desc('Install a complete new shopware instance');
