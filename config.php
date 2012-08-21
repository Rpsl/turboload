<?php

	include_once( realpath( __DIR__ ) . '/includes/simple_html_dom.php');
	include_once( realpath( __DIR__ ) . '/includes/class.phpmailer-lite.php');

	TurboFilm::$config = array(
		'login'			=> 'login',
		'password'		=> 'password',
		'cookie_file'	=> realpath(__DIR__) . '/cookie.txt',
		'tools'         => array(
			'wget'      => '/usr/bin/wget'
		),
		'watch'         => 1,
		'language'      => 'ru', // ru | en
		'only_hq'       => FALSE,
		'download_all'  => TRUE,
		'tasks'			=> 5,
		'email'         => array('mail@me.com'),
		'download_dir' 	=> realpath( __DIR__ ) . '/downloads',
		'log_file' 		=> realpath( __DIR__ ) . '/downloads.log',
	);

