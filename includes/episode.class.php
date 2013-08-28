<?php

    /**
     * Episode
     *
     * Обеспечивает взаимодействие на уровне одной серии.
     * - Парсинг данных
     * - Генерация урла
     * - Скачивание серии
     * - Уведомление по email
     *
     * Example:
     *
     *      $ep = new Episode('https://turbofilm.tv/Watch/BreakingBad/Season2/Episode1');
     *      $ep->download();
     *      unset( $ep );
     *
     */
    class Episode
    {
        private $name;
        private $serial_name;
        private $path;
        private $data = array();

        /**
         * Символы которые будут вырезаны из названия серии.
         *
         * @var array
         */
        private $replace_in_name = array(':', '/');

        public function __construct( $url )
        {
            $this->url = $url;

            $this->parse();
        }

        public function __get( $param )
        {
            if( array_key_exists( $param, $this->data ) )
            {
                return $this->data[ $param ];
            }

            return FALSE;
        }

        public function __set( $param, $value = NULL )
        {
            $this->data[ $param ] = $value;
        }

        public function __isset( $param )
        {
            return isset( $this->data[ $param ] );
        }

        public function getName()
        {
            return $this->name;
        }

        public function getPath()
        {
            return $this->path;
        }

        public function parse()
        {
            preg_match( '~https://turbofilm.tv/Watch/([a-z0-9]+)/Season([\d]+)/Episode([\d]+)~ui', $this->url, $found );

            if( empty( $found ) )
            {
                l( 'EP:    INVALIDE URL EPISODE: ' . $this->url . ' | ' . __LINE__ );

                return FALSE;
            }

            $res = TurboFilm::_curl( $this->url );

            if( empty( $res ) )
            {
                l( 'EP:    Empty body: ' . $this->url . ' | ' . __LINE__);

                return FALSE;
            }

            $html = str_get_html( $res );

            $name = $html->find( '.tdesc', 0 );
            $name = $name->plaintext;

            $serial_name = $html->find( '.mains a', 0 );
            $serial_name = $serial_name->plaintext;

            if( preg_match( '~Описание серии "(.*?)"~ui', $name, $f_name ) )
            {
                $s_name = $html->find('title', 0);
                $s_name = $s_name->plaintext;

                // fuck fuck fuck
                preg_match('~^(.*?) — (.*?)$~', $s_name, $found2 );

                $this->name = html_entity_decode( 's' . $found[ 2 ] . 'e' . sprintf( '%1$02d', $found[3] ) . ' ' . $found2[1] );
                $this->name = str_replace( $this->replace_in_name, '', $this->name );
            }
            else
            {
                l( 'cant detect name of episode / ' . $name );

                return FALSE;
            }

            $this->serial_name = $serial_name;
            $this->path        = TurboFilm::$config[ 'download_dir' ] . '/' . $serial_name . '/Season ' . $found[ 2 ] . '/' . $this->name . '.mp4';

            if( file_exists( $this->path ) )
            {
                l( 'EP:    File already exists, remove ' . $this->path);

                @unlink( $this->path );

                // Что бы не кэшировать
                clearstatcache();
            }

            $this->makeUrl( $html );

            return TRUE;
        }

        public function makeUrl( $html )
        {
            $metadata = $html->find( '#metadata', 0 )->value;

            $metadata = urldecode( $metadata );

            $f = array( "2", "I", "0", "=", "3", "Q", "8", "V", "7", "X", "G", "M", "R", "U", "H", "4", "1", "Z", "5", "D", "N", "6", "L", "9", "B", "W" );
            $t = array( "x", "u", "Y", "o", "k", "n", "g", "r", "m", "T", "w", "f", "d", "c", "e", "s", "i", "l", "y", "t", "p", "b", "z", "a", "J", "v" );

            $i = 0;

            while( $i < count( $f ) )
            {
                $metadata = self::_enc_replace_ab( $t[ $i ], $f[ $i ], $metadata );
                $i++;
            }

            $metadata = base64_decode( $metadata . '', TRUE );

            if( empty( $metadata ) )
            {
                l( 'cant decode metadata / ' . $this->url . ' / ' . __LINE__ );
            }

            $metadata = str_replace( 'utf-16', 'utf-8', $metadata );
            $metadata = simplexml_load_string( $metadata );

            if( !self::_checkEpisodeParams( $metadata ) )
            {
                return FALSE;
            }

            $b = sha1( TurboFilm::_makeCookie() . rand( 1000, 9999 ) );
            $a = sha1( $b . $metadata->eid . 'A2DC51DE0F8BC1E9' );

            $this->url_cdn = 'https://cdn.turbofilm.tv/' . sha1( TurboFilm::$config[ 'language' ] ) . '/' . (int)$metadata->eid . '/' . ( !empty( $metadata->sources2->hq ) ? $metadata->sources2->hq : $metadata->sources2->default ) . '/0/' . TurboFilm::_makeCookie() . '/' . $b . '/' . $a . '/r';

            $this->eid = (int)$metadata->eid;

            return TRUE;
        }

        static private function _enc_replace_ab( $e, $d, $c )
        {
            $c = str_replace( $e, '___', $c );
            $c = str_replace( $d, $e, $c );
            $c = str_replace( '___', $d, $c );

            return $c;
        }

        /**
         * @static
         *
         * Проверка параметров данной серии.
         * - Язык
         * - Качество
         *
         * @param $metadata
         *
         * @return bool
         */
        static private function _checkEpisodeParams( $metadata )
        {
            $return = TRUE;

            // Не качать если хотим только hq
            if( !empty( TurboFilm::$config[ 'only_hq' ] ) && empty( $metadata->hq ) )
            {
                l( 'dont have HQ quality. Skipped.' );
                $return = FALSE;
            }

            // Не качать если нету нашего языка
            if( empty( $metadata->langs->{TurboFilm::$config[ 'language' ]} ) )
            {
                l( 'dont have language ' );
                $return = FALSE;
            }

            return $return;
        }

        /**
         * Скачивание серии.
         *
         * Скачивание происходит с помощью wget, т.к. это самый
         * простой способ поддерживать переходы и докачку файла при обрыве потока.
         *
         * Проверяется код ответа wget и только если он успешный (int) 0 серия считается скачаной.
         * Если код ответа отличается, то попытка считается не успешной и файл удаляется.
         *
         * Если серия скачалась и в конфиге есть соответсвующая настройка, то серия отмечается как просмотренная.
         *
         */
        public function download()
        {
            // Сюда нужно запилить проверку, что все данные есть и серию можно скачивать.

            l( 'start downloading: ' . $this->name . ' | ' . $this->path );

            $path = pathinfo( $this->path );

            shell_exec( 'mkdir -p ' . escapeshellarg( $path[ 'dirname' ] ) );

            l( 'url: ' . $this->url );

            exec( TurboFilm::$config[ 'tools' ][ 'wget' ] . ' --no-check-certificate --random-wait -t 100 --retry-connrefused -U="Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:8.0.1) Gecko/20100101 Firefox/8.0.1"  -O ' . escapeshellarg( $this->path ) . ' ' . escapeshellarg( $this->url_cdn ), $output, $retvar );

            l( 'downloading finished, wget exit code: ' . $retvar );

            if( $retvar === 0 )
            {
                l( '[!] downloading is ok / ' . $this->name . ' / ' . $this->path );

                $this->downloaded = TRUE;

                TurboFilm::_curl( 'https://turbofilm.tv/services/epwatch', array( 'eid' => $this->eid, 'watch' => 1 ) );
            }
            else
            {
                l( 'downloading is broken, removed ' . $this->path );
                shell_exec( 'rm -f ' . $this->path );
            }
        }

        /**
         * Проверяем скачался ли эпизод и если необходимо, то отправляем уведомление по email
         */
        public function sendEmail()
        {
            // Не будем отправлять уведомления для одной серии больше чем 1 раз
            if( $this->emailed === TRUE )
            {
                return FALSE;
            }

            if( $this->downloaded === TRUE && !empty( TurboFilm::$config[ 'email' ] ) )
            {
                try
                {
                    $ch = curl_init();

                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($ch, CURLOPT_USERPWD, TurboFilm::$config['mailgun']['api-key']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE );

                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_URL, 'https://api.mailgun.net/v2/'. TurboFilm::$config['mailgun']['domain'] .'/messages');
                    curl_setopt($ch, CURLOPT_POSTFIELDS,
                        array(
                            'from'    => 'Turbofilm downloader <turboload@'. TurboFilm::$config['mailgun']['domain'].'>',
                            'to'      => implode(', ', TurboFilm::$config['email']),
                            'subject' => 'TurboLoader | ' . $this->serial_name . ' | ' . $this->name,
                            'text'    => 'Серия ' . $this->url . ' закачана.',
                            'html'    => '<html><p>Серия ' . $this->url . ' закачана.</p><p>&nbsp;</p><p>' . $this->path . '</p></html>',
                        )
                    );

                    curl_exec($ch);
                    curl_close($ch);

                    $this->emailed = TRUE;
                }
                catch( Exception $e )
                {
                    $this->emailed = FALSE;
                    return FALSE;
                }

                return TRUE;
            }

            return FALSE;
        }

        /**
         * При умирании, дернем отправку уведомлений, что бы не делать это руками.
         */
        public function __destruct()
        {
            $this->sendEmail();
        }
    }
