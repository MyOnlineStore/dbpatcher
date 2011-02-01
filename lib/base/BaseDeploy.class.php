<?php

/**
 * Standard deploymer
 *
 * @author Bert-Jan de Lange <bert-jan@bugbyte.nl>
 */
class BaseDeploy
{
	/**
	 * Format of the deployment directories
	 *
	 * @var string
	 */
	protected $remote_dir_format = '%project_name%_%timestamp%';

	/**
	 * Date format in the naam of the deployment directories
	 * (format parameter of date())
	 *
	 * @var string
	 */
	protected $remote_dir_timestamp_format = 'Y-m-d_His';

	/**
	 * The codename of the project
	 *
	 * @var string
	 */
	protected $project_name = null;

	/**
	 * The root path of the project
	 *
	 * @var string
	 */
	protected $basedir = null;

	/**
	 * The hostname of the remote server
	 *
	 * @var string
	 */
	protected $remote_host = null;

	/**
	 * The username on the remote server
	 *
	 * @var string
	 */
	protected $remote_user = null;

	/**
	 * The project's path on the remote server
	 *
	 * @var string
	 */
	protected $remote_dir = null;

	/**
	 * All files to be used in rsync --exclude-from
	 *
	 * @var array
	 */
	protected $rsync_excludes = array();

	/**
	 * The timestamp for this deployment
	 *
	 * @var timestamp
	 */
	protected $timestamp = null;

	/**
	 * De directory waar de nieuwe deploy terecht gaat komen
	 *
	 * @var string
	 */
	protected $remote_target_dir = null;

	/**
	 * De timestamp van de voorlaatste deployment
	 *
	 * @var timestamp
	 */
	protected $previous_timestamp = null;

	/**
	 * De timestamp van de laatste deployment
	 *
	 * @var timestamp
	 */
	protected $last_timestamp = null;

	/**
	 * De directory van de voorlaatste deployment
	 *
	 * @var string
	 */
	protected $previous_remote_target_dir = null;

	/**
	 * De directory van de laatste deployment
	 *
	 * @var string
	 */
	protected $last_remote_target_dir = null;

	/**
	 * Alle directories die moeten worden doorzocht naar SQL update files
	 *
	 * @var array
	 */
	protected $database_dirs = array();

	/**
	 * De hostname van de database server
	 *
	 * @var string
	 */
	protected $database_host = null;

	/**
	 * De naam van de database waar de SQL updates naartoe gaan
	 *
	 * @var string
	 */
	protected $database_name = null;

	/**
	 * De gebruikersnaam van de database
	 *
	 * @var string
	 */
	protected $database_user = null;

	/**
	 * Het wachtwoord dat bij de gebruikersnaam hoort
	 *
	 * @var string
	 */
	protected $database_pass = null;

	/**
	 * Of de database-gegevens gecontroleerd zijn
	 *
	 * @var boolean
	 */
	protected $database_checked = false;

	/**
	 * Het pad van de logfile, als logging gewenst is
	 *
	 * @var string
	 */
	protected $logfile = null;

	/**
	 * Doellocatie (stage of prod)
	 *
	 * @var string
	 */
	protected $target = null;

	/**
	 * Het pad van de database patcher, relatief vanaf de project root
	 *
	 * @var string
	 */
	protected $database_patcher = null;

	/**
	 * Het pad van de datadir symlinker, relatief vanaf de project root
	 *
	 * @var string
	 */
	protected $datadir_patcher = null;

	/**
	 * Directories waarin de site zelf dingen kan schrijven
	 *
	 * @var array
	 */
	protected $data_dirs = null;

	/**
	 * De naam van de directory waarin alle data_dirs worden geplaatst
	 *
	 * @var string
	 */
	protected $data_dir_prefix = 'data';

	/**
	 * deployment timestamps ophalen als deploy geïnstantieerd wordt
	 *
	 * @var boolean
	 */
	protected $auto_init = true;

	/**
	 * Files die specifiek zijn per omgeving
	 *
	 * voorbeeld:
	 * 		'config/databases.yml'
	 *
	 * bij publicatie naar stage gebeurd dit:
	 * 		'config/databases.stage.yml' => 'config/databases.yml'
	 *
	 * bij publicatie naar prod gebeurd dit:
	 * 		'config/databases.prod.yml' => 'config/databases.yml'
	 *
	 * @var array
	 */
	protected $target_specific_files = array();

