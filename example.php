<?php

// load Composer's autoloader
require __DIR__ .'/vendor/autoload.php';

use LemonWeb\Deployer\Deploy;


$deploy = new Deploy(array(
	'project_name' => 'project',
	'basedir' => dirname(__FILE__), // the root dir of the project
	'remote_host' => 'www.example.com', // can also be: array('serv1.example.com', 'serv2.example.com')
    'remote_port' => 22, // this is the default port and may be omitted
	'remote_dir' => '/home/user/project', // this is the same for all remote hosts if you define multiple
	'remote_user' => 'user', // setup public key access to make it easy for yourself, many connections are made
	'rsync_excludes' => 'config/rsync_exclude.txt',
	'data_dirs' => array( // UGC dirs. These dirs are separated from the other code and replaced by symlinks, to become deployment version-independent
	    'web/uploads',
	    'logs'
	),
	'target_specific_files' => array( // list of files that will be renamed on the remote host
		'web/.htaccess',
		'config/database.php'
	),
	'database_dirs' => array('data/sql-updates'),
	'database_host' => 'localhost',
	'database_port' => 3306,
	'database_name' => 'database',
	'database_user' => 'root', // if you omit these you will be asked for them if they are needed
	'database_pass' => 'p@ssw0rd',
	'target' => 'prod',
	'database_patcher' => 'vendor/lemonweb/deployer/bin/database-patcher.php',
	'datadir_patcher' => 'vendor/lemonweb/deployer/bin/datadir-patcher.php',

    // APC cache handling (see apc/frontcontroller_example.php for details)
    'apc_deploy_version_template' => 'lib/deployer/apc/deploy_version_template.php',
    'apc_deploy_version_path' => '../deploy_version.php', // this file will be placed in the project's root
    'apc_deploy_setrev_url' => 'http://localhost/deployer/apc/setrev.php' // this can be an array, to mirror remote_host
));

switch($_SERVER['argv'][1]) {
	case 'deploy':
		$deploy->deploy();
		break;
	case 'rollback':
		$deploy->rollback();
		break;
	case 'cleanup':
		$deploy->cleanup();
		break;
	default:
		echo 'Usage: php example.php [deploy|rollback|cleanup]'. PHP_EOL;
}
