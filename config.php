<?php

	include_once( realpath( __DIR__ ) . '/includes/simple_html_dom.php');
	include_once( realpath( __DIR__ ) . '/includes/class.phpmailer-lite.php');

	TurboFilm::$config = array(
		'login'			=> 'login',
		'password'		=> 'password',
		'cookie_file'	=> realpath(__DIR__) . '/cookie.txt',
		'tools'         => array(
			'mkdir'     => '/bin/mkdir',
			'wget'      => '/usr/bin/wget',
			'chmod'     => '/bin/chmod'
		),
		'watch'         => 1,
		'language'      => 'ru', // ru | en
		'only_hq'       => FALSE,
		'download_all'  => TRUE,
		'tasks'			=> 999,
		'email'         => array('mail@me.com'),
		'download_dir'  => '/c/media/Turbofilm',
		'log_file'      => '/c/media/Turbofilm/downloads.log',
	);