	/**
	 * Files die specifiek zijn per clusterrol (master/node)
	 *
	 * voorbeeld:
	 * 		'config/databases.yml'
	 *
	 * bij publicatie naar een cluster master:
	 * 		'config/databases.master.yml' => 'config/databases.yml'
	 *
	 * bij publicatie naar een cluster node:
	 * 		'config/databases.node.yml' => 'config/databases.yml'
	 *
	 * *** LET OP ***
	 * target_specific_files en cluster_specific files worden in die volgorde uitgevoerd en een file
	 * kan dus 2x hernoemd worden. Hernoemen gebeurd met extensie erbij en werkt dus van buiten naar binnen.
	 * Je kan dus hebben:
	 * 		'config/databases.master.prod.yml' => 'config/databases.master.yml' => 'config/databases.yml'
	 * Maar niet:
	 * 		'config/databases.stage.node.yml' => ...
	 *
	 *
	 * @var array
	 */
	protected $cluster_specific_files = array();

	/**
	 * Cache voor listFilesToRename()
	 *
	 * @var array
	 */
	protected $files_to_rename = array();

	/**
	 * Met dit commando worden APC caches op de remote hosts geleegd (zowel resp. apache/mod_php als nginx/php-fpm)
	 *
	 * @var string
	 */
	protected $clear_cache_cmd = 'curl -s -S localhost/clear_apc.php; curl -s -S localhost:82/clear_apc.php';

	/**
	 * Programma paden
	 */
	protected $rsync_path = 'rsync';
	protected $ssh_path = 'ssh';

	/**
	 * Bouwt een nieuwe Deploy class op met de gegeven opties
	 *
	 * @param array $options
	 * @return BaseDeploy
	 */
	public function __construct(array $options)
	{
		$this->project_name				= $options['project_name'];
		$this->basedir					= $options['basedir'];
		$this->remote_host				= $options['remote_host'];
		$this->remote_user				= $options['remote_user'];
		$this->database_dirs			= (array) $options['database_dirs'];
		$this->target					= $options['target'];
		$this->remote_dir				= $options['remote_dir'] .'/'. $this->target;
		$this->database_patcher 		= $options['database_patcher'];

		// als database host niet wordt meegegeven automatisch de eerste remote host (clustermaster) pakken.
		$this->database_host	= isset($options['database_host']) ? $options['database_host'] : (is_array($this->remote_host) ? $this->remote_host[0] : $this->remote_host);

		if (isset($options['database_name']))
			$this->database_name = $options['database_name'];

		if (isset($options['database_user']))
			$this->database_user = $options['database_user'];

		if (isset($options['database_pass']))
			$this->database_pass = $options['database_pass'];

		if (isset($options['rsync_excludes']))
			$this->rsync_excludes = (array) $options['rsync_excludes'];

		if (isset($options['logfile']))
			$this->logfile = $options['logfile'];

		if (isset($options['data_dirs']))
			$this->data_dirs = $options['data_dirs'];

		if (isset($options['datadir_patcher']))
			$this->datadir_patcher = $options['datadir_patcher'];

		if (isset($options['auto_init']))
			$this->auto_init = $options['auto_init'];

		if (isset($options['target_specific_files']))
			$this->target_specific_files = $options['target_specific_files'];

		if (isset($options['cluster_specific_files']))
			$this->cluster_specific_files = $options['cluster_specific_files'];

		$this->rsync_path		= isset($options['rsync_path']) ? $options['rsync_path'] : trim(`which rsync`);
		$this->ssh_path			= isset($options['ssh_path']) ? $options['ssh_path'] : trim(`which ssh`);

		if (!$this->auto_init)
			return;

		echo PHP_EOL . 'find timestamps: ' . $this->find_timestamps_on_init . PHP_EOL;

		$this->initialize();
	}

	/**
	 * Bepaalt de timestamp van een nieuwe deployment en de timestamps van de laatste en voorlaatste deployment
	 */
	protected function initialize()
	{
		$this->log('initialisatie', 2);

		// als er meerdere remote hosts zijn de eerste (cluster master) alvast initialiseren zodat het zoeken naar timestamps goed gaat
		$remote_host = is_array($this->remote_host) ? $this->remote_host[0] : $this->remote_host;

		$this->timestamp = time();
		$this->remote_target_dir = strtr($this->remote_dir_format, array('%project_name%' => $this->project_name, '%timestamp%' => date($this->remote_dir_timestamp_format, $this->timestamp)));

		list($this->previous_timestamp, $this->last_timestamp) = $this->findPastDeploymentTimestamps($remote_host);

		if ($this->previous_timestamp)
		{
			$this->previous_remote_target_dir = strtr($this->remote_dir_format, array('%project_name%' => $this->project_name, '%timestamp%' => date($this->remote_dir_timestamp_format, $this->previous_timestamp)));
		}

		if ($this->last_timestamp)
		{
			$this->last_remote_target_dir = strtr($this->remote_dir_format, array('%project_name%' => $this->project_name, '%timestamp%' => date($this->remote_dir_timestamp_format, $this->last_timestamp)));
		}
	}

