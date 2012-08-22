<?php

	/*
		login       - Ваш логин
		password    - Ваш пароль
		watch       - Отмечать серию как просмотренную после скачивания
		language    - Язык на котором скачивать серию
		tasks       - Максимальное кол-во серий скачиваемых за раз
		email       - email для уведомлений
		download_dir- Путь к папке для загрузок

		cookie_file - путь к cookie файлу, лучше не трогайте
		tools       - масив с путями к бинарникам
		only_hq     - Скачивать только в высоком качестве
				по умолчанию всегда выбирается наивысшее качество.
				если установить в TRUE, то серии не в HQ скачиваться не будут
		owner       - пользователь:группа в линукс системе
				которые будут установленны владельцами файлов
	 */

	TurboFilm::$config = array(
		'login'			=> 'login',
		'password'		=> 'password',
		'watch'         => 1,
		'language'      => 'ru', // ru | en
		'tasks'			=> 5,
		'email'         => array('mail@me.com'),
		'download_dir' 	=> realpath( __DIR__ ) . '/downloads',

		'cookie_file'	=> realpath(__DIR__) . '/cookie.txt',
		'tools'         => array(
			'wget'      => '/usr/bin/wget'
		),
		'only_hq'       => FALSE,
		'owner'         => 'admin:admin',

		'log_file' 		=> realpath( __DIR__ ) . '/downloads.log',
	);

