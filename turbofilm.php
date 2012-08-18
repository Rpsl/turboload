<?php
	//define('DEBUG', TRUE);
	define('DEBUG', FALSE);

	setlocale(LC_CTYPE, "en_US.UTF-8");

	error_reporting(E_ALL);
	ini_set('display_startup_errors', 1);
	ini_set('display_errors', 1);

	date_default_timezone_set('Europe/Moscow');

	$pid_file = realpath( __DIR__ ) . '/download.pid';

	if( file_exists( $pid_file ) ){ die(); }

	shell_exec('touch ' . $pid_file );

	include_once( realpath( __DIR__ ) .'/includes/etask.class.php' );
	include_once( realpath( __DIR__ ) .'/includes/episode.class.php' );
	include_once( realpath( __DIR__ ) .'/includes/turbofilm.class.php' );

	include_once( realpath( __DIR__ ) . '/config.php' );


	/**
	 * Логгиррование происходящего в системе.
	 *
	 * $level:
	 *  1 - NOTICE | WARNING | CRITICAL
	 *  2 - INFO
	 *  3 - DEBUG
	 *
	 * @param $string
	 * @param int $level
	 */
	function l( $string, $level = 0 )
	{
		$tr = array(
	        "А"=>"a","Б"=>"b","В"=>"v","Г"=>"g",
	        "Д"=>"d","Е"=>"e","Ж"=>"j","З"=>"z","И"=>"i",
	        "Й"=>"y","К"=>"k","Л"=>"l","М"=>"m","Н"=>"n",
	        "О"=>"o","П"=>"p","Р"=>"r","С"=>"s","Т"=>"t",
	        "У"=>"u","Ф"=>"f","Х"=>"h","Ц"=>"ts","Ч"=>"ch",
	        "Ш"=>"sh","Щ"=>"sch","Ъ"=>"","Ы"=>"yi","Ь"=>"",
	        "Э"=>"e","Ю"=>"yu","Я"=>"ya","а"=>"a","б"=>"b",
	        "в"=>"v","г"=>"g","д"=>"d","е"=>"e","ж"=>"j",
	        "з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l",
	        "м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
	        "с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h",
	        "ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y",
	        "ы"=>"yi","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya"
	    );

	    $string = strtr($string,$tr);

		if( 1<2 || defined('DEBUG') && DEBUG === TRUE )
		{
			echo $string . "\n";
		}

		shell_exec('echo ['.date('Y/m/d H:i:s').'] ' . escapeshellarg( $string ) .' >> ' . TurboFilm::$config['log_file'] );
	}

	l("start");

	TurboFilm::deleteNullFiles();

	if( !TurboFilm::checkLogin() ){ l('Authorize error'); return FALSE; }

	// Ну, на всякий случай
	exec('chown -R admin:admin '. TurboFilm::$config['download_dir'] );
	exec('chmod -R 0777 '. TurboFilm::$config['download_dir'] );

	#TurboFilm::getAllSeries(); // Скачиваем вообще все ( мои сериалы )
	TurboFilm::getNewSeries(); // Скачиваем только новинки


	Etask::download_tasks();


	@unlink( $pid_file );

	l("\n\n\n");