	/**
	 * Draait een dry-run naar de remote server om de gewijzigde bestanden te tonen
	 *
	 * @param string $action 		update of rollback
	 */
	protected function check($action)
	{
		$this->log('check', 2);

		if (is_array($this->remote_host))
		{
			foreach ($this->remote_host as $key => $remote_host)
			{
				if ($key == 0) continue;

				$this->prepareRemoteDirectory($remote_host, str_replace('clustermaster', 'clusternode', $this->remote_dir));
			}
		}

		if ($action == 'update')
			$this->checkFiles(is_array($this->remote_host) ? $this->remote_host[0] : $this->remote_host);

		$this->checkDatabase(null, $action);

		if ($action == 'update')
		{
			if (is_array($this->remote_host))
			{
				// eerst preDeploy draaien per host, dan alle files synchen
				foreach ($this->remote_host as $key => $remote_host)
				{
					$remote_dir = $key == 0 ? $this->remote_dir : str_replace('clustermaster', 'clusternode', $this->remote_dir);

					if ($files = $this->listFilesToRename($remote_host, $remote_dir))
					{
						$this->log("Files verplaatsen op $remote_host:");

						foreach ($files as $filepath => $newpath)
							$this->log("  $newpath => $filepath");
					}
				}
			}
			else
			{
				if ($files = $this->listFilesToRename($this->remote_host, $this->remote_dir))
				{
					$this->log('Files verplaatsen:');

					foreach ($files as $filepath => $newpath)
						$this->log("  $newpath => $filepath");
				}
			}
		}

		// als alles goed is gegaan kan er doorgegaan worden met de deployment
		if ($action == 'update')
			return $this->inputPrompt('Proceed with deployment? (yes/no): ', 'no') == 'yes';
		elseif ($action == 'rollback')
			return $this->inputPrompt('Proceed with rollback? (yes/no): ', 'no') == 'yes';
	}

	/**
	 * Zet het project online en voert database-aanpassingen uit
	 * Kan alleen worden uitgevoerd nadat check() heeft gedraaid
	 */
	public function deploy()
	{
		$this->log('deploy', 2);

		if (!$this->check('update'))
			return;

		if (is_array($this->remote_host))
		{
			// eerst preDeploy draaien per host, dan alle files synchen
			foreach ($this->remote_host as $key => $remote_host)
			{
				$remote_dir = $key == 0 ? $this->remote_dir : str_replace('clustermaster', 'clusternode', $this->remote_dir);

				$this->preDeploy($remote_host, $remote_dir);
				$this->updateFiles($remote_host, $remote_dir);
			}

			// na de uploads de database prepareren
			$this->updateDatabase();

			// alles de files er database klaarstaan kan de nieuwe versie geactiveerd worden
			// door de symlinks te updaten en postDeploy te draaien
			foreach ($this->remote_host as $key => $remote_host)
			{
				$remote_dir = $key == 0 ? $this->remote_dir : str_replace('clustermaster', 'clusternode', $this->remote_dir);

				$this->updateSymlink($remote_host, $remote_dir);
				$this->postDeploy($remote_host, $remote_dir);
				$this->clearRemoteCaches($remote_host);
			}
		}
		else
		{
			$this->preDeploy($this->remote_host, $this->remote_dir);
			$this->updateFiles();
			$this->updateDatabase();
			$this->updateSymlink();
			$this->postDeploy($this->remote_host, $this->remote_dir);
			$this->clearRemoteCaches($this->remote_host);
		}
	}

	/**
	 * Draait de laatste deployment terug
	 */
	public function rollback()
	{
		$this->log('rollback', 2);

		if ($this->previous_remote_target_dir)
		{
			if (!$this->check('rollback'))
				return;

			if (is_array($this->remote_host))
			{
				foreach ($this->remote_host as $key => $remote_host)
				{
					$remote_dir = $key == 0 ? $this->remote_dir : str_replace('clustermaster', 'clusternode', $this->remote_dir);

					$this->preRollback($remote_host, $remote_dir);
					$this->rollbackSymlink($remote_host, $remote_dir);
					$this->rollbackDatabase();
					$this->rollbackFiles($remote_host, $remote_dir);
					$this->postRollback($remote_host, $remote_dir);
					$this->clearRemoteCaches($remote_host);
				}
			}
			else
			{
				$this->preRollback($this->remote_host, $this->remote_dir);
				$this->rollbackSymlink();
				$this->rollbackDatabase();
				$this->rollbackFiles();
				$this->postRollback($this->remote_host, $this->remote_dir);
				$this->clearRemoteCaches($this->remote_host);
			}
		}
		else
		{
			$this->log('Rollback onmogelijk, geen vorige versie gevonden');
		}
	}

