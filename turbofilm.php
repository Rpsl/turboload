<?php

    setlocale( LC_CTYPE, "en_US.UTF-8" );

    error_reporting( E_ALL );
    ini_set( 'display_startup_errors', 1 );
    ini_set( 'display_errors', 1 );

    date_default_timezone_set( 'Europe/Moscow' );

    $pid_file = realpath( __DIR__ ) . '/download.pid';
    $pid      = getmypid();

    if( !$pid )
    {
        // @todo что тут делать ?
        die();
    }

    // Проверяем что у нас не просто есть пид, а что такой процесс действительно живет.
    if( file_exists( $pid_file ) )
    {
        $check_pid = trim( file_get_contents( $pid_file ) );

        // @todo В windows рабоатть не будеь
        if( !file_exists( "/proc/$check_pid" ) )
        {
            @unlink( $pid_file );
        }
        else
        {
            die();
        }

    }

    // На моем насе были проблемы с вызовом mkdir(). Функция не хотела работать,
    // в то время, как вызовы нативных команд работали прекрасно.
    // Быть может это из-за SPARC, но там супер урезанный линукс и пыха в таком состояние.
    // В итоге оказалось проще все подобные вызовы перевести на shell_exec()

    shell_exec( 'touch ' . $pid_file );
    shell_exec( "echo '$pid' > " . $pid_file );

    require_once( realpath( __DIR__ ) . '/includes/simple_html_dom.php' );

    require_once( realpath( __DIR__ ) . '/includes/etask.class.php' );
    require_once( realpath( __DIR__ ) . '/includes/episode.class.php' );
    require_once( realpath( __DIR__ ) . '/includes/turbofilm.class.php' );

    require_once( realpath( __DIR__ ) . '/config.php' );


    /**
     * Логгиррование происходящего в системе.
     * @param $string
     */
    function l( $string )
    {
        echo $string . "\n";

        shell_exec('echo ' . escapeshellarg( '[' . date( 'Y/m/d H:i:s' ) . '] ' . $string ) . ' >> ' . TurboFilm::$config[ 'log_file' ]);
    }

    l( "start" );

    if( !is_dir( TurboFilm::$config[ 'download_dir' ] ) )
    {
        shell_exec( 'mkdir -p ' . escapeshellarg( TurboFilm::$config[ 'download_dir' ] ) );
    }

    TurboFilm::deleteNullFiles();

    if( !TurboFilm::checkLogin() )
    {
        l( 'authorization error' );

        return FALSE;
    }

    #TurboFilm::getAllSeries(); // Скачиваем вообще все ( мои сериалы )
    TurboFilm::getNewSeries(); // Скачиваем только новинки

    Etask::download_tasks();

    @unlink( $pid_file );

    if( !empty(  TurboFilm::$config[ 'owner' ] ) )
    {
        shell_exec( 'chown -R ' . TurboFilm::$config[ 'owner' ] . ' ' . TurboFilm::$config[ 'download_dir' ] );
    }

    shell_exec( 'chmod -R 0777 ' . TurboFilm::$config[ 'download_dir' ] );

    l( "\n\n\n" );
