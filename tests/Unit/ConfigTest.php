<?php

declare(strict_types=1);

describe('netsons-deploy config', function () {
    it('can be loaded', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config)->toBeArray();
    });

    it('defaults strategy to ftp', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['strategy'])->toBe('ftp');
    });

    it('defaults SSH port to 65100', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['ssh']['port'])->toBe(65100);
    });

    it('defaults PHP binary to ea-php84', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['php_binary'])->toBe('/usr/local/bin/ea-php84');
    });

    it('defaults deploy path to public_html', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['deploy_path'])->toBe('public_html');
    });

    it('defaults FTP port to 21', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['ftp']['port'])->toBe(21);
    });

    it('defaults git branch to main', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['git']['branch'])->toBe('main');
    });

    it('defaults releases keep to 5', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['releases']['keep'])->toBe(5);
    });

    it('enables htaccess root and public by default', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['htaccess']['root'])->toBeTrue();
        expect($config['htaccess']['public'])->toBeTrue();
    });

    it('has post_deploy settings with all defaults enabled', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['post_deploy']['clear_cache'])->toBeTrue();
        expect($config['post_deploy']['migrate'])->toBeTrue();
        expect($config['post_deploy']['cache_config'])->toBeTrue();
        expect($config['post_deploy']['cache_routes'])->toBeTrue();
        expect($config['post_deploy']['cache_views'])->toBeTrue();
        expect($config['post_deploy']['cache_events'])->toBeTrue();
        expect($config['post_deploy']['queue_restart'])->toBeTrue();
    });

    it('has empty seeders array by default', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['seeders'])->toBe([]);
    });

    it('has empty env_mapping array by default', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['env_mapping'])->toBe([]);
    });

    it('has both stage and production environments', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['environments'])->toHaveKeys(['stage', 'production']);
    });

    it('defaults composer_binary to /usr/local/bin/composer', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['composer_binary'])->toBe('/usr/local/bin/composer');
    });

    it('defaults FTP root_path to empty string', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['ftp']['root_path'])->toBe('');
    });

    it('defaults SSH retries to 3', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['ssh']['retries'])->toBe(3);
    });

    it('defaults SSH retry_delay to 10', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['ssh']['retry_delay'])->toBe(10);
    });

    it('defaults SSH connect_timeout to 30', function () {
        $config = require __DIR__.'/../../config/netsons-deploy.php';
        expect($config['ssh']['connect_timeout'])->toBe(30);
    });
});