	protected function clearRemoteCaches($remote_host)
	{
		$this->sshExec($remote_host, $this->clear_cache_cmd, $output, $return);

		$this->log($output);

		if ($return != 0)
			$this->log("$remote_host: Clear cache failed");
	}

	/**
	 * Toont de files die veranderd zijn sinds de laatste upload (rsync dry-run output tegen de laatste directory online)
	 */
	protected function checkFiles($remote_host = null, $remote_dir = null)
	{
		$this->log('checkFiles', 2);

		if ($remote_host === null)
			$remote_host = $this->remote_host;

		if ($remote_dir === null)
			$remote_dir = $this->remote_dir;

		if ($this->last_remote_target_dir)
		{
			$this->rsyncExec($this->rsync_path .' -vazcO --force --dry-run --delete --progress '. $this->prepareExcludes() .' ./ '. $this->remote_user .'@'. $remote_host .':'. $remote_dir .'/'. $this->last_remote_target_dir, 'Rsync check is mislukt');
		}
		else
		{
			$this->log('geen deployment geschiedenis gevonden');
		}
	}

	/**
	 * Uploadt files naar een nieuwe directory op de live server
	 */
	protected function updateFiles($remote_host = null, $remote_dir = null)
	{
		$this->log('updateFiles', 2);

		if ($remote_host === null)
			$remote_host = $this->remote_host;

		if ($remote_dir === null)
			$remote_dir = $this->remote_dir;

		$this->rsyncExec($this->rsync_path .' -vazcO --force --delete --progress '. $this->prepareExcludes() .' '. $this->prepareLinkDest($remote_dir) .' ./ '. $this->remote_user .'@'. $remote_host .':'. $remote_dir .'/'. $this->remote_target_dir);

		$this->fixDatadirSymlinks($remote_host, $remote_dir);

		$this->renameTargetFiles($remote_host, $remote_dir);
	}

	protected function fixDatadirSymlinks($remote_host, $remote_dir)
	{
		$this->log('fixDatadirSymlinks', 2);

		if (!empty($this->data_dirs))
		{
			$cmd = "cd $remote_dir/{$this->remote_target_dir}; php {$this->datadir_patcher} --datadir-prefix={$this->data_dir_prefix} --previous-dir={$this->last_remote_target_dir} ". implode(' ', $this->data_dirs);

			$output = array();
			$return = null;
			$this->sshExec($remote_host, $cmd, $output, $return);

			$this->log($output);
		}
	}

	/**
	 * Verwijderd de laatst geuploadde directory
	 */
	protected function rollbackFiles($remote_host = null, $remote_dir = null)
	{
		$this->log('rollbackFiles', 2);

		if ($remote_host === null)
			$remote_host = $this->remote_host;

		if ($remote_dir === null)
			$remote_dir = $this->remote_dir;

		$output = array();
		$return = null;
		$this->sshExec($remote_host, 'cd '. $remote_dir .'; rm -rf '. $this->last_remote_target_dir, $output, $return);
	}

	/**
	 * Update de production-symlink naar de nieuwe upload directory
	 */
	protected function updateSymlink($remote_host = null, $remote_dir = null)
	{
		$this->log('updateSymlink', 2);

		if ($remote_host === null)
			$remote_host = $this->remote_host;

		if ($remote_dir === null)
			$remote_dir = $this->remote_dir;

		$output = array();
		$return = null;
		$this->sshExec($remote_host, "cd $remote_dir; rm production; ln -s {$this->remote_target_dir} production", $output, $return);
	}

	/**
	 * Zet de production-symlink terug naar de vorige upload directory
	 */
	protected function rollbackSymlink($remote_host = null, $remote_dir = null)
	{
		$this->log('rollbackSymlink', 2);

		if ($remote_host === null)
			$remote_host = $this->remote_host;

		if ($remote_dir === null)
			$remote_dir = $this->remote_dir;

		$output = array();
		$return = null;
		$this->sshExec($remote_host, "cd $remote_dir; rm production; ln -s {$this->previous_remote_target_dir} production", $output, $return);
	}

	/**
	 * Voert database migraties uit voor de nieuwste upload
	 *
	 * @param string $database_host
	 * @param string $action 			update of rollback
	 */
	protected function checkDatabase($database_host = null, $action)
	{
		$this->log('checkDatabase', 2);

		if ($database_host === null)
			$database_host = $this->database_host;

		if ($action == 'update')
			$files = $this->findSQLFilesForPeriod($this->last_timestamp, $this->timestamp);
		elseif ($action == 'rollback')
			$files = $this->findSQLFilesForPeriod($this->last_timestamp, $this->previous_timestamp);

		if (!($files))
		{
			$this->log('geen database updates gevonden');
		}
		else
		{
			self::checkDatabaseFiles($this->target, $this->basedir, $files);

			if ($action == 'update')
				$msg = 'database updates die uitgevoerd zullen worden:';
			elseif ($action == 'rollback')
				$msg = 'database rollbacks die uitgevoerd zullen worden:';

			$this->log($msg);
			$this->log($files);

			$this->getDatabaseLogin($database_host);
		}
	}

	/**
	 * Voert database migraties uit voor de nieuwste upload
	 */
	protected function updateDatabase($database_host = null, $remote_dir = null)
	{
		$this->log('updateDatabase', 2);

		if ($database_host === null)
			$database_host = $this->database_host;

		if ($remote_dir === null)
			$remote_dir = $this->remote_dir;

		if (!($files = $this->findSQLFilesForPeriod($this->last_timestamp, $this->timestamp)))
		{
			$this->log('geen database updates gevonden');

			return;
		}
		else
		{
			self::checkDatabaseFiles($this->target, $this->basedir, $files);

			$this->log('database updates die uitgevoerd zullen worden:');
			$this->log($files);

			$this->getDatabaseLogin($database_host);

			$this->sendToDatabase($database_host, "cd $remote_dir/{$this->remote_target_dir}; php {$this->database_patcher} update ". implode(' ', $files), $output, $return);
		}
	}

	/**
	 * Draait database migraties terug naar de vorige upload
	 */
	protected function rollbackDatabase($database_host = null)
	{
		$this->log('rollbackDatabase', 2);

		if ($database_host === null)
			$database_host = $this->database_host;

		if ($remote_dir === null)
			$remote_dir = $this->remote_dir;

		if (!($files = $this->findSQLFilesForPeriod($this->last_timestamp, $this->previous_timestamp)))
		{
			$this->log('geen database updates gevonden');

			return;
		}
		else
		{
			self::checkDatabaseFiles($this->target, $this->basedir, $files);

			$this->log('database rollbacks die uitgevoerd zullen worden:');
			$this->log($files);

			$this->getDatabaseLogin($database_host);

			$this->sendToDatabase($database_host, "cd $remote_dir/{$this->last_remote_target_dir}; php {$this->database_patcher} rollback ". implode(' ', $files), $output, $return);
		}
	}

	protected function renameTargetFiles($remote_host, $remote_dir)
	{
		if ($files_to_move = $this->listFilesToRename($remote_host, $remote_dir))
		{
			// configfiles verplaatsen
			$target_files_to_move = '';

			foreach ($files_to_move as $newpath => $currentpath)
				$target_files_to_move .= "mv $currentpath $newpath; ";

			$this->sshExec($remote_host, "cd {$remote_dir}/{$this->remote_target_dir}; $target_files_to_move");
		}
	}

	/**
	 * Maakt een lijst van de files die specifiek zijn voor een clusterrol of doel en op de doelserver hernoemd moeten worden
	 *
	 * @return array
	 */
	protected function listFilesToRename($remote_host, $remote_dir)
	{
		if (!isset($this->files_to_rename["$remote_host-$remote_dir"]))
		{
			$cluster_role = (strpos($remote_dir, 'clustermaster') !== false) ? 'master' : ((strpos($remote_dir, 'clusternode') !== false) ? 'node' : '');

			$target_files_to_move = array();

			// clusterrol-specifieke files hernoemen
			if (!empty($this->cluster_specific_files))
			{
				foreach ($this->cluster_specific_files as $filepath)
				{
					$ext = pathinfo($filepath, PATHINFO_EXTENSION);
					$target_filepath = str_replace(".$ext", ".{$cluster_role}.$ext", $filepath);

					$target_files_to_move[$filepath] = $target_filepath;
				}
			}

			// doelspecifieke files hernoemen
			if (!empty($this->target_specific_files))
			{
				foreach ($this->target_specific_files as $filepath)
				{
					$ext = pathinfo($filepath, PATHINFO_EXTENSION);

					if (isset($target_files_to_move[$filepath]))
					{
						$target_filepath = str_replace(".$ext", ".{$this->target}.$ext", $target_files_to_move[$filepath]);
					}
					else
					{
						$target_filepath = str_replace(".$ext", ".{$this->target}.$ext", $filepath);
					}

					$target_files_to_move[$filepath] = $target_filepath;
				}
			}

			// controleren of alle files bestaan
			if (!empty($target_files_to_move))
			{
				foreach ($target_files_to_move as $current_filepath)
				{
					if (!file_exists($current_filepath))
					{
						throw new DeployException("$current_filepath does not exist");
					}
				}
			}

			$this->files_to_rename["$remote_host-$remote_dir"] = $target_files_to_move;
		}

		return $this->files_to_rename["$remote_host-$remote_dir"];
	}

	/**
	 * Controleert of alle opgegeven bestanden bestaan
	 *
	 * @param string $update		update of rollback
	 * @param array $filenames
	 * @returns array				De absolute paden van alle files
	 */
	static public function checkDatabaseFiles($action, $path_prefix, $filenames)
	{
		$classes = array();

		foreach ($filenames as $filename)
		{
			$filepath = $path_prefix .'/'. $filename;

			if (!file_exists($filepath))
				throw new DeployException("$filepath not found");

			$classname = str_replace('.class', '', pathinfo($filename, PATHINFO_FILENAME));

			require_once $filepath;

			if (!class_exists($classname))
				throw new DeployException("Class $classname not found in $filepath");

			$sql = new $classname();

			if (!$sql instanceof SQL_update)
				throw new DeployException("Class $classname doesn't implement SQL_update");

			$classes[] = $sql;
		}

		return $classes;
	}

	protected function getDatabaseLogin($database_host)
	{
		if ($this->database_checked)
			return;

		$database_name = $this->database_name !== null ? $this->database_name : self::inputPrompt('Database: ');
		$username = $this->database_user !== null ? $this->database_user : self::inputPrompt('Database username [root]: ', 'root');
		$password = $this->database_pass !== null ? $this->database_pass : self::inputPrompt('Database password: ', '', true);

		// controleren of deze gebruiker een tabel mag aanmaken (rudimentaire toegangstest)
		$this->databaseExec($database_host, "CREATE TABLE temp_{$this->timestamp} (field1 INT NULL); DROP TABLE temp_{$this->timestamp};", $output, $return, $database_name, $username, $password);

		if ($return != 0)
			return $this->getDatabaseLogin($database_host);

		$this->log('database check passed');
		$this->database_checked = true;
		$this->database_name = $database_name;
		$this->database_user = $username;
		$this->database_pass = $password;
	}

	protected function databaseExec($database_host, $command, &$output, &$return, $database_name = null, $username = null, $password = null)
	{
		$this->sendToDatabase($database_host, "echo '". addslashes($command) ."'", $output, $return, $database_name, $username, $password);
	}

	protected function sendToDatabase($database_host, $command, &$output, &$return, $database_name = null, $username = null, $password = null)
	{
		if ($database_name === null)
			$database_name = $this->database_name;

		if ($username === null)
			$username = $this->database_user;

		if ($password === null)
			$password = $this->database_pass;

		$output = array();
		$return = null;
		$this->sshExec($database_host, "$command | mysql -u$username -p$password $database_name", $output, $return, '/ mysql -u([^ ]+) -p[^ ]+ /', ' mysql -u$1 -p***** ');
	}

	/**
	 * Maakt een lijstje van alle SQL update files die binnen het timeframe vallen, in de volgorde die de start- en endtime impliceren.
	 * Als de starttime *voor* de endtime ligt is het een gewone update cyclus en worden de files chronologisch gerangschikt.
	 * Als de starttime *na* de endtime ligt is het een rollback en worden de files van nieuw naar oud gerangschikt.
	 *
	 * @param timestamp $starttime
	 * @param timestamp $endtime
	 */
	public function findSQLFilesForPeriod($starttime, $endtime)
	{
		$this->log('findSQLFilesForPeriod('. date('Y-m-d H:i:s', $starttime) .','. date('Y-m-d H:i:s', $endtime) .')', 2);

		$reverse = $starttime > $endtime;

		if ($reverse)
		{
			$starttime2 = $starttime;
			$starttime = $endtime;
			$endtime = $starttime2;
			unset($starttime2);
		}

		$update_files = array();

		foreach ($this->database_dirs as $database_dir)
		{
			$dir = new DirectoryIterator($database_dir);

			foreach ($dir as $entry)
			{
				if (!$entry->isDot() && $entry->isFile())
				{
					if (preg_match('/sql_(\d{8}_\d{6})\.class.php/', $entry->getFilename(), $matches))
					{
						if (!($timestamp = strtotime(preg_replace('/(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/', '$1-$2-$3 $4:$5:$6', $matches[1]))))
							throw new DeployException('Kan '. $matches[1] .' niet converteren naar timestamp');

						if ($timestamp > $starttime && $timestamp < $endtime)
							$update_files[$timestamp] = $entry->getPathname();
					}
				}
			}
		}

		if (!empty($update_files))
		{
			if (!$reverse)
				ksort($update_files, SORT_NUMERIC);
			else
				krsort($update_files, SORT_NUMERIC);
		}

		return $update_files;
	}

	/**
	 * Output wrapper
	 *
	 * @param string $message
	 * @param integer $level		1 = gewoon (altijd tonen), 2 = debugging (standaard verbergen)
	 */
	protected function log($message, $level = 1)
	{
		if ($level > 2)
			return;

		if (is_array($message))
		{
			if (count($message) == 0)
				return;

			$message = implode(PHP_EOL, $message);
		}

		echo $message . PHP_EOL;

		if ($this->logfile)
			error_log($message . PHP_EOL, 3, $this->logfile);
	}

	/**
	 * Zet het array van rsync excludes om in een lijst rsync parameters
	 *
	 * @returns string
	 */
	protected function prepareExcludes()
	{
		$this->log('prepareExcludes', 2);

		chdir($this->basedir);

		$exclude_param = '';

		if (count($this->rsync_excludes) > 0)
		{
			foreach ($this->rsync_excludes as $exclude)
			{
				if (!file_exists($exclude))
					throw new DeployException('Rsync exclude file niet gevonden: '. $exclude);

				$exclude_param .= '--exclude-from='. escapeshellarg($exclude) .' ';
			}
		}

		if (!empty($this->data_dirs))
		{
			foreach ($this->data_dirs as $data_dir)
			{
				$exclude_param .= '--exclude '. escapeshellarg("/$data_dir") .' ';
			}
		}

		return $exclude_param;
	}

	/**
	 * Bereidt de --link-dest parameter voor rsync voor als dat van toepassing is
	 *
	 * @returns string
	 */
	protected function prepareLinkDest($remote_dir)
	{
		$this->log('prepareLinkDest', 2);

		if ($remote_dir === null)
			$remote_dir = $this->remote_dir;

		$linkdest = '';

		if ($this->last_remote_target_dir)
		{
			$linkdest = "--copy-dest=$remote_dir/{$this->last_remote_target_dir}";
		}

		return $linkdest;
	}

	protected function prepareRemoteDirectory($remote_host, $remote_dir = null)
	{
		if ($remote_dir === null)
			$remote_dir = $this->remote_dir;

		$output = array();
		$return = null;
		$this->sshExec($remote_host, "mkdir -p $remote_dir", $output, $return); // | grep '{$this->project_name}_'

		if (!empty($this->data_dirs))
		{
			$cmd = "mkdir -v -m 0775 -p $remote_dir/{$this->data_dir_prefix}/{". implode(',', $this->data_dirs) ."}";

			$output = array();
			$return = null;
			$this->sshExec($remote_host, $cmd, $output, $return);
		}
	}

	/**
	 * Geeft de timestamps van de voorlaatste en laatste deployments terug
	 *
	 * @returns array [previous_timestamp, last_timestamp]
	 */
	protected function findPastDeploymentTimestamps($remote_host, $remote_dir = null)
	{
		$this->log('findPastDeploymentTimestamps', 2);

		$this->prepareRemoteDirectory($remote_host, $remote_dir);

		if ($remote_dir === null)
			$remote_dir = $this->remote_dir;

		$dirs = array();
		$return = null;
		$this->sshExec($remote_host, "ls -1 $remote_dir", $dirs, $return); // | grep '{$this->project_name}_'

		$this->log($dirs);

		if ($return !== 0) {
			throw new DeployException('ssh initialize failed');
		}

		$deployment_timestamps = array();

		if (count($dirs))
		{
			foreach ($dirs as $dirname)
			{
				if (
					preg_match('/'. preg_quote($this->project_name) .'_\d{4}-\d{2}-\d{2}_\d{6}/', $dirname) &&
					($time = strtotime(str_replace(array($this->project_name .'_', '_'), array('', ' '), $dirname)))
				   )
				{
					$deployment_timestamps[] = $time;
				}
			}

			$count = count($deployment_timestamps);

			if ($count > 0)
			{
				sort($deployment_timestamps);

				if ($count > 1)
					return array_slice($deployment_timestamps, -2);

				return array(null, array_pop($deployment_timestamps));
			}
		}

		return array(null, null);
	}

	/**
	 * Wrapper voor SSH commando's
	 *
	 * @param string $command
	 * @param string $output
	 * @param int $return
	 * @param string $hide_pattern		Regexp waarmee de output kan worden gekuisd
	 * @param string $hide_replacement
	 */
	protected function sshExec($remote_host, $command, &$output, &$return, $hide_pattern = '', $hide_replacement = '')
	{
		$cmd = $this->ssh_path .' '. $this->remote_user .'@'. $remote_host .' "'. str_replace('"', '\"', $command) .'"';

		if ($hide_pattern != '')
			$show_cmd = preg_replace($hide_pattern, $hide_replacement, $cmd);
		else
			$show_cmd = $cmd;

		$this->log('sshExec: '. $show_cmd);

		exec($cmd, $output, $return);
	}

	/**
	 * Wrapper voor rsync commando's
	 *
	 * @param string $command
	 * @param string $error_msg
	 */
	protected function rsyncExec($command, $error_msg = 'Rsync is mislukt')
	{
		$this->log('execRSync: '. $command, 2);

		chdir($this->basedir);

		passthru($command, $return);

		if ($return !== 0) {
			throw new DeployException($error_msg);
		}
	}

	/**
	 * Vraagt om invoer van de gebruiker
	 *
	 * @param string $message
	 * @param string $default
	 * @param boolean $isPassword
	 * @return string
	 */
	static protected function inputPrompt($message, $default = '', $isPassword = false)
	{
		fwrite(STDOUT, $message);

		if (!$isPassword)
		{
			$input = trim(fgets(STDIN));
		}
		else
		{
			$input = self::getPassword(false);
			echo PHP_EOL;
		}

		if ($input == '')
			$input = $default;

		return $input;
	}

	/**
	 * Stub methode voor extra uitbreidingen die *voor* deploy worden uitgevoerd
	 */
	protected function preDeploy($remote_host = null, $remote_dir = null)
	{
		$this->log("preDeploy($remote_host, $remote_dir): $command", 2);
	}

	/**
	 * Stub methode voor extra uitbreidingen die *na* deploy worden uitgevoerd
	 */
	protected function postDeploy($remote_host = null, $remote_dir = null)
	{
		$this->log("postDeploy($remote_host, $remote_dir): $command", 2);
	}

	/**
	 * Stub methode voor extra uitbreidingen die *voor* rollback worden uitgevoerd
	 */
	protected function preRollback($remote_host = null, $remote_dir = null)
	{
		$this->log("preRollback($remote_host, $remote_dir): $command", 2);
	}

	/**
	 * Stub methode voor extra uitbreidingen die *na* rollback worden uitgevoerd
	 */
	protected function postRollback($remote_host = null, $remote_dir = null)
	{
		$this->log("postRollback($remote_host, $remote_dir): $command", 2);
	}

	/**
	 * Get a password from the shell.
	 *
	 * This function works on *nix systems only and requires shell_exec and stty.
	 *
	 * @author http://www.dasprids.de/blog/2008/08/22/getting-a-password-hidden-from-stdin-with-php-cli
	 * @param  boolean $stars Wether or not to output stars for given characters
	 * @return string
	 */
	static protected function getPassword($stars = false)
	{
	    // Get current style
	    $oldStyle = shell_exec('stty -g');

	    if ($stars === false) {
	        shell_exec('stty -echo');
	        $password = rtrim(fgets(STDIN), "\n");
	    } else {
	        shell_exec('stty -icanon -echo min 1 time 0');

	        $password = '';
	        while (true) {
	            $char = fgetc(STDIN);

	            if ($char === "\n") {
	                break;
	            } else if (ord($char) === 127) {
	                if (strlen($password) > 0) {
	                    fwrite(STDOUT, "\x08 \x08");
	                    $password = substr($password, 0, -1);
	                }
	            } else {
	                fwrite(STDOUT, "*");
	                $password .= $char;
	            }
	        }
	    }

	    // Reset old style
	    shell_exec('stty ' . $oldStyle);

	    // Return the password
	    return $password;
	}
}
